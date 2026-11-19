import unittest

from app.audit import analyze_page, analyze_site

WEAK = "<html><head><title>Hi</title></head><body><p>short</p><img src=a.png></body></html>"

ANSWER = " ".join(["Courier delivery to the UK usually takes three to five working days from Colombo."] * 3)
GOOD = f"""<html lang=en><head><title>International Courier Service from Sri Lanka | Parcel.LK</title>
<meta name=description content="Send parcels from Sri Lanka to 200+ countries with tracking, door pickup and fast customs clearance. Get an instant quote today.">
<meta name=viewport content="width=device-width"><link rel=canonical href="https://parcel.lk/">
<meta property=og:title content=x><meta property=og:image content=y><meta name=author content=Team>
<script type="application/ld+json">{{"@context":"https://schema.org","@graph":[{{"@type":"Organization","name":"Parcel.LK"}},{{"@type":"FAQPage","datePublished":"2026-01-01"}}]}}</script>
</head><body><h1>International courier</h1><h2>How long does delivery to the UK take?</h2><p>{ANSWER}</p>
<ul><li>a</li></ul><a href="/rates">Courier rates</a><a href="https://dhl.com" rel="nofollow">DHL</a>
<img src="/box.png" alt="International courier box">
<p>{"Reliable shipping for families and businesses across the world. " * 40}</p></body></html>"""


class AuditTests(unittest.TestCase):
    def test_weak_page_has_issues_and_low_scores(self):
        r = analyze_page("https://example.com/x", 200, {}, WEAK, 1500)
        codes = {i["code"] for i in r["issues"]}
        self.assertIn("h1_missing", codes)
        self.assertIn("viewport_missing", codes)
        self.assertLess(r["scores"]["seo"], 50)
        self.assertLess(r["scores"]["ai"], 80)

    def test_good_page_scores_full(self):
        r = analyze_page("https://parcel.lk/", 200, {}, GOOD, 400)
        self.assertEqual(r["issues"], [])
        self.assertEqual(r["scores"], {"seo": 100, "ai": 100})
        self.assertEqual(r["schema_types"], ["FAQPage", "Organization"])

    def test_links_carry_anchor_and_nofollow(self):
        r = analyze_page("https://parcel.lk/", 200, {}, GOOD, 400)
        by_url = {l["url"]: l for l in r["links"]}
        self.assertEqual(by_url["https://parcel.lk/rates"]["anchor"], "Courier rates")
        self.assertTrue(by_url["https://parcel.lk/rates"]["internal"])
        self.assertTrue(by_url["https://dhl.com"]["nofollow"])
        self.assertFalse(by_url["https://dhl.com"]["internal"])

    def test_images_and_body_text_returned(self):
        r = analyze_page("https://parcel.lk/", 200, {}, GOOD, 400)
        self.assertEqual(r["images"][0]["alt"], "International courier box")
        self.assertIn("Reliable shipping", r["body_text"])
        self.assertNotIn("schema.org", r["body_text"])

    def test_noindex_header_detected(self):
        r = analyze_page("https://parcel.lk/", 200, {"X-Robots-Tag": "noindex"}, GOOD, 400)
        self.assertFalse(r["indexable"])

    def test_site_checks_and_redirects_excluded_from_score(self):
        good = analyze_page("https://parcel.lk/", 200, {}, GOOD, 400)
        redirect = {"url": "https://parcel.lk/old", "redirect": True, "issues": [], "scores": {"seo": None, "ai": None},
                    "indexable": False, "title": None, "description": None}
        s = analyze_site([good, redirect], "User-agent: GPTBot\nDisallow: /\n", False, False,
                         [{"from": "x", "to": "y", "status": 404}])
        codes = {i["code"] for i in s["issues"]}
        self.assertEqual(s["blocked_ai_bots"], ["GPTBot"])
        self.assertTrue({"sitemap_missing", "llms_txt_missing", "ai_bots_blocked", "broken_links"} <= codes)
        self.assertEqual(s["pages_crawled"], 1)
        self.assertEqual(s["redirects"], 1)


if __name__ == "__main__":
    unittest.main()
