---
name: agentready
description: >
  Raise a website's Agent Ready score toward 100. Scans a site with the Agent Ready audit
  (agentready.sneeze.it), maps every finding to a concrete edit in that site's codebase,
  applies the fixes it can make safely, and hands off the ones that need infrastructure or
  a business decision. Use when the user says "agentready", "/agentready", "fix the agent
  score", "make this site agent ready", "WebMCP", "agent readiness", or names a site to
  raise the score on.
allowed-tools: Read, Grep, Glob, Bash, Write, Edit, WebFetch
---

# Agent Ready fixer

Raises a site's Agent Ready score. Four pillars, 17 checks, 100 points.
Full spec: `~/sneeze-agent-ready/README.md`. The audit is live at agentready.sneeze.it.

```
/agentready <url>                  scan and produce the plan, change nothing
/agentready <url> --fix            scan, apply what is safe, rescan, report the delta
/agentready <url> --repo <path>    say where the code is, skip discovery
/agentready <url> --wp             WordPress: generate the drop-in plugin instead of editing
/agentready <url> --target 85      stop when the score reaches this, default: the ceiling
```

## Two modes, decided in Phase 0

**Repo mode.** There is a codebase you can edit and deploy: anything built with AI here
(Next, Astro, Nuxt, Hono, static, iJoin, Sneeze Studio). Edit source, commit, deploy, rescan.

**Plugin mode.** WordPress with admin access and no repo, which is most local businesses and
most of the client base this sells into. You cannot edit source, so you generate a drop-in
plugin the owner uploads once. See `references/wordpress.md`. Never edit a client's live site
yourself, and never paste PHP into the theme editor.

Decide which before Phase 1. Running repo-mode discovery against a hosted WordPress site
wastes a lot of time finding nothing.

---

## The single most important rule

**100 is usually not reachable by an agent alone, and pretending otherwise is the failure
mode of this skill.**

Points split three ways:

| Class | Who does it | Examples |
|---|---|---|
| **AGENT** | You, in the codebase | llms.txt, JSON-LD, form labels, tel links, sitemap, mcp.json, WebMCP registration |
| **INFRA** | A human with Cloudflare, WAF or DNS access | bot wall, rate limit, captcha |
| **DECISION** | The business owner | server rendering an SPA, moving booking out of a third party iframe, removing a login wall |

**Compute the ceiling before you start** and say it out loud: "AGENT fixes take this from
41 to 78. The remaining 22 needs X in Cloudflare and a decision about Y."

Never report a score you have not re-measured. Never claim a fix you did not verify.

---

## Never do these, for any number of points

1. **Never invent a business fact.** Hours, prices, phone numbers, addresses, service areas,
   credentials. Read them from the site, or ask. JSON-LD asserting a phone number that does
   not ring is worse than no JSON-LD: it is a machine readable lie that agents will repeat.
2. **Never register a WebMCP tool without a working endpoint behind it.** A `check_availability`
   that returns plausible-looking slots will have an agent tell a customer to show up at a
   time nobody is expecting them. If the endpoint does not exist, build it or skip the tool.
3. **Never weaken real security to gain points.** Do not strip a captcha off a login, a
   checkout, or a password reset. The fix is always to *scope* protection to the routes that
   need it, never to remove it. If the only way to a point is a genuine security reduction,
   do not take the point. Write down why.
4. **Never fake server rendered content.** Do not stuff hidden text into the HTML to pass the
   no-JS check. Either render it for real or report the finding as a DECISION.
5. **Never open robots.txt to training crawlers on a client's behalf.** Live agents
   (`ChatGPT-User`, `Claude-User`, `Perplexity-User`) are customers and should be allowed.
   Training crawlers (`GPTBot`, `CCBot`, `Google-Extended`) are a business choice that
   belongs to the site owner. Allow the first group, ask about the second.
6. **Never edit a client's production site directly.** Work in the repo, on a branch, and let
   the normal deploy carry it.

---

## Phase 0 — locate

Resolve two things before touching anything.

**The site.** Normalise the URL. Confirm it is one we are allowed to change.

**The code.** In order of preference:
1. `--repo <path>` if given.
2. Search likely roots: `ls -d ~/*/ | grep -i <brandtoken>`, then check for `package.json`,
   `wp-config.php`, `astro.config.*`, `next.config.*`, `_config.yml`, `Gemfile`.
3. Ask. Do not guess a repo and start editing it.

**Detect the stack**, because the same fix lands in different places:

| Signal | Stack | Where fixes go |
|---|---|---|
| `next.config.*` | Next.js | `app/layout.tsx` metadata, `public/`, route handlers |
| `astro.config.*` | Astro | `src/layouts/`, `public/` |
| `nuxt.config.*` | Nuxt | `nuxt.config` head, `public/` |
| `wp-config.php` | WordPress | theme `functions.php`, `header.php`, root files |
| `hono`/`express` in `package.json` | Node server | route handlers, a `public/` mount |
| `index.html` + no framework | static | edit directly |
| iJoin site builder | iJoin | see `references/fixes.md`, do NOT hand edit a club's hosted site |

**WordPress detected and no repo?** Switch to plugin mode now, and read
`references/wordpress.md` before anything else. Two things there will bite otherwise: an SEO
plugin already emitting JSON-LD (never add a second business identity), and caching serving a
stale llms.txt so a correct fix looks like a failed one.

If it is a **paying client's hosted site on our platform**, stop and confirm before editing.
The Clay boundary rule applies: those visitors and that site are the client's, not ours.

---

## Phase 1 — baseline

```bash
node ~/.claude/skills/agentready/scripts/scan.mjs <url> --baseline
```

Prints the score, per-pillar bars, and every open finding sorted by points available, with
the finding `id`, what was observed, and the suggested fix. Writes
`.agentready-baseline.json` for the delta later.

**If the scan reports a BOT WALL**, stop and read: Reach cannot exceed 0 until a human
changes the WAF. Everything else was measured through a real browser and is still fixable,
so continue, but lead the report with the wall. Hand them
`~/sneeze-agent-ready/docs/cloudflare-agent-access.md`.

---

## Phase 2 — plan

For each open finding, map it to a concrete edit using `references/fixes.md`, which has the
exact fix per check per stack. Classify each as AGENT, INFRA or DECISION.

Present a table before changing anything:

```
FINDING                        PTS  CLASS      WHAT I WILL DO
structured_data                +10  AGENT      Add LocalBusiness JSON-LD to the layout head
llms_txt                        +4  AGENT      Write public/llms.txt from the services pages
machine_fillable_forms          +6  AGENT      Add labels and autocomplete to the quote form
webmcp_tools                   +14  AGENT*     *needs /api/availability first, see below
agent_ua_access                 +7  INFRA      Cloudflare Bot Fight Mode, owner must action
no_js_content                   +7  DECISION   SPA renders empty, needs SSR, ~2 day change

  Ceiling with AGENT fixes only: 41 -> 78
  With INFRA: 85.  With the SSR decision: 92.
```

Delegate findings (personal agents) are preview: list them in a separate block under the
table, and do not count them in the ceiling. See the DELEGATE section of `references/fixes.md`.

Then get a go-ahead before Phase 3 unless `--fix` was passed.

---

## Phase 3 — fix

Work in this order, cheapest and most certain first. Commit in logical chunks.

1. **llms.txt** — `templates/llms.txt`. Fill from the real site content, never invented.
2. **robots.txt** — allow live agents. Add the sitemap line.
3. **sitemap.xml** — generate or wire up the framework's generator, reference it in robots.
4. **Page basics** — one title, meta description over 40 chars, exactly one h1, `lang`.
5. **JSON-LD** — `templates/local-business.jsonld`. Every value verified against the site.
6. **tel: and mailto:** — real links. If the number is in an image or assembled by script,
   put it in the markup.
7. **Forms** — `name`, a real `<label for>`, an `autocomplete` token, `type="email"` and
   `type="tel"`. This also fixes accessibility, so say so.
8. **/.well-known/mcp.json** — `templates/mcp.json`. Only list tools that exist.
9. **WebMCP** — `templates/webmcp.js` and `references/webmcp.md`. Heaviest single check at
   14 points, and the one with the anti-cheat rule attached. Build the endpoints first.

After each meaningful group, rebuild and rescan rather than doing everything blind.

---

## Phase 4 — verify

```bash
node ~/.claude/skills/agentready/scripts/verify.mjs <url>
```

Prints the before and after per pillar and exits non-zero on a regression. **A fix is not
done until the rescan proves it.** If the score did not move where you expected, the change
did not deploy, or you fixed something the scanner measures differently than you assumed.
Go and look, do not explain it away.

Note the scan caches per host for 30 minutes. For a tight loop use a cache-busting query or
wait it out.

---

## Phase 5 — report

```
<host>: 41 -> 78  (+37)

DONE          7 fixes, all verified by rescan
STILL OPEN    agent_ua_access (+7)  needs Cloudflare, see docs/cloudflare-agent-access.md
              no_js_content (+7)    needs SSR, ~2 days, your call
NOT TAKEN     no_captcha_wall       the captcha is on the login, removing it is not worth
                                    6 points and I am not doing it
CEILING       85 once Cloudflare is done. 92 with SSR.
```

Say plainly what is left and who owns it. A handoff that does not name the owner is not a
handoff.

---

## References

- `references/fixes.md` — every check, the exact fix, per stack
- `references/wordpress.md` — plugin mode, SEO plugin conflicts, caching, page builders
- `references/webmcp.md` — implementing WebMCP correctly and honestly
- `templates/` — llms.txt, mcp.json, local-business.jsonld, webmcp.js
- `templates/wordpress/` — the drop-in plugin, built by `scripts/make-wp-plugin.mjs`
- `~/sneeze-agent-ready/docs/cloudflare-agent-access.md` — the INFRA handoff
- `~/sneeze-agent-ready/README.md` — the scoring model and why Act is weighted 35
