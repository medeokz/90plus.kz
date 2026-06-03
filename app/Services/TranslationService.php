<?php

namespace App\Services;

use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationService
{
    private GoogleTranslate $translator;

    public function __construct()
    {
        $driver = config('football.translation_driver', 'google');

        $this->translator = new GoogleTranslate('kk', 'en');

        if ($driver === 'libre') {
            $url = config('football.libretranslate_url');
            $key = config('football.libretranslate_api_key');
            if ($url) {
                $this->translator->setUrl($url);
            }
            if ($key) {
                $this->translator->setApiKey($key);
            }
        }
    }

    /** Короткий текст без форматирования (заголовки, summary). */
    public function toKazakh(?string $text, string $sourceLang = 'en'): ?string
    {
        if (empty(trim($text ?? ''))) {
            return $text;
        }

        try {
            return $this->translatePlainChunk(strip_tags($text), $sourceLang);
        } catch (\Throwable $e) {
            report($e);

            return $text;
        }
    }

    /**
     * Перевод с сохранением форматирования:
     * абзацы (\n\n), заголовки [h2]/[h3], цитаты [blockquote], списки [li], inline <strong>/<em>.
     */
    public function toKazakhFormatted(?string $text, string $sourceLang = 'en'): ?string
    {
        if (empty(trim($text ?? ''))) {
            return $text;
        }

        try {
            $blocks = preg_split('/\n\s*\n/', trim($text)) ?: [$text];
            $translated = [];

            foreach ($blocks as $block) {
                $block = trim($block);
                if ($block === '') {
                    continue;
                }

                try {
                    $translated[] = $this->translateBlock($block, $sourceLang);
                } catch (\Throwable $e) {
                    report($e);
                    $translated[] = $block;
                }

                usleep(200000);
            }

            return implode("\n\n", $translated);
        } catch (\Throwable $e) {
            report($e);

            return $text;
        }
    }

    private function translateBlock(string $block, string $sourceLang = 'en'): string
    {
        if (preg_match('/^\[h([2-4])\](.*)\[\/h\1\]$/s', $block, $m)) {
            return '[h'.$m[1].']'.$this->translatePlainChunk(trim($m[2]), $sourceLang).'[/h'.$m[1].']';
        }

        if (preg_match('/^\[blockquote\](.*)\[\/blockquote\]$/s', $block, $m)) {
            return '[blockquote]'.$this->translatePlainChunk(trim(strip_tags($m[1])), $sourceLang).'[/blockquote]';
        }

        if (preg_match('/^\[li\](.*)\[\/li\]$/s', $block, $m)) {
            return '[li]'.$this->translatePlainChunk(trim(strip_tags($m[1])), $sourceLang).'[/li]';
        }

        if (preg_match('/<[a-z][^>]*>/i', $block)) {
            return $this->translateHtmlPreservingTags($block, $sourceLang);
        }

        return $this->translatePlainChunk($block, $sourceLang);
    }

    private function translateHtmlPreservingTags(string $html, string $sourceLang = 'en'): string
    {
        $html = preg_replace('/(<\/?(?:strong|b|em|i)>)/i', ' $1 ', $html) ?? $html;
        $html = preg_replace('/\s+/u', ' ', $html) ?? $html;

        $parts = preg_split('/(<\/?(?:strong|b|em|i)>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $this->translatePlainChunk(strip_tags($html), $sourceLang);
        }

        $result = '';
        $needSpaceBefore = false;

        foreach ($parts as $part) {
            if (preg_match('/^<\/?(?:strong|b|em|i)>$/i', $part)) {
                $result .= $part;
                $needSpaceBefore = str_starts_with($part, '</');
            } elseif (trim(strip_tags($part)) !== '') {
                $translated = $this->translatePlainChunk($part, $sourceLang);

                if ($needSpaceBefore) {
                    $translated = ltrim($translated);
                    if ($translated !== '' && ! str_ends_with(rtrim($result), ' ') && $this->startsWithWordChar($translated)) {
                        $result .= ' ';
                    }
                    $needSpaceBefore = false;
                } elseif ($translated !== '' && $this->needsSpaceBefore($result) && $this->startsWithWordChar($translated)) {
                    $result .= ' ';
                }

                $result .= $translated;
            }
        }

        return $result !== '' ? $this->normalizeInlineTagSpacing(preg_replace('/\s+/u', ' ', $result) ?? $result) : $this->translatePlainChunk(strip_tags($html), $sourceLang);
    }

    private function normalizeInlineTagSpacing(string $text): string
    {
        $text = preg_replace('/<(strong|em|b|i)>\s+/i', '<$1>', $text) ?? $text;
        $text = preg_replace('/\s+<\/(strong|em|b|i)>/i', '</$1>', $text) ?? $text;

        return $text;
    }

    private function startsWithWordChar(string $text): bool
    {
        $first = mb_substr(ltrim($text), 0, 1);

        return $this->isWordChar($first);
    }

    private function needsSpaceBefore(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $last = mb_substr(rtrim($text), -1);

        return $this->isWordChar($last);
    }

    private function isWordChar(string $char): bool
    {
        return $char !== '' && preg_match('/[\p{L}\p{N}]/u', $char) === 1;
    }

    private function translatePlainChunk(string $text, string $sourceLang = 'en'): string
    {
        if (trim($text) === '') {
            return $text;
        }

        preg_match('/^(\s*)(.*?)(\s*)$/su', $text, $parts);
        $leading = $parts[1] ?? '';
        $core = $parts[2] ?? trim($text);
        $trailing = $parts[3] ?? '';

        if (trim($core) === '') {
            return $text;
        }

        $this->translator->setSource($sourceLang);

        if (mb_strlen($core) <= 4500) {
            try {
                return $leading.$this->translator->translate($core).$trailing;
            } catch (\Throwable $e) {
                report($e);

                return $text;
            }
        }

        $chunks = $this->splitLongText($core);
        $translated = [];

        foreach ($chunks as $chunk) {
            try {
                $translated[] = $this->translator->translate($chunk);
            } catch (\Throwable $e) {
                report($e);
                $translated[] = $chunk;
            }
            usleep(200000);
        }

        return $leading.implode(' ', $translated).$trailing;
    }

    private function splitLongText(string $text, int $maxLength = 4500): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];
        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current.' '.$sentence) > $maxLength) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                }
                $current = $sentence;
            } else {
                $current = trim($current.' '.$sentence);
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return $chunks ?: [$text];
    }
}
