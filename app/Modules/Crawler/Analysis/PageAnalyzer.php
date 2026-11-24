<?php

namespace App\Modules\Crawler\Analysis;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Page-level SEO + AEO/GEO checks. PHP port of engine/app/audit.py analyze_page():
 * same checks, same issue codes, same scoring and the same output shape, so the
 * PHP and Python crawl engines are interchangeable.
 */
final class PageAnalyzer
{
    /** Severity → points deducted from 100. */
    public const WEIGHTS = ['critical' => 15, 'warning' => 6, 'notice' => 2];

    /** AI-readiness has fewer, more decisive checks, so its notices count more. */
    public const AI_WEIGHTS = ['critical' => 15, 'warning' => 6, 'notice' => 6];

    private const QUESTION_RE = '/^(what|why|how|when|where|who|which|can|does|do|is|are|should|will)\b|\?$/iu';

    private array $issues = [];

    public function analyze(string $url, int $status, array $headers, string $html, int $loadMs = 0): array
    {
        $this->issues = [];
        $headers = array_change_key_case(array_map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v, $headers));
        $host = Url::host($url);

        $dom = new DOMDocument;
        if (trim($html) !== '') {
            @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT);
        }
        $xp = new DOMXPath($dom);

        // ---------- HTTP ----------
        if ($status >= 400) {
            $this->issue('seo', 'critical', 'http_error', "Page returns HTTP {$status}.");
        }
        if ($loadMs > 3000) {
            $this->issue('seo', 'warning', 'slow_response', "Server responded in {$loadMs} ms (aim for under 1000 ms).");
        } elseif ($loadMs > 1000) {
            $this->issue('seo', 'notice', 'slowish_response', "Server responded in {$loadMs} ms.");
        }
        $sizeKb = round(strlen($html) / 1024, 1);
        if ($sizeKb > 2048) {
            $this->issue('seo', 'warning', 'large_html', "HTML is {$sizeKb} KB; keep pages lean.");
        }
        $xRobots = strtolower($headers['x-robots-tag'] ?? '');

        // ---------- Head ----------
        $title = trim($this->text($xp->query('//title')->item(0)));
        $titleLen = mb_strlen($title);
        if ($title === '') {
            $this->issue('seo', 'critical', 'title_missing', 'Missing <title> tag.');
        } elseif ($titleLen < 30) {
            $this->issue('seo', 'warning', 'title_short', "Title is {$titleLen} characters (aim for 30–60).");
        } elseif ($titleLen > 60) {
            $this->issue('seo', 'warning', 'title_long', "Title is {$titleLen} characters and may be cut off in Google (aim for 30–60).");
        }

        $description = trim((string) $this->metaContent($xp, 'description'));
        $descLen = mb_strlen($description);
        if ($description === '') {
            $this->issue('seo', 'warning', 'description_missing', 'Missing meta description.');
        } elseif ($descLen < 70) {
            $this->issue('seo', 'notice', 'description_short', "Meta description is {$descLen} characters (aim for 70–160).");
        } elseif ($descLen > 160) {
            $this->issue('seo', 'notice', 'description_long', "Meta description is {$descLen} characters (aim for 70–160).");
        }

        $robotsMeta = strtolower((string) $this->metaContent($xp, 'robots'));
        $noindex = str_contains($robotsMeta, 'noindex') || str_contains($xRobots, 'noindex');
        if ($noindex) {
            $this->issue('seo', 'critical', 'noindex', 'Page is set to noindex — Google will not show it.');
        }

        $canonical = '';
        foreach ($xp->query('//link[@rel]') as $link) {
            $rels = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel'))));
            if (in_array('canonical', $rels, true) && $link->getAttribute('href') !== '') {
                $canonical = Url::resolve($url, $link->getAttribute('href'));
                break;
            }
        }
        if ($canonical === '') {
            $this->issue('seo', 'warning', 'canonical_missing', 'No canonical tag.');
        } elseif (Url::normalize($canonical) !== Url::normalize($url)) {
            $this->issue('seo', 'notice', 'canonical_other', "Canonical points to another URL: {$canonical}");
        }

        $htmlEl = $xp->query('//html')->item(0);
        $lang = $htmlEl instanceof DOMElement ? $htmlEl->getAttribute('lang') : '';
        if ($lang === '') {
            $this->issue('seo', 'notice', 'lang_missing', '<html> has no lang attribute.');
        }
        if ($this->metaContent($xp, 'viewport') === null) {
            $this->issue('seo', 'critical', 'viewport_missing', 'No viewport meta tag — page is not mobile-friendly.');
        }

        $og = [];
        foreach ($xp->query('//meta[starts-with(@property, "og:")]') as $m) {
            $og[$m->getAttribute('property')] = $m->getAttribute('content');
        }
        if (empty($og['og:title']) || empty($og['og:image'])) {
            $this->issue('seo', 'notice', 'og_incomplete', 'Open Graph title/image missing (affects social shares).');
        }
        $hreflang = [];
        foreach ($xp->query('//link[@hreflang]') as $l) {
            $hreflang[] = $l->getAttribute('hreflang');
        }

        // Structured data is read before scripts are stripped from the body.
        [$ld, $ldErrors] = $this->jsonLd($xp);
        $types = $this->types($ld);

        // ---------- Content ----------
        $body = $xp->query('//body')->item(0) ?? $dom;
        foreach (['script', 'style', 'noscript', 'template', 'svg'] as $tag) {
            foreach (iterator_to_array($xp->query('.//'.$tag, $body)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        $text = $this->text($body);
        $wordCount = count(self::words($text));
        if ($wordCount < 300) {
            $this->issue('seo', 'warning', 'thin_content', "Only {$wordCount} words of content (thin page).");
        }

        $h1s = [];
        foreach ($xp->query('//h1') as $h) {
            $h1s[] = $this->text($h);
        }
        if (! $h1s) {
            $this->issue('seo', 'critical', 'h1_missing', 'No H1 heading.');
        } elseif (count($h1s) > 1) {
            $this->issue('seo', 'notice', 'h1_multiple', count($h1s).' H1 headings found; use one.');
        }

        $headings = [];
        foreach ($xp->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') as $h) {
            $headings[] = ['level' => (int) substr($h->nodeName, 1), 'text' => $this->text($h), 'node' => $h];
        }
        $prev = 0;
        foreach ($headings as $h) {
            if ($prev && $h['level'] > $prev + 1) {
                $this->issue('seo', 'notice', 'heading_skip', "Heading levels skip from H{$prev} to H{$h['level']}.");
                break;
            }
            $prev = $h['level'];
        }

        $images = [];
        $noAlt = [];
        $imgNodes = iterator_to_array($xp->query('//img'));
        foreach ($imgNodes as $i => $img) {
            $alt = trim($img->getAttribute('alt'));
            if ($i < 200) {
                $images[] = ['src' => Url::resolve($url, $img->getAttribute('src')), 'alt' => $alt];
            }
            if ($alt === '') {
                $noAlt[] = $img->getAttribute('src');
            }
        }
        if ($noAlt) {
            $this->issue('seo', 'warning', 'img_alt_missing', count($noAlt).' of '.count($imgNodes).' images have no alt text.');
        }

        // ---------- Links ----------
        $internal = $external = $links = $seen = [];
        foreach ($xp->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || preg_match('/^(mailto:|tel:|javascript:|#)/i', $href)) {
                continue;
            }
            $abs = Url::clean(Url::resolve($url, $href));
            if (! str_starts_with($abs, 'http')) {
                continue;
            }
            $isInternal = Url::host($abs) === $host;
            $isInternal ? $internal[$abs] = true : $external[$abs] = true;
            $anchor = mb_substr($this->text($a), 0, 300);
            $key = $abs."\0".$anchor;
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $rel = preg_split('/\s+/', strtolower(trim($a->getAttribute('rel'))));
                $links[] = ['url' => $abs, 'anchor' => $anchor, 'internal' => $isInternal, 'nofollow' => in_array('nofollow', $rel, true)];
            }
        }
        if (! $internal) {
            $this->issue('seo', 'notice', 'no_internal_links', 'No internal links on this page.');
        }

        // ---------- Structured data ----------
        if ($ldErrors) {
            $this->issue('seo', 'warning', 'schema_invalid', "{$ldErrors} JSON-LD block(s) contain invalid JSON.");
        }
        if (! $ld) {
            $this->issue('ai', 'warning', 'schema_missing', 'No structured data (JSON-LD). AI engines and rich results rely on it.');
        }

        // ---------- AEO / GEO ----------
        $questionHeads = [];
        foreach ($headings as $h) {
            if ($h['level'] >= 2 && preg_match(self::QUESTION_RE, $h['text'])) {
                $questionHeads[] = $h['text'];
            }
        }
        if (! $questionHeads) {
            $this->issue('ai', 'notice', 'no_question_headings',
                'No question-style headings (What/How/Why…). These help pages get quoted in AI answers and featured snippets.');
        }

        $weakAnswers = 0;
        foreach ($headings as $h) {
            if ($h['level'] >= 2 && $h['level'] <= 4 && preg_match(self::QUESTION_RE, $h['text'])) {
                $next = $xp->query('following::*[self::p or self::ul or self::ol][1]', $h['node'])->item(0);
                $n = $next ? count(self::words($this->text($next))) : 0;
                if ($n < 25 || $n > 80) {
                    $weakAnswers++;
                }
            }
        }
        if ($questionHeads && $weakAnswers) {
            $this->issue('ai', 'notice', 'answers_not_concise', "{$weakAnswers} question heading(s) aren't followed by a direct 25–80 word answer.");
        }
        if ($questionHeads && ! in_array('FAQPage', $types, true)) {
            $this->issue('ai', 'notice', 'faq_schema_missing', 'Page has Q&A content but no FAQPage schema.');
        }

        $hasLists = $xp->query('//ul | //ol')->length > 0;
        $hasTables = $xp->query('//table')->length > 0;
        if ($wordCount >= 600 && ! ($hasLists || $hasTables)) {
            $this->issue('ai', 'notice', 'no_structure', 'Long page with no lists or tables; structured content is easier for AI to extract.');
        }

        $author = $this->metaContent($xp, 'author') !== null;
        $dated = false;
        foreach ($ld as $obj) {
            $author = $author || array_key_exists('author', $obj);
            $dated = $dated || array_key_exists('datePublished', $obj) || array_key_exists('dateModified', $obj);
        }
        $dated = $dated
            || $xp->query('//meta[@property="article:published_time" or @property="article:modified_time"]')->length > 0
            || $xp->query('//time')->length > 0;
        if (! $author) {
            $this->issue('ai', 'notice', 'no_author', 'No author signal (E-E-A-T). Add an author byline or Article.author schema.');
        }
        if (! $dated) {
            $this->issue('ai', 'notice', 'no_date', 'No published/updated date. AI engines prefer fresh, dated content.');
        }
        if (! array_intersect(['Organization', 'LocalBusiness', 'Person', 'WebSite'], $types)) {
            $this->issue('ai', 'notice', 'no_entity_schema', 'No Organization/LocalBusiness schema to define your brand entity.');
        }

        $sentences = array_filter(preg_split('/[.!?]+\s/u', $text), fn ($s) => count(self::words($s)) > 2);
        $avgSentence = $sentences ? round($wordCount / count($sentences), 1) : 0;
        if ($avgSentence > 25) {
            $this->issue('ai', 'notice', 'long_sentences', "Average sentence is {$avgSentence} words; shorter sentences are easier to quote.");
        }
        $stats = preg_match_all('/\b\d+(?:[.,]\d+)?\s?(?:%|percent|million|billion|k)\b/iu', $text);

        sort($types);
        $internalUrls = array_keys($internal);
        $externalUrls = array_keys($external);
        sort($internalUrls);
        sort($externalUrls);

        // An error page has no content worth auditing: report the HTTP error only.
        if ($status >= 400) {
            $this->issues = array_values(array_filter($this->issues, fn ($i) => $i['code'] === 'http_error'));
        }

        return [
            'url' => $url,
            'status' => $status,
            'load_ms' => $loadMs,
            'size_kb' => $sizeKb,
            'indexable' => $status < 400 && ! $noindex,
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'lang' => $lang,
            'h1' => $h1s,
            'headings' => array_map(fn ($h) => ['level' => $h['level'], 'text' => $h['text']], array_slice($headings, 0, 50)),
            'word_count' => $wordCount,
            'images' => $images,
            'image_count' => count($imgNodes),
            'images_missing_alt' => array_slice($noAlt, 0, 20),
            'internal_links' => $internalUrls,
            'external_links' => $externalUrls,
            'links' => $links,
            'body_text' => mb_substr($text, 0, 100_000),
            'schema_types' => array_values($types),
            'hreflang' => $hreflang,
            'open_graph' => $og,
            'ai_signals' => [
                'question_headings' => array_slice($questionHeads, 0, 20),
                'faq_schema' => in_array('FAQPage', $types, true),
                'lists' => $hasLists,
                'tables' => $hasTables,
                'author' => $author,
                'dated' => $dated,
                'stats_mentions' => $stats,
                'avg_sentence_words' => $avgSentence,
            ],
            'scores' => ['seo' => $this->score('seo'), 'ai' => $this->score('ai')],
            'issues' => $this->issues,
        ];
    }

    /** @return list<string> */
    public static function words(string $text): array
    {
        preg_match_all("/[\\p{L}\\p{N}_'-]+/u", $text, $m);

        return $m[0];
    }

    private function issue(string $area, string $severity, string $code, string $message): void
    {
        $this->issues[] = ['area' => $area, 'severity' => $severity, 'code' => $code, 'message' => $message];
    }

    private function score(string $area): int
    {
        $weights = $area === 'ai' ? self::AI_WEIGHTS : self::WEIGHTS;
        $penalty = 0;
        foreach ($this->issues as $i) {
            if ($i['area'] === $area) {
                $penalty += $weights[$i['severity']];
            }
        }

        return max(0, 100 - $penalty);
    }

    /** Text of a node: trimmed text pieces joined by single spaces (like BeautifulSoup get_text(" ", strip=True)). */
    private function text(?DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }
        $parts = [];
        $walk = function (DOMNode $n) use (&$walk, &$parts) {
            foreach ($n->childNodes ?? [] as $child) {
                if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                    $t = trim($child->nodeValue ?? '');
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                    $walk($child);
                }
            }
        };
        $node->nodeType === XML_TEXT_NODE ? $parts[] = trim($node->nodeValue ?? '') : $walk($node);

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));
    }

    /** Content of <meta name="..."> (case-insensitive name); null when the tag is absent. */
    private function metaContent(DOMXPath $xp, string $name): ?string
    {
        $q = sprintf('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]', $name);
        $node = $xp->query($q)->item(0);

        return $node instanceof DOMElement ? $node->getAttribute('content') : null;
    }

    /** @return array{0: list<array>, 1: int} JSON-LD objects (with @graph flattened) and invalid block count */
    private function jsonLd(DOMXPath $xp): array
    {
        $items = [];
        $errors = 0;
        foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors++;

                continue;
            }
            $stack = is_array($data) && array_is_list($data) ? $data : [$data];
            while ($stack) {
                $obj = array_pop($stack);
                if (is_array($obj) && ! array_is_list($obj)) {
                    $items[] = $obj;
                    if (isset($obj['@graph']) && is_array($obj['@graph']) && array_is_list($obj['@graph'])) {
                        array_push($stack, ...$obj['@graph']);
                    }
                }
            }
        }

        return [$items, $errors];
    }

    /** @return list<string> */
    private function types(array $items): array
    {
        $out = [];
        foreach ($items as $obj) {
            $t = $obj['@type'] ?? null;
            foreach (is_array($t) ? $t : [$t] as $v) {
                if (is_string($v)) {
                    $out[$v] = true;
                }
            }
        }

        return array_keys($out);
    }
}
