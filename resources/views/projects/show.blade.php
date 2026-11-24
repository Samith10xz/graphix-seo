@extends('layouts.app', ['title' => $project->domain])
@use('App\Support\Ui')

@section('content')
@include('partials.project-header')

@if ($progress && $progress['running'])
<section class="card live" id="live" data-url="{{ route('projects.status', $project) }}">
    <div style="flex:1 1 360px;min-width:0">
        <div style="display:flex;align-items:center;gap:10px;font-weight:600"><span class="pulse"></span>
            <span id="live-head">{{ $progress['status'] === 'queued' ? 'Starting crawl of '.$project->domain.'…' : 'Crawling '.$project->domain }}</span></div>
        <div class="bar"><span id="live-bar" style="width:{{ $progress['percent'] }}%"></span></div>
        <div style="color:#CBD5E1;font-size:13px;margin-top:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <span id="live-now">{{ $progress['current'] ? 'Last: '.Ui::path($progress['current']) : 'Waiting for the first pages (the queue runs every minute)…' }}</span>
        </div>
    </div>
    <div style="display:flex;gap:28px;flex-wrap:wrap">
        <div><div class="big" style="color:#fff" id="live-pages">{{ Ui::num($progress['pages_crawled']) }}</div><div style="color:#CBD5E1;font-size:12px">pages crawled</div></div>
        <div><div class="big" style="color:#fff" id="live-links">{{ Ui::num($progress['links']) }}</div><div style="color:#CBD5E1;font-size:12px">links found</div></div>
        <div><div class="big" style="color:#FCA5A5" id="live-crit">{{ Ui::num($progress['critical']) }}</div><div style="color:#CBD5E1;font-size:12px">critical issues</div></div>
    </div>
</section>
@elseif ($crawl && $crawl->status === 'failed')
<div class="alert crit">The last crawl failed: {{ $crawl->error ?: 'unknown error' }}. Check the site is reachable, then re-crawl.</div>
@endif

@if (! $shown || ($shown->status !== 'done' && $progress['pages_crawled'] === 0))
    <section class="card empty">
        <p class="ct">Results will appear here</p>
        <div class="small">The crawler checks every page, link and image. Charts fill in as pages arrive; scores appear when the crawl finishes.</div>
    </section>
@else
@php
    $statusTotal = max(1, $status['total']);
    $done = $shown->status === 'done';
@endphp

@if (! $done)
    <div class="alert info small">Showing pages crawled so far. Scores and site-wide checks appear when the crawl finishes.</div>
@elseif ($crawl && $crawl->id !== $shown->id)
    <div class="alert info small">Showing the crawl from {{ $shown->finished_at?->diffForHumans() }} while the new crawl runs.</div>
@endif

<div class="grid g240">
    @foreach ([['SEO score', $shown->seo_score, '#4F46E5', $changes['seo_before'] ?? null, 'Titles, headings, links, indexing and speed'], ['AI-readiness', $shown->ai_score, '#EA580C', $changes['ai_before'] ?? null, 'How likely ChatGPT, Gemini and Perplexity cite you']] as [$label, $score, $color, $before, $hint])
    <div class="card" style="display:flex;gap:16px;align-items:center">
        <svg width="84" height="84" viewBox="0 0 84 84" role="img" aria-label="{{ $label }} {{ $score ?? 'pending' }} out of 100">
            <circle cx="42" cy="42" r="34" fill="none" stroke="#E2E8F0" stroke-width="9"/>
            <circle cx="42" cy="42" r="34" fill="none" stroke="{{ $color }}" stroke-width="9" stroke-linecap="round" stroke-dasharray="{{ Ui::ring($score) }}" transform="rotate(-90 42 42)"/>
            <text x="42" y="49" text-anchor="middle" font-family="Space Grotesk" font-size="22" font-weight="700" fill="#0F172A">{{ $score ?? '…' }}</text>
        </svg>
        <div>
            <p class="ct">{{ $label }}</p>
            <div class="muted small">
                @if ($score !== null && $before !== null && $score !== $before)
                    {{ $score > $before ? 'Up' : 'Down' }} {{ abs($score - $before) }} since last crawl
                @else
                    {{ $hint }}
                @endif
            </div>
        </div>
    </div>
    @endforeach
    <a class="card" href="{{ route('crawler.issues', $project) }}" style="text-decoration:none;color:inherit">
        <p class="ct">Issues found</p>
        <div style="display:flex;gap:18px;margin-top:10px;flex-wrap:wrap">
            <div><div class="big" style="color:#B91C1C">{{ Ui::num($severity['critical']) }}</div><div class="muted xs">Critical</div></div>
            <div><div class="big" style="color:#B45309">{{ Ui::num($severity['warning']) }}</div><div class="muted xs">Warnings</div></div>
            <div><div class="big" style="color:#475569">{{ Ui::num($severity['notice']) }}</div><div class="muted xs">Notices</div></div>
        </div>
    </a>
</div>

@php
    $steps = $project->steps;
    $currentStep = $steps->first(fn ($s) => $s->status !== 'approved');
@endphp
@if ($steps->isNotEmpty())
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <div><p class="ct">SEO plan · {{ $steps->count() }} steps</p><div class="muted small">Each step unlocks when the one before it is approved.</div></div>
        @if ($currentStep)
            <span class="pill info">Step {{ $steps->search(fn ($s) => $s->id === $currentStep->id) + 1 }}: {{ $currentStep->name }}</span>
        @else
            <span class="pill ok">Plan complete</span>
        @endif
    </div>
    <div class="steps">
        @foreach ($steps as $i => $step)
            @php
                $cls = match ($step->status) { 'approved' => 'done', 'locked' => 'lk', default => 'cur' };
                $label = match ($step->status) { 'approved' => 'Approved', 'locked' => 'Locked', 'generating' => 'Preparing data…', 'review' => 'Ready for review', default => 'Open' };
            @endphp
            <div class="stp {{ $cls }}">
                <div class="sn">STEP {{ $i + 1 }}</div>
                <div style="font-weight:600;margin-top:4px">{{ $step->name }}</div>
                <div style="font-size:12px;margin-top:6px;{{ $cls === 'cur' ? 'color:#3730A3;font-weight:600' : '' }}">{{ $label }}</div>
            </div>
        @endforeach
    </div>
</section>
@endif

<div class="grid g300">
    <section class="card">
        <p class="ct">Status codes</p>
        @php
            $segments = [['200 OK', $status['2xx'], '#4F46E5', 'all'], ['3xx Redirect', $status['3xx'], '#F59E0B', 'redirects'], ['4xx Broken', $status['4xx'], '#DC2626', 'broken'], ['5xx Server error', $status['5xx'], '#7F1D1D', 'broken']];
            $circ = 2 * M_PI * 44; $offset = 0;
        @endphp
        <div style="display:flex;align-items:center;gap:18px;margin-top:10px;flex-wrap:wrap">
            <svg width="120" height="120" viewBox="0 0 120 120" role="img" aria-label="Status codes: {{ $status['2xx'] }} OK, {{ $status['3xx'] }} redirects, {{ $status['4xx'] + $status['5xx'] }} errors">
                <circle cx="60" cy="60" r="44" fill="none" stroke="#E2E8F0" stroke-width="18"/>
                @foreach ($segments as [$l, $n, $col])
                    @if ($n > 0)
                        @php $len = $n / $statusTotal * $circ; @endphp
                        <circle cx="60" cy="60" r="44" fill="none" stroke="{{ $col }}" stroke-width="18" stroke-dasharray="{{ round($len, 1) }} {{ round($circ, 1) }}" stroke-dashoffset="{{ round(-$offset, 1) }}" transform="rotate(-90 60 60)"/>
                        @php $offset += $len; @endphp
                    @endif
                @endforeach
                <text x="60" y="66" text-anchor="middle" font-size="18" font-weight="700" fill="#0F172A">{{ Ui::num($status['total']) }}</text>
            </svg>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
                @foreach ($segments as [$l, $n, $col, $f])
                    <a href="{{ route('crawler.explorer', [$project, 'f' => $f]) }}" style="color:inherit;text-decoration:none"><span class="legend" style="background:{{ $col }}"></span>{{ $l }} · {{ Ui::num($n) }}</a>
                @endforeach
            </div>
        </div>
    </section>
    <section class="card">
        <p class="ct">Crawl depth</p>
        <div class="muted xs">Clicks from the home page</div>
        @php $maxDepth = max(1, max($depths)); @endphp
        <div class="vbars">
            @foreach ($depths as $d => $n)
                <div title="{{ $n }} pages at depth {{ $d }}"><span class="xs muted">{{ $n }}</span><i style="height:{{ max(2, round($n / $maxDepth * 90)) }}px;{{ $d === '4+' ? 'background:#EA580C' : '' }}"></i><span class="xs">{{ $d }}</span></div>
            @endforeach
        </div>
        <div class="muted xs" style="margin-top:8px">{{ $depths['4+'] > 0 ? $depths['4+'].' '.\Illuminate\Support\Str::plural('page', $depths['4+']).' '.($depths['4+'] === 1 ? 'is' : 'are').' 4+ clicks deep. Link to them from higher pages.' : 'Every page is within 3 clicks. Good.' }}</div>
    </section>
    <section class="card">
        <p class="ct">Indexability</p>
        @php $idxTotal = max(1, array_sum($indexability)); @endphp
        <div class="bar" style="height:16px;margin-top:14px">
            <span style="width:{{ Ui::pct($indexability['indexable'], $idxTotal) }}%;background:#4F46E5"></span>
            <span style="width:{{ Ui::pct($indexability['canonicalised'], $idxTotal) }}%;background:#F59E0B"></span>
            <span style="width:{{ Ui::pct($indexability['noindex'] + $indexability['other'], $idxTotal) }}%;background:#DC2626"></span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;margin-top:14px">
            <div style="display:flex;justify-content:space-between"><span><span class="legend" style="background:#4F46E5"></span>Indexable</span><strong>{{ Ui::num($indexability['indexable']) }}</strong></div>
            <div style="display:flex;justify-content:space-between"><span><span class="legend" style="background:#F59E0B"></span>Canonicalised to another URL</span><strong>{{ Ui::num($indexability['canonicalised']) }}</strong></div>
            <div style="display:flex;justify-content:space-between"><span><span class="legend" style="background:#DC2626"></span>Noindex / blocked</span><strong>{{ Ui::num($indexability['noindex'] + $indexability['other']) }}</strong></div>
        </div>
    </section>
</div>

<div class="grid g360">
    <section class="card">
        <div style="display:flex;justify-content:space-between;align-items:center"><p class="ct">Top issues to fix first</p><a href="{{ route('crawler.issues', $project) }}" style="font-weight:600;font-size:13px">All issues</a></div>
        @forelse ($topIssues as $issue)
            <a class="issue" href="{{ route('crawler.issues', [$project, 'code' => $issue['code']]) }}">
                <span class="pill {{ Ui::severityClass($issue['severity']) }}">{{ ucfirst($issue['severity']) }}</span>
                <span style="flex:1">{{ config('issue_guides.'.$issue['code'].'.title') ?? $issue['message'] }}</span>
                <strong>{{ $issue['pages'] ? Ui::num($issue['pages']).' '.\Illuminate\Support\Str::plural('page', $issue['pages']) : 'site' }}</strong>
            </a>
        @empty
            <div class="muted small" style="padding:12px 0">No issues found yet.</div>
        @endforelse
    </section>
    <section class="card">
        <div style="display:flex;justify-content:space-between;align-items:center"><p class="ct">Keyword usage</p><a href="{{ route('keywords.index', $project) }}" style="font-weight:600;font-size:13px">Keyword Tracker</a></div>
        @if ($keywordStats['tracked'] === 0)
            <div class="muted small" style="padding:12px 0">No keywords yet. <a href="{{ route('keywords.index', $project) }}">Add your target keywords</a> to see where each page uses them.</div>
        @else
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:12px">
            <div class="stat"><div class="big">{{ $keywordStats['tracked'] }}</div><div class="muted xs">keywords tracked</div></div>
            <div class="stat"><div class="big" style="color:#166534">{{ $keywordStats['well_placed'] }}</div><div class="muted xs">well placed on their page</div></div>
            <div class="stat"><div class="big" style="color:#B45309">{{ $keywordStats['needs_fixes'] }}</div><div class="muted xs">need on-page fixes</div></div>
            <div class="stat"><div class="big" style="color:#B91C1C">{{ $keywordStats['cannibalised'] }}</div><div class="muted xs">cannibalised (2+ pages)</div></div>
        </div>
        @endif
    </section>
</div>

@if ($done)
<section class="card">
    <p class="ct">Site-wide checks</p>
    <div class="grid g220" style="margin-top:8px">
        @foreach (['robots_missing' => 'robots.txt', 'sitemap_missing' => 'XML sitemap', 'llms_txt_missing' => 'llms.txt (AI guide)', 'ai_bots_blocked' => 'AI crawlers allowed'] as $code => $label)
            @php $okay = ! in_array($code, $siteCodes, true); @endphp
            <div class="kv"><span>{{ $label }}</span><span class="pill {{ $okay ? 'ok' : 'warn' }}">{{ $okay ? 'Yes' : 'No' }}</span></div>
        @endforeach
    </div>
</section>
@endif
@endif
@endsection

@push('scripts')
@if ($progress && $progress['running'])
<script>
(function () {
    var box = document.getElementById('live');
    if (!box) return;
    var fmt = function (n) { return Number(n || 0).toLocaleString(); };
    var path = function (u) { try { var x = new URL(u); return x.pathname + x.search; } catch (e) { return u; } };
    var lastPages = {{ (int) $progress['pages_crawled'] }};
    function tick() {
        fetch(box.dataset.url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                if (!d.running) { window.location.reload(); return; }
                document.getElementById('live-bar').style.width = d.percent + '%';
                document.getElementById('live-pages').textContent = fmt(d.pages_crawled);
                document.getElementById('live-links').textContent = fmt(d.links);
                document.getElementById('live-crit').textContent = fmt(d.critical);
                document.getElementById('live-head').textContent = d.status === 'queued' ? 'Starting crawl…' : 'Crawling · ' + fmt(d.pages_crawled) + ' of about ' + fmt(d.expected) + ' pages';
                if (d.current) document.getElementById('live-now').textContent = 'Last: ' + path(d.current);
                // Refresh the charts once the first pages arrive.
                if (lastPages === 0 && d.pages_crawled > 0) { window.location.reload(); return; }
                lastPages = d.pages_crawled;
            })
            .catch(function () {})
            .finally(function () { setTimeout(tick, 4000); });
    }
    setTimeout(tick, 4000);
})();
</script>
@endif
@endpush
