// Build: Claude Design .dc.html source -> WordPress-safe body partial + base CSS
import { readFileSync, writeFileSync } from 'node:fs';

const SRC = 'C:/wamp64/www/automatiza-tech/_design-ref/home-v2-source.html';
const OUT_DIR = 'C:/wamp64/www/automatiza-tech/assets/home-premium';
let src = readFileSync(SRC, 'utf8');

// --- extract <style> from helmet ---
const styleM = src.match(/<style>([\s\S]*?)<\/style>/);
const baseCss = styleM ? styleM[1].trim() : '';

// --- slice body = after </helmet> .. before </x-dc> ---
const a = src.indexOf('</helmet>');
const b = src.lastIndexOf('</x-dc>');
let body = src.slice(a + '</helmet>'.length, b).trim();

// --- transforms ---
const before = body;
body = body
  .replace(/onClick="\{\{\s*(\w+)\s*\}\}"/g, 'data-act="$1"')
  .replace(/onSubmit="\{\{\s*(\w+)\s*\}\}"/g, 'data-act-submit="$1"')
  .replace(/\sstyle-hover=/g, ' data-hover=')
  .replace(/<sc-if\s+value="\{\{\s*(\w+)\s*\}\}"(?:\s+hint-placeholder-val="\{\{[^}]*\}\}")?\s*>/g, '<sc-if data-when="$1">')
  .replace(/data-motion="\{\{\s*motion\s*\}\}"/g, 'data-motion="on"')
  .replace(/\{\{\s*flowCols\s*\}\}/g, 'var(--flow-cols)')
  .replace(/\{\{\s*flowConnDisplay\s*\}\}/g, 'var(--flow-conn)');

// --- image-slot -> styled placeholder ---
body = body.replace(/<image-slot\b[^>]*?placeholder="([^"]*)"[^>]*><\/image-slot>/g,
  (m, label) =>
    `<div class="at-slot" role="img" aria-label="${label}">` +
    `<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>` +
    `<span>${label}</span></div>`);

// --- sanity: no template tokens or image-slot left ---
const leftover = body.match(/\{\{[^}]*\}\}|<image-slot/g);
if (leftover) { console.error('LEFTOVER TOKENS:', [...new Set(leftover)]); }

writeFileSync(OUT_DIR + '/body.html', body, 'utf8');
writeFileSync(OUT_DIR + '/_base.css', baseCss, 'utf8');
console.log('body.html bytes=', body.length, ' base.css bytes=', baseCss.length, ' transformed=', before !== body);
