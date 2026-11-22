<?php

namespace App\Modules\Crawler\Analysis;

/** URL helpers matching the Python engine (urljoin + fragment strip + trailing slash trim). */
final class Url
{
    /** Strip #fragment and a trailing slash, like engine/app/audit.py _norm(). */
    public static function normalize(string $url): string
    {
        $url = explode('#', $url, 2)[0];
        $trimmed = rtrim($url, '/');

        return $trimmed !== '' ? $trimmed : $url;
    }

    public static function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    /** Resolve $ref against $base (RFC 3986, enough for crawling). */
    public static function resolve(string $base, string $ref): string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return $base;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $ref)) {
            return $ref; // absolute (http:, https:, mailto:, ...)
        }

        $b = parse_url($base);
        $scheme = $b['scheme'] ?? 'https';
        $authority = ($b['host'] ?? '').(isset($b['port']) ? ':'.$b['port'] : '');

        if (str_starts_with($ref, '//')) {
            return $scheme.':'.$ref;
        }
        if (str_starts_with($ref, '#')) {
            return explode('#', $base, 2)[0].$ref;
        }
        if (str_starts_with($ref, '?')) {
            return $scheme.'://'.$authority.($b['path'] ?? '/').$ref;
        }

        if (str_starts_with($ref, '/')) {
            $path = $ref;
        } else {
            $basePath = $b['path'] ?? '/';
            $dir = substr($basePath, 0, (int) strrpos($basePath, '/') + 1) ?: '/';
            $path = $dir.$ref;
        }

        [$pathOnly, $rest] = array_pad(preg_split('/(?=[?#])/', $path, 2), 2, '');

        return $scheme.'://'.$authority.self::removeDotSegments($pathOnly).$rest;
    }

    private static function removeDotSegments(string $path): string
    {
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                if (count($out) > 1) {
                    array_pop($out);
                }
            } elseif ($segment !== '.') {
                $out[] = $segment;
            }
        }
        $result = implode('/', $out);
        if (str_ends_with($path, '/.') || str_ends_with($path, '/..')) {
            $result .= '/';
        }

        return str_starts_with($result, '/') ? $result : '/'.$result;
    }
}
