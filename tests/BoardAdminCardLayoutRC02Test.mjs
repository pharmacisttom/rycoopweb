#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const page = readFileSync('src/pages/PublicContentAdminPage.jsx','utf8');
const css = readFileSync('src/pages/PublicContentAdminPage.css','utf8');
for (const part of ['public-content-board-tools-grid','public-content-board-action-buttons','public-content-board-media','public-content-board-photo-preview','public-content-board-photo']) {
  assert(page.includes(part), 'missing board UI: '+part);
  assert(css.includes(part), 'missing board CSS: '+part);
}
assert(page.includes('disabled={busy||mediaBusy} onClick={copyBoardToTerm}'));
assert(page.includes('disabled={busy||mediaBusy} onClick={startBoardReplacement}'));
assert(css.includes('@media(max-width:860px)') && css.includes('@media(max-width:520px)'));
assert(!/\n\s*(?:body|html)\s*\{/.test(css));
console.log('PASS: responsive board action and upload layout contracts');
