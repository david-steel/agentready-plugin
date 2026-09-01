#!/usr/bin/env node
/**
 * Agent Ready scan, digested for a coding agent.
 *
 * Usage:
 *   node scan.mjs <url>            compact digest, exit 0
 *   node scan.mjs <url> --json     raw ScanResult
 *   node scan.mjs <url> --baseline write .agentready-baseline.json for a later delta
 */
const API = process.env.AGENTREADY_API || 'https://agentready.sneeze.it'
const args = process.argv.slice(2)
const url = args.find(a => !a.startsWith('--'))
if (!url) { console.error('usage: node scan.mjs <url> [--json] [--baseline]'); process.exit(1) }

const res = await fetch(`${API}/api/scan?url=${encodeURIComponent(url)}`, {
  signal: AbortSignal.timeout(90000),
}).catch(e => { console.error(`scan failed: ${e.message}`); process.exit(1) })

if (!res.ok) { console.error(`scan failed: HTTP ${res.status}`); process.exit(1) }
const r = await res.json()

if (args.includes('--json')) { console.log(JSON.stringify(r, null, 2)); process.exit(0) }

if (args.includes('--baseline')) {
  const { writeFileSync } = await import('node:fs')
  writeFileSync('.agentready-baseline.json', JSON.stringify(
    { host: r.host, score: r.score, at: r.scannedAt, pillars: r.pillars.map(p => ({ key: p.key, score: p.score })) }, null, 2))
}

if (r.error) { console.log(`${r.host || url}: SCAN FAILED: ${r.error}`); process.exit(0) }

const bar = n => '█'.repeat(Math.round(n / 10)).padEnd(10, '·')
console.log(`\n${r.host}  ${r.score}/100  grade ${r.grade}  (${r.gradeLabel})`)
if (r.blocked) console.log(`  BOT WALL: plain HTTP is refused. Reach cannot score above 0 until infra changes.`)
if (r.measuredBehindWall) console.log(`  Everything past Reach was measured through a real browser.`)
console.log('')
for (const p of r.pillars) {
  const s = p.applicable ? `${String(p.score).padStart(3)}/100 ${bar(p.score)}` : '    not scored'
  console.log(`  ${p.label.padEnd(11)} ${s}   weight ${p.weight}`)
}

// Everything not passing, with the points on the table, so the agent can prioritise.
const open = r.pillars.flatMap(p => p.findings)
  .filter(f => f.status === 'fail' || f.status === 'warn')
  .sort((a, b) => (b.max - b.points) - (a.max - a.points))

console.log(`\nOPEN (${open.length}), by points available:`)
for (const f of open) {
  console.log(`\n  [${f.status.toUpperCase()}] ${f.title}  +${f.max - f.points} pts  (${f.pillar}, ${f.severity})`)
  console.log(`    id:       ${f.id}`)
  console.log(`    observed: ${f.evidence}`)
  if (f.fix) console.log(`    fix:      ${f.fix}`)
}
const headroom = open.reduce((a, f) => a + (f.max - f.points), 0)
console.log(`\n  Total points on the table: ${headroom}\n`)
