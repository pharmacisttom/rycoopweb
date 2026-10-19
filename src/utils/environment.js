export function normalizeBase(value='/') {
 if(!/^\/(?:[A-Za-z0-9_-]+\/)*$/.test(value))throw new Error('Base path must be an absolute directory path');
 return value;
}
export const BASE_PATH=normalizeBase(import.meta.env?.BASE_URL || '/');
export const ROUTER_BASE_PATH=normalizeBase(import.meta.env?.VITE_ROUTER_BASE_PATH || BASE_PATH);
export const STATIC_PREVIEW=import.meta.env?.VITE_STATIC_PREVIEW === 'true';
export function logicalPath(value,base=ROUTER_BASE_PATH) {
 const pathname=String(value);
 return base!=='/' && pathname.startsWith(base) ? '/'+pathname.slice(base.length) : pathname;
}
export function appUrl(value='/',base=ROUTER_BASE_PATH) {
 normalizeBase(base);
 const pathname=String(value);
 if(!pathname.startsWith('/') || pathname.startsWith('//') || pathname.includes('\\'))throw new Error('Expected internal absolute path');
 return base==='/' || pathname.startsWith(base) ? pathname : base+pathname.slice(1);
}
export function assetUrl(value,base=BASE_PATH) {
 if(value == null)return '';
 const item=String(value).trim().replace(/^\.\//,'');
 if(!item)return '';
 if(/^https?:\/\//i.test(item)||/^data:image\//.test(item))return item;
 if(/^(?:[a-z]+:|\/\/)/i.test(item)||item.includes('..')||item.includes('\\'))return '';
 return appUrl('/'+item.replace(/^\//,''),base);
}
export function imageFallback(event) {
 const image=event.currentTarget;
 if(image.dataset.fallbackTried==='true'){image.onerror=null;image.style.visibility='hidden';return;}
 image.dataset.fallbackTried='true';image.src=assetUrl('/img/logo.webp');
}
export const protectedPreviewPath=path=>/^\/(?:admin|staff|member|login|profile|settings|portal|e-tracking|tracking|loan-requests)(?:\/|$)/i.test(path);
