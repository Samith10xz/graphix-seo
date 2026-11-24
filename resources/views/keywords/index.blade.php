@extends('layouts.app', ['title' => 'Keywords · '.$project->domain])
@use('App\Support\Ui')
@use('Illuminate\Support\Str')

@section('content')
@include('partials.project-header', ['section' => 'Keyword Tracker'])

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
    <div class="card"><div class="big">{{ $summary['tracked'] }}</div><div class="muted small">keywords tracked</div></div>
    <div class="card"><div class="big" style="color:#166534">{{ $summary['well_placed'] }}</div><div class="muted small">well placed (score 75+)</div></div>
    <div class="card"><div class="big" style="color:#B45309">{{ $summary['needs_fixes'] }}</div><div class="muted small">need on-page fixes</div></div>
    <div class="card"><div class="big" style="color:#B91C1C">{{ $summary['cannibalised'] }}</div><div class="muted small">cannibalised: 2+ pages compete</div></div>
</div>

<div class="row-wrap">
<section class="card" style="flex:3 1 520px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <div><p class="ct">Keyword × page heatmap</p><div class="muted xs">On-page keyword score 0–100 from the latest crawl. Outlined cell = the page this keyword is mapped to. Click a cell for placement checks.</div></div>
        <div style="display:flex;gap:12px;font-size:12px;align-items:center;flex-wrap:wrap">
            <span><span class="c g" style="display:inline-flex;height:14px;min-width:22px;margin-right:6px;vertical-align:middle"></span>75+</span>
            <span><span class="c a" style="display:inline-flex;height:14px;min-width:22px;margin-right:6px;vertical-align:middle"></span>40–74</span>
            <span><span class="c r" style="display:inline-flex;height:14px;min-width:22px;margin-right:6px;vertical-align:middle"></span>under 40</span>
        </div>
    </div>

    @if ($keywords->isEmpty())
        <div class="empty">Add your target keywords on the right to see where each page uses them.</div>
    @elseif (! $crawl)
        <div class="empty">Keyword usage is checked after the first crawl finishes.</div>
    @elseif ($columns->isEmpty())
        <div class="empty">No tracked keyword was found on any page yet. If you just added keywords, the check runs within a minute or two.
            <form method="post" action="{{ route('keywords.analyse', $project) }}" style="margin-top:12px">@csrf<button class="btn2 btn-sm" type="submit">Re-check now</button></form>
        </div>
    @else
    <div class="scroll" style="margin-top:12px">
        <table class="heat">
            <thead><tr>
                <th class="l">Keyword</th><th>Volume</th><th>KD</th>
                @foreach ($columns as $col)
                    <th title="{{ $col->url }}">{{ Str::limit(trim(Ui::path($col->url), '/') ?: 'Home', 16) }}</th>
                @endforeach
                <th>Best</th>
            </tr></thead>
            <tbody>
            @foreach ($keywords as $kw)
                @php $rows = $usage->get($kw->id, collect())->keyBy('page_id'); @endphp
                <tr class="{{ $selKeyword?->id === $kw->id ? 'sel' : '' }}">
                    <td class="kw"><a href="{{ route('keywords.index', [$project, 'k' => $kw->id]) }}" style="color:inherit;text-decoration:none">{{ $kw->keyword }}</a></td>
                    <td class="m">{{ $kw->volume !== null ? Ui::num($kw->volume) : ($kw->volume_range ?: '—') }}</td>
                    <td class="m">{{ $kw->difficulty ?? '—' }}</td>
                    @foreach ($columns as $col)
                        @php $u = $rows->get($col->id); @endphp
                        <td>
                            @if ($u)
                                <a class="c {{ Ui::heatClass($u->score) }} {{ $u->is_target ? 't' : '' }} {{ $selUsage?->id === $u->id ? 'on' : '' }}" href="{{ route('keywords.index', [$project, 'k' => $kw->id, 'p' => $col->id]) }}" title="{{ $kw->keyword }} on {{ Ui::path($col->url) }}">{{ $u->score }}</a>
                            @else
                                <span class="c e">–</span>
                            @endif
                        </td>
                    @endforeach
                    @php $best = $insights->bestScore($usage->get($kw->id, collect())); @endphp
                    <td class="m" style="font-weight:700">{{ $best ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>

<aside style="flex:2 1 320px;display:flex;flex-direction:column;gap:16px">
    <section class="card">
        <p class="ct">Add keywords</p>
        <form method="post" action="{{ route('keywords.store', $project) }}" style="display:flex;flex-direction:column;gap:10px;margin-top:8px">
            @csrf
            <label for="keywords" class="sr">Keywords</label>
            <textarea id="keywords" name="keywords" class="in" rows="5" placeholder="courier service sri lanka, /&#10;international courier sri lanka, /cross-border/&#10;send parcel to uk" required>{{ old('keywords') }}</textarea>
            <div class="hint" style="margin-top:0">One per line. Optionally add the target page after a comma: <span class="mono">keyword, /page/</span>. Paste straight from your keyword map.</div>
            <button class="btn" type="submit">Add keywords</button>
        </form>
    </section>

    @foreach ($cannibalised->take(3) as $c)
    <section class="card" style="border-color:#FCA5A5">
        <span class="pill crit">Cannibalisation</span>
        <p class="ct" style="margin-top:10px">{{ $c['keyword']->keyword }}</p>
        <div class="muted small">{{ $c['rows']->count() }} pages compete for this keyword, so Google may switch between them.</div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;margin-top:12px">
            @foreach ($c['rows'] as $r)
                <div style="display:flex;justify-content:space-between;gap:10px"><span class="mono">{{ Ui::path($pageUrls[$r->page_id] ?? null) }}{{ $r->is_target ? ' (target)' : '' }}</span><strong>{{ $r->score }}</strong></div>
            @endforeach
        </div>
        <div class="small" style="margin-top:12px">Fix: keep the phrase on one page (the target). Refocus the others on a related keyword and link them to the target page.</div>
    </section>
    @endforeach

    @if ($orphans->isNotEmpty())
    <section class="card">
        <p class="ct">Orphan keywords</p>
        <div class="muted small">Mapped to no page yet. Set a target page so each keyword has one home.</div>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:13px;margin-top:12px">
            @foreach ($orphans->take(10) as $o)
                <form method="post" action="{{ route('keywords.update', [$project, $o]) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    @csrf @method('patch')
                    <span style="flex:1 1 140px">{{ $o->keyword }}</span>
                    <input class="in" name="target_url" placeholder="/page/" aria-label="Target page for {{ $o->keyword }}" style="min-height:34px;flex:1 1 110px;width:auto">
                    <button class="btn2 btn-sm" type="submit">Map</button>
                </form>
            @endforeach
            @if ($orphans->count() > 10)<div class="muted xs">+ {{ $orphans->count() - 10 }} more</div>@endif
        </div>
    </section>
    @endif
</aside>
</div>

@if ($selKeyword && $selUsage && $selPage)
<section class="card">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:flex-start">
        <div><p class="ct">Placement checks</p><div class="small"><strong>{{ $selKeyword->keyword }}</strong> on <a class="mono" href="{{ route('crawler.explorer', [$project, 'p' => $selPage->id]) }}">{{ Ui::path($selPage->url) }}</a>{{ $selUsage->is_target ? ' (target page)' : '' }}</div></div>
        <span class="pill {{ $selUsage->score >= 75 ? 'ok' : ($selUsage->score >= 40 ? 'warn' : 'crit') }}">Score {{ $selUsage->score }}</span>
    </div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));column-gap:28px;row-gap:0;margin-top:10px">
        @foreach (array_chunk($checkRows, 5) as $chunk)
            <div>
                @foreach ($chunk as $row)
                    <div class="ck"><span class="st {{ $row['state'] }}">{{ ['y' => '✓', 'n' => '✕', 'h' => '!'][$row['state']] }}</span><span style="flex:1">{{ $row['label'] }}</span><span class="muted">{{ $row['detail'] }}</span></div>
                @endforeach
            </div>
        @endforeach
    </div>
    @if ($fixes)
        <div style="background:#EEF2FF;border-radius:10px;padding:14px;margin-top:14px;font-size:13px;color:#312E81">
            <strong>Suggested fixes:</strong>
            <ul style="margin:8px 0 0;padding-left:18px">@foreach ($fixes as $fix)<li style="margin-bottom:4px">{{ $fix }}</li>@endforeach</ul>
        </div>
    @endif
</section>
@elseif ($selKeyword && $crawl && $keywords->isNotEmpty())
<section class="card">
    <p class="ct">Placement checks · {{ $selKeyword->keyword }}</p>
    <div class="muted small">This keyword was not found on any crawled page{{ $selKeyword->target_url ? ', including its target '.Ui::path($selKeyword->target_url) : '' }}. Add it to the title, H1 and first paragraph of the page you want to rank.</div>
</section>
@endif

@if ($keywords->isNotEmpty())
<section class="card" style="padding:0">
    <div style="padding:18px 20px 8px"><p class="ct" style="margin:0">All keywords</p></div>
    <div class="scroll">
    <table class="data">
        <thead><tr><th>Keyword</th><th>Target page</th><th>Best score</th><th>Pages using it</th><th>Source</th><th></th></tr></thead>
        <tbody>
        @foreach ($keywords as $kw)
            @php $rows = $usage->get($kw->id, collect()); @endphp
            @php $best = $insights->bestScore($rows); @endphp
            <tr>
                <td style="font-weight:500"><a href="{{ route('keywords.index', [$project, 'k' => $kw->id]) }}" style="color:inherit">{{ $kw->keyword }}</a></td>
                <td class="url">{{ $kw->target_url ? Ui::path($kw->target_url) : '—' }}</td>
                <td>@if ($best !== null)<span class="pill {{ $best >= 75 ? 'ok' : ($best >= 40 ? 'warn' : 'crit') }}">{{ $best }}</span>@else — @endif</td>
                <td>{{ $rows->count() }}</td>
                <td class="muted">{{ ucfirst($kw->source) }}</td>
                <td>
                    <form method="post" action="{{ route('keywords.destroy', [$project, $kw]) }}" onsubmit="return confirm('Remove “{{ addslashes($kw->keyword) }}”?')">
                        @csrf @method('delete')
                        <button class="linkbtn" type="submit" style="color:#B91C1C">Remove</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</section>
@endif
@endsection
