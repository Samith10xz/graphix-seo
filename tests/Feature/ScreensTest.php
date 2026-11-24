<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Crawler\Analysis\PageAnalyzer;
use App\Modules\Crawler\Jobs\StartCrawl;
use App\Modules\Crawler\Models\Crawl;
use App\Modules\Crawler\Services\CrawlIngestService;
use App\Modules\Keywords\Jobs\AnalyzeKeywordUsage;
use App\Modules\Projects\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // no real crawls or API calls in tests
    }

    private function owner(): User
    {
        $user = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret-password-1']);
        $workspace = Workspace::create(['name' => 'Graphix', 'slug' => 'graphix']);
        $workspace->users()->attach($user->id, ['role' => 'owner']);

        return $user;
    }

    /** A project with one finished crawl of two analysed pages. */
    private function crawledProject(User $user): Project
    {
        $this->actingAs($user)->post('/projects', [
            'url' => 'parcel.lk', 'country' => 'LK', 'language' => 'en', 'max_pages' => 100,
        ]);
        $project = Project::firstOrFail();
        $crawl = $project->crawls()->firstOrFail();

        $analyzer = new PageAnalyzer;
        $home = $analyzer->analyze('https://parcel.lk', 200, [], '<html lang="en"><head><title>Courier service Sri Lanka | Parcel</title></head><body><h1>Courier service Sri Lanka</h1><p>Courier service Sri Lanka for every parcel.</p><a href="/rates/">Rates</a><a href="/gone/">Old</a></body></html>', 300);
        $rates = $analyzer->analyze('https://parcel.lk/rates/', 200, [], '<html><head><title>Rates</title></head><body><p>Prices.</p></body></html>', 1500);
        $gone = $analyzer->analyze('https://parcel.lk/gone/', 404, [], '<html><body>Not found</body></html>', 50);
        $home['depth'] = 0;
        $rates['depth'] = $gone['depth'] = 1;

        $ingest = app(CrawlIngestService::class);
        $ingest->storePages($crawl, [$home, $rates, $gone]);
        $ingest->finish($crawl, [
            'scores' => ['seo' => 71, 'ai' => 55],
            'issues' => [['area' => 'seo', 'severity' => 'critical', 'code' => 'broken_links', 'message' => '1 broken link(s) found.']],
            'link_status' => ['https://parcel.lk/gone/' => 404],
        ]);

        return $project->fresh();
    }

    public function test_first_visit_goes_to_setup_and_creates_owner_workspace(): void
    {
        $this->get('/')->assertRedirect('/projects');
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('Create the owner account');

        $this->post('/setup', [
            'workspace' => 'Graphix', 'name' => 'Samith', 'email' => 'Owner@Example.com',
            'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
        ])->assertRedirect('/projects');

        $user = User::firstOrFail();
        $this->assertSame('owner@example.com', $user->email);
        $this->assertSame('owner', $user->currentWorkspace()->pivot->role);
        $this->assertAuthenticatedAs($user);

        // Setup only works once.
        $this->post('/logout');
        $this->get('/setup')->assertRedirect('/login');
    }

    public function test_login_and_wrong_password(): void
    {
        $this->owner();
        $this->get('/login')->assertOk()->assertSee('Sign in');

        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'secret-password-1'])->assertRedirect('/projects');
        $this->assertAuthenticated();
    }

    public function test_new_project_starts_a_crawl_and_shows_live_progress(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->get('/projects')->assertOk()->assertSee('Start a new SEO project');

        $this->post('/projects', ['url' => 'https://www.parcel.lk/', 'country' => 'LK', 'language' => 'en', 'niche' => 'Courier', 'competitors' => ['domex.lk', ''], 'max_pages' => 250])
            ->assertRedirect();

        $project = Project::firstOrFail();
        $this->assertSame('parcel.lk', $project->domain);
        $this->assertSame(250, $project->setting('max_pages'));
        $this->assertSame(['domex.lk'], $project->competitors()->pluck('domain')->all());
        Queue::assertPushed(StartCrawl::class);

        $this->get("/projects/{$project->id}")->assertOk()->assertSee('Starting crawl')->assertSee('Competitor Analysis');
        $this->getJson("/projects/{$project->id}/crawl-status")->assertOk()->assertJson(['status' => 'queued', 'running' => true]);
    }

    public function test_invalid_url_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->post('/projects', ['url' => 'not a url', 'country' => 'LK', 'language' => 'en', 'max_pages' => 100])
            ->assertSessionHasErrors('url');
        $this->assertSame(0, Project::count());
    }

    public function test_result_screens_render_crawl_data(): void
    {
        $user = $this->owner();
        $project = $this->crawledProject($user);
        $id = $project->id;

        $this->get("/projects/{$id}")->assertOk()->assertSee('SEO score')->assertSee('71')->assertSee('Top issues to fix first');
        $this->get("/projects/{$id}/explorer")->assertOk()->assertSee('/rates/')->assertSee('/gone/');
        $this->get("/projects/{$id}/explorer?f=broken")->assertOk()->assertSee('/gone/')->assertDontSee('/rates/</a>', false);
        $page = Crawl::firstOrFail()->pages()->where('url', 'https://parcel.lk/rates/')->firstOrFail();
        $this->get("/projects/{$id}/explorer?p={$page->id}")->assertOk()->assertSee('PAGE DETAIL')->assertSee('Linked from');
        $this->get("/projects/{$id}/issues")->assertOk()->assertSee('Broken links')->assertSee('How to fix');
        $this->get("/projects/{$id}/issues?code=broken_links")->assertOk()->assertSee('https://parcel.lk/gone/');
    }

    public function test_keywords_are_added_and_analysed(): void
    {
        $user = $this->owner();
        $project = $this->crawledProject($user);

        $this->post("/projects/{$project->id}/keywords", ['keywords' => "Courier Service Sri Lanka, /\ncourier rates\n\n"])
            ->assertRedirect("/projects/{$project->id}/keywords");

        $this->assertSame(['courier rates', 'courier service sri lanka'], $project->keywords()->orderBy('keyword')->pluck('keyword')->all());
        $this->assertSame('https://parcel.lk/', $project->keywords()->where('keyword', 'courier service sri lanka')->value('target_url'));
        Queue::assertPushed(AnalyzeKeywordUsage::class);

        (new AnalyzeKeywordUsage(Crawl::firstOrFail()->id))->handle(app(\App\Modules\Keywords\Services\KeywordUsageAnalyzer::class));

        $this->get("/projects/{$project->id}/keywords")->assertOk()
            ->assertSee('Keyword × page heatmap')->assertSee('Placement checks')->assertSee('Orphan keywords');
    }

    public function test_other_workspaces_cannot_see_a_project(): void
    {
        $project = $this->crawledProject($this->owner());
        $stranger = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-password-2']);

        $this->actingAs($stranger);
        foreach (['', '/explorer', '/issues', '/keywords', '/crawl-status'] as $suffix) {
            $this->get("/projects/{$project->id}{$suffix}")->assertForbidden();
        }
        $this->post("/projects/{$project->id}/crawl")->assertForbidden();
    }
}
