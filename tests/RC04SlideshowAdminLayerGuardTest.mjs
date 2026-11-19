import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const read = path => readFileSync(new URL('../'+path, import.meta.url), 'utf8');
const layout = read('app/Services/DynamicLayoutService.php');
const editor = read('src/components/admin/DynamicLayoutEditor.jsx');
const publicSections = read('src/components/layout/PublishedDynamicSections.jsx');
const slider = read('src/components/layout/DynamicSlideshow.jsx');
const adminShell = read('src/components/admin/AdminControlCenterShell.jsx');
const style = read('src/components/admin/AdminLayerSystemRC04.css');

assert.match(layout, /'slideshow'/);
assert.match(layout, /count\(\$slides\) > 12/);
assert.match(layout, /'slide_interval'/);
assert.match(layout, /str_contains\(\$image, '\.\.'/);
assert.match(layout, /AuditService::log\('dynamic_layout', 'publish'/);
assert.match(editor, /value="slideshow"/);
assert.match(editor, /updateSlides/);
assert.match(editor, /slide_interval/);
assert.match(editor, /บันทึกร่าง/);
assert.match(publicSections, /<DynamicSlideshow/);
assert.match(slider, /aria-modal="true"/);
assert.match(slider, /prefers-reduced-motion/);
assert.match(slider, /setExpanded\(false\)/);
assert.match(adminShell, /AdminLayerSystemRC04\.css/);
assert.match(style, /\.admin-dialog::backdrop/);
assert.match(style, /\.acc-shell \.dynamic-slide-edit-card/);
console.log('RC04_SLIDESHOW_ADMIN_LAYER_GUARDS=PASS');
