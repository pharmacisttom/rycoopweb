#!/usr/bin/env node
/**
 * Build a selector-scoped AdminLTE v4 stylesheet for the React Admin shell.
 * Only compiled vendor CSS is used; AdminLTE's DOM-manipulating JavaScript
 * must NOT be loaded because React owns navigation, dialogs and dropdowns.
 *
 * npm ci invokes this via postinstall, before Vite or Playwright run.
 * Generated CSS stays in node_modules beside the official package assets so
 * relative url(...) references remain valid. No global Bootstrap reboot.
 */
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';
import selectorParser from 'postcss-selector-parser';

const cssDir = path.resolve('node_modules/admin-lte/dist/css');
const input = path.join(cssDir, 'adminlte.css');
const output = path.join(cssDir, 'adminlte.rycoop.css');
if (!fs.existsSync(input)) {
  throw new Error('AdminLTE CSS is missing; run npm ci with dev dependencies');
}
const source = fs.readFileSync(input, 'utf8');
const root = postcss.parse(source, {from:input});
let scoped = 0;
root.walkRules(rule => {
  let parent = rule.parent;
  while (parent) {
    if (parent.type === 'atrule' && /keyframes$/i.test(parent.name)) return;
    parent = parent.parent;
  }
  if (!rule.selector) return;
  const transform = selectorParser(selectors => {
    selectors.each(selector => {
      // Remove document-root qualifiers which cannot be descendants of
      // the staff-only .acc-shell container.
      while (selector.nodes.length) {
        const first = selector.nodes[0];
        const isRoot = (first.type === 'tag' && /^(html|body)$/i.test(first.value))
          || (first.type === 'pseudo' && first.value === ':root');
        if (!isRoot) break;
        first.remove();
        if (selector.nodes[0]?.type === 'combinator' && !selector.nodes[0].value.trim()) {
          selector.nodes[0].remove();
        }
      }
      if (selector.nodes.length) selector.prepend(selectorParser.combinator({value:' '}));
      selector.prepend(selectorParser.className({value:'acc-shell'}));
      scoped++;
    });
  });
  rule.selector = transform.processSync(rule.selector);
});
const header = `/* AdminLTE v4.10.2 © ColorlibHQ, MIT License.
 * Derived scoped stylesheet for RYCOOPWEB admin only.
 * Original project: https://github.com/ColorlibHQ/AdminLTE
 * Retain upstream copyright and license notices.
 */\n`;
fs.writeFileSync(output, header+root.toString());
if (scoped < 1000) throw new Error(`Unexpected AdminLTE CSS rule count: ${scoped}`);
console.log(`PASS: scoped ${scoped} AdminLTE CSS selectors into ${output}`);
