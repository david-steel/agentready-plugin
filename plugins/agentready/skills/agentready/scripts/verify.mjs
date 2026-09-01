#!/usr/bin/env node
/** Rescan and print the delta against .agentready-baseline.json. */
import { readFileSync, existsSync } from 'node:fs'
const API = process.env.AGENTREADY_API || 'https://agentready.sneeze.it'
const url = process.argv[2]
if (!url) { console.error('usage: node verify.mjs <url>'); process.exit(1) }

const res = await fetch(`${API}/api/scan?url=${encodeURIComponent(url)}`, { signal: AbortSignal.timeout(90000) })
const r = await res.json()
if (r.error) { console.error(`rescan failed: ${r.error}`); process.exit(1) }

if (!existsSync('.agentready-baseline.json')) {
  console.log(`${r.host}: ${r.score}/100 (no baseline recorded, nothing to compare)`)
  process.exit(0)
}
const b = JSON.parse(readFileSync('.agentready-baseline.json', 'utf8'))
const d = r.score - b.score
const sign = d > 0 ? '+' : ''
console.log(`\n${r.host}  ${b.score} -> ${r.score}  (${sign}${d})  grade ${r.grade}\n`)
for (const p of r.pillars) {
  const was = b.pillars.find(x => x.key === p.key)?.score ?? 0
  const pd = p.score - was
  const mark = pd > 0 ? `+${pd}` : pd < 0 ? `${pd}` : '  0'
  console.log(`  ${p.label.padEnd(11)} ${String(was).padStart(3)} -> ${String(p.score).padStart(3)}  ${mark}${p.applicable ? '' : '  (not scored)'}`)
}
const still = r.pillars.flatMap(p => p.findings).filter(f => f.status === 'fail')
console.log(`\n  Still failing: ${still.length ? still.map(f => f.id).join(', ') : 'nothing'}\n`)
if (d < 0) { console.error('REGRESSION: the score went down. Investigate before continuing.'); process.exit(1) }
