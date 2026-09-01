/**
 * WebMCP tool registration.
 *
 * WebMCP is an experimental Chrome feature (Google + Microsoft, shipped February 2026)
 * and the API surface is still moving. This registers defensively against both shapes
 * that have appeared, and does nothing at all where the API is absent, so it is safe to
 * ship on every page today and it starts working as support lands.
 *
 * THE RULE THAT MATTERS: every tool here must call a real endpoint that returns real data.
 * A tool that returns a plausible guess is worse than no tool, because an agent will act
 * on it and tell a customer something untrue. If the endpoint does not exist yet, do not
 * register the tool yet.
 */
(function () {
  var mc = typeof navigator !== 'undefined' && navigator.modelContext
  if (!mc) return

  var tools = [
    {
      name: 'check_availability',
      description: 'Check open appointment times for a service on a given date.',
      inputSchema: {
        type: 'object',
        properties: {
          service: { type: 'string', description: 'Service name, for example "60 minute massage".' },
          date: { type: 'string', description: 'Date in YYYY-MM-DD.' },
        },
        required: ['service', 'date'],
      },
      async execute(input) {
        var r = await fetch('/api/availability?service=' +
          encodeURIComponent(input.service) + '&date=' + encodeURIComponent(input.date),
          { headers: { accept: 'application/json' } })
        if (!r.ok) return { error: 'Availability is unavailable right now.' }
        return await r.json()
      },
    },
    {
      name: 'get_price',
      description: 'Get the current price for a service or product.',
      inputSchema: {
        type: 'object',
        properties: { service: { type: 'string' } },
        required: ['service'],
      },
      async execute(input) {
        var r = await fetch('/api/pricing?service=' + encodeURIComponent(input.service),
          { headers: { accept: 'application/json' } })
        if (!r.ok) return { error: 'Pricing is unavailable right now.' }
        return await r.json()
      },
    },
    {
      name: 'book_appointment',
      description: 'Book an appointment. Confirms with the customer before charging anything.',
      inputSchema: {
        type: 'object',
        properties: {
          slot: { type: 'string', description: 'Slot id returned by check_availability.' },
          name: { type: 'string' },
          email: { type: 'string' },
          phone: { type: 'string' },
        },
        required: ['slot', 'name', 'email'],
      },
      async execute(input) {
        // The browser session carries the login. No API key, no token to hand out.
        var r = await fetch('/api/bookings', {
          method: 'POST',
          headers: { 'content-type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(input),
        })
        if (!r.ok) return { error: 'That slot could not be booked. It may have just been taken.' }
        return await r.json()
      },
    },
  ]

  try {
    if (typeof mc.provideContext === 'function') {
      mc.provideContext({ tools: tools })
    } else if (typeof mc.registerTool === 'function') {
      tools.forEach(function (t) { mc.registerTool(t) })
    }
  } catch (e) {
    // Never let an experimental API break the page for a human visitor.
    if (window.console && console.debug) console.debug('WebMCP registration skipped:', e)
  }
})()
