import {useEffect, useState} from 'react';

export const DEFAULT_SITE_CHROME = Object.freeze({
  nav_menu: true,
  footer_brand: true,
  footer_finance: true,
  footer_welfare: true,
  footer_contact: true,
  footer_counter: true
});

/** Fail open for optional navigation presentation; legal footer links remain outside these flags. */
export default function useSiteChromeVisibility() {
  const [flags,setFlags] = useState(DEFAULT_SITE_CHROME);
  useEffect(() => {
    const controller = new AbortController();
    fetch('/api/public/site-chrome',{signal:controller.signal,cache:'no-cache',credentials:'same-origin'})
      .then(r=>r.ok?r.json():null)
      .then(data=>{
        if (!data?.success || !data.data || typeof data.data !== 'object') return;
        const next = {};
        for (const key of Object.keys(DEFAULT_SITE_CHROME)) {
          if (typeof data.data[key] !== 'boolean') return;
          next[key] = data.data[key];
        }
        setFlags(next);
      }).catch(()=>{});
    return ()=>controller.abort();
  },[]);
  return flags;
}
