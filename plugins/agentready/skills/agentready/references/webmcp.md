# Implementing WebMCP without lying to anyone

14 points, the heaviest single check, and the only one where a careless implementation does
real harm to a real customer.

## The precondition

**A tool must call an endpoint that returns true data.**

`check_availability` that returns invented slots means an agent tells a customer to arrive
at 2pm on Thursday and nobody is expecting them. That is worse than the site having no
tools, because the customer trusted the answer.

So the order is always:

1. Does a real endpoint exist? If not, either build it or do not register the tool.
2. Does it return live data, not a fixture? Check.
3. Only then register.

Registering two honest tools scores better and behaves better than registering six hopeful ones.

## The API, and its instability

WebMCP shipped February 2026 as a joint Google and Microsoft experiment in Chrome. It is
experimental and the surface is still moving. `templates/webmcp.js` feature-detects both
shapes seen so far (`provideContext` and `registerTool`), and no-ops where the API is absent,
so it is safe to ship on every page today.

Two consequences worth knowing:

- **A static scan can only find the call site in your bundle**, not what actually registered.
  The audit scores a signature lower than a confirmed live enumeration, deliberately.
- **Never let it throw.** The template wraps registration in try/catch. An experimental API
  must never break the page for a human.

## Auth: the good part

Tools are conditional on the browser session. The agent shares the session, so **the login
is the authentication**. No API keys, no tokens, no OAuth dance, nothing to issue or revoke.

Practically:
- Use `credentials: 'same-origin'` on fetches from a tool.
- Expose read-only tools logged out; expose the acting tools only when logged in.
- Your existing CSRF and session checks still apply, unchanged. Do not add a bypass for
  agents. The agent IS the logged-in user's browser.

## Choosing which tools

Two or three, covering what a customer actually comes to the site to do. Not a wrapper
around every API you happen to have.

Good, for a local service business:
- `check_availability(service, date)`
- `get_price(service)`
- `book_appointment(slot, name, email)`

Good, for commerce with fitment:
- `find_compatible(product_id, category)` — the espresso store's real insight
- `add_to_cart(sku, qty)`
- `apply_promotion(code)`

Bad: `get_blog_posts`, `list_all_products` with no filter, anything that just dumps a table.

## Where the iframe problem gets solved

If booking runs in a Calendly or Mindbody iframe, an agent cannot see into it. Do not remove
the embed. Keep it for humans, and expose the same action as a WebMCP tool backed by the
vendor's API. One action, two surfaces: humans use the frame, agents use the tool.

This is how `conversion_path` and `webmcp_tools` get fixed by the same piece of work.

## Verifying

The scanner's live pass needs Chrome with WebMCP enabled:
```bash
AGENTREADY_BROWSER=1 npm run scan -- <url>    # from ~/sneeze-agent-ready
```
Evidence saying "signatures found... registration is unconfirmed" means the static path ran.
Evidence naming your tools means they actually registered. Only the second is done.

## The manifest

`/.well-known/mcp.json` (`templates/mcp.json`) is how an agent discovers you are worth
interacting with before it loads a page. 5 more points, a static file, and it must list only
tools that exist.
