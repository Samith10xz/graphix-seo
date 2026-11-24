<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title.' · ' : '' }}Graphix SEO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="app">
<nav class="side" aria-label="Main">
    <a class="brand" href="{{ route('projects.index') }}"><span class="logo">G</span>Graphix SEO</a>
    <a class="nav {{ request()->routeIs('projects.index') ? 'on' : '' }}" href="{{ route('projects.index') }}">+ New project</a>

    @isset($project)
        <div class="navlabel" title="{{ $project->domain }}">{{ $project->domain }}</div>
        <a class="nav {{ request()->routeIs('projects.show') ? 'on' : '' }}" href="{{ route('projects.show', $project) }}">Overview</a>
        <a class="nav {{ request()->routeIs('crawler.explorer') ? 'on' : '' }}" href="{{ route('crawler.explorer', $project) }}">Crawl Explorer</a>
        <span class="nav lock">Site Structure · soon</span>
        <a class="nav {{ request()->routeIs('crawler.issues') ? 'on' : '' }}" href="{{ route('crawler.issues', $project) }}">Issues</a>
        <a class="nav {{ request()->routeIs('keywords.*') ? 'on' : '' }}" href="{{ route('keywords.index', $project) }}">Keyword Tracker</a>

        @php $planSteps = $project->steps()->where('enabled', true)->get(['id', 'name', 'status', 'position']); @endphp
        @if ($planSteps->isNotEmpty())
            <div class="navlabel">SEO plan steps</div>
            @foreach ($planSteps->take(3) as $i => $step)
                <span class="nav {{ $step->status === 'locked' ? 'lock' : '' }}">{{ $i + 1 }} · {{ $step->name }}{{ $step->status === 'locked' ? ' · locked' : '' }}</span>
            @endforeach
            @if ($planSteps->count() > 3)
                <span class="nav lock">+ {{ $planSteps->count() - 3 }} more steps</span>
            @endif
        @endif
        <div class="navlabel">Coming in Phase 4</div>
        <span class="nav lock">Link Building</span>
    @endisset

    <div class="side-foot">
        <div class="who">{{ auth()->user()->name }}</div>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="nav" type="submit">Sign out</button>
        </form>
    </div>
</nav>
<main class="main">
    @if (session('status'))
        <div class="alert ok" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any() && ! ($hideErrors ?? false))
        <div class="alert crit" role="alert">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</main>
</div>
@stack('scripts')
</body>
</html>
