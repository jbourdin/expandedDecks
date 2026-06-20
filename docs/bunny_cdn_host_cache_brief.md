# Infra brief: Bunny CDN cache contamination across hostnames (robots.txt)

> **Audience:** Infrastructure agent (OpenTofu / Bunny CDN) · **Scope:** Resolved brief

← Back to [Documentation](docs.md) | [SEO/GSO Audit](seo_gso_audit.md)

> **Status:** ✅ RESOLVED 2026-06-20 (hostname-varied cache key shipped; verified per-domain). Kept for the record.
> **For:** infrastructure agent managing the Bunny CDN pull zone via OpenTofu (`tofu`).
> **From:** SEO/discovery audit of the Expanded Decks platform, 2026-06-20.
> **You do not need the application repo** — everything required is below.

---

## 1. One-paragraph summary

A **single Bunny pull zone (`5485865`)** serves **two hostnames** —
`expandeddecks.app` and `dowsingmachine.com` — from one origin (a Symfony app that
selects its "channel" from the incoming `Host` header). Bunny's cache key is
**path-only by default**, so a cached, host-dependent response is served to *both*
hostnames. `robots.txt` is the one such response currently cached at the edge, and it
is being served wrong: `dowsingmachine.com/robots.txt` returns the *other* domain's
ruleset, which **`Disallow`s the content site's primary content and advertises the
wrong sitemap**. The required change is to make the pull zone **vary its cache by
hostname**.

## 2. Evidence (reproduce before and after)

```bash
# Both hostnames return a byte-identical, SHARED cached object:
curl -sS -D - -o /dev/null https://dowsingmachine.com/robots.txt | grep -iE 'cdn-cache|cdn-cachedat|cdn-edgestorageid|cache-control|cdn-pullzone'
curl -sS -D - -o /dev/null https://expandeddecks.app/robots.txt   | grep -iE 'cdn-cache|cdn-cachedat|cdn-edgestorageid|cache-control|cdn-pullzone'
```

Observed 2026-06-20 — **identical** across both hostnames (the smoking gun):

| Header | Value (both domains) |
|--------|----------------------|
| `cdn-pullzone` | `5485865` |
| `cdn-cache` | `HIT` |
| `cdn-cachedat` | `06/20/2026 11:02:03` |
| `cdn-edgestorageid` | `1320` |
| `cache-control` | `public, max-age=86400` |

Same `cdn-cachedat` **and** `cdn-edgestorageid` on two different hostnames = one cached
object shared between them. Note `max-age=86400` is served at the edge even though the
origin sets `max-age=3600`, so a **Bunny-side cache/edge rule is already overriding the
TTL for `robots.txt`** — look for it; it may be where the hostname-vary needs to apply.

For contrast, `sitemap.xml` is served `cache-control: no-cache` / `cdn-cache: MISS`,
hits origin per request, and therefore resolves the correct hostname/channel. That is
why only `robots.txt` is corrupted today.

## 3. Root cause

Path-only cache key on a multi-hostname pull zone whose origin response depends on
`Host`. Not an application bug — the origin generates the correct per-host `robots.txt`;
the edge collapses both into one cache entry.

## 4. Required end-state (acceptance criteria)

1. After `tofu apply` + cache purge, `dowsingmachine.com/robots.txt` and
   `expandeddecks.app/robots.txt` return **different** bodies and **different**
   `cdn-cachedat`/`cdn-edgestorageid` (no longer a shared object).
2. `dowsingmachine.com/robots.txt` **allows** `/en/archetypes` and `/fr/archetypes`
   and its `Sitemap:` line points to `https://dowsingmachine.com/sitemap.xml`.
3. `expandeddecks.app/robots.txt` still contains its `Disallow: /archetypes` ruleset and
   `Sitemap: https://expandeddecks.app/sitemap.xml`.
4. No regression for other content on the zone.
5. Change is expressed in OpenTofu/HCL and committed (no manual dashboard-only edits).

## 5. Recommended fix — vary cache by hostname (primary)

Enable **Vary Cache → by Hostname** on the pull zone. This fixes `robots.txt` now and
**future-proofs** any later edge-caching of host-dependent HTML (a separate
recommendation from the audit) on this shared zone.

- **Dashboard equivalent:** Pull Zone → Caching → *Vary Cache* → enable *Vary by Hostname*.
- **OpenTofu (BunnyWay/bunnynet provider):** add `hostname` to the pull zone's cache-vary
  set. The exact attribute name depends on your provider version — **confirm against the
  provider schema and your existing `bunnynet_pullzone` resource** before applying. It is
  typically a set like:

  ```hcl
  resource "bunnynet_pullzone" "main" {
    # ...existing config...
    cache_vary = ["hostname"]   # add "hostname"; verify attribute name + existing values
  }
  ```

  If your provider exposes it as a discrete boolean (e.g. `cache_vary_hostname = true`) or
  under a nested `cache {}` / `vary {}` block, set it there instead. Run
  `tofu plan` and confirm the diff touches *only* the cache-vary parameter.

## 6. Fallback fix — bypass cache for robots.txt (if hostname-vary is unavailable/undesired)

If you prefer to match the working `sitemap.xml` behaviour exactly, add an **edge rule**
that bypasses cache for `/robots.txt` so it always hits origin (origin cost is negligible —
roughly one request per crawler per day):

```hcl
resource "bunnynet_pullzone_edgerule" "robots_no_cache" {
  pullzone    = bunnynet_pullzone.main.id
  enabled     = true
  description = "robots.txt is host-dependent; bypass shared cache so each domain hits origin"

  actions = [{ type = "BypassCache" }]   # verify action type name against provider schema

  match_type = "MATCH_ANY"
  triggers = [{
    type        = "URL"
    match_type  = "MATCH_ANY"
    patterns    = ["*/robots.txt"]
  }]
}
```

Trade-off vs. §5: this fixes only `robots.txt`. It does **not** protect future host-dependent
HTML caching on this zone — so §5 (hostname vary) is preferred. Do not apply both unless you
deliberately want robots.txt uncached *and* hostname-varied.

## 7. Rollout

1. `tofu plan` — confirm the diff is limited to the cache-vary parameter (or the single edge
   rule).
2. `tofu apply`.
3. **Purge the cached `robots.txt`** for the zone (both hostnames) — the change won't take
   effect on already-cached objects until purge or TTL expiry. Purge via the Bunny API/CLI or
   a targeted purge of `https://dowsingmachine.com/robots.txt` and
   `https://expandeddecks.app/robots.txt`.
4. Re-run the §2 `curl` commands and confirm the §4 acceptance criteria.

## 8. Hand-back to the app/SEO owner (not your task, just FYI)

Once §4 passes, the application owner should **submit each sitemap in its own Google Search
Console property** (`dowsingmachine.com` and `expandeddecks.app`) and request indexing of key
archetype/news URLs, since those have been crawl-blocked while this bug was live.

## 9. Notes / guardrails

- This is the only cached host-dependent response today, so blast radius is small — but
  **verify** other cached objects on the zone don't rely on path-only keys in a way that
  hostname-vary would change unexpectedly (it shouldn't, given the two domains serve distinct
  content).
- Keep the fix in OpenTofu state; if there is an out-of-band dashboard edge rule overriding
  the `robots.txt` TTL to 86400 (see §2), reconcile it into HCL rather than leaving it
  unmanaged.
