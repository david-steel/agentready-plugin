/**
 * WebMCP registration for WordPress, companion to agent-ready.php.
 *
 * Ships with NO tools registered on purpose. A tool must call an endpoint that returns
 * true data; on a WordPress site that means a real REST route backed by the booking or
 * commerce plugin actually in use. Wire the ones below to real routes, uncomment them,
 * and delete the ones you did not wire.
 *
 * A tool that returns plausible-looking availability will have an agent tell a customer
 * to arrive at a time nobody is expecting them.
 */
(function () {
  var mc = typeof navigator !== 'undefined' && navigator.modelContext;
  if (!mc) return;
  var cfg = window.AgentReadyConfig || {};
  var root = (cfg.root || '/wp-json/').replace(/\/$/, '/');

  var tools = [
    // Reading published content is always safe: it is already public.
    {
      name: 'search_site',
      description: 'Search this site for pages and posts matching a query.',
      inputSchema: {
        type: 'object',
        properties: { query: { type: 'string' } },
        required: ['query'],
      },
      execute: function (input) {
        return fetch(root + 'wp/v2/search?per_page=8&search=' + encodeURIComponent(input.query))
          .then(function (r) { return r.ok ? r.json() : { error: 'Search is unavailable.' }; });
      },
    },

    /* ---- Wire these to real routes before uncommenting. ----
    {
      name: 'check_availability',
      description: 'Check open appointment times for a service on a date.',
      inputSchema: {
        type: 'object',
        properties: { service: { type: 'string' }, date: { type: 'string' } },
        required: ['service', 'date'],
      },
      execute: function (input) {
        return fetch(root + 'YOUR-PLUGIN/v1/availability?service='
          + encodeURIComponent(input.service) + '&date=' + encodeURIComponent(input.date),
          { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : { error: 'Availability is unavailable.' }; });
      },
    },
    ---- */
  ];

  try {
    if (typeof mc.provideContext === 'function') mc.provideContext({ tools: tools });
    else if (typeof mc.registerTool === 'function') tools.forEach(function (t) { mc.registerTool(t); });
  } catch (e) {
    if (window.console && console.debug) console.debug('WebMCP registration skipped:', e);
  }
})();
