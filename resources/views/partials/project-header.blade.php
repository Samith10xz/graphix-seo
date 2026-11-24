@php
    $countries = \App\Modules\Projects\Http\ProjectController::COUNTRIES;
    $languages = \App\Modules\Projects\Http\ProjectController::LANGUAGES;
    $tabs = [
        'projects.show' => 'Overview',
        'crawler.explorer' => 'Crawl Explorer',
        'crawler.issues' => 'Issues',
        'keywords.index' => 'Keywords',
    ];
    $running = $project->crawls()->whereIn('status', ['queued', 'running'])->exists();
@endphp
<div class="top">
    <div style="min-width:0">
        <div class="crumb"><a href="{{ route('projects.index') }}">Projects</a> / {{ $project->domain }}{{ isset($section) ? ' / '.$section : '' }}</div>
        <h1 class="title">{{ $project->name }}</h1>
        <div class="muted" style="font-size:13px;margin-top:4px">
            {{ $countries[$project->country] ?? $project->country }} · {{ $languages[$project->language] ?? $project->language }}{{ $project->niche ? ' · '.$project->niche : '' }}
            · <a href="{{ $project->url }}" target="_blank" rel="noopener">{{ $project->url }}</a>
        </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <form method="post" action="{{ route('projects.recrawl', $project) }}">
            @csrf
            <button class="btn" type="submit" @disabled($running)>{{ $running ? 'Crawling…' : 'Re-crawl' }}</button>
        </form>
    </div>
</div>
<div class="tabs" role="navigation" aria-label="Project views">
    @foreach ($tabs as $route => $label)
        <a class="tab {{ request()->routeIs($route) ? 'on' : '' }}" href="{{ route($route, $project) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</div>
