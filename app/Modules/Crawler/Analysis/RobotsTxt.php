<?php

namespace App\Modules\Crawler\Analysis;

/**
 * robots.txt matcher following Google's rules: the most specific user-agent group applies,
 * the longest matching rule wins, Allow wins a tie, and * and $ wildcards are supported.
 */
final class RobotsTxt
{
    /** @var array<string, list<array{allow:bool, pattern:string}>> */
    private array $groups = [];

    /** @var list<string> */
    private array $sitemaps = [];

    public function __construct(?string $content)
    {
        $agents = [];
        $lastWasAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', (string) $content) as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);

            if ($key === 'user-agent') {
                if (! $lastWasAgent) {
                    $agents = [];
                }
                $agents[] = strtolower($value);
                $this->groups[strtolower($value)] ??= [];
                $lastWasAgent = true;

                continue;
            }
            $lastWasAgent = false;

            if ($key === 'sitemap') {
                $this->sitemaps[] = $value;
            } elseif (($key === 'allow' || $key === 'disallow') && $agents) {
                foreach ($agents as $agent) {
                    if ($value !== '' || $key === 'allow') {
                        $this->groups[$agent][] = ['allow' => $key === 'allow', 'pattern' => $value];
                    }
                }
            }
        }
    }

    /** @return list<string> */
    public function sitemaps(): array
    {
        return $this->sitemaps;
    }

    public function allows(string $userAgent, string $url): bool
    {
        $rules = $this->rulesFor($userAgent);
        if ($rules === null) {
            return true;
        }

        $path = (parse_url($url, PHP_URL_PATH) ?: '/').(($q = parse_url($url, PHP_URL_QUERY)) ? '?'.$q : '');
        $best = null;

        foreach ($rules as $rule) {
            if ($rule['pattern'] === '') {
                continue;
            }
            if ($this->matches($rule['pattern'], $path)) {
                $len = strlen($rule['pattern']);
                if ($best === null || $len > $best['len'] || ($len === $best['len'] && $rule['allow'])) {
                    $best = ['len' => $len, 'allow' => $rule['allow']];
                }
            }
        }

        return $best === null ? true : $best['allow'];
    }

    /** Most specific group whose name appears in the user agent's product token; else '*'. */
    private function rulesFor(string $userAgent): ?array
    {
        $token = strtolower(explode('/', $userAgent)[0]);
        $match = null;

        foreach ($this->groups as $agent => $rules) {
            if ($agent !== '*' && $agent !== '' && str_contains($token, $agent)) {
                if ($match === null || strlen($agent) > strlen($match)) {
                    $match = $agent;
                }
            }
        }

        return $match !== null ? $this->groups[$match] : ($this->groups['*'] ?? null);
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = rtrim($pattern, '$');
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }
}
