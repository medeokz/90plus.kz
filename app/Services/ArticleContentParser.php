<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

class ArticleContentParser
{
    /** @var array<string, array<string>> */
    private array $articleContainers = [
        'bbc.co.uk' => [
            '//article',
            '//main[@id="main-content"]',
        ],
        'theguardian.com' => [
            '//div[contains(@class, "article-body-commercial-selector")]',
            '//article',
        ],
        'skysports.com' => [
            '//div[contains(@class, "sdc-article-body")]',
            '//article',
        ],
        'espn.com' => [
            '//article',
            '//main',
        ],
        'reuters.com' => [
            '//article',
            '//main',
        ],
        'cbssports.com' => [
            '//div[contains(@class, "Article-body")]',
            '//article',
        ],
        'premierleague.com' => [
            '//div[contains(@class, "articleBody")]',
            '//article',
        ],
        'uefa.com' => [
            '//section[contains(@class, "article-body")]',
            '//article',
        ],
        'goal.com' => [
            '//div[contains(@class, "article_body")]',
            '//article',
        ],
        'talksport.com' => [
            '//div[contains(@class, "article-content")]',
            '//div[contains(@class, "entry-content")]',
        ],
        'worldsoccertalk.com' => [
            '//div[contains(@class, "entry-content")]',
            '//div[contains(@class, "post-content")]',
            '//article[contains(@class, "post")]',
        ],
    ];

    public function parse(string $url, string $html): array
    {
        $dom = $this->loadHtml($html);
        if ($dom === null) {
            return ['content' => '', 'image' => null];
        }

        $xpath = new DOMXPath($dom);
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $blocks = $this->extractBlocks($xpath, $host);

        if (empty($blocks)) {
            $blocks = $this->genericExtract($xpath);
        }

        $content = $this->serializeBlocks($blocks);
        $image = $this->extractOgImage($xpath) ?? $this->extractFirstArticleImage($xpath);

        return [
            'content' => $content,
            'image' => $image,
        ];
    }

    private function loadHtml(string $html): ?DOMDocument
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOWARNING | LIBXML_NOERROR
        );
        libxml_clear_errors();

        return $loaded ? $dom : null;
    }

    /** @return array<int, array{type: string, level?: int, text: string}> */
    private function extractBlocks(DOMXPath $xpath, string $host): array
    {
        $domain = $this->matchDomain($host);
        $queries = $domain !== null
            ? ($this->articleContainers[$domain] ?? ['//article', '//main'])
            : ['//article', '//main'];

        foreach ($queries as $query) {
            $containers = $xpath->query($query);
            if ($containers === false || $containers->length === 0) {
                continue;
            }

            $blocks = [];
            $stop = false;
            $this->walkContainer($containers->item(0), $blocks, $stop);

            if (count($blocks) >= 2) {
                return $blocks;
            }
        }

        return [];
    }

    /** @param array<int, array{type: string, level?: int, text: string}> $blocks */
    private function walkContainer(?DOMNode $node, array &$blocks, bool &$stop = false): void
    {
        if ($node === null || $stop) {
            return;
        }

        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);

            if (in_array($tag, ['p', 'h2', 'h3', 'h4', 'blockquote'], true)) {
                $text = $this->getInlineHtml($node);
                if ($this->isValidBlock($tag, $text)) {
                    if ($tag === 'blockquote') {
                        $blocks[] = ['type' => 'blockquote', 'text' => strip_tags($text)];
                    } elseif (str_starts_with($tag, 'h')) {
                        $blocks[] = ['type' => 'heading', 'level' => (int) substr($tag, 1), 'text' => strip_tags($text)];
                    } else {
                        $blocks[] = ['type' => 'paragraph', 'text' => $text];
                    }
                }

                return;
            }

            if (in_array($tag, ['ul', 'ol'], true)) {
                foreach ($node->getElementsByTagName('li') as $li) {
                    $text = trim($li->textContent ?? '');
                    if ($this->isValidBlock('li', $text)) {
                        $blocks[] = ['type' => 'list_item', 'text' => $text];
                    }
                }

                return;
            }

            if (in_array($tag, ['script', 'style', 'nav', 'footer', 'aside', 'form', 'iframe'], true)) {
                return;
            }

            if (in_array($tag, ['div', 'section', 'figure'], true) && $this->isPromotionalContainer($node)) {
                $stop = true;

                return;
            }
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->walkContainer($child, $blocks, $stop);
                if ($stop) {
                    return;
                }
            }
        }
    }

    /** @return array<int, array{type: string, level?: int, text: string}> */
    private function genericExtract(DOMXPath $xpath): array
    {
        $blocks = [];
        $queries = [
            '//article//*[self::p or self::h2 or self::h3 or self::blockquote]',
            '//main//*[self::p or self::h2 or self::h3 or self::blockquote]',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes === false || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($node->tagName);
                $text = $tag === 'p' ? $this->getInlineHtml($node) : trim($node->textContent ?? '');

                if ($this->isValidBlock($tag, $text)) {
                    if (str_starts_with($tag, 'h')) {
                        $blocks[] = ['type' => 'heading', 'level' => (int) substr($tag, 1), 'text' => strip_tags($text)];
                    } elseif ($tag === 'blockquote') {
                        $blocks[] = ['type' => 'blockquote', 'text' => strip_tags($text)];
                    } else {
                        $blocks[] = ['type' => 'paragraph', 'text' => $text];
                    }
                }
            }

            if (count($blocks) >= 2) {
                return $blocks;
            }
        }

        return [];
    }

    /** @param array<int, array{type: string, level?: int, text: string}> $blocks */
    private function serializeBlocks(array $blocks): string
    {
        $blocks = $this->filterPromotionalBlocks($blocks);
        $parts = [];

        foreach ($blocks as $block) {
            $text = trim($block['text']);
            if ($text === '') {
                continue;
            }

            $parts[] = match ($block['type']) {
                'heading' => '[h'.($block['level'] ?? 2).']'.$text.'[/h'.($block['level'] ?? 2).']',
                'blockquote' => '[blockquote]'.$text.'[/blockquote]',
                'list_item' => '[li]'.$text.'[/li]',
                default => $text,
            };
        }

        $content = implode("\n\n", array_unique($parts));

        return Str::limit(trim($content), 15000);
    }

    private function getInlineHtml(DOMElement $node): string
    {
        $inner = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $inner .= $child->textContent;
            } elseif ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['strong', 'b', 'em', 'i'], true)) {
                    $inner .= '<'.$tag.'>'.trim($child->textContent ?? '').'</'.$tag.'>';
                } else {
                    $inner .= $child->textContent;
                }
            }
        }

        return trim(preg_replace('/\s+/', ' ', $inner) ?? '');
    }

    private function isValidBlock(string $tag, string $text): bool
    {
        $plain = strip_tags($text);
        $minLength = match (true) {
            str_starts_with($tag, 'h') => 10,
            $tag === 'li' => 25,
            default => 40,
        };

        if (mb_strlen($plain) < $minLength) {
            return false;
        }

        if ($this->isPromotionalContent($plain)) {
            return false;
        }

        return true;
    }

    /** @param array<int, array{type: string, level?: int, text: string}> $blocks */
    private function filterPromotionalBlocks(array $blocks): array
    {
        return array_values(array_filter(
            $blocks,
            fn (array $block) => ! $this->isPromotionalContent(strip_tags($block['text']))
        ));
    }

    private function isPromotionalContainer(DOMElement $node): bool
    {
        $class = mb_strtolower($node->getAttribute('class'));
        $id = mb_strtolower($node->getAttribute('id'));

        $needles = [
            'advert', 'advertisement', 'ad-', 'ads-', 'promo', 'promotion',
            'streaming-offer', 'streaming_offer', 'offer-box', 'browse-offer',
            'affiliate', 'commercial', 'newsletter', 'subscribe', 'widget-area',
            'sidebar', 'related-post', 'related_post', 'related-article',
            'trending', 'editors-pick', 'editors_pick', 'editor-pick', 'footer-logo',
            'streaming offers', 'google-preferred', 'more-stories', 'also-read',
            'recommended', 'post-tags', 'tag-list', 'share-buttons',
        ];

        foreach ($needles as $needle) {
            if (str_contains($class, $needle) || str_contains($id, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isPromotionalContent(string $text): bool
    {
        $lower = mb_strtolower(trim($text));

        if ($lower === '') {
            return true;
        }

        $phrases = [
            'cookie', 'subscribe', 'newsletter', 'sign up', 'advertisement',
            'read more', 'related stories', 'all rights reserved', 'share this',
            'browse offers', 'browse offer', 'streaming offers', 'streaming offer',
            'tweet placeholder', 'add as a preferred source on google',
            'preferred source on google', 'follow us on google',
            'price: plans starting', 'price: starting at', 'plans starting at $',
            '200+ channels', 'every mls match in one place', 'many sports & espn',
            '2,000+ soccer games', 'home of the premier league',
            '175+ exclusive epl', 'includes: every mls', 'includes: 80+ sports',
            'watch every mls regular season', 'watch ligue 1, copa libertadores',
            'features laliga, bundesliga', 'features champions league, serie a',
            'now included with standard subscription', 'or espn unlimited for $',
            'latino)', 'leagues cup', 'nwsl', 'fa cup & nwsl',
            'here\'s how fans', 'how fans in the united states can watch',
            'live stream and tv for', 'how to watch', 'confirmed lineups for',
            'will face each other in a 2026 international friendly',
            '28-player squad ahead of the 2026 fifa world cup',
            'unveiling a 28-player squad',
            'will be preparing to the 2026 world cup by hosting the game against',
            'generated excitement ahead of the 2026 world cup',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        if (preg_match('/^price:\s*\$/i', $text)) {
            return true;
        }

        if (preg_match('/\$\d+(?:\.\d{2})?\s*\/\s*mo\b/i', $text)) {
            return true;
        }

        if (preg_match('/^(includes|features|watch):\s/i', $text)) {
            return true;
        }

        return false;
    }

    private function matchDomain(string $host): ?string
    {
        $host = strtolower(str_replace('www.', '', $host));

        foreach (array_keys($this->articleContainers) as $domain) {
            if (str_contains($host, $domain)) {
                return $domain;
            }
        }

        return null;
    }

    private function extractOgImage(DOMXPath $xpath): ?string
    {
        $queries = [
            '//meta[@property="og:image"]/@content',
            '//meta[@property="og:image:secure_url"]/@content',
            '//meta[@name="twitter:image"]/@content',
            '//meta[@name="twitter:image:src"]/@content',
            '//link[@rel="image_src"]/@href',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes !== false && $nodes->length > 0) {
                $url = trim((string) $nodes->item(0)?->nodeValue);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return $this->extractLargestArticleImage($xpath);
    }

    private function extractLargestArticleImage(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//article//img | //main//img');
        if ($nodes === false) {
            return null;
        }

        $bestUrl = null;
        $bestSize = 0;

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $src = trim($node->getAttribute('src') ?: $node->getAttribute('data-src'));
            if ($src === '' || str_contains($src, 'pixel') || str_contains($src, 'logo') || str_contains($src, 'sprite')) {
                continue;
            }

            $srcset = $node->getAttribute('srcset');
            if ($srcset !== '') {
                $largest = $this->parseLargestFromSrcset($srcset);
                if ($largest !== null) {
                    $src = $largest;
                }
            }

            $width = (int) $node->getAttribute('width');
            $size = $width > 0 ? $width : 800;

            if ($size >= $bestSize) {
                $bestUrl = $src;
                $bestSize = $size;
            }
        }

        return $bestUrl;
    }

    private function parseLargestFromSrcset(string $srcset): ?string
    {
        $bestUrl = null;
        $bestWidth = 0;

        foreach (explode(',', $srcset) as $part) {
            $part = trim($part);
            if (preg_match('/^(\S+)\s+(\d+)w$/', $part, $m)) {
                $width = (int) $m[2];
                if ($width > $bestWidth) {
                    $bestWidth = $width;
                    $bestUrl = $m[1];
                }
            }
        }

        return $bestUrl;
    }

    private function extractFirstArticleImage(DOMXPath $xpath): ?string
    {
        return $this->extractLargestArticleImage($xpath);
    }
}
