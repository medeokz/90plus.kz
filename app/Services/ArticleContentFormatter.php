<?php

namespace App\Services;

class ArticleContentFormatter
{
    public function toHtml(string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        $blocks = preg_split('/\n\s*\n/', trim($content)) ?: [];
        $html = '';
        $listOpen = false;

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            if (preg_match('/^\[h([2-4])\](.*)\[\/h\1\]$/s', $block, $m)) {
                $html .= $this->closeList($listOpen);
                $listOpen = false;
                $level = (int) $m[1];
                $html .= '<h'.$level.'>'.e(trim($m[2])).'</h'.$level.'>';
                continue;
            }

            if (preg_match('/^\[blockquote\](.*)\[\/blockquote\]$/s', $block, $m)) {
                $html .= $this->closeList($listOpen);
                $listOpen = false;
                $html .= '<blockquote>'.e(trim($m[1])).'</blockquote>';
                continue;
            }

            if (preg_match('/^\[li\](.*)\[\/li\]$/s', $block, $m)) {
                if (! $listOpen) {
                    $html .= '<ul>';
                    $listOpen = true;
                }
                $html .= '<li>'.e(trim($m[1])).'</li>';
                continue;
            }

            $html .= $this->closeList($listOpen);
            $listOpen = false;
            $html .= '<p>'.$this->formatInlineHtml($block).'</p>';
        }

        $html .= $this->closeList($listOpen);

        return $html;
    }

    private function closeList(bool $open): string
    {
        return $open ? '</ul>' : '';
    }

    private function formatInlineHtml(string $text): string
    {
        $allowed = strip_tags($text, '<strong><b><em><i>');
        $allowed = preg_replace('/<(b)>/i', '<strong>', $allowed) ?? $allowed;
        $allowed = preg_replace('/<\/(b)>/i', '</strong>', $allowed) ?? $allowed;
        $allowed = preg_replace('/<(i)>/i', '<em>', $allowed) ?? $allowed;
        $allowed = preg_replace('/<\/(i)>/i', '</em>', $allowed) ?? $allowed;

        $parts = preg_split('/(<\/?(?:strong|em)>)/', $allowed, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return e($text);
        }

        $result = '';
        foreach ($parts as $part) {
            if (preg_match('/^<\/?(?:strong|em)>$/', $part)) {
                $result .= $part;
            } else {
                $result .= e($part);
            }
        }

        return $result;
    }
}
