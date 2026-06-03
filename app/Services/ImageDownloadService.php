<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageDownloadService
{
    private string $directory;

    public function __construct()
    {
        $this->directory = public_path('images/articles');
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /**
     * Скачивает оригинальное изображение и возвращает локальный путь (/images/articles/...).
     */
    public function download(?string $url, ?string $referer = null): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = $this->resolveAbsoluteUrl($url, $referer);
        $url = $this->normalizeToOriginal($url);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer' => $referer ?? $url,
                ])
                ->get($url);

            if (! $response->successful()) {
                if (! $this->shouldSilenceImageFailure($url, $response->status())) {
                    Log::warning('Image download failed', ['url' => $url, 'status' => $response->status()]);
                }

                return null;
            }

            $body = $response->body();
            if (strlen($body) < 5000) {
                Log::warning('Image too small, skipping', ['url' => $url, 'size' => strlen($body)]);

                return null;
            }

            $extension = $this->detectExtension($url, $response->header('Content-Type'));
            $filename = md5($url).'.'.$extension;
            $path = $this->directory.DIRECTORY_SEPARATOR.$filename;

            if (! file_put_contents($path, $body)) {
                return null;
            }

            return '/images/articles/'.$filename;
        } catch (\Throwable $e) {
            Log::warning('Image download error', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Преобразует URL миниатюры в оригинал/максимальный размер. */
    public function normalizeToOriginal(string $url): string
    {
        if (str_contains($url, 'ichef.bbci.co.uk')) {
            return preg_replace('#(/\d+)(/[^/]+/[^/]+$)#', '/976$2', $url) ?? $url;
        }

        if (str_contains($url, 'i.guim.co.uk')) {
            $url = preg_replace('/width=\d+/', 'width=1200', $url) ?? $url;
            $url = preg_replace('/quality=\d+/', 'quality=100', $url) ?? $url;

            return $url;
        }

        if (str_contains($url, 'skysports.com')) {
            return preg_replace('#/\d+x\d+/#', '/1200x630/', $url) ?? $url;
        }

        if (str_contains($url, 'espncdn.com') || str_contains($url, 'a.espncdn.com')) {
            return preg_replace('#/\d+x\d+#', '/1296x729', $url) ?? $url;
        }

        if (str_contains($url, 'premierleague.com')) {
            return preg_replace('/w_\d+/', 'w_1200', $url) ?? $url;
        }

        return $url;
    }

    private function resolveAbsoluteUrl(string $url, ?string $base): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if ($base && str_starts_with($url, '/')) {
            $parts = parse_url($base);

            return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$url;
        }

        return $url;
    }

    private function shouldSilenceImageFailure(string $url, int $status): bool
    {
        if (! in_array($status, [401, 403], true)) {
            return false;
        }

        return str_contains($url, 'i.guim.co.uk')
            || str_contains($url, 'guim.co.uk');
    }

    private function detectExtension(string $url, ?string $contentType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
        ];

        if ($contentType) {
            $mime = strtolower(trim(explode(';', $contentType)[0]));
            if (isset($map[$mime])) {
                return $map[$mime];
            }
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)
            ? ($ext === 'jpeg' ? 'jpg' : $ext)
            : 'jpg';
    }
}
