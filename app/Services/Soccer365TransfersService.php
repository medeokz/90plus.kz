<?php

namespace App\Services;

use App\Models\Transfer;
use App\Support\Soccer365Url;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Soccer365TransfersService
{
    public function sync(string $url = 'https://soccer365.ru/transfers/', ?string $season = null): int
    {
        $response = Http::timeout(40)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            ])
            ->get($url);

        if (! $response->successful()) {
            return 0;
        }

        $items = $this->parseHtml($response->body(), $url, $season);
        if ($items === []) {
            return 0;
        }

        Transfer::upsert(
            $items,
            ['fingerprint'],
            [
                'season',
                'player_name',
                'player_url',
                'player_icon',
                'position',
                'from_club',
                'from_club_source_id',
                'from_club_url',
                'from_club_icon',
                'to_club',
                'to_club_source_id',
                'to_club_url',
                'to_club_icon',
                'transfer_date',
                'date_text',
                'fee',
                'country',
                'source_url',
                'parsed_at',
                'updated_at',
            ]
        );

        return count($items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseHtml(string $html, string $sourceUrl, ?string $season): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query('//table//tr[td]');
        if ($rows === false || $rows->length === 0) {
            return [];
        }

        $seasonFromPage = $season ?: $this->extractSeason($xpath);
        $parsedAt = now();
        $out = [];

        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells === false || $cells->length < 3) {
                continue;
            }

            $playerLink = $xpath->query('.//a[contains(@href, "/players/")][1]', $row)?->item(0);
            if (! $playerLink instanceof \DOMElement) {
                continue;
            }

            $clubLinks = $xpath->query('.//a[contains(@href, "/clubs/")]', $row);
            if ($clubLinks === false || $clubLinks->length < 1) {
                continue;
            }

            $playerName = $this->clean($playerLink->textContent ?? '');
            if ($playerName === '') {
                continue;
            }

            $fromClubEl = $clubLinks->item(0);
            $toClubEl = $clubLinks->length > 1 ? $clubLinks->item(1) : null;
            $playerIconEl = $xpath->query('.//img[contains(@src, "players")][1]', $row)?->item(0);
            $clubIconEls = $xpath->query('.//img[contains(@src, "teams") or contains(@src, "logo") or contains(@src, "no_logo")]', $row);

            $dateText = $this->clean($cells->item(2)?->textContent ?? '');
            $feeText = $this->clean($cells->item(3)?->textContent ?? '');
            $transferDate = $this->parseDate($dateText);

            $position = '';
            $firstCellText = $this->clean($cells->item(0)?->textContent ?? '');
            if ($firstCellText !== '' && Str::startsWith($firstCellText, $playerName)) {
                $position = trim(Str::after($firstCellText, $playerName));
            }

            $fromClub = $fromClubEl instanceof \DOMElement ? $this->clean($fromClubEl->textContent ?? '') : '';
            $toClub = $toClubEl instanceof \DOMElement ? $this->clean($toClubEl->textContent ?? '') : 'Еркін агент';
            $fromClubUrl = $fromClubEl instanceof \DOMElement ? $this->absUrl($sourceUrl, $fromClubEl->getAttribute('href')) : null;
            $toClubUrl = $toClubEl instanceof \DOMElement ? $this->absUrl($sourceUrl, $toClubEl->getAttribute('href')) : null;

            $fingerprint = hash('sha256', implode('|', [
                $seasonFromPage,
                $playerName,
                $fromClub,
                $toClub,
                $dateText,
                $feeText,
            ]));

            $out[] = [
                'season' => $seasonFromPage,
                'player_name' => $playerName,
                'player_url' => $this->absUrl($sourceUrl, $playerLink->getAttribute('href')),
                'player_icon' => $playerIconEl instanceof \DOMElement ? $this->absUrl($sourceUrl, $playerIconEl->getAttribute('src')) : null,
                'position' => $position !== '' ? $position : null,
                'from_club' => $fromClub !== '' ? $fromClub : null,
                'from_club_source_id' => Soccer365Url::extractClubId($fromClubUrl),
                'from_club_url' => $fromClubUrl,
                'from_club_icon' => $clubIconEls !== false && $clubIconEls->length > 0 && $clubIconEls->item(0) instanceof \DOMElement
                    ? $this->absUrl($sourceUrl, $clubIconEls->item(0)->getAttribute('src'))
                    : null,
                'to_club' => $toClub !== '' ? $toClub : null,
                'to_club_source_id' => Soccer365Url::extractClubId($toClubUrl),
                'to_club_url' => $toClubUrl,
                'to_club_icon' => $clubIconEls !== false && $clubIconEls->length > 1 && $clubIconEls->item(1) instanceof \DOMElement
                    ? $this->absUrl($sourceUrl, $clubIconEls->item(1)->getAttribute('src'))
                    : null,
                'transfer_date' => $transferDate,
                'date_text' => $dateText !== '' ? $dateText : null,
                'fee' => $feeText !== '' ? $feeText : null,
                'country' => null,
                'source_url' => $sourceUrl,
                'fingerprint' => $fingerprint,
                'parsed_at' => $parsedAt,
                'created_at' => $parsedAt,
                'updated_at' => $parsedAt,
            ];
        }

        return $out;
    }

    private function extractSeason(\DOMXPath $xpath): ?string
    {
        $node = $xpath->query('//h2[contains(@class,"h1") or contains(@class,"title")]')?->item(0);
        if ($node instanceof \DOMElement) {
            $text = $this->clean($node->textContent ?? '');
            if ($text !== '') {
                return $text;
            }
        }

        return 'Лето '.now()->year;
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return $value;
    }

    private function absUrl(string $base, ?string $path): ?string
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

        $baseHost = parse_url($base, PHP_URL_SCHEME).'://'.parse_url($base, PHP_URL_HOST);

        return $baseHost.'/'.ltrim($path, '/');
    }

    private function parseDate(string $date): ?string
    {
        if (! preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $m)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d.m.Y', $date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

