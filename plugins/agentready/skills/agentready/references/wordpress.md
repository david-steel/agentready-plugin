# WordPress

Most local businesses are on WordPress, which is also most of the ICP this tool sells into:
med spas, gyms, law firms, home service, dentists. It needs its own path because the
assumption the rest of the skill makes, that there is a repo you can edit, is usually false.

## Decide the mode first

| Situation | Mode |
|---|---|
| You have the theme in git and a deploy | **Repo mode.** Treat it like any other codebase, edit the child theme. |
| Hosted site, admin login only, no repo | **Plugin mode.** Generate the drop-in. This is the common case. |
| Client's live production site, no staging | **Plugin mode, and they install it.** Never edit a client's live site yourself. |

## Plugin mode

```bash
node ~/.claude/skills/agentready/scripts/make-wp-plugin.mjs ~/Desktop
```

Produces `agent-ready.zip`. The owner uploads it once:

1. Plugins, Add New, Upload Plugin, choose the zip, Activate
2. **Settings, Agent Ready** - fill in the real business facts, Save
3. **Settings, Permalinks, Save** once, to flush rewrite rules
4. Rescan

What it does on activation:

| Check | Points | How |
|---|---|---|
| `llms_txt` | 4 | Serves `/llms.txt` built from the settings and the page list |
| `mcp_discovery` | 5 | Serves `/.well-known/mcp.json` |
| `structured_data` | up to 10 | JSON-LD in `wp_head` on the front page, from the settings |
| `ai_crawler_policy` | 10 | `robots_txt` filter: live agents allowed, training crawlers by choice |
| `sitemap` | 4 | Points robots.txt at WordPress core's `wp-sitemap.xml` |
| `webmcp_tools` | partial | Registers `search_site` only. Real tools need real routes, see below. |

**The facts come from a settings form, not from the model.** That is the anti-invention rule
enforced structurally: a blank field publishes nothing, so nobody can assert a phone number
that does not ring. Tell the client that plainly. A blank field costs a few points, a wrong
one costs a customer.

## What the plugin deliberately does not do

- **It ships no booking tools.** WebMCP tools must call endpoints that return true data. On
  WordPress that means a REST route backed by whatever booking plugin the site actually runs
  (Amelia, Bookly, WooCommerce Bookings, a Zenoti or Mindbody bridge). The template has a
  commented `check_availability` to wire up. Do not uncomment it until the route is real.
- **It does not touch forms.** `machine_fillable_forms` (9 pts) lives in whatever form plugin
  they use. See below.
- **It cannot fix `agent_ua_access`.** That is the host or WAF. INFRA handoff.

## Fixes that live outside the plugin

**Forms (9 pts).** Gravity Forms, WPForms, Contact Form 7 and Elementor forms all emit fields
with names but usually without `autocomplete` tokens or properly associated labels.
- Gravity Forms: field settings have an Autocomplete Attribute box. Set `email`, `tel`,
  `given-name`, `postal-code`.
- WPForms and CF7: add the attribute in the field's advanced settings, or filter the markup.
- Elementor: the form widget exposes no autocomplete field. Add it with a small `wp_footer`
  script, or move the form to a plugin that does.
- Check `type` too: an email field emitting `type="text"` scores lower.

**SEO plugin conflicts.** Yoast and RankMath both write their own robots.txt and their own
JSON-LD graph.
- **robots.txt:** they override the `robots_txt` filter. Edit robots.txt in the plugin's own
  tools screen instead, and confirm by fetching `/robots.txt`.
- **JSON-LD:** if Yoast or RankMath is already emitting an `Organization` or `LocalBusiness`
  node, do not add a second one. Two competing business identities is worse than one. Fill in
  theirs, and leave the Agent Ready business fields blank so it stays quiet.
- Always fetch the live page and count `application/ld+json` blocks before and after.

**`no_js_content`.** Rare on WordPress and usually good news: classic themes server render.
If it fails, the culprit is normally an Elementor or Divi page built entirely from widgets
that load over AJAX, or a headless setup. Check the raw HTML before assuming.

**Page builders.** Elementor, Divi and Beaver Builder often produce two `h1`s and no meta
description. `page_basics` (4 pts) is usually four quick wins in the builder's own settings.

## Caching

WP Rocket, W3 Total Cache, LiteSpeed and host level caching will serve a stale `/llms.txt`
and stale `robots.txt`. **Purge the cache after activating, before rescanning.** A scan that
shows no change is usually a cache, not a failed fix. Cloudflare in front of the site caches
these too.

## Multisite

Settings are per site, not network wide. Activate per site, or network activate and configure
each one. `/llms.txt` and `/.well-known/mcp.json` resolve per site through the rewrite rules.

## Verify, always

```bash
curl -s https://SITE/llms.txt | head -5
curl -s https://SITE/.well-known/mcp.json | head -5
curl -s https://SITE/robots.txt | grep -A1 ChatGPT-User
node ~/.claude/skills/agentready/scripts/verify.mjs SITE
```

A 404 on the first two almost always means the permalinks flush in step 3 was skipped.
