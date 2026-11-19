import React, { useEffect, useMemo, useState } from 'react';
import { Download, FileText, Search } from 'lucide-react';
import { fetchDocuments } from '../services/api';

const formatBytes = (bytes) => {
  const size = Number(bytes);
  if (!Number.isFinite(size) || size < 0) return '—';
  return size < 1024 * 1024 ? `${(size / 1024).toFixed(1)} KB` : `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

export default function DocumentsPage() {
  const [documents, setDocuments] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('ทั้งหมด');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    setLoading(true);
    fetchDocuments()
      .then((result) => {
        if (!active) return;
        if (!result?.success || !Array.isArray(result.data)) {
          throw new Error(result?.message || 'ข้อมูลเอกสารไม่ถูกต้อง');
        }
        setDocuments(result.data);
        setError('');
      })
      .catch((err) => {
        if (active) setError(err.message || 'ไม่สามารถโหลดเอกสารได้');
      })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, []);

  const categories = useMemo(() => ['ทั้งหมด', ...new Set(documents.map((doc) => doc.category).filter(Boolean))], [documents]);
  const filteredDocs = useMemo(() => {
    const term = searchTerm.trim().toLocaleLowerCase('th-TH');
    return documents.filter((doc) => {
      const category = String(doc.category || '');
      const title = String(doc.title || '');
      return (selectedCategory === 'ทั้งหมด' || category === selectedCategory)
        && (!term || title.toLocaleLowerCase('th-TH').includes(term) || category.toLocaleLowerCase('th-TH').includes(term));
    });
  }, [documents, searchTerm, selectedCategory]);

  return (
    <div className="section">
      <div className="container">
        <div className="section-title-wrap">
          <span className="section-badge">ศูนย์ดาวน์โหลด</span>
          <h1 className="section-title">เอกสารและแบบฟอร์ม</h1>
          <p className="section-subtitle">ดาวน์โหลดแบบฟอร์มคำขอ ระเบียบ ข้อบังคับ และรายงานประจำปีของสหกรณ์</p>
          <div className="section-line" />
        </div>
        <div className="surface-card" style={{ padding: '1.5rem', marginBottom: '2rem' }}>
          <label htmlFor="document-search">ค้นหาเอกสาร</label>
          <div style={{ position: 'relative' }}>
            <Search size={18} style={{ position: 'absolute', left: 14, top: 14 }} />
            <input id="document-search" className="form-control" style={{ paddingLeft: '2.75rem' }}
              placeholder="ค้นหาชื่อแบบฟอร์มหรือหมวดหมู่" value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} />
          </div>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 16 }}>
            {categories.map((category) => (
              <button type="button" key={category} className={selectedCategory === category ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm'}
                onClick={() => setSelectedCategory(category)}>{category}</button>
            ))}
          </div>
        </div>
        {loading && <p role="status">กำลังโหลดรายการเอกสาร…</p>}
        {error && <div role="alert" className="surface-card" style={{ padding: 20 }}>
          <p>{error}</p><button type="button" className="btn btn-outline" onClick={() => window.location.reload()}>ลองใหม่</button>
        </div>}
        {!loading && !error && <p role="status">พบเอกสาร {filteredDocs.length} รายการ</p>}
        {!loading && !error && filteredDocs.map((doc) => (
          <article key={doc.id} className="surface-card" style={{ padding: '1.25rem 1.5rem', marginBottom: 12, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
              <FileText size={24} aria-hidden="true" />
              <div><span className="badge badge-primary">{doc.category || 'เอกสาร'}</span>
                <h3 style={{ margin: '8px 0 4px', fontSize: '1rem' }}>{doc.title}</h3>
                <small>{formatBytes(doc.file_size)}</small></div>
            </div>
            <a className="btn btn-outline btn-sm" href={`/api/public/documents/${encodeURIComponent(doc.id)}/download`}
              target="_blank" rel="noopener noreferrer" aria-label={`เปิดเอกสาร ${doc.title}`}>
              <Download size={16} aria-hidden="true" /> เปิด/ดาวน์โหลด
            </a>
          </article>
        ))}
        {!loading && !error && filteredDocs.length === 0 && <p>ไม่พบเอกสารที่ค้นหา</p>}
      </div>
    </div>
  );
}
