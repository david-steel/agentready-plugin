# Agent Ready

Search asked whether Google understood your page. AI search asked whether it would cite you.
The next question is the one that moves money: **when an agent arrives to book, quote or buy
on a customer's behalf, can it finish the job?**

This plugin adds `/agentready` to Claude Code. It scans a site against the
[Agent Ready audit](https://agentready.sneeze.it), maps every finding to a concrete edit,
applies what is safe, and hands off what needs infrastructure or a business decision.

## Install

```
/plugin marketplace add david-steel/agentready-plugin
/plugin install agentready@agentready
```

Then:

```
/agentready example.com                  scan and plan, change nothing
/agentready example.com --fix            apply what is safe, rescan, report the delta
/agentready example.com --repo ~/site    skip repo discovery
/agentready example.com --wp             WordPress: generate a drop-in plugin
```

No API key. The audit endpoint is public.

## What it measures

Four pillars, 17 checks, 100 points, in the order an agent hits them.

| Pillar | Weight | Question |
|---|---|---|
| Reach | 25 | Can the agent get in, or does something turn it away at the door |
| Comprehend | 25 | Can it work out what you sell and what it costs |
| **Act** | **35** | **Can it actually do the thing. The WebMCP layer.** |
| Complete | 15 | Does it reach the end, or hit a captcha, a login or a rate limit |

Act carries the most because it is the only pillar that decides whether a transaction can
complete, and the one almost nobody has done. Real scores measured at build time:

| Site | Total | Act |
|---|---|---|
| stripe.com | 64 | 9 |
| a multi-location gym chain | 59 | 26 |
| a med spa | 53 | 14 |

Stripe scores 9 out of 100 on Act. That gap is the whole point.

## It will not fake your score

100 is usually not reachable by an agent alone, and the plugin says so up front rather than
inventing points. Every finding is classed:

- **AGENT** — it makes the edit
- **INFRA** — a human with WAF or DNS access, it writes the handoff
- **DECISION** — the business owner, it estimates and asks

Six things it will never do, because each is a way to score higher while making the site worse:

1. Invent a business fact. Structured data asserting a phone number that does not ring is a
   machine readable lie that agents will repeat.
2. Register a WebMCP tool with no working endpoint behind it. A tool returning plausible
   availability sends a customer to an appointment nobody is expecting.
3. Weaken real security for points. Captcha on a login stays.
4. Fake server rendered content with hidden text.
5. Open robots.txt to training crawlers on the owner's behalf. Live agents are customers and
   are allowed. Training crawlers are the owner's decision.
6. Edit a client's production site directly.

## WordPress

Most local businesses are on WordPress, and usually there is no repo to edit. Run with
`--wp` and it generates a single drop-in plugin carrying every safe fix: `/llms.txt`,
`/.well-known/mcp.json`, JSON-LD from facts entered in a settings screen, WebMCP
registration, and robots.txt rules. One upload, no FTP, no theme editing, and it survives
theme updates.

Facts come from a settings form rather than from the model, which is how rule 1 above is
enforced structurally instead of by good intentions.

## Credit

The idea comes from Greg Isenberg and Vince Anger's WebMCP episode. WebMCP shipped February
2026 as a joint Google and Microsoft experiment in Chrome.

MIT licensed. Built by [Sneeze It](https://sneeze.it).
