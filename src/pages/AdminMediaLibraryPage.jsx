import React, { useCallback, useEffect, useState } from 'react';
import AdminControlCenterShell from '../components/admin/AdminControlCenterShell';
import { mediaLibraryApi } from '../services/mediaLibraryApi';
import './AdminMediaLibraryPage.css';

/**
 * Reusable CMS image catalog. onSelect(image) can also be used by news/board/hero editors.
 * Rendering does not issue write requests; upload/update/delete require an explicit action.
 */
export default function AdminMediaLibraryPage({ onSelect }) {
  const [rows, setRows] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, per_page: 24, total: 0 });
  const [search, setSearch] = useState('');
  const [folder, setFolder] = useState('');
  const [query, setQuery] = useState({ page: 1, search: '', folder: '' });
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [file, setFile] = useState(null);
  const [uploadAlt, setUploadAlt] = useState('');
  const [uploadCaption, setUploadCaption] = useState('');
  const [editing, setEditing] = useState(null);

  const reload = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await mediaLibraryApi.list(query);
      setRows(result.data || []);
      setPagination(result.pagination || { page: 1, per_page: 24, total: 0 });
    } catch (e) {
      setError(e.message || 'ไม่สามารถโหลดคลังรูปภาพ');
    } finally {
      setLoading(false);
    }
  }, [query]);

  useEffect(() => { reload(); }, [reload]);

  const upload = async (e) => {
    e.preventDefault();
    if (!file || busy) return;
    setBusy(true); setError('');
    try {
      await mediaLibraryApi.upload(file, { folder: folder || 'general', altText: uploadAlt, caption: uploadCaption });
      setFile(null); setUploadAlt(''); setUploadCaption('');
      e.currentTarget?.reset?.();
      setQuery({ page: 1, search: '', folder: '' });
      setSearch(''); setFolder('');
    } catch (e) {
      setError(e.message);
    } finally { setBusy(false); }
  };

  const save = async (e) => {
    e.preventDefault();
    if (!editing || busy) return;
    setBusy(true); setError('');
    try {
      await mediaLibraryApi.update(editing.id, {
        altText: editing.alt_text, caption: editing.caption, version: editing.version
      });
      setEditing(null);
      await reload();
    } catch (e) { setError(e.message); }
    finally { setBusy(false); }
  };

  const remove = async (image) => {
    if (busy || !window.confirm('ยืนยันซ่อนรูปนี้จากคลัง? รูปที่ถูกอ้างอิงอาจยังแสดงอยู่ในหน้าเว็บ')) return;
    setBusy(true); setError('');
    try {
      await mediaLibraryApi.remove(image.id, image.version);
      await reload();
    } catch (e) { setError(e.message); }
    finally { setBusy(false); }
  };

  return (
    <AdminControlCenterShell title="คลังรูปภาพ" subtitle="อัปโหลด จัดเก็บ และเลือกรูปสำหรับข่าวสารและเว็บไซต์">
      <section className="ry-media-library" aria-label="คลังรูปภาพสหกรณ์">
        <form className="ry-media-panel ry-media-upload" onSubmit={upload}>
          <h2>เพิ่มรูปภาพ</h2>
          <p className="ry-media-help">JPEG, PNG, WebP สูงสุด 10 MB ระบบแปลงเป็น WebP และลบ EXIF</p>
          <div className="ry-media-fields">
            <label>ไฟล์รูปภาพ<input type="file" accept="image/jpeg,image/png,image/webp" required
              onChange={(e) => setFile(e.target.files?.[0] || null)} /></label>
            <label>ข้อความอธิบายภาพ (Alt Text)<input value={uploadAlt} maxLength={255}
              onChange={(e) => setUploadAlt(e.target.value)} /></label>
            <label>คำบรรยาย<input value={uploadCaption} maxLength={255}
              onChange={(e) => setUploadCaption(e.target.value)} /></label>
            <button className="ry-media-primary" type="submit" disabled={!file || busy}>
              {busy ? 'กำลังบันทึก...' : 'อัปโหลดรูปภาพ'}
            </button>
          </div>
        </form>

        <form className="ry-media-panel ry-media-filter" onSubmit={(e) => {
          e.preventDefault(); setQuery({ page: 1, search, folder });
        }}>
          <label>ค้นหารูปภาพ<input value={search} onChange={(e) => setSearch(e.target.value)}
            placeholder="ชื่อไฟล์ / Alt Text / คำบรรยาย" /></label>
          <label>โฟลเดอร์<input value={folder} onChange={(e) => setFolder(e.target.value)}
            placeholder="general" pattern="[a-z0-9][a-z0-9_-]{0,49}" /></label>
          <button type="submit">ค้นหา</button>
          <button type="button" onClick={reload}>รีเฟรช</button>
        </form>

        {error && <p className="ry-media-error" role="alert">{error}</p>}
        <p aria-live="polite">{loading ? 'กำลังโหลดรูปภาพ...' : `พบ ${pagination.total} รูป`}</p>
        {!loading && rows.length === 0 && <p className="ry-media-empty">ยังไม่มีรูปภาพตามเงื่อนไขที่เลือก</p>}
        <div className="ry-media-grid">
          {rows.map((image) => (
            <article key={image.id} className="ry-media-card">
              <img loading="lazy" src={image.url} alt={image.alt_text || image.original_name}
                referrerPolicy="same-origin" />
              <div className="ry-media-card-body">
                <strong title={image.original_name}>{image.original_name}</strong>
                <p>{image.caption || image.alt_text || 'ไม่มีคำบรรยาย'}</p>
                <div className="ry-media-actions">
                  {onSelect && <button type="button" onClick={() => onSelect(image)}>เลือกรูปนี้</button>}
                  <button type="button" onClick={() => setEditing({ ...image })}>แก้ไข</button>
                  <button type="button" onClick={() => { navigator.clipboard?.writeText(new URL(image.url, window.location.origin).href); }}>
                    คัดลอก URL
                  </button>
                  <button type="button" className="ry-media-danger" onClick={() => remove(image)} disabled={busy}>ซ่อน</button>
                </div>
              </div>
            </article>
          ))}
        </div>

        <nav className="ry-media-pages" aria-label="หน้าคลังรูปภาพ">
          <button type="button" disabled={loading || query.page <= 1}
            onClick={() => setQuery((prev) => ({ ...prev, page: prev.page - 1 }))}>ก่อนหน้า</button>
          <span>หน้า {pagination.page} / {Math.max(1, pagination.pages || 1)}</span>
          <button type="button" disabled={loading || pagination.page >= (pagination.pages || 1)}
            onClick={() => setQuery((prev) => ({ ...prev, page: prev.page + 1 }))}>ถัดไป</button>
        </nav>

        {editing && (
          <div className="ry-media-modal-backdrop" role="presentation">
            <form className="ry-media-modal" role="dialog" aria-modal="true" aria-label="แก้ไขรายละเอียดรูป" onSubmit={save}>
              <h2>แก้ไขรายละเอียดรูปภาพ</h2>
              <label>Alt Text<input autoFocus maxLength={255} value={editing.alt_text || ''}
                onChange={(e) => setEditing({ ...editing, alt_text: e.target.value })} /></label>
              <label>คำบรรยาย<input maxLength={255} value={editing.caption || ''}
                onChange={(e) => setEditing({ ...editing, caption: e.target.value })} /></label>
              <div className="ry-media-actions">
                <button className="ry-media-primary" type="submit" disabled={busy}>บันทึก</button>
                <button type="button" onClick={() => setEditing(null)} disabled={busy}>ยกเลิก</button>
              </div>
            </form>
          </div>
        )}
      </section>
    </AdminControlCenterShell>
  );
}
