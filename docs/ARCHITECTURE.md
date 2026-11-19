# Architecture

## Principles

1. **Modules, not one big app.** Each feature (Projects, Crawler, Keywords, Competitors, Plan,
   AI, Backlinks, Outreach, Reports) lives in `web/app/Modules/<Name>` with its own models,
   services, jobs and routes. A new feature is a new module.
2. **The SEO plan is configuration.** Steps are defined in `web/config/seo_plan.php`. Each
   project copies them into `project_steps`, where they can be enabled, disabled, reordered or
   extended per client without code changes.
3. **Providers behind contracts.** Data (DataForSEO, Moz, Google) and AI (Claude, Gemini) are
   reached through interfaces in `web/app/Contracts`. Swap or add a provider by writing one class
   and changing config.
4. **Numbers come from data, words come from AI.** Volumes, rankings and authority come from data
   providers and our crawler. AI explains, groups and writes; it never invents metrics.
5. **Heavy work runs in the background.** Laravel queues jobs; the Python engine crawls and calls
   back with results. The UI shows progress and never blocks.
6. **Multi-tenant from day one.** Every project belongs to a workspace; users have roles per
   workspace (owner, manager, client).

## Flow of a crawl

```
User submits URL → Laravel creates Project + Crawl (status=queued)
  → POST engine /v1/site-audits {url, max_pages, callback_url, crawl_id}
  → engine-worker crawls, analyses pages
  → engine POSTs results to /api/internal/crawls/{id}/results (X-Engine-Key)
  → Laravel stores pages, links, issues; computes keyword usage; marks crawl done
```

## AI layer

`App\Modules\AI\AiManager` picks a provider per **task** (`config/ai.php`):

| Task | Default provider | Why |
|---|---|---|
| `analysis` (audit summaries, competitor insights, roadmap) | Claude | Reasoning and writing quality |
| `writing` (meta titles, briefs, outreach emails) | Claude | Writing quality |
| `bulk` (intent labels, page summaries at volume) | Gemini Flash | Cost at high volume |

Every call is logged in `ai_requests` (task, provider, model, tokens, cost estimate, project) so
cost per client is visible.

## Data model (core)

| Table | Holds |
|---|---|
| `workspaces`, `workspace_user` | Agencies/clients and member roles |
| `projects` | Website, country, language, niche, settings |
| `project_steps` | The plan per project: key, position, enabled, status, data, approval |
| `competitors` | Competitor domains + metrics |
| `crawls` | One crawl run: status, counts, scores |
| `pages` | One row per URL per crawl |
| `links` | Internal/external links found |
| `issues` | Issue code, severity, area (seo/ai/speed), page |
| `keywords` | Target keywords: volume, KD, intent, target URL |
| `keyword_page_usage` | Placement checks + score per keyword × page |
| `rank_checks` | Keyword position history |
| `ai_requests` | AI usage log |

## Adding a plan step

1. Add an entry to `config/seo_plan.php` (key, name, module, handler class).
2. Write the handler implementing `App\Contracts\PlanStepHandler` (`generate()` fills the step's
   data; `validate()` checks it before approval).
3. Existing projects get it via `php artisan plan:sync` (added disabled, so clients opt in).
