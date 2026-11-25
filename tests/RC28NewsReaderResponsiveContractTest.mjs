import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const news=readFileSync('src/pages/NewsPage.jsx','utf8');
const gallery=readFileSync('src/components/common/ActivitySlideshow.jsx','utf8');
const css=readFileSync('src/components/common/ActivitySlideshow.css','utf8');

assert.match(news,/maxWidth: '1160px'/,'news detail must not be constrained to the old 680px');
assert.match(news,/maxHeight: '95dvh'/,'news detail must remain inside mobile viewport');
assert.match(news,/overflowY: 'auto'/,'news detail must scroll to the end');
assert.match(news,/เปิดภาพเต็มจอ \/ ซูมอ่าน/,'news detail should explain how to read documents');
assert.match(gallery,/openReader/,'image reader must be available');
assert.match(gallery,/showModal\(\)/,'reader must open accessibly');
assert.match(gallery,/setZoom/,'reader must support zoom');
assert.match(gallery,/Math\.min\(4/,'reader must cap zoom at 400%');
assert.match(gallery,/activity-reader-viewport/,'image viewport scrolls at higher zoom');
assert.match(gallery,/activity-reader-toolbar/,'zoom and close controls visible');
assert.match(gallery,/activity-close/,'close button remains available');
assert.match(gallery,/onCancel=\{event => \{ event\.stopPropagation\(\); \}\}/,'Escape closes only reader dialog');
assert.match(css,/\.activity-reader-viewport\{[^}]*overflow:auto/,'image viewer must scroll horizontal and vertical overflow');
assert.match(css,/@media\(max-width:640px\)/,'mobile reader rules must exist');
assert.match(css,/\.activity-lightbox\[open\]\{display:flex\}/,'lightbox must not be forced visible when closed');
assert.match(css,/min-height:44px/,'touch controls are at least 44px');
console.log('RC28_NEWS_READER_RESPONSIVE_CONTRACT=PASS');
