#!/usr/bin/env node
/**
 * Package the WordPress drop-in as a ZIP the site owner uploads once.
 * Usage: node make-wp-plugin.mjs [outDir]
 */
import { mkdtempSync, mkdirSync, copyFileSync, existsSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execFileSync } from 'node:child_process'

const here = dirname(fileURLToPath(import.meta.url))
const src = resolve(here, '..', 'templates', 'wordpress')
const outDir = resolve(process.argv[2] || process.cwd())
if (!existsSync(src)) { console.error(`missing templates at ${src}`); process.exit(1) }

const stage = mkdtempSync(join(tmpdir(), 'agentready-'))
const dir = join(stage, 'agent-ready')
mkdirSync(dir)
for (const f of ['agent-ready.php', 'webmcp.js']) copyFileSync(join(src, f), join(dir, f))

const zip = join(outDir, 'agent-ready.zip')
execFileSync('zip', ['-r', '-q', zip, 'agent-ready'], { cwd: stage })
console.log(`\nBuilt ${zip}\n`)
console.log('Install: WordPress admin > Plugins > Add New > Upload Plugin > choose this zip > Activate.')
console.log('Then:    Settings > Agent Ready, fill in the real business facts, Save.')
console.log('Then:    Settings > Permalinks > Save once, to flush rewrite rules.')
console.log('Verify:  /llms.txt and /.well-known/mcp.json should both serve.\n')
