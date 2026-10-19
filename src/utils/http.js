import { STATIC_PREVIEW } from './environment';
export function apiUrl(path,base=import.meta.env?.VITE_API_BASE_URL || '') {
 if(!base)return path;
 return base.replace(/\/$/,'')+'/'+String(path).replace(/^\//,'');
}
export function apiRequest(path,options={}) {
 const unsafeHttp=typeof location!=='undefined' && location.protocol==='http:' && !['localhost','127.0.0.1','[::1]'].includes(location.hostname);
 if(STATIC_PREVIEW || unsafeHttp && !String(path).startsWith('/api/public/')) {
  return Promise.resolve(new Response(JSON.stringify({success:false,code:'PREVIEW_DISABLED',message:'โหมดประชาสัมพันธ์ไม่เปิดบริการเข้าสู่ระบบหรือทำรายการ'}),{status:503,headers:{'Content-Type':'application/json'}}));
 }
 return fetch(apiUrl(path),options);
}
