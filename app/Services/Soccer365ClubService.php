<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Support\Soccer365Url;
use App\Support\Soccer365ImageUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Soccer365ClubService
{
    private const BASE = 'https://soccer365.ru';

    public function __construct(
        private ?TranslationService $translator = null,
    ) {}

    public function discoverAll(int $maxPages = 15, int $crawlDepth = 1): int
    {
        $ids = [];

        foreach ($this->discoverySeedUrls($maxPages) as $url) {
            $this->collectClubIdsFromHtml($this->fetchHtml($url), $ids);
        }

        $this->collectClubIdsFromTransfers($ids);

        $seedIds = array_keys($ids);
        for ($round = 0; $round < max(1, $crawlDepth); $round++) {
            $batch = array_slice($round === 0 ? $seedIds : array_keys($ids), 0, 120);
            foreach (array_chunk($batch, 20) as $chunk) {
                $responses = Http::pool(function ($pool) use ($chunk) {
                    foreach ($chunk as $clubId) {
                        $pool->as((string) $clubId)
                            ->timeout(25)
                            ->withHeaders([
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                                'Accept-Language' => 'ru-RU,ru;q=0.9',
                            ])
                            ->get(Soccer365Url::clubUrl((int) $clubId));
                    }
                });

                foreach ($responses as $html) {
                    if ($html instanceof \Illuminate\Http\Client\Response && $html->successful()) {
                        $this->collectClubIdsFromHtml($html->body(), $ids);
                    }
                }
            }
        }

        $now = now();
        $rows = [];
        foreach (array_keys($ids) as $clubId) {
            $rows[] = [
                'source_club_id' => $clubId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('club_discovery')->upsert($chunk, ['source_club_id'], ['updated_at']);
        }

        return count($ids);
    }

    public function syncAll(
        int $limit = 0,
        int $offset = 0,
        int $batchSize = 60,
        bool $onlyDiscovered = true
    ): array {
        $query = $onlyDiscovered
            ? DB::table('club_discovery')->orderBy('source_club_id')
            : Club::query()->orderBy('source_club_id')->select('source_club_id');

        if ($limit > 0) {
            $ids = $query->offset($offset)->limit($limit)->pluck('source_club_id')->all();
        } elseif ($offset > 0) {
            $ids = $query->offset($offset)->limit(PHP_INT_MAX)->pluck('source_club_id')->all();
        } else {
            $ids = $query->pluck('source_club_id')->all();
        }

        $clubsSynced = 0;
        $playersSynced = 0;
        $processed = 0;

        foreach (array_chunk($ids, max(1, $batchSize)) as $chunk) {
            foreach ($chunk as $clubId) {
                $result = $this->syncClub(Soccer365Url::clubUrl((int) $clubId));
                $clubsSynced += $result['club'] ? 1 : 0;
                $playersSynced += $result['players'];
                $processed++;
            }
        }

        return [
            'clubs' => $clubsSynced,
            'players' => $playersSynced,
            'urls' => count($ids),
            'processed' => $processed,
        ];
    }

    /**
     * @return array{club: bool, players: int}
     */
    public function syncClub(string $clubUrl): array
    {
        $html = $this->fetchHtml($clubUrl);
        if ($html === null) {
            return ['club' => false, 'players' => 0];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        if (! preg_match('~/clubs/(\d+)/?~', $clubUrl, $m)) {
            return ['club' => false, 'players' => 0];
        }

        $sourceClubId = (int) $m[1];
        $name = $this->extractClubName($xpath);
        if ($name === '') {
            return ['club' => false, 'players' => 0];
        }

        $logo = $this->extractClubLogo($xpath);
        $profile = $this->extractClubProfile($xpath);
        $sourceCountryId = $this->extractSourceCountryId($xpath, $html);
        $country = $this->resolveCountry($sourceCountryId);

        $profileData = $profile['data'];
        $profileData['competitions'] = $this->extractCompetitions($xpath);
        $profileData['standings'] = $this->extractStandings($xpath);

        $scheduleHtml = $this->fetchClubTab($sourceClubId, 'schedule');
        $resultsHtml = $this->fetchClubTab($sourceClubId, 'result_last');
        $allSchedule = $this->mergeGames(
            $this->extractClubGames($xpath),
            $this->extractClubGames($this->makeXPath($scheduleHtml))
        );
        $allResults = $this->mergeGames(
            $this->extractClubGames($this->makeXPath($resultsHtml)),
            $allSchedule
        );

        $profileData['schedule'] = array_values(array_filter(
            $allSchedule,
            fn (array $game) => ! $this->gameHasScore($game)
        ));
        $profileData['results'] = array_values(array_filter(
            $allResults,
            fn (array $game) => $this->gameHasScore($game)
        ));

        $club = Club::updateOrCreate(
            ['source_club_id' => $sourceClubId],
            [
                'name' => $name,
                'name_en' => $profile['name_en'],
                'description' => $this->translateDescription($profile['description']),
                'profile_data' => $profileData,
                'slug' => Str::slug($name).'-'.$sourceClubId,
                'logo_url' => Soccer365ImageUrl::clubLogo($logo),
                'country_id' => $country?->id,
                'country' => $country?->name,
                'city' => $profile['data']['stadium_city'] ?? null,
                'source_url' => $clubUrl,
            ]
        );

        DB::table('club_discovery')->updateOrInsert(
            ['source_club_id' => $sourceClubId],
            ['updated_at' => now(), 'created_at' => now()]
        );

        $playersCount = $this->syncSquadForClub($club, $xpath);

        return ['club' => true, 'players' => $playersCount];
    }

    /**
     * @return array{clubs: int, players: int, processed: int}
     */
    public function backfillSquads(bool $emptyOnly = true, int $limit = 0, int $batchSize = 30): array
    {
        $query = Club::query()
            ->whereNotNull('source_club_id')
            ->orderBy('id');

        if ($emptyOnly) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('club_player')
                    ->whereColumn('club_player.club_id', 'clubs.id')
                    ->where(function ($q2) {
                        $q2->whereNull('club_player.position')
                            ->orWhere('club_player.position', '')
                            ->orWhereNull('club_player.age');
                    });
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $clubsSynced = 0;
        $playersSynced = 0;
        $processed = 0;

        $clubIds = $query->pluck('id')->all();
        foreach (array_chunk($clubIds, max(1, $batchSize)) as $chunkIds) {
            $clubs = Club::query()->whereIn('id', $chunkIds)->orderBy('id')->get();
            foreach ($clubs as $club) {
                $result = $this->syncClubSquad(Soccer365Url::clubUrl((int) $club->source_club_id));
                $clubsSynced += $result['club'] ? 1 : 0;
                $playersSynced += $result['players'];
                $processed++;
            }
        }

        return [
            'clubs' => $clubsSynced,
            'players' => $playersSynced,
            'processed' => $processed,
        ];
    }

    /**
     * @return array{club: bool, players: int}
     */
    public function syncClubSquad(string $clubUrl): array
    {
        $html = $this->fetchHtml($clubUrl);
        if ($html === null) {
            return ['club' => false, 'players' => 0];
        }

        if (! preg_match('~/clubs/(\d+)/?~', $clubUrl, $m)) {
            return ['club' => false, 'players' => 0];
        }

        $club = Club::query()->where('source_club_id', (int) $m[1])->first();
        if (! $club) {
            return ['club' => false, 'players' => 0];
        }

        $xpath = $this->makeXPath($html);

        return [
            'club' => true,
            'players' => $this->syncSquadForClub($club, $xpath),
        ];
    }

    private function syncSquadForClub(Club $club, \DOMXPath $xpath): int
    {
        $players = $this->dedupeSquadPlayers($this->extractSquadPlayers($xpath));
        $now = now();
        $pivotRows = [];

        foreach ($players as $row) {
            $player = Player::updateOrCreate(
                ['source_player_id' => $row['source_player_id']],
                [
                    'name' => $row['name'],
                    'slug' => Str::slug($row['name']).'-'.$row['source_player_id'],
                    'photo_url' => Soccer365ImageUrl::playerPhoto($row['photo_url']),
                    'nationality' => $row['nationality'],
                    'nationality_flag_url' => Soccer365ImageUrl::flag(null, $row['nationality_flag_url']),
                    'age' => $row['age'],
                    'source_url' => null,
                ]
            );

            $pivotRows[] = [
                'club_id' => $club->id,
                'player_id' => $player->id,
                'position' => $row['position'],
                'number' => $row['number'],
                'age' => $row['age'],
                'nationality' => $row['nationality'],
                'season' => $row['season'],
                'parsed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($pivotRows !== []) {
            $pivotRows = $this->dedupePivotRows($pivotRows);

            foreach (array_chunk($pivotRows, 100) as $chunk) {
                DB::table('club_player')->upsert(
                    $chunk,
                    ['club_id', 'player_id'],
                    ['position', 'number', 'age', 'nationality', 'season', 'parsed_at', 'updated_at']
                );
            }
        }

        return count($pivotRows);
    }

    public function backfillTransferClubIds(): int
    {
        $updated = 0;
        Transfer::query()->chunkById(200, function ($transfers) use (&$updated) {
            foreach ($transfers as $transfer) {
                $fromId = Soccer365Url::extractClubId($transfer->from_club_url);
                $toId = Soccer365Url::extractClubId($transfer->to_club_url);
                if ($fromId !== $transfer->from_club_source_id || $toId !== $transfer->to_club_source_id) {
                    $transfer->update([
                        'from_club_source_id' => $fromId,
                        'to_club_source_id' => $toId,
                    ]);
                    $updated++;
                }
                foreach (array_filter([$fromId, $toId]) as $clubId) {
                    DB::table('club_discovery')->updateOrInsert(
                        ['source_club_id' => $clubId],
                        ['updated_at' => now(), 'created_at' => now()]
                    );
                }
            }
        });

        return $updated;
    }

    public function linkAllClubsToCountries(): int
    {
        $linked = 0;

        Club::query()
            ->whereNull('country_id')
            ->whereNotNull('source_url')
            ->chunkById(40, function ($clubs) use (&$linked) {
                foreach ($clubs as $club) {
                    if ($this->linkClubToCountry($club)) {
                        $linked++;
                    }
                }
            });

        return $linked;
    }

    public function linkClubToCountry(Club $club): bool
    {
        $html = $this->fetchHtml($club->source_url);
        if ($html === null) {
            return false;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $country = $this->resolveCountry($this->extractSourceCountryId($xpath, $html));
        if (! $country) {
            return false;
        }

        $club->update([
            'country_id' => $country->id,
            'country' => $country->name,
        ]);

        return true;
    }

    public function refreshAllLogos(int $batchSize = 25): int
    {
        $updated = 0;

        Club::query()
            ->whereNotNull('source_url')
            ->orderBy('id')
            ->chunkById(max(1, $batchSize), function ($clubs) use (&$updated) {
                $responses = Http::pool(function ($pool) use ($clubs) {
                    foreach ($clubs as $club) {
                        $pool->as((string) $club->id)
                            ->timeout(25)
                            ->withHeaders([
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                                'Accept-Language' => 'ru-RU,ru;q=0.9',
                            ])
                            ->get($club->source_url);
                    }
                });

                foreach ($clubs as $club) {
                    $response = $responses[(string) $club->id] ?? null;
                    if (! $response instanceof \Illuminate\Http\Client\Response || ! $response->successful()) {
                        continue;
                    }

                    $dom = new \DOMDocument();
                    libxml_use_internal_errors(true);
                    $dom->loadHTML('<?xml encoding="UTF-8">'.$response->body());
                    libxml_clear_errors();
                    $xpath = new \DOMXPath($dom);

                    $logo = $this->extractClubLogo($xpath);
                    if (! $logo) {
                        continue;
                    }

                    $logoUrl = Soccer365ImageUrl::clubLogo($logo);
                    $current = $club->getRawOriginal('logo_url');
                    if ($current !== $logoUrl || $this->isStaleLogo($current)) {
                        $club->update(['logo_url' => $logoUrl]);
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    private function resolveCountry(?int $sourceCountryId): ?\App\Models\Country
    {
        if (! $sourceCountryId) {
            return null;
        }

        $service = app(Soccer365CountryService::class);
        $country = $service->findBySourceId($sourceCountryId);
        if ($country) {
            return $country;
        }

        if (! $service->syncCountry($sourceCountryId)) {
            return null;
        }

        return $service->findBySourceId($sourceCountryId);
    }

    private function extractSourceCountryId(\DOMXPath $xpath, string $html): ?int
    {
        $profileFlag = $xpath->query('//div[contains(@class,"profile_info")]//img[contains(@src,"/flags/")][1]')?->item(0);
        if ($profileFlag instanceof \DOMElement && preg_match('~/flags/(\d+)~', $profileFlag->getAttribute('src'), $m)) {
            return (int) $m[1];
        }

        $link = $xpath->query('//a[contains(@href,"/countries/")]')?->item(0);
        if ($link instanceof \DOMElement && preg_match('~/countries/(\d+)/?~', $link->getAttribute('href'), $m)) {
            return (int) $m[1];
        }

        $countryName = $this->extractCountryNameFromProfile($xpath);
        if ($countryName) {
            $country = \App\Models\Country::query()->where('name', $countryName)->first();

            return $country?->source_country_id;
        }

        if (preg_match('~/countries/(\d+)/?~', $html, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function extractCountryNameFromProfile(\DOMXPath $xpath): ?string
    {
        $rows = $xpath->query('//tr');
        if ($rows === false) {
            return null;
        }

        foreach ($rows as $tr) {
            $text = trim(preg_replace('/\s+/u', ' ', $tr->textContent ?? '') ?? '');
            if (! str_contains($text, 'Стадион') || ! str_contains($text, ',')) {
                continue;
            }

            if (preg_match('/,\s*([^,]+)$/u', $text, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function discoverySeedUrls(int $maxPages): array
    {
        $urls = [
            self::BASE.'/',
            self::BASE.'/transfers/',
            self::BASE.'/countries/',
            self::BASE.'/competitions/',
            self::BASE.'/ratings/',
        ];

        $home = $this->fetchHtml(self::BASE.'/');
        if ($home !== null) {
            preg_match_all('~href=["\'](/competitions/\d+/?)["\']~i', $home, $compMatches);
            foreach (array_unique($compMatches[1] ?? []) as $path) {
                $urls[] = self::BASE.$path;
            }
            preg_match_all('~href=["\'](/countries/[^"\']+/?)["\']~i', $home, $countryMatches);
            foreach (array_slice(array_unique($countryMatches[1] ?? []), 0, 80) as $path) {
                $urls[] = self::BASE.$path;
            }
        }

        $expanded = [];
        foreach ($urls as $baseUrl) {
            for ($page = 1; $page <= max(1, $maxPages); $page++) {
                $expanded[] = $page === 1
                    ? $baseUrl
                    : $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'page='.$page;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @param  array<int, true>  $ids
     */
    private function collectClubIdsFromHtml(?string $html, array &$ids): void
    {
        if ($html === null || $html === '') {
            return;
        }

        if (preg_match_all('~(?:https?://(?:www\.)?soccer365\.ru)?/clubs/(\d+)/?~i', $html, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[(int) $id] = true;
            }
        }
    }

    /**
     * @param  array<int, true>  $ids
     */
    private function collectClubIdsFromTransfers(array &$ids): void
    {
        Transfer::query()
            ->select(['id', 'from_club_url', 'to_club_url', 'from_club_source_id', 'to_club_source_id'])
            ->chunkById(300, function ($rows) use (&$ids) {
                foreach ($rows as $row) {
                    foreach ([
                        $row->from_club_source_id,
                        $row->to_club_source_id,
                        Soccer365Url::extractClubId($row->from_club_url),
                        Soccer365Url::extractClubId($row->to_club_url),
                    ] as $clubId) {
                        if ($clubId) {
                            $ids[(int) $clubId] = true;
                        }
                    }
                }
            });
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(35)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                    'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
                ])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    private function extractClubName(\DOMXPath $xpath): string
    {
        $h1 = $xpath->query('//h1')?->item(0);
        $name = $h1?->textContent ? trim(preg_replace('/^\s*Футбольный клуб\s*[«"]?|[»"]\s*$/u', '', $h1->textContent)) : '';
        if ($name !== '') {
            return preg_replace('/\s+/u', ' ', $name) ?? $name;
        }

        return '';
    }

    private function isStaleLogo(?string $url): bool
    {
        if (! $url) {
            return true;
        }

        return str_contains($url, 's1/logo/') && preg_match('/_16_\d+\.(png|svg|webp)$/i', $url);
    }

    private function extractClubLogo(\DOMXPath $xpath): ?string
    {
        $img = $xpath->query('//div[contains(@class,"profile_head")]//div[contains(@class,"profile_foto")]//img')?->item(0);
        if ($img instanceof \DOMElement) {
            return $this->absUrl($img->getAttribute('src'));
        }

        $img = $xpath->query('//img[contains(@src,"/teams/") and not(contains(@src,"_32_"))]')?->item(0);
        if ($img instanceof \DOMElement) {
            return $this->absUrl($img->getAttribute('src'));
        }

        $img = $xpath->query('//img[contains(@src,"/teams/") or contains(@src,"/logo/")]')?->item(0);
        if (! $img instanceof \DOMElement) {
            return null;
        }

        return $this->absUrl($img->getAttribute('src'));
    }

    /**
     * @return array{name_en: ?string, description: ?string, data: array<string, mixed>}
     */
    private function extractClubProfile(\DOMXPath $xpath): array
    {
        $nameEn = trim($xpath->query('//div[contains(@class,"profile_head")]//div[contains(@class,"profile_en_title")]')?->item(0)?->textContent ?? '');
        $wiki = $xpath->query('//div[contains(@class,"profile_head")]//div[contains(@class,"profile_wiki")]')?->item(0);
        $description = null;
        if ($wiki instanceof \DOMElement) {
            $description = trim(preg_replace('/\s+/u', ' ', $wiki->textContent ?? '') ?? '');
            $description = preg_replace('/\s*Wikipedia\s*$/u', '', $description) ?: null;
        }

        $data = [];
        $rows = $xpath->query('//div[contains(@class,"profile_head")]//table[contains(@class,"profile_params")]//tr');
        if ($rows !== false) {
            foreach ($rows as $tr) {
                $key = trim($xpath->query('./td[contains(@class,"params_key")]', $tr)?->item(0)?->textContent ?? '');
                $valNode = $xpath->query('./td[not(contains(@class,"params_key"))]', $tr)?->item(0);
                if ($key === '' || ! $valNode instanceof \DOMElement) {
                    continue;
                }

                $value = trim(preg_replace('/\s+/u', ' ', $valNode->textContent ?? '') ?? '');
                if ($value === '') {
                    continue;
                }

                match ($key) {
                    'Полное название' => $data['full_name'] = $value,
                    'Главный тренер' => $data['coach_name'] = trim($xpath->query('.//a', $valNode)?->item(0)?->textContent ?? $value),
                    'Стадион' => $this->parseStadiumRow($valNode, $data),
                    'Год основания' => $data['founded'] = $value,
                    'Рейтинг УЕФА' => $data['uefa_rank'] = $value,
                    default => null,
                };

                $coachImg = $xpath->query('.//img[contains(@src,"/players/")]', $valNode)?->item(0);
                if ($key === 'Главный тренер' && $coachImg instanceof \DOMElement) {
                    $data['coach_photo_url'] = Soccer365ImageUrl::playerPhoto($this->absUrl($coachImg->getAttribute('src')));
                }
            }
        }

        return [
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'description' => $description,
            'data' => $data,
        ];
    }

    private function fetchClubTab(int $sourceClubId, string $tab): ?string
    {
        return $this->fetchHtml(self::BASE.'/clubs/'.$sourceClubId.'/?tab='.$tab);
    }

    private function makeXPath(?string $html): ?\DOMXPath
    {
        if ($html === null || $html === '') {
            return null;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new \DOMXPath($dom);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractCompetitions(\DOMXPath $xpath): array
    {
        $items = [];
        $links = $xpath->query('//div[contains(@class,"profile_head")]//td[contains(@class,"params_comp")]//a');
        if ($links === false) {
            return [];
        }

        foreach ($links as $link) {
            if (! $link instanceof \DOMElement) {
                continue;
            }

            $name = trim($xpath->query('.//span', $link)?->item(0)?->textContent ?? $link->textContent ?? '');
            if ($name === '') {
                continue;
            }

            $img = $xpath->query('.//img', $link)?->item(0);
            $items[] = [
                'name' => $name,
                'url' => $this->absUrl($link->getAttribute('href')),
                'logo_url' => $img instanceof \DOMElement ? $this->absUrl($img->getAttribute('src')) : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractStandings(\DOMXPath $xpath): array
    {
        $tables = $xpath->query('//table[contains(@class,"stngs")]');
        if ($tables === false) {
            return [];
        }

        $standings = [];
        foreach ($tables as $table) {
            if (! $table instanceof \DOMElement) {
                continue;
            }

            $competition = '';
            $header = $xpath->query('preceding::div[contains(@class,"block_header")][1]', $table)?->item(0);
            if ($header instanceof \DOMElement) {
                $competition = trim($xpath->query('.//span', $header)?->item(0)?->textContent ?? $header->textContent ?? '');
            }

            $rows = [];
            $trs = $xpath->query('.//tbody/tr', $table);
            if ($trs === false) {
                continue;
            }

            foreach ($trs as $tr) {
                $text = trim(preg_replace('/\s+/u', ' ', $tr->textContent ?? '') ?? '');
                if ($text === '') {
                    continue;
                }

                $clubLink = $xpath->query('.//a[contains(@href,"/clubs/")]', $tr)?->item(0);
                $logo = $xpath->query('.//img[contains(@src,"/teams/") or contains(@src,"/logo/")]', $tr)?->item(0);
                $tds = $xpath->query('./td', $tr);
                $cells = [];
                if ($tds !== false) {
                    foreach ($tds as $td) {
                        $cells[] = trim(preg_replace('/\s+/u', ' ', $td->textContent ?? '') ?? '');
                    }
                }

                $rows[] = [
                    'position' => $cells[0] ?? null,
                    'club_name' => $clubLink instanceof \DOMElement ? trim($clubLink->textContent) : ($cells[1] ?? null),
                    'club_url' => $clubLink instanceof \DOMElement ? $this->absUrl($clubLink->getAttribute('href')) : null,
                    'club_logo' => $logo instanceof \DOMElement ? $this->absUrl($logo->getAttribute('src')) : null,
                    'played' => $cells[2] ?? null,
                    'goal_diff' => $cells[3] ?? null,
                    'points' => $cells[4] ?? null,
                ];
            }

            if ($rows !== []) {
                $standings[] = [
                    'competition' => $competition,
                    'rows' => $rows,
                ];
            }
        }

        return $standings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractClubGames(?\DOMXPath $xpath): array
    {
        if ($xpath === null) {
            return [];
        }

        $games = [];
        $blocks = $xpath->query('//div[contains(@class,"game_block")]');
        if ($blocks === false) {
            return [];
        }

        foreach ($blocks as $block) {
            if (! $block instanceof \DOMElement) {
                continue;
            }

            $link = $xpath->query('.//a[contains(@class,"game_link")]', $block)?->item(0);
            if (! $link instanceof \DOMElement) {
                continue;
            }

            $homeScore = trim($xpath->query('.//div[contains(@class,"ht")]//div[contains(@class,"gls")]', $block)?->item(0)?->textContent ?? '-');
            $awayScore = trim($xpath->query('.//div[contains(@class,"at")]//div[contains(@class,"gls")]', $block)?->item(0)?->textContent ?? '-');
            $homeLogo = $xpath->query('.//div[contains(@class,"ht")]//img', $block)?->item(0);
            $awayLogo = $xpath->query('.//div[contains(@class,"at")]//img', $block)?->item(0);
            $compLogo = $xpath->query('.//div[contains(@class,"cmp")]//img', $block)?->item(0);

            $homeLogoUrl = $homeLogo instanceof \DOMElement ? $this->absUrl($homeLogo->getAttribute('src')) : null;
            $awayLogoUrl = $awayLogo instanceof \DOMElement ? $this->absUrl($awayLogo->getAttribute('src')) : null;

            $games[] = [
                'external_id' => $link->getAttribute('dt-id') ?: null,
                'title' => trim($link->getAttribute('title')),
                'date_text' => trim($xpath->query('.//div[contains(@class,"status")]//span', $block)?->item(0)?->textContent ?? ''),
                'home_name' => trim($xpath->query('.//div[contains(@class,"ht")]//div[contains(@class,"name")]//span', $block)?->item(0)?->textContent ?? ''),
                'away_name' => trim($xpath->query('.//div[contains(@class,"at")]//div[contains(@class,"name")]//span', $block)?->item(0)?->textContent ?? ''),
                'home_logo' => $homeLogoUrl,
                'away_logo' => $awayLogoUrl,
                'home_club_source_id' => $this->sourceClubIdFromLogoUrl($homeLogoUrl),
                'away_club_source_id' => $this->sourceClubIdFromLogoUrl($awayLogoUrl),
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'competition' => trim($xpath->query('.//div[contains(@class,"cmp")]//span', $block)?->item(0)?->textContent ?? ''),
                'competition_logo' => $compLogo instanceof \DOMElement ? $this->absUrl($compLogo->getAttribute('src')) : null,
                'url' => $this->absUrl($link->getAttribute('href')),
            ];
        }

        return $games;
    }

    private function sourceClubIdFromLogoUrl(?string $url): ?int
    {
        if ($url && preg_match('~_32_(\d+)\.(png|svg|webp|jpe?g)$~i', $url, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$lists
     * @return array<int, array<string, mixed>>
     */
    private function mergeGames(array ...$lists): array
    {
        $merged = [];
        foreach ($lists as $list) {
            foreach ($list as $game) {
                $key = $game['external_id'] ?? ($game['date_text'].'|'.$game['title']);
                $merged[$key] = $game;
            }
        }

        return array_values($merged);
    }

    private function gameHasScore(array $game): bool
    {
        $home = $game['home_score'] ?? '-';
        $away = $game['away_score'] ?? '-';

        return $home !== '-' && $away !== '-' && is_numeric($home) && is_numeric($away);
    }

    private function translateDescription(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $translator = $this->translator ?? app(TranslationService::class);
        $translated = $translator->toKazakh($text, 'ru');

        return $translated && trim($translated) !== '' ? $translated : $text;
    }

    public function translateClubDescription(?string $text): ?string
    {
        return $this->translateDescription($text);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parseStadiumRow(\DOMElement $cell, array &$data): void
    {
        $links = $cell->getElementsByTagName('a');
        if ($links->length > 0) {
            $data['stadium_name'] = trim($links->item(0)->textContent ?? '');
        }

        foreach ($cell->getElementsByTagName('span') as $span) {
            if (str_contains($span->getAttribute('class'), 'min_gray')) {
                $data['stadium_city'] = trim($span->textContent ?? '');
                break;
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractSquadPlayers(\DOMXPath $xpath): array
    {
        $rows = $xpath->query('//div[contains(@class,"club_lineup")]//table//tr');
        if ($rows === false || $rows->length === 0) {
            $rows = $xpath->query('//tr');
        }

        $result = [];
        $currentPosition = null;

        foreach ($rows as $tr) {
            $posCell = $xpath->query('.//td[contains(@class,"position")]', $tr)?->item(0);
            if ($posCell instanceof \DOMElement) {
                $currentPosition = $this->normalizePositionKey(trim($posCell->textContent ?? ''));
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $tr->textContent ?? '') ?? '');
            if ($text === '') {
                continue;
            }

            $positionFromRow = $this->normalizePositionKey($text);
            if ($positionFromRow !== null && ! preg_match('~/players/\d+~', $text)) {
                $currentPosition = $positionFromRow;
                continue;
            }

            $playerLink = $xpath->query('.//td[contains(@class,"title")]//a[contains(@href,"/players/")]', $tr)?->item(0);
            if (! $playerLink instanceof \DOMElement) {
                $playerLinks = $xpath->query('.//a[contains(@href,"/players/")]', $tr);
                if ($playerLinks !== false) {
                    foreach ($playerLinks as $link) {
                        if ($link instanceof \DOMElement && trim($link->textContent) !== '') {
                            $playerLink = $link;
                            break;
                        }
                    }
                }
            }
            if (! $playerLink instanceof \DOMElement) {
                continue;
            }

            $href = $playerLink->getAttribute('href');
            if (! preg_match('~/players/(\d+)/?~', $href, $pm)) {
                continue;
            }

            $name = trim($playerLink->textContent ?? '');
            if ($name === '') {
                continue;
            }

            $playerId = (int) $pm[1];
            $number = trim($xpath->query('.//td[contains(@class,"number")]', $tr)?->item(0)?->textContent ?? '');
            $number = preg_match('/^\d{1,3}$/', $number) ? $number : null;

            $ageText = trim($xpath->query('.//td[contains(@class,"age")]', $tr)?->item(0)?->textContent ?? '');
            $age = $this->parsePlayerAge($ageText);

            $flag = $xpath->query('.//td[contains(@class,"national")]//img[contains(@src,"/flags/")][1]', $tr)?->item(0)
                ?? $xpath->query('.//img[contains(@src,"/flags/")][1]', $tr)?->item(0);
            $nationality = $flag instanceof \DOMElement ? trim($flag->getAttribute('title')) : null;
            $nationalityFlagUrl = $flag instanceof \DOMElement
                ? Soccer365ImageUrl::flag(null, $this->absUrl($flag->getAttribute('src')))
                : null;
            $photo = $xpath->query('.//td[contains(@class,"thumb")]//img[contains(@src,"/players/")][1]', $tr)?->item(0)
                ?? $xpath->query('.//img[contains(@src,"/players/")][1]', $tr)?->item(0);
            $photoUrl = $photo instanceof \DOMElement
                ? Soccer365ImageUrl::playerPhoto($this->absUrl($photo->getAttribute('src')))
                : null;

            $result[] = [
                'source_player_id' => $playerId,
                'name' => $name,
                'photo_url' => $photoUrl,
                'nationality' => $nationality ?: null,
                'nationality_flag_url' => $nationalityFlagUrl,
                'age' => $age,
                'position' => $this->toKazakhPositionSingular($currentPosition),
                'number' => $number,
                'season' => null,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pivotRows
     * @return array<int, array<string, mixed>>
     */
    private function dedupePivotRows(array $pivotRows): array
    {
        $byPlayer = [];
        foreach ($pivotRows as $row) {
            $byPlayer[(int) $row['player_id']] = $row;
        }

        return array_values($byPlayer);
    }

    /**
     * @param  array<int, array<string, mixed>>  $players
     * @return array<int, array<string, mixed>>
     */
    private function dedupeSquadPlayers(array $players): array
    {
        $byId = [];
        foreach ($players as $row) {
            $byId[(int) $row['source_player_id']] = $row;
        }

        return array_values($byId);
    }

    private function absUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        if (Str::startsWith($path, '//')) {
            return 'https:'.$path;
        }

        return self::BASE.'/'.ltrim($path, '/');
    }

    private function normalizePositionKey(?string $pos): ?string
    {
        if ($pos === null || trim($pos) === '') {
            return null;
        }

        $pos = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $pos) ?? ''), 'UTF-8');

        return match (true) {
            str_contains($pos, 'вратар') => 'вратари',
            str_contains($pos, 'защит') => 'защитники',
            str_contains($pos, 'полузащит') => 'полузащитники',
            str_contains($pos, 'напада') => 'нападающие',
            default => null,
        };
    }

    private function parsePlayerAge(?string $ageText): ?int
    {
        $ageText = trim($ageText ?? '');
        if ($ageText === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})\s*(?:лет|года|год)/u', $ageText, $am)) {
            return (int) $am[1];
        }

        if (preg_match('/^\d{1,2}$/', $ageText)) {
            return (int) $ageText;
        }

        return null;
    }

    private function toKazakhPositionSingular(?string $pos): ?string
    {
        return match ($this->normalizePositionKey($pos) ?? $pos) {
            'вратари' => 'Қақпашы',
            'защитники' => 'Қорғаушы',
            'полузащитники' => 'Жартылай қорғаушы',
            'нападающие' => 'Шабуылшы',
            default => null,
        };
    }

    private function toKazakhPosition(?string $pos): ?string
    {
        return match ($this->normalizePositionKey($pos) ?? $pos) {
            'вратари' => 'Қақпашылар',
            'защитники' => 'Қорғаушылар',
            'полузащитники' => 'Жартылай қорғаушылар',
            'нападающие' => 'Шабуылшылар',
            default => null,
        };
    }
}
