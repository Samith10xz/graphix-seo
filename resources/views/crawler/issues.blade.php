@extends('layouts.app', ['title' => 'Issues · '.$project->domain])
@use('App\Support\Ui')
@use('Illuminate\Support\Str')

@section('content')
@include('partials.project-header', ['section' => 'Issues'])

@if (! $crawl || ! $all)
    <section class="card empty"><p class="ct">No crawl yet</p><div class="small">Issues appear after the first crawl.</div></section>
@else
@php
    $link = fn (array $extra) => route('crawler.issues', array_merge([$project], array_filter(['severity' => $severity, 'area' => $area, 'code' => request('code')]), $extra));
    $titleOf = fn (array $g) => config('issue_guides.'.$g['code'].'.title') ?? Str::limit($g['message'], 80);
@endphp
<div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="chip {{ ! $severity ? 'on' : '' }}" href="{{ $link(['severity' => null]) }}">All {{ Ui::num(array_sum($all)) }}</a>
    <a class="chip {{ $severity === 'critical' ? 'on' : '' }}" href="{{ $link(['severity' => 'critical', 'code' => null]) }}">Critical {{ Ui::num($all['critical']) }}</a>
    <a class="chip {{ $severity === 'warning' ? 'on' : '' }}" href="{{ $link(['severity' => 'warning', 'code' => null]) }}">Warnings {{ Ui::num($all['warning']) }}</a>
    <a class="chip {{ $severity === 'notice' ? 'on' : '' }}" href="{{ $link(['severity' => 'notice', 'code' => null]) }}">Notices {{ Ui::num($all['notice']) }}</a>
    <span style="width:12px"></span>
    <a class="chip {{ ! $area ? 'on' : '' }}" href="{{ $link(['area' => null]) }}">All areas</a>
    <a class="chip {{ $area === 'seo' ? 'on' : '' }}" href="{{ $link(['area' => 'seo', 'code' => null]) }}">SEO</a>
    <a class="chip {{ $area === 'ai' ? 'on' : '' }}" href="{{ $link(['area' => 'ai', 'code' => null]) }}">AI-readiness (AEO/GEO)</a>
</div>

<div class="row-wrap">
<section class="card" style="flex:3 1 560px;padding:0;overflow:hidden">
    @php $bySeverity = collect($groups)->groupBy('severity'); @endphp
    @foreach (['critical' => ['CRITICAL', '#991B1B'], 'warning' => ['WARNINGS', '#92400E'], 'notice' => ['NOTICES', '#334155']] as $sev => [$heading, $color])
        @if ($bySeverity->has($sev))
            <div class="grp" style="color:{{ $color }}"><span>{{ $heading }}</span><span>pages affected</span></div>
            @foreach ($bySeverity[$sev] as $g)
                <a class="irow {{ $current && $current['code'] === $g['code'] ? 'sel' : '' }}" href="{{ $link(['code' => $g['code']]) }}">
                    <span class="pill {{ Ui::severityClass($g['severity']) }}">{{ ucfirst($g['severity']) }}</span>
                    <span class="nm">{{ $titleOf($g) }}</span>
                    <span class="pill {{ $g['area'] === 'ai' ? 'info' : 'neutral' }}">{{ Ui::areaLabel($g['area']) }}</span>
                    <span class="cnt">{{ $g['pages'] ? Ui::num($g['pages']) : 'site' }}</span>
                </a>
            @endforeach
        @endif
    @endforeach
    @if (empty($groups))
        <div class="empty">No issues match this filter{{ $crawl->status === 'done' ? '' : ' yet' }}.</div>
    @endif
</section>

@if ($current)
<aside class="card" aria-label="Issue detail" style="flex:2 1 380px;display:flex;flex-direction:column;gap:16px">
    <div>
        <span class="pill {{ Ui::severityClass($current['severity']) }}">{{ ucfirst($current['severity']) }} · {{ Ui::areaLabel($current['area']) }}</span>
        <h2 style="font-family:'Space Grotesk',sans-serif;font-size:20px;margin:10px 0 4px">{{ $titleOf($current) }}</h2>
        <div class="muted small">
            @if ($current['pages']) {{ Ui::num($current['pages']) }} {{ Str::plural('page', $current['pages']) }} affected. @endif
            @foreach ($siteMessages as $m) {{ $m }} @endforeach
        </div>
    </div>
    @if ($guide)
        <div class="box"><p class="ct" style="font-size:14px">Why it matters</p>{{ $guide['why'] }}</div>
        <div>
            <p class="ct" style="font-size:14px">How to fix</p>
            <ol class="howto">@foreach ($guide['steps'] as $s)<li>{{ $s }}</li>@endforeach<li>Re-crawl to confirm the issue is gone.</li></ol>
        </div>
    @endif

    @if (! empty($broken))
        <div>
            <p class="ct" style="font-size:14px">Broken URLs and where they are linked</p>
            <div class="list">
                @foreach ($broken as $b)
                    <div><span class="mono" style="overflow-wrap:anywhere">{{ $b['url'] }} <span class="pill crit">{{ $b['status'] }}</span></span><span style="white-space:nowrap">linked from {{ $b['sources'] }} {{ Str::plural('page', $b['sources']) }}</span></div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($affected->isNotEmpty())
        <div>
            <p class="ct" style="font-size:14px">Affected pages</p>
            <div class="list">
                @foreach ($affected as $issue)
                    <div style="flex-direction:column;gap:2px">
                        <a class="mono" href="{{ route('crawler.explorer', [$project, 'p' => $issue->page_id]) }}">{{ Ui::path($issue->page?->url) }}</a>
                        <span class="muted xs">{{ $issue->message }}</span>
                    </div>
                @endforeach
            </div>
            @if ($current['pages'] > $affected->count())<div class="muted xs" style="margin-top:6px">Showing the first {{ $affected->count() }}.</div>@endif
        </div>
    @endif
</aside>
@endif
</div>
@endif
@endsection
