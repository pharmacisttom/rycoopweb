import React, { useEffect, useState } from 'react';
import { memberAdminApi } from '../services/memberAdminApi';
import HomePage from './HomePage';
import { LayoutPreviewContext } from '../components/layout/DynamicLayoutRenderer';
export default function DynamicLayoutPreviewPage() {
  const [draft, setDraft] = useState(null), [error, setError] = useState('');
  useEffect(() => { const abort = new AbortController(); memberAdminApi('/api/admin/v1/layout/preview', { signal: abort.signal }).then(r => setDraft(r.data)).catch(e => { if (e.name !== 'AbortError') setError(e.message); }); return () => abort.abort(); }, []);
  return <>{error && <p role="alert">{error}</p>}{draft && <><p className="container" role="status">ตัวอย่าง Draft v{draft.version} — ยังไม่ใช่ข้อมูลเผยแพร่</p><LayoutPreviewContext.Provider value={draft.state}><HomePage /></LayoutPreviewContext.Provider></>}</>;
}
