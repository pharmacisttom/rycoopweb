import {test} from 'node:test';import assert from 'node:assert/strict';
import {assetUrl,appUrl,imageFallback,normalizeBase,protectedPreviewPath,logicalPath} from '../src/utils/environment.js';
for(const base of ['/','/test/','/nested/preview/'])test('assets and redirects '+base,()=>{
 for(const value of ['/assets/img/logo.webp','assets/img/logo.webp','./assets/img/logo.webp'])assert.equal(assetUrl(value,base),base+'assets/img/logo.webp');
 assert.equal(appUrl('/about',base),base+'about');assert.equal(assetUrl(base+'img/logo.webp',base),base+'img/logo.webp');
 assert.equal(logicalPath(base+'member/profile',base),'/member/profile');assert.equal(assetUrl('',base),'');
});
test('reject unsafe paths',()=>{for(const value of ['//evil/path','javascript:alert(1)','../secret'])assert.equal(assetUrl(value),'');assert.throws(()=>normalizeBase('/test/../'));});
test('fallback does not retry indefinitely',()=>{const target={dataset:{},style:{}};imageFallback({currentTarget:target});const first=target.src;imageFallback({currentTarget:target});assert.equal(target.src,first);assert.equal(target.style.visibility,'hidden');});
test('protected preview routes',()=>{for(const p of ['/login','/admin/login','/member/dividends','/staff/dashboard'])assert.equal(protectedPreviewPath(p),true);assert.equal(protectedPreviewPath('/news'),false);});
