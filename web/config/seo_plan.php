<?php

use App\Modules\Plan\Handlers\CompetitorAnalysisHandler;
use App\Modules\Plan\Handlers\ManualStepHandler;

/*
|--------------------------------------------------------------------------
| SEO plan steps
|--------------------------------------------------------------------------
| The default plan copied into every new project (project_steps table).
| Order here = default order. Per project, steps can be disabled, reordered
| or extended without touching this file.
|
| key      unique, never rename once projects use it
| name     shown in the UI
| sheet    the tab of the original SEO plan sheet this step replaces
| handler  class implementing App\Contracts\PlanStepHandler
| default  enabled for new projects?
*/

return [
    'steps' => [
        ['key' => 'competitor_analysis', 'name' => 'Competitor Analysis', 'sheet' => 'Compititor analysis / 12_Competitor_Analysis', 'handler' => CompetitorAnalysisHandler::class, 'default' => true],
        ['key' => 'keyword_research', 'name' => 'Keyword Research', 'sheet' => '02_Keyword_Research', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'intent_analysis', 'name' => 'Intent Analysis', 'sheet' => '03_Intent_Analysis', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'keyword_clustering', 'name' => 'Keyword Clustering', 'sheet' => '04_Keyword_Clustering', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'keywords_sitemap', 'name' => 'Keywords Sitemap', 'sheet' => 'Keywords Sitemap', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'redirection', 'name' => 'Redirection', 'sheet' => 'Redirection', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'keyword_mapping', 'name' => 'Keyword Mapping', 'sheet' => '05_Keyword_Mapping', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'meta_titles', 'name' => 'Meta Titles', 'sheet' => '06_Meta_Titles', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'meta_descriptions', 'name' => 'Meta Descriptions', 'sheet' => '07_Meta_Descriptions', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'onpage_audit', 'name' => 'On-Page Audit', 'sheet' => '08_OnPage_Audit', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'content_brief', 'name' => 'Content Brief', 'sheet' => '09_Content_Brief', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'internal_links', 'name' => 'Internal Links', 'sheet' => '10_Internal_Links', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'technical_audit', 'name' => 'Technical Audit', 'sheet' => '11_Technical_Audit', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'backlink_tracker', 'name' => 'Backlink Tracker', 'sheet' => '13_Backlink_Tracker', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'content_calendar', 'name' => 'Content Calendar', 'sheet' => '14_Content_Calendar', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'kpi_tracker', 'name' => 'KPI Tracker', 'sheet' => '15_KPI_Tracker', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'roadmap_90_day', 'name' => '90-Day Roadmap', 'sheet' => '16_90Day_Roadmap', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'audit_summary', 'name' => 'SEO Audit Summary', 'sheet' => '17_SEO_Audit_Summary', 'handler' => ManualStepHandler::class, 'default' => true],
        ['key' => 'final_checklist', 'name' => 'Final Project Checklist', 'sheet' => '18_Final_Project_Checklist', 'handler' => ManualStepHandler::class, 'default' => true],
    ],
];
