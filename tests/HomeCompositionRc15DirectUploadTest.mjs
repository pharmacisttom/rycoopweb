import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const editor=readFileSync('src/components/admin/HomeCompositionEditor.jsx','utf8');
const media=readFileSync('src/services/mediaLibraryApi.js','utf8');
const endpoint=readFileSync('app/Controllers/Api/MediaLibraryController.php','utf8');
const release=readFileSync('scripts/deploy-prod-home-direct-upload-rc15.sh','utf8');

const requires=[
  ['file input restricted to raster images',editor,'accept="image/jpeg,image/png,image/webp"'],
  ['upload per slot',editor,'uploadImage(file,slot,key,slideId=null)'],
  ['existing CSRF-protected media client',editor,"mediaLibraryApi.upload(file,{folder:'highlights'"],
  ['only accepted public media URL',editor,"if(!allowedImage(url)||!url)"],
  ['test public file is reachable',editor,"(check.headers.get('content-type')||'').startsWith('image/')"],
  ['separate desktop and mobile fields',editor,'label="ภาพสไลด์ Mobile"'],
  ['preview after upload',editor,'<img src={value}'],
  ['requires MFA to publish',editor,"submit('publish')"],
  ['image size limit',editor,'MAX_UPLOAD_BYTES'],
  ['media endpoint uses FormData',media,'new FormData()'],
  ['CSRF-protected media upload route',media,"memberAdminApi('/api/admin/media', { data })"],
  ['media checks permission',endpoint,"self::authorize('create')"],
  ['media strips metadata through image conversion',endpoint,'imagewebp($processed'],
  ['media logs upload',endpoint,"AuditService::log('media', 'create'"],
  ['rollout is editor-only',release,'RC15_DIRECT_UPLOAD_PRODUCTION_DEPLOY=PASS'],
  ['rollout rejects unexpected active release',release,'test "$(grep -Fc "$OLD/public" "$CONFIG")" -eq 1']
];
for(const [name,source,needle] of requires){
  assert(source.includes(needle),name);
  console.log('PASS',name);
}
console.log('RC15_DIRECT_IMAGE_UPLOAD_CONTRACT=PASS');
