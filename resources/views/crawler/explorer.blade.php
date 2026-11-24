@extends('layouts.app', ['title' => 'Crawl Explorer · '.$project->domain])
@use('App\Support\Ui')
@use('Illuminate\Support\Str')

@section('content')
@include('partials.project-header', ['section' => 'Crawl Explorer'])

@if (! $crawl || ($counts['all'] ?? 0) === 0)
    <section class="card empty">
        <p class="ct">No pages yet</p>
        <div class="small">Pages appear here as the crawler finds them. Start a crawl with the Re-crawl button.</div>
    </section>
@else
@php
    $q = fn (array $extra) => route('crawler.explorer', array_merge([$project], array_filter(['f' => $filter !== 'all' ? $filter : null, 'q' => $search ?: null, 'sort' => $sort !== 'depth' ? $sort : null, 'dir' => $dir !== 'asc' ? $dir : null, 'crawl' => request('crawl')]), $extra));
    $total = max(1, $status['total']);
@endphp

@if ($crawls->count() > 1 || $crawl->status !== 'done')
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap" class="small">
    @if ($crawl->status !== 'done')<span class="pill info">Crawl in progress · showing pages so far</span>@endif
    @if ($crawls->count() > 1)
        <form method="get" action="{{ route('crawler.explorer', $project) }}" style="display:flex;gap:8px;align-items:center">
            <label for="crawl" style="margin:0">Crawl</label>
            <select id="crawl" name="crawl" class="in" style="min-height:36px;width:auto" onchange="this.form.submit()">
                @foreach ($crawls as $c)
                    <option value="{{ $c->id }}" @selected($c->id === $crawl->id)>{{ $c->finished_at?->format('j M Y, H:i') }} · {{ $c->pages_crawled }} pages</option>
                @endforeach
            </select>
        </form>
    @endif
</div>
@endif

<div class="grid g220">
    <div class="card" style="padding:16px">
        <div style="display:flex;justify-content:space-between"><span class="ct" style="margin:0">Status codes</span><span class="muted xs">click to filter</span></div>
        <div class="bar" style="height:14px;margin-top:16px">
            <span style="width:{{ Ui::pct($status['2xx'], $total) }}%;background:#4F46E5"></span>
            <span style="width:{{ Ui::pct($status['3xx'], $total) }}%;background:#F59E0B"></span>
            <span style="width:{{ Ui::pct($status['4xx'], $total) }}%;background:#DC2626"></span>
            <span style="width:{{ Ui::pct($status['5xx'], $total) }}%;background:#7F1D1D"></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-top:10px;gap:6px">
            <a href="{{ $q(['f' => null]) }}">2xx {{ $status['2xx'] }}</a>
            <a href="{{ $q(['f' => 'redirects', 'page' => null]) }}">3xx {{ $status['3xx'] }}</a>
            <a href="{{ $q(['f' => 'broken', 'page' => null]) }}">4xx {{ $status['4xx'] }}</a>
            <a href="{{ $q(['f' => 'broken', 'page' => null]) }}">5xx {{ $status['5xx'] }}</a>
        </div>
    </div>
    @foreach ([['Response time', $times, ['3s+', '2s']], ['Word count', $words, ['<300']]] as [$label, $hist, $bad])
        @php $max = max(1, max($hist)); @endphp
        <div class="card" style="padding:16px">
            <span class="ct">{{ $label }}</span>
            <div class="mini">
                @foreach ($hist as $bucket => $n)
                    <span title="{{ $bucket }}: {{ $n }} pages" style="height:{{ max(4, round($n / $max * 100)) }}%;{{ in_array($bucket, $bad, true) ? 'background:#EA580C' : '' }}"></span>
                @endforeach
            </div>
            <div style="display:flex;justify-content:space-between;font-size:11px;color:#475569;margin-top:6px">
                @foreach ($hist as $bucket => $n)<span>{{ $bucket }}</span>@endforeach
            </div>
        </div>
    @endforeach
    <div class="card" style="padding:16px">
        <span class="ct">Change since last crawl</span>
        @if ($changes)
            @php $delta = $changes['issues_now'] - $changes['issues_before']; @endphp
            <div style="display:flex;flex-direction:column;gap:6px;font-size:13px;margin-top:12px">
                <div style="display:flex;justify-content:space-between"><span>New pages</span><strong style="color:#166534">+{{ $changes['new'] }}</strong></div>
                <div style="display:flex;justify-content:space-between"><span>Removed pages</span><strong>-{{ $changes['removed'] }}</strong></div>
                <div style="display:flex;justify-content:space-between"><span>Issues</span><strong style="color:{{ $delta > 0 ? '#B91C1C' : '#166534' }}">{{ $delta > 0 ? '+' : '' }}{{ $delta }}</strong></div>
            </div>
        @else
            <div class="muted small" style="margin-top:12px">Shown after the second crawl.</div>
        @endif
    </div>
</div>

<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
    <form method="get" action="{{ route('crawler.explorer', $project) }}" style="flex:1 1 260px;max-width:360px;display:flex">
        <label for="q" class="sr">Search URLs</label>
        @if ($filter !== 'all')<input type="hidden" name="f" value="{{ $filter }}">@endif
        @if (request('crawl'))<input type="hidden" name="crawl" value="{{ request('crawl') }}">@endif
        <input id="q" name="q" value="{{ $search }}" placeholder="Search URL or title" class="in" style="min-height:40px">
    </form>
    @foreach (\App\Modules\Crawler\Http\ExplorerController::FILTERS as $key => $label)
        @if ($key === 'all' || ($counts[$key] ?? 0) > 0)
            <a class="chip {{ $filter === $key ? 'on' : '' }}" href="{{ $q(['f' => $key === 'all' ? null : $key, 'page' => null, 'p' => null]) }}" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }} <b>{{ Ui::num($counts[$key] ?? 0) }}</b></a>
        @endif
    @endforeach
</div>

<div class="row-wrap">
<section class="card" style="flex:999 1 640px;padding:0;overflow:hidden">
    <div class="scroll">
    <table class="data">
        <thead><tr>
            @foreach (['url' => 'Address', 'status_code' => 'Status', '_idx' => 'Indexable', '_title' => 'Title (chars)', '_h1' => 'H1', 'word_count' => 'Words', 'depth' => 'Depth', 'inlinks' => 'Inlinks', 'load_ms' => 'Resp.', 'seo_score' => 'SEO', '_iss' => 'Issues'] as $key => $label)
                <th>
                    @if (str_starts_with($key, '_'))
                        {{ $label }}
                    @else
                        @php $nextDir = $sort === $key && $dir === 'asc' ? 'desc' : 'asc'; @endphp
                        <a href="{{ $q(['sort' => $key, 'dir' => $nextDir, 'page' => null]) }}" style="color:inherit;text-decoration:none">{{ $label }}{{ $sort === $key ? ($dir === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                    @endif
                </th>
            @endforeach
        </tr></thead>
        <tbody>
        @forelse ($pages as $page)
            @php
                $titleLen = $page->title !== null ? mb_strlen($page->title) : null;
                $h1Count = count($page->h1 ?? []);
                $isHtml = $page->status_code >= 200 && $page->status_code < 300;
                $href = $q(['p' => $page->id, 'page' => request('page')]);
            @endphp
            <tr class="click {{ $selected?->id === $page->id ? 'sel' : '' }}" onclick="location.href='{{ $href }}'">
                <td><a class="url" href="{{ $href }}" title="{{ $page->url }}" style="color:inherit;text-decoration:none;{{ $selected?->id === $page->id ? 'font-weight:600' : '' }}">{{ Ui::path($page->url) }}</a></td>
                <td><span class="pill {{ Ui::statusClass($page->status_code) }}">{{ $page->status_code ?? '—' }}</span></td>
                <td>@if (! $isHtml) No @elseif ($page->indexable) Yes @else <span class="pill crit">No</span> @endif</td>
                <td>@if ($titleLen === null) — @elseif ($titleLen > 60 || $titleLen < 30) <span class="pill warn">{{ $titleLen }}</span> @else {{ $titleLen }} @endif</td>
                <td>@if (! $isHtml) — @elseif ($h1Count === 0) <span class="pill crit">0</span> @elseif ($h1Count > 1) <span class="pill warn">{{ $h1Count }}</span> @else 1 @endif</td>
                <td>@if (! $isHtml) — @elseif ($page->word_count < 300) <span class="pill warn">{{ Ui::num($page->word_count) }}</span> @else {{ Ui::num($page->word_count) }} @endif</td>
                <td>{{ $page->depth ?? '—' }}</td>
                <td>{{ Ui::num($page->inlinks) }}</td>
                <td>@if ($page->load_ms > 1000)<span class="pill warn">{{ Ui::seconds($page->load_ms) }}</span>@else {{ Ui::seconds($page->load_ms) }} @endif</td>
                <td>@if ($page->seo_score !== null)<span class="pill {{ Ui::scoreClass($page->seo_score) }}">{{ $page->seo_score }}</span>@else — @endif</td>
                <td>{{ $page->issues_count }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="empty">No pages match this filter.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pager">
        <span>Showing {{ Ui::num($pages->firstItem() ?? 0) }}–{{ Ui::num($pages->lastItem() ?? 0) }} of {{ Ui::num($pages->total()) }} URLs</span>
        <span style="display:flex;gap:8px;align-items:center">
            @if ($pages->onFirstPage())<span class="off">Previous</span>@else<a href="{{ $pages->previousPageUrl() }}">Previous</a>@endif
            <span>Page {{ $pages->currentPage() }} of {{ $pages->lastPage() }}</span>
            @if ($pages->hasMorePages())<a href="{{ $pages->nextPageUrl() }}">Next</a>@else<span class="off">Next</span>@endif
        </span>
    </div>
</section>

@if ($selected)
<aside class="card" aria-label="Page detail" style="flex:1 1 340px;display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
        <div style="min-width:0"><div class="muted xs" style="font-weight:600">PAGE DETAIL</div><div class="url" style="font-size:14px;font-weight:600;margin-top:4px">{{ Ui::path($selected->url) }}</div></div>
        <a class="btn2 btn-sm" href="{{ $q(['p' => null, 'page' => request('page')]) }}" aria-label="Close page detail">Close</a>
    </div>
    @if ($selected->title || $selected->meta_description)
    <div style="border:1px solid #E2E8F0;border-radius:10px;padding:14px">
        <div class="muted" style="font-size:11px;font-weight:600;margin-bottom:6px">GOOGLE PREVIEW</div>
        <div style="font-size:12px;color:#334155;overflow-wrap:anywhere">{{ $project->domain }} › {{ trim(Ui::path($selected->url), '/') ?: '' }}</div>
        <div style="font-size:17px;color:#1A0DAB;margin-top:2px">{{ Str::limit($selected->title ?: 'No title', 62) }}</div>
        <div style="font-size:13px;color:#334155;margin-top:4px">{{ Str::limit($selected->meta_description ?: 'No meta description — Google will pick text from the page.', 160) }}</div>
    </div>
    @endif
    <div>
        <div class="kv"><span class="muted">Status</span><span>{{ $selected->status_code }} · {{ Ui::seconds($selected->load_ms) }}{{ $selected->size_kb ? ' · '.$selected->size_kb.' KB' : '' }}</span></div>
        <div class="kv"><span class="muted">Canonical</span><span class="url">{{ ! $selected->canonical ? 'None' : (rtrim($selected->canonical, '/') === rtrim($selected->url, '/') ? 'Self' : Ui::path($selected->canonical)) }}</span></div>
        <div class="kv"><span class="muted">Depth · Inlinks · Outlinks</span><span>{{ $selected->depth ?? '—' }} · {{ $selected->inlinks }} · {{ $selected->outlinks }}</span></div>
        <div class="kv"><span class="muted">H1</span><span>{{ implode(' | ', $selected->h1 ?? []) ?: '—' }}</span></div>
        <div class="kv"><span class="muted">Words · Images</span><span>{{ Ui::num($selected->word_count) }} · {{ count($selected->images ?? []) }}</span></div>
        <div class="kv"><span class="muted">Schema</span><span>{{ implode(', ', $selected->schema_types ?? []) ?: 'None' }}</span></div>
        <div class="kv"><span class="muted">SEO · AI-readiness</span><span>{{ $selected->seo_score ?? '—' }} · {{ $selected->ai_score ?? '—' }}</span></div>
        <div class="kv"><span class="muted">Open page</span><span><a href="{{ $selected->url }}" target="_blank" rel="noopener">Visit ↗</a></span></div>
    </div>
    <div>
        <p class="ct">Issues on this page</p>
        @forelse ($detail['issues'] as $issue)
            <div class="chkrow"><span class="dot" style="background:{{ ['critical' => '#B91C1C', 'warning' => '#B45309'][$issue->severity] ?? '#64748B' }}">!</span>
                <span><a href="{{ route('crawler.issues', [$project, 'code' => $issue->code]) }}" style="color:inherit">{{ $issue->message }}</a></span></div>
        @empty
            <div class="muted small">No issues. Nice.</div>
        @endforelse
    </div>
    @if ($detail['broken_out']->isNotEmpty())
    <div>
        <p class="ct">Broken links on this page</p>
        @foreach ($detail['broken_out'] as $l)
            <div class="kv"><span class="url">{{ $l->to_url }}</span><span class="pill crit">{{ $l->status_code }}</span></div>
        @endforeach
    </div>
    @endif
    <div>
        <p class="ct">Keywords on this page</p>
        @if ($detail['keywords']->isEmpty())
            <div class="muted small">No tracked keywords found here. <a href="{{ route('keywords.index', $project) }}">Add keywords</a></div>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
                @foreach ($detail['keywords'] as $u)
                    <a class="pill {{ $u->score >= 75 ? 'ok' : ($u->score >= 40 ? 'warn' : 'crit') }}" style="text-decoration:none" href="{{ route('keywords.index', [$project, 'k' => $u->keyword_id, 'p' => $selected->id]) }}">{{ $u->keyword->keyword }} · {{ $u->score }}</a>
                @endforeach
            </div>
        @endif
    </div>
    <div>
        <p class="ct">Linked from</p>
        @forelse ($detail['sources'] as $src)
            <div class="url" style="padding:4px 0">{{ Ui::path($src) }}</div>
        @empty
            <div class="muted small">No internal links point here{{ $selected->depth === 0 ? ' (start page)' : '' }}.</div>
        @endforelse
        @if ($detail['anchors']->isNotEmpty())
            <div class="muted xs" style="margin-top:8px">Top anchors: {{ $detail['anchors']->keys()->map(fn ($a) => '“'.Str::limit($a, 40).'”')->implode(', ') }}</div>
        @endif
    </div>
</aside>
@endif
</div>
@endif
@endsection
