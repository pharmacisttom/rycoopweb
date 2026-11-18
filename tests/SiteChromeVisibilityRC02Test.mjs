#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = p=>readFileSync(p,'utf8');
const php=read('app/Services/SiteChromeVisibilityService.php');
const controller=read('app/Controllers/Admin/SiteChromeVisibilityController.php');
const routes=read('config/routes.php');
const navbar=read('src/components/layout/Navbar.jsx');
const footer=read('src/components/layout/Footer.jsx');
const editor=read('src/components/admin/SiteChromeVisibilityEditor.jsx');
const page=read('src/pages/AdminHomepageLayoutPage.jsx');
const hook=read('src/hooks/useSiteChromeVisibility.js');
for(const key of ['nav_menu','footer_brand','footer_finance','footer_welfare','footer_contact','footer_counter']){
 assert(php.includes("'"+key+"'"),'missing setting '+key);
 assert(hook.includes(key),'missing safe default '+key);
}
assert(php.includes('AuditService::log') && php.includes('hash_equals'),'missing audit/concurrency');
assert(controller.includes('requireFreshStepUpMfa'),'missing MFA');
assert(routes.includes("'/api/public/site-chrome'") && routes.includes("'/api/admin/site-chrome'"),'routes missing');
assert(routes.includes('CsrfMiddleware::class'),'CSRF middleware missing');
assert(navbar.includes('showNavMenu && <nav'),'public nav not conditional');
assert(navbar.includes('showNavMenu && mobileMenuOpen'),'mobile menu not conditional');
assert(footer.includes('visibility.footer_brand &&') && footer.includes('visibility.footer_contact &&'),'footer flags missing');
assert(footer.includes('<LockKeyhole') && footer.includes('to="/privacy/policy"'),'legal/admin links must remain visible');
assert(editor.includes('site-chrome-totp') && editor.includes("save('draft')") && editor.includes("save('publish')"));
assert(page.includes('<SiteChromeVisibilityEditor />'),'editor not mounted');
console.log('PASS: public site menu/footer conditional visibility with mandatory access and MFA contracts');
