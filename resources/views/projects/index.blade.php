@extends('layouts.app', ['title' => 'Projects', 'hideErrors' => true])
@use('App\Support\Ui')

@section('content')
<div class="top">
    <div>
        <div class="crumb">Projects / New project</div>
        <h1 class="title">Start a new SEO project</h1>
    </div>
</div>

<div class="row-wrap" style="gap:20px">
<form class="card" method="post" action="{{ route('projects.store') }}" style="flex:2 1 560px;display:flex;flex-direction:column;gap:22px;padding:28px" onsubmit="this.querySelector('button[type=submit]').disabled=true">
    @csrf
    <div class="field">
        <label for="url">Website URL</label>
        <div style="display:flex;flex-wrap:wrap;gap:10px">
            <input id="url" class="in" style="flex:1 1 320px;font-size:16px;min-height:52px" type="text" inputmode="url" name="url" value="{{ old('url') }}" placeholder="https://example.lk" required autofocus>
            <button class="btn" type="submit" style="min-height:52px">Analyse website</button>
        </div>
        @error('url')<div class="err">{{ $message }}</div>@enderror
        <div class="hint">We create the project, then start the first crawl in the background. Results appear live.</div>
    </div>

    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div class="field">
            <label for="country">Target country</label>
            <select id="country" name="country" class="in">
                @foreach ($countries as $code => $name)
                    <option value="{{ $code }}" @selected(old('country', 'LK') === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="language">Language</label>
            <select id="language" name="language" class="in">
                @foreach ($languages as $code => $name)
                    <option value="{{ $code }}" @selected(old('language', 'en') === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="niche">Field (niche)</label>
            <input id="niche" name="niche" class="in" value="{{ old('niche') }}" placeholder="e.g. Courier & logistics" list="niches">
            <datalist id="niches">
                @foreach (['Courier & logistics', 'Food & beverage', 'Fashion', 'Travel & tourism', 'Real estate', 'Education', 'Healthcare', 'E-commerce', 'Professional services'] as $n)
                    <option value="{{ $n }}">
                @endforeach
            </datalist>
        </div>
    </div>

    <div class="field">
        <label for="comp1">Competitors <span class="muted" style="font-weight:400">(optional, up to 5)</span></label>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
            @for ($i = 0; $i < 3; $i++)
                <input id="comp{{ $i + 1 }}" class="in" name="competitors[]" value="{{ old('competitors.'.$i) }}" aria-label="Competitor {{ $i + 1 }}" placeholder="competitor{{ $i + 1 }}.lk">
            @endfor
        </div>
        <label class="chk" style="margin-top:12px"><input type="checkbox" name="suggest_competitors" value="1" @checked(old('suggest_competitors', true))> Suggest competitors from Google results</label>
    </div>

    <details style="border-top:1px solid #E2E8F0;padding-top:16px">
        <summary style="font-weight:600;cursor:pointer;min-height:32px">Crawl settings</summary>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:14px;align-items:center">
            <div class="field">
                <label for="max_pages">Page limit</label>
                <select id="max_pages" name="max_pages" class="in">
                    @foreach ($pageLimits as $limit)
                        @if ($limit <= $maxPages)
                            <option value="{{ $limit }}" @selected((int) old('max_pages', 500) === $limit)>{{ number_format($limit) }} pages</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <label class="chk"><input type="checkbox" name="respect_robots" value="1" @checked(old('respect_robots', true))> Respect robots.txt</label>
        </div>
    </details>
</form>

<aside class="card" style="flex:1 1 300px;display:flex;flex-direction:column;gap:18px;padding:24px">
    <p class="ct" style="margin:0">What happens next</p>
    <div class="step"><span class="num">1</span><div><div style="font-weight:600">Crawl your site</div><div class="muted small" style="margin-top:2px">Every page, link and image checked, like Screaming Frog.</div></div></div>
    <div class="step"><span class="num">2</span><div><div style="font-weight:600">See the results visually</div><div class="muted small" style="margin-top:2px">Scores, charts and a plain-English issue list with fixes.</div></div></div>
    <div class="step"><span class="num">3</span><div><div style="font-weight:600">Track your keywords</div><div class="muted small" style="margin-top:2px">Add your keyword map and see where each page uses them.</div></div></div>
    <div class="step"><span class="num">4</span><div><div style="font-weight:600">Follow the SEO plan</div><div class="muted small" style="margin-top:2px">{{ count(config('seo_plan.steps')) }} steps, one at a time, starting with Competitor Analysis.</div></div></div>
</aside>
</div>

<section class="card" style="padding:0">
    <div style="padding:18px 20px 8px"><p class="ct" style="margin:0">Your projects</p></div>
    @if ($projects->isEmpty())
        <div class="empty">No projects yet. Add a website above to start.</div>
    @else
    <div class="scroll">
        <table class="data">
            <thead><tr><th>Website</th><th>Field</th><th>SEO score</th><th>AI-readiness</th><th>Current step</th><th>Last crawl</th><th></th></tr></thead>
            <tbody>
            @foreach ($projects as $p)
                @php
                    $c = $p->latestCrawl;
                    $current = $p->steps->first(fn ($s) => $s->status !== 'approved');
                    $currentIndex = $current ? $p->steps->search(fn ($s) => $s->id === $current->id) + 1 : null;
                @endphp
                <tr>
                    <td style="font-weight:600"><a href="{{ route('projects.show', $p) }}" style="color:inherit;text-decoration:none">{{ $p->domain }}</a></td>
                    <td>{{ $p->niche ?: '—' }}</td>
                    <td>@if ($c?->seo_score !== null)<span class="pill {{ Ui::scoreClass($c->seo_score) }}">{{ $c->seo_score }}</span>@else <span class="muted">—</span> @endif</td>
                    <td>@if ($c?->ai_score !== null)<span class="pill {{ Ui::scoreClass($c->ai_score) }}">{{ $c->ai_score }}</span>@else <span class="muted">—</span> @endif</td>
                    <td>{{ $current ? $currentIndex.' · '.$current->name : 'Plan complete' }}</td>
                    <td class="muted">
                        @if (! $c) Not crawled
                        @elseif (in_array($c->status, ['queued', 'running'])) Crawling now
                        @elseif ($c->status === 'failed') Failed
                        @else {{ $c->finished_at?->diffForHumans() }}
                        @endif
                    </td>
                    <td><a href="{{ route('projects.show', $p) }}">Open</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>
@endsection
