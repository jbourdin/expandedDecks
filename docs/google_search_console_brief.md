# Brief: Google Search Console vs. natural crawling — do we need it?

> **Audience:** CTO · **Scope:** Decision brief

← Back to [Documentation](docs.md) | [SEO/GSO Audit](seo_gso_audit.md) | [Issue #296 (F19.3)](https://github.com/jbourdin/expandedDecks/issues/296)

> **Related:** [seo_gso_audit.md](seo_gso_audit.md) (C1, H1) · **Date:** 2026-06-20

---

## Verdict

**Yes — set up Google Search Console (GSC) for both domains. And it's especially warranted
right now.** This is not an "implementation" in the engineering sense: there is no code to
write and nothing to maintain. It's a one-time, ~15-minutes-per-domain verification (a DNS TXT
record) that unlocks free, passive instrumentation and a few active controls. The recommendation
extends to **Bing Webmaster Tools**, which matters specifically for GSO (Bing's index feeds
Copilot and parts of ChatGPT Search).

## The honest case for "natural crawling is enough"

It's true that **GSC is not required for indexing.** Googlebot will discover and index
`dowsingmachine.com` on its own via links and the `robots.txt`-advertised sitemap. If the only
goal were "exist in Google's index eventually," you could skip it.

But "natural crawling" means flying blind: you get indexing **without any visibility, control,
or feedback**. You cannot see what's indexed, what's excluded and why, what queries you rank
for, or whether a regression has tanked your coverage — until traffic moves and you guess at
the cause.

## What GSC adds that natural crawling cannot

| Capability | Natural crawling | With GSC |
|------------|------------------|----------|
| Get indexed at all | ✅ Yes | ✅ Yes |
| **See which URLs are indexed / excluded + the reason** | ❌ No visibility | ✅ Coverage report |
| **Submit each sitemap to the correct property** | ⚠️ Only via `robots.txt` directive | ✅ Explicit per-property submission |
| **Request (re-)indexing of specific URLs** | ❌ Wait for the crawler | ✅ URL Inspection → Request indexing |
| **See real query / click / impression / position data** | ❌ None | ✅ Performance report |
| Inspect how Googlebot renders a page | ❌ No | ✅ Live URL Inspection |
| Confirm canonical/hreflang/duplicate handling | ❌ Guess | ✅ Shows chosen canonical per URL |
| Alerts: manual actions, security issues, indexing drops | ❌ No | ✅ Email alerts |
| Core Web Vitals field data, mobile usability | ❌ No | ✅ Reports |

## Why now, specifically

1. **Verify the C1 recovery.** We just fixed a `robots.txt` bug that **blocked the entire
   archetype catalogue from indexing** for an unknown window. Without GSC there is no way to
   confirm Google has re-crawled and re-indexed those pages — or to *accelerate* it. With GSC
   you submit the sitemap, watch archetype URLs move into "Indexed," and can manually request
   indexing of the top archetypes to speed recovery from days/weeks to hours.
2. **The cross-domain sitemap history.** While C1 was live, `robots.txt` pointed at the wrong
   domain's sitemap. Explicitly submitting `dowsingmachine.com/sitemap.xml` in its own GSC
   property removes any lingering ambiguity about which sitemap belongs to which site.
3. **Validate the H1 locale fix.** GSC's per-URL canonical report will *show* how Google treats
   the `/fr/` duplicates ("Alternate page with proper canonical tag"), confirming the audit's
   reasoning and letting you measure the crawl-budget reclaim after the sitemap is trimmed.
4. **Content ROI.** dowsingmachine is a content/marketing play. The Performance report tells you
   which archetypes and guides actually pull search traffic — turning content decisions from
   guesswork into data.

## Cost / effort / risk

- **Effort:** ~15 min/domain. Verify via a **DNS TXT record** (you control DNS through the infra
  stack) — a *Domain property* then covers `https`, `www`, and all subdomains in one. No code,
  no deploy, no app dependency.
- **Ongoing cost:** none (free). No maintenance burden.
- **Risk:** negligible. GSC is read-only telemetry plus opt-in actions; it cannot change the
  site. Verification proves ownership; it does not grant Google any new access (Googlebot
  already crawls public pages).

## Recommendation

1. Create **two GSC Domain properties** — `dowsingmachine.com` and `expandeddecks.app` — via DNS
   TXT verification.
2. **Submit each domain's sitemap** in its own property.
3. After C1's fix propagates, **URL-Inspect a few key archetype pages and Request indexing** to
   accelerate recovery.
4. Set up **Bing Webmaster Tools** for both domains too (you can import settings from GSC). This
   covers the Bing-derived answer engines and enables **IndexNow** for near-instant
   change notification — a meaningful GSO edge for a freshness-sensitive content site.
5. Revisit the Performance report after ~4 weeks of data to inform content priorities.

## Notes / guardrails

- **GDPR:** GSC aggregates search-traffic data (queries, anonymized click/impression counts) —
  no end-user PII you control, and it's Google acting as the search provider, not a processor of
  *your* users' data. Standard and low-concern. If you later wire the GSC API into your own
  analytics store, treat that store under your existing data-handling policy.
- This brief deliberately scopes to **measurement/verification**, not engineering. The code-side
  work it depends on (C1 ✅ done; H1 sitemap honesty; meta descriptions) is tracked in
  [seo_gso_audit.md](seo_gso_audit.md) and issue #296.
