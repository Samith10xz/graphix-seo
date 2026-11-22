<?php

namespace App\Modules\Crawler\Http;

use App\Http\Controllers\Controller;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Services\CrawlIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Internal endpoints the Python engine calls (protected by engine.key middleware). */
class EngineCallbackController extends Controller
{
    public function __construct(private CrawlIngestService $ingest) {}

    public function pages(Request $request, Crawl $crawl): JsonResponse
    {
        $data = $request->validate(['pages' => ['required', 'array', 'max:200']]);

        return response()->json(['stored' => $this->ingest->storePages($crawl, $data['pages'])]);
    }

    public function finish(Request $request, Crawl $crawl): JsonResponse
    {
        $this->ingest->finish($crawl, $request->validate(['site' => ['required', 'array']])['site']);

        return response()->json(['ok' => true]);
    }

    public function fail(Request $request, Crawl $crawl): JsonResponse
    {
        $this->ingest->fail($crawl, (string) $request->input('error', 'Unknown engine error'));

        return response()->json(['ok' => true]);
    }
}
