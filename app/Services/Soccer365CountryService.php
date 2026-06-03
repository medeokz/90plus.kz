<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Soccer365CountryService
{
    private const BASE = 'https://soccer365.ru';

    public function syncAll(int $maxPages = 5): int
    {
        $ids = $this->discoverCountryIds($maxPages);
        $synced = 0;

        foreach ($ids as $countryId) {
            if ($this->syncCountry($countryId)) {
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * @return array<int, int>
     */
    private function discoverCountryIds(int $maxPages): array
    {
        $ids = [];

        for ($page = 1; $page <= max(1, $maxPages); $page++) {
            $url = $page === 1
                ? self::BASE.'/countries/'
                : self::BASE.'/countries/?page='.$page;

            $html = $this->fetchHtml($url);
            if ($html === null) {
                continue;
            }

            if (preg_match_all('~/countries/(\d+)/?~i', $html, $matches)) {
                foreach ($matches[1] as $id) {
                    $ids[(int) $id] = (int) $id;
                }
            }
        }

        return array_values($ids);
    }

    public function syncCountry(int $sourceCountryId): bool
    {
        $html = $this->fetchHtml(self::BASE.'/countries/'.$sourceCountryId.'/');
        if ($html === null) {
            return false;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $name = $this->extractCountryName($xpath);
        if ($name === '') {
            return false;
        }

        $flag = $this->extractCountryFlag($xpath, $sourceCountryId);

        Country::updateOrCreate(
            ['source_country_id' => $sourceCountryId],
            [
                'name' => $name,
                'slug' => Str::slug($name).'-'.$sourceCountryId,
                'flag_url' => $flag,
            ]
        );

        return true;
    }

    public function findBySourceId(?int $sourceCountryId): ?Country
    {
        if (! $sourceCountryId) {
            return null;
        }

        return Country::query()->where('source_country_id', $sourceCountryId)->first();
    }

    private function extractCountryName(\DOMXPath $xpath): string
    {
        $node = $xpath->query('//h1[contains(@class,"profile_info_title")]')?->item(0)
            ?? $xpath->query('//h1[contains(@class,"breadcrumb")]')?->item(0)
            ?? $xpath->query('//h1')?->item(0);

        $name = trim($node?->textContent ?? '');

        return $name !== '' ? (preg_replace('/\s+/u', ' ', $name) ?? $name) : '';
    }

    private function extractCountryFlag(\DOMXPath $xpath, int $sourceCountryId): ?string
    {
        $img = $xpath->query('//div[contains(@class,"profile_head")]//div[contains(@class,"profile_foto")]//img')?->item(0);
        if ($img instanceof \DOMElement) {
            return \App\Support\Soccer365ImageUrl::flag($sourceCountryId, $this->absUrl($img->getAttribute('src')));
        }

        return \App\Support\Soccer365ImageUrl::flag($sourceCountryId);
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
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
}
