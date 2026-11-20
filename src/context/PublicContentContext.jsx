import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import reference from '../data/legacyReference.json';

// Fail closed when the public CMS is unavailable: never present bundled reference
// people, financial products, rates or contact details as current live content.
const emptyCatalog = {
  ...reference,
  coopInfo: Object.fromEntries(Object.keys(reference.coopInfo).map(key => [key, ''])),
  boardMembers: [], boardGroups: [], staffMembers: [], advisors: [], auditors: [], loanProducts: [],
  interestRates: {...reference.interestRates, deposits: [], loans: [], depositSource: null, loanSource: null},
  sources: Object.fromEntries(Object.keys(reference.sources).map(key => [key, {url: '', checkedOn: ''}]))
};
const PublicContentContext = createContext({ catalog: emptyCatalog, status: 'unavailable', reload: async () => {} });
export function PublicContentProvider({ children }) {
  const [catalog, setCatalog] = useState(emptyCatalog);
  const [status, setStatus] = useState('loading');
  const reload = useCallback(async signal => {
    try {
      const response = await fetch('/api/public/content', { credentials: 'same-origin', cache: 'no-cache', signal, headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!response.ok || !result.success || !result.data?.coopInfo || !Array.isArray(result.data?.boardMembers)) throw new Error('CONTENT_UNAVAILABLE');
      setCatalog(result.data); setStatus('published');
    } catch (error) { if (error.name !== 'AbortError') { setCatalog(emptyCatalog); setStatus('unavailable'); } }
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
  // Published API may contain partial/legacy fields. Always normalize lists before
  // React renders a route; never fill absent financial data with demo values.
  const rawRates = catalog?.interestRates && typeof catalog.interestRates === 'object' ? catalog.interestRates : {};
  const rates = {
    ...rawRates,
    deposits: Array.isArray(rawRates.deposits) ? rawRates.deposits.filter(item => item && typeof item === 'object') : [],
    loans: Array.isArray(rawRates.loans) ? rawRates.loans.filter(item => item && typeof item === 'object') : [],
    depositSource: rawRates.depositSource && typeof rawRates.depositSource === 'object' ? rawRates.depositSource : null,
    loanSource: rawRates.loanSource && typeof rawRates.loanSource === 'object' ? rawRates.loanSource : null,
  };
  for (const [section,key] of [['deposits','depositSource'],['loan_rates','loanSource']]) {
    const published=catalog.publication?.[section];
    if(published) rates[key]={url:published.sourceUrl,checkedOn:published.effectiveOn,note:'ข้อมูลที่สหกรณ์เผยแพร่ มีผลตั้งแต่ '+published.effectiveOn,published:true};
  }
  const sources={...(catalog?.sources || {})};
  for(const [section,key] of [['boards','board'],['staff','staff']]) {
    const published=catalog.publication?.[section];
    if(published)sources[key]={...sources[key],url:published.sourceUrl,checkedOn:published.effectiveOn};
  }
  return { COOP_INFO:catalog?.coopInfo || {},BOARD_MEMBERS:Array.isArray(catalog?.boardMembers) ? catalog.boardMembers.filter(Boolean) : [],STAFF_MEMBERS:Array.isArray(catalog?.staffMembers) ? catalog.staffMembers.filter(Boolean) : [],INTEREST_RATES:rates,LOAN_PRODUCTS:Array.isArray(catalog?.loanProducts) ? catalog.loanProducts.filter(item => item && typeof item === 'object') : [],legacyReference:{...catalog,sources} };
}
