<?php

/*
|--------------------------------------------------------------------------
| Issue guides
|--------------------------------------------------------------------------
| Plain-English title, why it matters and how to fix, per issue code produced by
| App\Modules\Crawler\Analysis\PageAnalyzer and SiteAnalyzer. Shown on the Issues tab.
*/

return [
    // ── SEO · critical ───────────────────────────────────────────────
    'http_error' => [
        'title' => 'Page returns an error (4xx/5xx)',
        'why' => 'Visitors and Google cannot open the page. Links pointing to it pass no value.',
        'steps' => ['Check whether the page should exist.', 'If it moved, add a 301 redirect to the new URL.', 'If it is gone for good, remove links to it from your pages and sitemap.', 'For 5xx errors, ask your developer or host to check the server logs.'],
    ],
    'broken_links' => [
        'title' => 'Broken links',
        'why' => 'Visitors hit a dead end, and Google wastes crawl time on pages that are gone. Links to missing pages pass no authority.',
        'steps' => ['Open each broken URL below and decide: does a replacement page exist?', 'If yes, add a 301 redirect from the old URL to the new one.', 'Update the links on the pages that point to it.', 'Re-crawl to confirm the issue is gone.'],
    ],
    'noindex' => [
        'title' => 'Page set to noindex',
        'why' => 'Google will not show this page in search results. That is right for thank-you or login pages, but costly for service or product pages.',
        'steps' => ['Decide whether the page should rank.', 'If yes, remove the noindex meta tag or X-Robots-Tag header (in WordPress: Yoast/Rank Math advanced settings).', 'Check Settings → Reading → "Discourage search engines" is off.', 'Request indexing in Google Search Console.'],
    ],
    'title_missing' => [
        'title' => 'Missing page title',
        'why' => 'The title is the blue link in Google results and one of the strongest ranking signals.',
        'steps' => ['Write a unique title of 30–60 characters.', 'Put the main keyword near the start.', 'End with your brand name if there is room.'],
    ],
    'h1_missing' => [
        'title' => 'Missing H1 heading',
        'why' => 'The H1 tells visitors and search engines what the page is about. Pages without one are harder to rank.',
        'steps' => ['Add one H1 near the top of the page.', 'Include the page\'s main keyword in it.', 'Keep logos and menus out of the H1.'],
    ],
    'viewport_missing' => [
        'title' => 'No mobile viewport tag',
        'why' => 'Without it, phones show a zoomed-out desktop page. Google indexes the mobile version first.',
        'steps' => ['Add <meta name="viewport" content="width=device-width, initial-scale=1"> to the page head.', 'Check the page on a phone after the change.'],
    ],

    // ── SEO · warnings ───────────────────────────────────────────────
    'title_long' => [
        'title' => 'Title longer than 60 characters',
        'why' => 'Google cuts long titles off with "…", so the end of your message is lost.',
        'steps' => ['Shorten the title to about 50–60 characters.', 'Keep the main keyword at the start.', 'Drop filler words and repeated brand names.'],
    ],
    'title_short' => [
        'title' => 'Title too short',
        'why' => 'Very short titles miss the chance to include the keyword and a reason to click.',
        'steps' => ['Expand the title to 30–60 characters.', 'Add the main keyword and a benefit (price, speed, location).'],
    ],
    'description_missing' => [
        'title' => 'Missing meta description',
        'why' => 'Google writes its own snippet, often from random page text. A good description raises clicks.',
        'steps' => ['Write 120–160 characters that sum up the page.', 'Use the main keyword once.', 'End with a reason to click, e.g. "Get a free quote".'],
    ],
    'canonical_missing' => [
        'title' => 'No canonical tag',
        'why' => 'Without a canonical, URL variations (tracking parameters, http/https) can be treated as duplicates.',
        'steps' => ['Add a self-referencing <link rel="canonical"> on every indexable page.', 'Most SEO plugins do this automatically once enabled.'],
    ],
    'img_alt_missing' => [
        'title' => 'Images without alt text',
        'why' => 'Alt text helps screen-reader users, image search and keyword relevance.',
        'steps' => ['Describe each meaningful image in a few words.', 'Use the page keyword where it fits naturally.', 'Leave alt empty only for purely decorative images.'],
    ],
    'thin_content' => [
        'title' => 'Thin content (under 300 words)',
        'why' => 'Short pages rarely answer the searcher\'s question fully, so they rank below competitors.',
        'steps' => ['Expand the page with useful detail: process, prices, FAQs, examples.', 'Compare with the top 3 ranking pages for the keyword.', 'If the page has no purpose, merge it into a stronger page and redirect.'],
    ],
    'large_html' => [
        'title' => 'Large HTML page',
        'why' => 'Heavy pages load slowly on mobile data and can be partly ignored by crawlers.',
        'steps' => ['Remove inline scripts, styles and unused page-builder blocks.', 'Lazy-load long lists and galleries.'],
    ],
    'slow_response' => [
        'title' => 'Slow server response (over 1s)',
        'why' => 'Slow pages lose visitors and hurt Core Web Vitals, a Google ranking factor.',
        'steps' => ['Turn on page caching (e.g. LiteSpeed Cache on Hostinger).', 'Compress and resize images; serve WebP.', 'Remove unused plugins and scripts.', 'Use a CDN such as Cloudflare.'],
    ],
    'schema_invalid' => [
        'title' => 'Invalid structured data',
        'why' => 'Broken JSON-LD is ignored by Google, so you lose rich results.',
        'steps' => ['Test the page in Google\'s Rich Results Test.', 'Fix the JSON syntax or missing required fields it reports.'],
    ],
    'duplicate_titles' => [
        'title' => 'Duplicate titles',
        'why' => 'When several pages share a title, Google cannot tell which one to rank.',
        'steps' => ['Give every page a unique title that matches its own topic.', 'If two pages really cover the same thing, merge them and redirect.'],
    ],
    'robots_missing' => [
        'title' => 'No robots.txt file',
        'why' => 'robots.txt tells crawlers what to skip and where your sitemap is.',
        'steps' => ['Create /robots.txt allowing all crawlers.', 'Add a "Sitemap: https://yoursite/sitemap.xml" line.'],
    ],
    'sitemap_missing' => [
        'title' => 'No XML sitemap',
        'why' => 'A sitemap helps Google find all your important pages, especially new or deep ones.',
        'steps' => ['Enable the sitemap in your SEO plugin, or generate /sitemap.xml.', 'Reference it in robots.txt.', 'Submit it in Google Search Console.'],
    ],

    // ── SEO · notices ────────────────────────────────────────────────
    'redirect' => [
        'title' => 'Internal link goes through a redirect',
        'why' => 'Each redirect adds a delay and leaks a little link value. Internal links should point straight to the final URL.',
        'steps' => ['Find the pages that link to the old URL (see Crawl Explorer → page detail → Linked from).', 'Change those links to the final URL shown in the message.'],
    ],
    'canonical_other' => [
        'title' => 'Canonicalised to another URL',
        'why' => 'This page tells Google another URL is the main version, so this one will not rank.',
        'steps' => ['Check the canonical target is correct.', 'If this page should rank on its own, make the canonical point to itself.'],
    ],
    'description_long' => [
        'title' => 'Meta description too long',
        'why' => 'Google cuts descriptions after about 160 characters.',
        'steps' => ['Trim to 120–160 characters, keeping the key message first.'],
    ],
    'description_short' => [
        'title' => 'Meta description too short',
        'why' => 'A very short description wastes space that could sell the click.',
        'steps' => ['Expand to 120–160 characters with the keyword and a benefit.'],
    ],
    'duplicate_descriptions' => [
        'title' => 'Duplicate meta descriptions',
        'why' => 'Repeated descriptions make pages look the same in search results.',
        'steps' => ['Write a unique description for each important page.'],
    ],
    'h1_multiple' => [
        'title' => 'More than one H1',
        'why' => 'Several H1s blur the page\'s main topic.',
        'steps' => ['Keep one H1 for the main topic.', 'Change the others to H2.'],
    ],
    'heading_skip' => [
        'title' => 'Heading levels skipped',
        'why' => 'Jumping from H1 to H4 confuses screen readers and weakens the page outline.',
        'steps' => ['Use headings in order: H1, then H2, then H3.', 'Style headings with CSS rather than picking a level for its size.'],
    ],
    'lang_missing' => [
        'title' => 'No language set',
        'why' => 'The lang attribute helps search engines and screen readers serve the right audience.',
        'steps' => ['Add lang="en" (or si / ta) to the <html> tag.'],
    ],
    'no_internal_links' => [
        'title' => 'No internal links on the page',
        'why' => 'Internal links spread authority and help visitors and crawlers move through the site.',
        'steps' => ['Link to 2–5 related pages with descriptive anchor text.'],
    ],
    'og_incomplete' => [
        'title' => 'Open Graph tags incomplete',
        'why' => 'Shared links on Facebook, WhatsApp and LinkedIn show without a proper title or image.',
        'steps' => ['Add og:title, og:description and og:image (1200×630) in your SEO plugin.'],
    ],
    'slowish_response' => [
        'title' => 'Response time could be faster',
        'why' => 'Pages taking over half a second to respond feel sluggish on mobile.',
        'steps' => ['Enable caching and a CDN.', 'Check heavy plugins or database queries.'],
    ],

    // ── AI-readiness (AEO / GEO) ─────────────────────────────────────
    'ai_bots_blocked' => [
        'title' => 'robots.txt blocks AI crawlers',
        'why' => 'Blocked AI crawlers cannot read your pages, so ChatGPT, Claude or Perplexity will not cite you.',
        'steps' => ['Open robots.txt and remove Disallow rules for GPTBot, ClaudeBot, PerplexityBot and Google-Extended, unless you block them on purpose.', 'Re-crawl to confirm.'],
    ],
    'schema_missing' => [
        'title' => 'No structured data (JSON-LD)',
        'why' => 'Structured data tells Google and AI assistants exactly what the page is: a service, product, article or business.',
        'steps' => ['Add Organization or LocalBusiness schema site-wide.', 'Add Service, Product, Article or FAQPage schema to matching pages.', 'Validate in the Rich Results Test.'],
    ],
    'llms_txt_missing' => [
        'title' => 'No /llms.txt file',
        'why' => 'llms.txt is a short guide that points AI assistants to your most important pages.',
        'steps' => ['Create /llms.txt with a one-line description of the business.', 'List your key pages with one sentence each.'],
    ],
    'faq_schema_missing' => [
        'title' => 'FAQ content without FAQPage schema',
        'why' => 'The page answers questions but does not mark them up, so they are less likely to be quoted.',
        'steps' => ['Wrap the questions and answers in FAQPage JSON-LD.'],
    ],
    'no_question_headings' => [
        'title' => 'No question-style headings',
        'why' => 'People ask AI assistants questions. Headings phrased as questions get matched and quoted more.',
        'steps' => ['Add H2s such as "How much does it cost to…?" with a direct answer below.'],
    ],
    'answers_not_concise' => [
        'title' => 'Answers are not concise',
        'why' => 'AI assistants prefer a short, direct answer (40–60 words) right after a question.',
        'steps' => ['Start each answer with a one or two-sentence summary, then add detail.'],
    ],
    'long_sentences' => [
        'title' => 'Long sentences',
        'why' => 'Long sentences are harder to read and harder for AI to quote cleanly.',
        'steps' => ['Split sentences over 25 words.', 'Use lists for steps and options.'],
    ],
    'no_author' => [
        'title' => 'No author shown',
        'why' => 'A named author builds trust (E-E-A-T) with Google and AI systems.',
        'steps' => ['Show the author\'s name and role, linked to a short bio page.'],
    ],
    'no_date' => [
        'title' => 'No published or updated date',
        'why' => 'Dates show the content is current. AI assistants favour fresh sources.',
        'steps' => ['Show "Updated on" with the date.', 'Add datePublished / dateModified to the page schema.'],
    ],
    'no_entity_schema' => [
        'title' => 'No Organization schema',
        'why' => 'Organization schema connects your site to your brand, logo and social profiles.',
        'steps' => ['Add Organization (or LocalBusiness) JSON-LD with name, logo, address and sameAs links.'],
    ],
    'no_structure' => [
        'title' => 'Little structure (lists, tables)',
        'why' => 'Lists and tables are easy for readers to scan and for AI to extract.',
        'steps' => ['Turn steps into numbered lists and comparisons into tables.'],
    ],
];
