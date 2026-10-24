# Every check, and the exact fix

17 scored checks, plus 6 Delegate checks in preview. `id` is what `scan.mjs` prints. Class is who can do it.

---

## REACH (25 pts)

### `ai_crawler_policy` — 10 pts — AGENT
robots.txt disallowing AI crawlers.

Allow the **live agents** always. They are customers mid-task:
```
User-agent: ChatGPT-User
Allow: /
User-agent: Claude-User
Allow: /
User-agent: Perplexity-User
Allow: /
User-agent: OAI-SearchBot
Allow: /
```
**Training crawlers** (`GPTBot`, `CCBot`, `Google-Extended`, `Applebot-Extended`) are the
owner's business decision. Ask. Do not silently open them.

Always add: `Sitemap: https://<host>/sitemap.xml`

| Stack | File |
|---|---|
| Next.js | `app/robots.ts` or `public/robots.txt` |
| Astro / static | `public/robots.txt` |
| WordPress | root `robots.txt`, or the SEO plugin's editor. Check Yoast/RankMath first, they override. |
| Hono / Express | a route returning `text/plain` |

### `agent_ua_access` — 7 pts — **INFRA**
The site 403s a non-browser request. Not fixable in code.
Hand off `~/sneeze-agent-ready/docs/cloudflare-agent-access.md`. Bot Fight Mode first: it
cannot be scoped and is the usual culprit.

### `https_clean` — 4 pts — INFRA
No HTTPS, or a hostname that does not match. DNS and certificate work.

### `sitemap` — 4 pts — AGENT
A sitemap index counts. Reference it from robots.txt for the last point.
Next.js `app/sitemap.ts`, Astro `@astrojs/sitemap`, WordPress via the SEO plugin.

---

## COMPREHEND (25 pts)

### `structured_data` — 10 pts — AGENT
Biggest single Comprehend win. Use `templates/local-business.jsonld`.

Scored in five independent parts, so partial credit is real:
identity (4) + what you sell (3) + FAQ (1) + phone or address (1) + opening hours (1).

**Every value must be true.** Pull hours from the contact page, prices from the pricing page,
phone from the footer. If a value is not on the site, ask. Do not infer it.

One `<script type="application/ld+json">` in the `<head>` of the layout. One block per
location for multi-location brands.

### `no_js_content` — 7 pts — AGENT or **DECISION**
Fewer than 400 words of visible text in the raw HTML.

- Marketing pages already SSR (Astro, Next app router server components, WordPress): usually
  just thin content. Write more real copy. **AGENT.**
- Client-rendered SPA that returns an empty `<div id="root">`: needs SSR or prerendering.
  That is an architecture change. **DECISION.** Estimate it, do not start it uninvited.
- Interim, if SSR is refused: put the core facts, services, prices and contact details into
  the initial HTML even if the app hydrates over them.

**Never** stuff hidden text to pass this.

### `llms_txt` — 4 pts — AGENT
Cheapest points on the board. `templates/llms.txt` at `/llms.txt`.
Must serve as `text/plain` and must not be the SPA's 404 page. Verify with curl.
Needs links or markdown to score the full 4.

### `page_basics` — 4 pts — AGENT
One point each: title over 10 chars, meta description over 40, exactly one `h1`, `lang` on
`<html>`. Two `h1`s is the most common failure. Check the header component.

---

## ACT (35 pts) — the pillar that matters

### `webmcp_tools` — 14 pts — AGENT, with a hard precondition
See `references/webmcp.md`. **Build the endpoints first.** Registering a tool with nothing
behind it is the one thing this skill must never do.

Scoring: signature in the bundle scores partial, tools actually enumerated in a browser
scores full. So shipping it real beats shipping it decorative.

### `mcp_discovery` — 5 pts — AGENT
`/.well-known/mcp.json`, `application/json`, from `templates/mcp.json`.
Only list tools that exist. Static file for most stacks; a route for Next/Hono.

### `machine_fillable_forms` — 9 pts — AGENT
Per visible field: a `name`, a real `<label for="id">`, an `autocomplete` token, and a
specific `type`. Coverage is scored as a ratio, so every field you fix earns.

```html
<label for="email">Work email</label>
<input id="email" name="email" type="email" autocomplete="email" required>
```
Tokens that matter: `name`, `given-name`, `family-name`, `email`, `tel`, `organization`,
`street-address`, `postal-code`, `bday`.

Zero fields found means the form is built in JavaScript and invisible in the raw HTML.
That is the `no_js_content` problem wearing a different hat.

A captcha on the page costs 3 points here as well as in Complete.

Say out loud that this also fixes screen reader accessibility. It is the same work.

### `direct_contact` — 4 pts — AGENT
`<a href="tel:+15555555555">` and `<a href="mailto:...">`. 2 points each.
Numbers set in an image, or assembled by script to dodge scrapers, are invisible to agents
and to screen readers, and stopped fooling scrapers years ago.

### `conversion_path` — 3 pts — AGENT or DECISION
Booking inside a third party iframe (Calendly, Mindbody, Zenoti, Boulevard, Jobber,
HighLevel) scores 0. An agent reaches the page and cannot see into the frame.

The fix is not to remove the embed. Keep it for humans, and expose the same action as a
WebMCP tool on your own page, backed by the vendor's API. That is the same work as
`webmcp_tools`, so do them together.

---

## COMPLETE (15 pts)

Skipped entirely when there is no form, no tel, no mailto and no booking on the page. The
pillar drops out of the denominator rather than scoring 0.

### `no_captcha_wall` — 6 pts — AGENT, carefully
A captcha on a **marketing or booking** page: give agents a path that does not need solving
it, via a WebMCP tool posting to your own validated endpoint.

A captcha on a **login, checkout, or password reset**: leave it. Do not take these 6 points.
Record the decision in the report.

### `no_login_wall` — 3 pts — DECISION
Prices and services behind auth. Gate the transaction, not the shop window. Owner's call.
A page that really is account-only passes once the site publishes OAuth discovery (see
`delegated_auth`), because then the sign in is a door an agent can be let through.

### `stable_to_repeat` — 3 pts — INFRA
Rate limited on a second request. Raise the threshold for verified agent traffic.

### `iframe_dependency` — 3 pts — AGENT or DECISION
Three or more iframes, or two or more third party services. Reduce on conversion pages, or
mirror what they do on your own domain.

---

## DELEGATE (preview, not in the score) — personal agents

Shown in every report, excluded from the total and from the fix plan until the scanner sets
`DELEGATE_SCORED=1`. Work it after the scored pillars, and say it is preview work when you
report the ceiling. It asks what a customer's OWN agent can do after sign in.

### `agent_identity` — 4 pts — INFRA
The scanner re-requests the homepage signed with Web Bot Auth (RFC 9421 HTTP Message
Signatures, Ed25519, `Signature-Agent` header, key directory at
`/.well-known/http-message-signatures-directory`). Refused = the WAF blocks agents whether or
not they prove who they are. Fix in the WAF: verify signed agents and let them through
(Cloudflare: Bots, signed agents). Owner or whoever runs the WAF must action.

### `delegated_auth` — 4 pts — AGENT where an auth server exists, else DECISION
Only scored where customers have accounts. Needs, at the site root:
- `/.well-known/oauth-protected-resource` (RFC 9728): `resource`, `authorization_servers`
- `/.well-known/oauth-authorization-server` (RFC 8414) with `authorization_endpoint`,
  `token_endpoint`, `code_challenge_methods_supported: ["S256"]`, and `registration_endpoint`
  (or `client_id_metadata_document_supported: true`)
This is the MCP connector auth flow, so if the site already runs an MCP server with OAuth
the documents usually exist on that server and only need serving from the root. **Never
publish discovery for an auth server that does not exist.** No auth server = DECISION.

### `agent_login` — 4 pts — AGENT (own form) or INFRA (hosted widget)
On the sign in page: identifier input `autocomplete="username"` (or `"email"`; add
` webauthn` when passkeys are on), password `autocomplete="current-password"`, a passkey or
one-time code option, no captcha. Clerk, Auth0 and similar widgets: set it in the provider's
config or appearance options, do not patch their DOM. Passkeys are a provider setting.

### `account_actions` — 3 pts — AGENT, with the same precondition as `webmcp_tools`
Tools that act on the signed in account: list my bookings, reschedule, cancel, order status,
update contact details. Register as WebMCP tools (they run in the customer's own session) and
list them in `/.well-known/mcp.json`. **Each tool must call a real endpoint that already
enforces the session.** Anything that costs money or ends a membership must ask the person
to confirm. A self-service link ("Manage my booking") earns 1 pt on its own.

### `accessible_controls` — 3 pts — AGENT
Every `<a href>` and `<button>` needs an accessible name: text, `aria-label`, or an `<img alt>`
or `<svg><title>` inside. Replace `<div onclick>` with `<button>`. Icon buttons and social
links are the usual offenders. Also an accessibility fix, say so.

### `agent_policy` — 2 pts — AGENT for llms.txt, DECISION for the signal values
1 pt: a `## Agents` section in llms.txt (see `templates/llms.txt`) saying what an agent may
do for a customer and what needs confirming. 1 pt: a `Content-Signal:` line in robots.txt,
e.g. `Content-Signal: search=yes, ai-input=yes, ai-train=no`. The values are the owner's
call, same as training crawlers. Ask.

---

## The order to work in

Cheapest and most certain first, so the score moves early and the loop stays tight:

1. `llms_txt` (4) — minutes
2. `page_basics` (4) — minutes
3. `sitemap` (4) — minutes
4. `direct_contact` (4) — minutes
5. `ai_crawler_policy` (10) — minutes, ask about training crawlers
6. `structured_data` (10) — an hour, mostly gathering true values
7. `machine_fillable_forms` (9) — an afternoon
8. `mcp_discovery` (5) — after the tools exist
9. `webmcp_tools` (14) — the real build
10. Hand off INFRA. Present DECISIONs with estimates.
