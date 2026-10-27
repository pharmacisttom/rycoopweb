import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import reference from '../data/legacyReference.json';

const PublicContentContext = createContext({ catalog: reference, status: 'reference', reload: async () => {} });
export function PublicContentProvider({ children }) {
  const [catalog, setCatalog] = useState(reference);
  const [status, setStatus] = useState('loading');
  const reload = useCallback(async signal => {
    try {
      const response = await fetch('/api/public/content', { credentials: 'same-origin', cache: 'no-cache', signal, headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!response.ok || !result.success || !result.data?.coopInfo || !Array.isArray(result.data?.boardMembers)) throw new Error('CONTENT_UNAVAILABLE');
      setCatalog(result.data); setStatus('published');
    } catch (error) { if (error.name !== 'AbortError') setStatus('unavailable'); }
  }, []);
  useEffect(() => { const controller = new AbortController(); reload(controller.signal); return () => controller.abort(); }, [reload]);
  useEffect(() => {
    const onFocus = () => reload();
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [reload]);
  return <PublicContentContext.Provider value={{ catalog, status, reload }}>{children}</PublicContentContext.Provider>;
}
export const usePublicContent = () => useContext(PublicContentContext);
export function usePublicInformation() {
  const { catalog } = usePublicContent();
  const rates = { ...catalog.interestRates };
  for (const [section,key] of [['deposits','depositSource'],['loan_rates','loanSource']]) {
    const published=catalog.publication?.[section];
    if(published) rates[key]={url:published.sourceUrl,checkedOn:published.effectiveOn,note:'ข้อมูลที่สหกรณ์เผยแพร่ มีผลตั้งแต่ '+published.effectiveOn,published:true};
  }
  const sources={...catalog.sources};
  for(const [section,key] of [['boards','board'],['staff','staff']]) {
    const published=catalog.publication?.[section];
    if(published)sources[key]={...sources[key],url:published.sourceUrl,checkedOn:published.effectiveOn};
  }
  return { COOP_INFO:catalog.coopInfo,BOARD_MEMBERS:catalog.boardMembers,STAFF_MEMBERS:catalog.staffMembers,INTEREST_RATES:rates,LOAN_PRODUCTS:catalog.loanProducts,legacyReference:{...catalog,sources} };
}
