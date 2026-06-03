<?php

namespace App\Services;

use App\Models\Article;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ArticleFetchService
{
    public function __construct(
        private TranslationService $translator,
        private ArticleContentParser $contentParser,
        private ImageDownloadService $imageDownloader
    ) {}

    public function fetchAll(int $limitPerSource = 5, bool $fullContent = false): int
    {
        $count = 0;

        foreach (config('football.sources', []) as $source) {
            if (! $this->isSourceEnabled($source)) {
                continue;
            }

            try {
                $count += $this->fetchFromSource($source, $limitPerSource, $fullContent);
            } catch (\Throwable $e) {
                Log::error('Failed to fetch from '.$source['name'], [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Каждый час — по 1 новости с каждого сайта (полный текст + перевод).
     */
    public function fetchHourlyFromAllSources(): int
    {
        $sources = config('football.sources', []);
        if (empty($sources)) {
            return 0;
        }

        $total = 0;

        foreach ($sources as $source) {
            if (! $this->isSourceEnabled($source)) {
                continue;
            }

            try {
                $count = $this->fetchFromSource($source, 1, true);
                $total += $count;

                Log::info('Hourly fetch from source', [
                    'source' => $source['name'],
                    'imported' => $count,
                ]);
            } catch (\Throwable $e) {
                Log::error('Hourly fetch failed for '.$source['name'], [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $total;
    }

    public function fetchFromSource(array $source, int $limit = 1, bool $fullContent = false): int
    {
        if (($source['type'] ?? null) === 'soccer365') {
            return $this->fetchFromSoccer365($source, $limit);
        }
        if (($source['type'] ?? null) === 'soccer365_press') {
            return $this->fetchFromSoccer365($source, $limit, '/\/press\/\d+\/?$/i');
        }

        $response = $this->fetchRss($source);

        $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new \RuntimeException('Invalid RSS XML');
        }

        $items = $xml->channel->item ?? $xml->entry ?? [];
        $count = 0;

        foreach ($items as $item) {
            if ($count >= $limit) {
                break;
            }

            $link = $this->normalizeUrl($this->extractLink($item));
            if ($link === '' || ! filter_var($link, FILTER_VALIDATE_URL) || Article::where('source_url', $link)->exists()) {
                continue;
            }

            $titleEn = html_entity_decode(strip_tags((string) ($item->title ?? '')), ENT_QUOTES, 'UTF-8');
            $summaryEn = $this->extractSummary($item);
            $publishedAt = $this->extractPublishedAt($item);
            $imageUrl = $this->extractImage($item);

            $contentEn = $summaryEn;
            if ($fullContent) {
                $parsed = $this->fetchFullArticle($link, $source['lang'] ?? 'en');
                if ($parsed['content'] !== '') {
                    $contentEn = $parsed['content'];
                }
                // og:image со страницы — обычно оригинал, приоритетнее RSS-миниатюры
                if ($parsed['image'] !== null) {
                    $imageUrl = $parsed['image'];
                }
            }

            $localImage = $this->imageDownloader->download($imageUrl, $link);
            if ($localImage !== null) {
                $imageUrl = $localImage;
            }

            $sourceLang = $source['lang'] ?? 'en';

            $titleKk = $this->translator->toKazakh($titleEn, $sourceLang);
            $summaryKk = $this->translator->toKazakh(Str::limit(strip_tags($contentEn), 500), $sourceLang);
            $contentKk = $this->translator->toKazakhFormatted($contentEn, $sourceLang);

            Article::create([
                'title_en' => $titleEn,
                'title_kk' => $titleKk ?: $titleEn,
                'summary_en' => Str::limit($summaryEn, 500),
                'summary_kk' => $summaryKk ?: Str::limit($summaryEn, 500),
                'content_en' => $contentEn,
                'content_kk' => $contentKk ?: $contentEn,
                'source_url' => $link,
                'source_name' => $source['name'],
                'image_url' => $imageUrl,
                'slug' => Article::generateSlug($titleKk ?: $titleEn),
                'published_at' => $publishedAt,
                'status' => 'published',
                'fetched_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    private function fetchFromSoccer365(array $source, int $limit = 1, string $pathPattern = '/\/news\/\d+\/?$/i'): int
    {
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; FootballKZ/1.0)'])
            ->get($source['news_url'] ?? 'https://soccer365.ru/news/');

        if (! $response->successful()) {
            throw new \RuntimeException('Soccer365 list fetch failed: '.$response->status());
        }

        $links = $this->extractSoccer365Links($response->body(), $pathPattern);
        $count = 0;
        $sourceLang = $source['lang'] ?? 'ru';

        foreach ($links as $link) {
            if ($count >= $limit) {
                break;
            }

            if (Article::where('source_url', $link)->exists()) {
                continue;
            }

            $articleResponse = Http::timeout(45)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])
                ->get($link);

            if (! $articleResponse->successful()) {
                continue;
            }

            $html = $articleResponse->body();
            $titleRu = $this->extractTitleFromHtml($html) ?? 'Soccer365';
            $parsed = $this->parseSoccer365Article($html, $link);
            $contentRu = $parsed['content'] !== '' ? $parsed['content'] : $titleRu;
            $summaryRu = Str::limit(strip_tags($contentRu), 500);
            $imageUrl = $parsed['image'];

            $localImage = $this->imageDownloader->download($imageUrl, $link);
            if ($localImage !== null) {
                $imageUrl = $localImage;
            }

            $titleKk = $this->translator->toKazakh($titleRu, $sourceLang);
            $summaryKk = $this->translator->toKazakh($summaryRu, $sourceLang);
            $contentKk = $this->translator->toKazakhFormatted($contentRu, $sourceLang);

            Article::create([
                'title_en' => $titleRu,
                'title_kk' => $titleKk ?: $titleRu,
                'summary_en' => $summaryRu,
                'summary_kk' => $summaryKk ?: $summaryRu,
                'content_en' => $contentRu,
                'content_kk' => $contentKk ?: $contentRu,
                'source_url' => $link,
                'source_name' => $source['name'],
                'image_url' => $imageUrl,
                'slug' => Article::generateSlug($titleKk ?: $titleRu),
                'published_at' => now(),
                'status' => 'published',
                'fetched_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    private function fetchFullArticle(string $url, string $lang = 'en'): array
    {
        $url = $this->normalizeUrl($url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['content' => '', 'image' => null];
        }

        try {
            $acceptLanguage = $lang === 'ru' ? 'ru-RU,ru;q=0.9' : 'en-US,en;q=0.9';

            $response = Http::timeout(45)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => $acceptLanguage,
                ])
                ->get($url);

            if (! $response->successful()) {
                return ['content' => '', 'image' => null];
            }

            return $this->contentParser->parse($url, $response->body());
        } catch (\Throwable $e) {
            Log::warning('Full article fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return ['content' => '', 'image' => null];
        }
    }

    private function isSourceEnabled(array $source): bool
    {
        if (($source['enabled'] ?? true) === false) {
            return false;
        }

        $blocked = config('football.disabled_source_names', ['Reuters']);

        return ! in_array($source['name'] ?? '', $blocked, true);
    }

    private function fetchRss(array $source): \Illuminate\Http\Client\Response
    {
        $url = $source['rss_url'] ?? '';
        $timeout = (int) ($source['rss_timeout'] ?? 60);
        $lastError = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::timeout($attempt === 1 ? $timeout : $timeout + 30)
                    ->connectTimeout(20)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; FootballKZ/1.0)'])
                    ->get($url);

                if ($response->successful()) {
                    return $response;
                }

                $lastError = new \RuntimeException('RSS fetch failed: '.$response->status());
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        throw $lastError ?? new \RuntimeException('RSS fetch failed');
    }

    private function normalizeUrl(string $url): string
    {
        return preg_replace('/\s+/u', '', trim($url));
    }

    private function extractLink(\SimpleXMLElement $item): string
    {
        if (! empty($item->link)) {
            return (string) $item->link;
        }

        $namespaces = $item->getNamespaces(true);
        if (isset($namespaces[''])) {
            $atom = $item->children($namespaces['']);
            if (isset($atom->link)) {
                $attrs = $atom->link->attributes();
                if (isset($attrs['href'])) {
                    return (string) $attrs['href'];
                }
            }
        }

        return '';
    }

    private function extractPublishedAt(\SimpleXMLElement $item): string
    {
        if (isset($item->pubDate)) {
            return date('Y-m-d H:i:s', strtotime((string) $item->pubDate));
        }

        if (isset($item->published)) {
            return date('Y-m-d H:i:s', strtotime((string) $item->published));
        }

        return now()->toDateTimeString();
    }

    private function extractSummary(\SimpleXMLElement $item): string
    {
        $description = (string) ($item->description ?? $item->summary ?? $item->children('content', true)->encoded ?? '');
        $text = html_entity_decode(strip_tags($description), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return Str::limit($text, 2000);
    }

    private function extractImage(\SimpleXMLElement $item): ?string
    {
        $namespaces = $item->getNamespaces(true);
        $bestUrl = null;
        $bestWidth = 0;

        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);

            foreach ($media->content ?? [] as $content) {
                $attrs = $content->attributes();
                $url = (string) ($attrs['url'] ?? '');
                $width = (int) ($attrs['width'] ?? 0);
                if ($url !== '' && $width >= $bestWidth) {
                    $bestUrl = $url;
                    $bestWidth = $width;
                }
            }

            if ($bestUrl === null && isset($media->thumbnail)) {
                $attrs = $media->thumbnail->attributes();
                $bestUrl = (string) ($attrs['url'] ?? '');
            }
        }

        if ($bestUrl !== null && $bestUrl !== '') {
            return $bestUrl;
        }

        $description = (string) ($item->description ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /** @return array<int, string> */
    private function extractSoccer365Links(string $html, string $pathPattern = '/\/news\/\d+\/?$/i'): array
    {
        if (str_contains($pathPattern, 'press')) {
            preg_match_all('/press_modal\((\d+)\)/i', $html, $idMatches);
            $ids = array_values(array_unique($idMatches[1] ?? []));
            $links = array_map(static fn (string $id) => 'https://soccer365.ru/press/'.$id.'/', $ids);

            return array_slice($links, 0, 50);
        }

        preg_match_all('/href=["\'](\/[^"\']+)["\']/i', $html, $matches);
        $paths = array_filter($matches[1] ?? [], static fn (string $path) => preg_match($pathPattern, $path) === 1);
        $links = array_map(static fn (string $path) => 'https://soccer365.ru'.$path, $paths);
        $links = array_values(array_unique($links));

        return array_slice($links, 0, 50);
    }

    private function extractTitleFromHtml(string $html): ?string
    {
        if (preg_match('/<div[^>]+id=["\']press_show_title["\'][^>]*>(.*?)<\/div>/is', $html, $m)) {
            $title = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
            $title = preg_replace('/\s+/', ' ', trim($title)) ?? '';
            if ($title !== '') {
                return $title;
            }
        }

        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
            if ($title !== '') {
                return $title;
            }
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
            $title = preg_replace('/\s+/', ' ', trim($title)) ?? '';

            return $title !== '' ? $title : null;
        }

        return null;
    }

    /**
     * Soccer365 часто хранит текст в div+br без p-тегов, поэтому нужен отдельный парсер.
     *
     * @return array{content: string, image: ?string}
     */
    private function parseSoccer365Article(string $html, string $url): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        if (! $loaded) {
            return ['content' => '', 'image' => null];
        }

        $xpath = new DOMXPath($dom);

        $image = null;
        $imageNode = $xpath->query('//meta[@property="og:image"]/@content');
        if ($imageNode !== false && $imageNode->length > 0) {
            $candidate = trim((string) $imageNode->item(0)?->nodeValue);
            $image = $candidate !== '' ? $candidate : null;
        }

        $nodes = $xpath->query('//div[contains(@class,"news_body")]');
        $isPress = false;
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query('//*[@id="press_show_fulltext"]');
            $isPress = true;
        }
        if ($nodes === false || $nodes->length === 0) {
            // fallback на общий парсер
            return $this->contentParser->parse($url, $html);
        }

        $bodyHtml = '';
        $node = $nodes->item(0);
        if ($node !== null) {
            foreach ($node->childNodes as $child) {
                $bodyHtml .= $dom->saveHTML($child);
            }
        }

        // Чистим рекламу/виджеты и тех-блоки
        $bodyHtml = preg_replace('/<table[^>]*class="[^"]*adv[^"]*"[^>]*>.*?<\/table>/is', '', $bodyHtml) ?? $bodyHtml;
        $bodyHtml = preg_replace('/<div[^>]*class="[^"]*(adv|news_prediction|bookmaker|share|press_show_links)[^"]*"[^>]*>.*?<\/div>/is', '', $bodyHtml) ?? $bodyHtml;
        $bodyHtml = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $bodyHtml) ?? $bodyHtml;
        $bodyHtml = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $bodyHtml) ?? $bodyHtml;

        // Нормализуем переносы
        $bodyHtml = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml);
        $text = html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/\r\n|\r/u", "\n", $text) ?? $text;
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        $text = trim($text);

        // Отрезаем хвосты страницы (ближайшие матчи/темы и т.п.)
        $stopNeedles = [
            'Оцените материал',
            'Ближайшие матчи',
            'Последние темы',
            'Прогноз Soccer365.ru',
            'Реклама 18+',
        ];
        foreach ($stopNeedles as $needle) {
            $pos = mb_stripos($text, $needle);
            if ($pos !== false && $pos > 400) {
                $text = trim(mb_substr($text, 0, $pos));
                break;
            }
        }

        if ($isPress) {
            // У пресс-страниц короткое тело и один основной абзац.
            $text = preg_replace('/\bЧитать на\s+[^\n]+/u', '', $text) ?? $text;
            $text = trim($text);
        }

        return [
            'content' => Str::limit($text, 15000, ''),
            'image' => $image,
        ];
    }
}
