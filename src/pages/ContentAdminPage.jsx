import { AdminPageHeader, AdminErrorState } from '../components/admin/AdminUI';
import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { memberAdminApi } from '../services/memberAdminApi';
import alertService from '../services/alertService';
import RichTextEditor from '../components/common/RichTextEditor';
import RichTextContent from '../components/common/RichTextContent';
import NewsGalleryEditor from '../components/common/NewsGalleryEditor';
import ActivitySlideshow from '../components/common/ActivitySlideshow';
import { normalizeGallery } from '../utils/news';

const empty = {
  title: '',
  category: 'ข่าวประชาสัมพันธ์',
  excerpt: '',
  content: '',
  image: '/assets/img/news_placeholder.jpg',
  workflow_status: 'draft',
  gallery_images: [],
};

export default function ContentAdminPage() {
  const { can } = useAuth();
  const [params, setParams] = useSearchParams();
  const tab = params.get('tab') === 'faqs' ? 'faqs' : 'news';
  const allowed = can(`${tab}.view`);

  const [readingImages, setReadingImages] = useState(false);
  const [items, setItems] = useState([]);
  const [edit, setEdit] = useState(null);
  const [preview, setPreview] = useState(null);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [revision, setRevision] = useState(0);
  const [loadFailed, setLoadFailed] = useState(false);

  useEffect(() => {
    if (!allowed) {
      setLoading(false);
      return;
    }
    const c = new AbortController();
    setLoading(true);
    setError('');
    setLoadFailed(false);
    setEdit(null);

    memberAdminApi(tab === 'news' ? '/api/admin/news' : '/api/admin/faqs', { signal: c.signal })
      .then((r) => {
        if (!Array.isArray(r.data)) throw new Error('ข้อมูลรายการไม่ครบถ้วน');
        setItems(r.data.map((item) => (tab === 'news' ? { ...item, gallery_images: normalizeGallery(item.gallery_images) } : item)));
      })
      .catch((e) => {
        if (e.name !== 'AbortError') {
          setError(e.message);
          setLoadFailed(true);
        }
      })
      .finally(() => {
        if (!c.signal.aborted) setLoading(false);
      });
    return () => c.abort();
  }, [tab, allowed, revision]);

  async function save(e) {
    e.preventDefault();
    if (readingImages) return;
    setBusy(true);
    setError('');
    try {
      const path = `/api/admin/${tab}${edit.id ? `/${edit.id}/update` : ''}`;
      await memberAdminApi(path, { data: edit });
      setEdit(null);
      const successMsg = tab === 'news' ? 'บันทึกร่าง / ส่งตรวจแล้ว ยังไม่เผยแพร่' : 'บันทึกคำถามแล้ว';
      alertService.toastSuccess(successMsg);
      setMessage(successMsg);
      setRevision((v) => v + 1);
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  async function disable(item) {
    const confirmed = await alertService.confirmDelete(item.title || item.question, 'รายการนี้');
    if (!confirmed) return;
    setBusy(true);
    setError('');
    try {
      await memberAdminApi(`/api/admin/${tab}/${item.id}/delete`, { data: { version: item.version } });
      setEdit(null);
      setPreview(null);
      alertService.toastSuccess('ปิดรายการแล้ว');
      setMessage('ปิดรายการแล้ว');
      setRevision((v) => v + 1);
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  async function publish(item) {
    const confirmed = await alertService.confirm({
      title: 'ยืนยันเผยแพร่ข่าว',
      text: 'ตรวจสอบตัวอย่างข่าวแล้ว และยืนยันเผยแพร่สู่หน้าเว็บหรือไม่?',
      confirmButtonText: 'เผยแพร่ทันที',
    });
    if (!confirmed) return;
    setBusy(true);
    setError('');
    try {
      await memberAdminApi(`/api/admin/news/${item.id}/publish`, { data: { version: item.version } });
      setPreview(null);
      alertService.toastSuccess('เผยแพร่ข่าวสู่หน้าเว็บไซต์แล้ว');
      setMessage('เผยแพร่ข่าวแล้ว');
      setRevision((v) => v + 1);
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  const handleReload = async () => {
    if (edit) {
      const confirmed = await alertService.confirmUnsavedChanges();
      if (!confirmed) return;
    }
    setRevision((v) => v + 1);
  };

  const handleTabChange = async (t) => {
    if (edit) {
      const confirmed = await alertService.confirmUnsavedChanges();
      if (!confirmed) return;
    }
    setParams({ tab: t });
    setPreview(null);
  };

  return (
    <section className="admin-module">
      <AdminPageHeader
        title={tab === 'news' ? 'จัดการข่าวและกิจกรรมประชาสัมพันธ์' : 'จัดการคำถามที่พบบ่อย (FAQ)'}
        description="บันทึกร่าง ส่งตรวจ และตรวจตัวอย่างก่อนเผยแพร่สู่เว็บไซต์สาธารณะ"
        breadcrumbs={[
          { label: 'Admin Control Center', path: '/admin/dashboard' },
          { label: 'พื้นที่ทำงาน', path: '/admin/workspace' },
          { label: tab === 'news' ? 'ข่าวสาร & ประชาสัมพันธ์' : 'คำถามที่พบบ่อย (FAQ)' }
        ]}
        actions={
          <button className="btn btn-outline" disabled={busy || loading} onClick={handleReload} style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
            <span>{loading ? 'กำลังโหลด...' : 'โหลดข้อมูลล่าสุด'}</span>
          </button>
        }
      />
      <nav style={{ display: 'flex', gap: 12, marginBottom: 20 }}>
        {['news', 'faqs'].filter((t) => can(`${t}.view`)).map((t) => (
          <button
            className={`btn ${tab === t ? 'btn-primary' : 'btn-outline'}`}
            key={t}
            disabled={busy || readingImages}
            onClick={() => handleTabChange(t)}
          >
            {t === 'news' ? 'ข่าวประชาสัมพันธ์' : 'คำถามที่พบบ่อย'}
          </button>
        ))}
      </nav>
      {error && <AdminErrorState message={error} />}
      {message && <p role="status">{message}</p>}
      {!allowed ? (
        <p>ไม่มีสิทธิ์ในหมวดนี้</p>
      ) : loading ? (
        <p role="status">กำลังโหลด…</p>
      ) : loadFailed ? null : (
        <>
          {can(`${tab}.create`) && (
            <button
              className="btn btn-primary"
              disabled={busy || readingImages}
              onClick={() => setEdit(tab === 'news' ? { ...empty } : { question: '', answer: '' })}
            >
              เพิ่ม{tab === 'news' ? 'ข่าว' : 'คำถาม'}
            </button>
          )}
          {edit && (
            <form onSubmit={save} style={{ display: 'grid', gap: 12, margin: '24px 0' }}>
              <label>
                {tab === 'news' ? 'หัวข้อข่าว' : 'คำถาม'}
                <input
                  required
                  className="form-control"
                  value={tab === 'news' ? edit.title : edit.question}
                  onChange={(e) => setEdit({ ...edit, [tab === 'news' ? 'title' : 'question']: e.target.value })}
                />
              </label>
              {tab === 'faqs' && (
                <label>
                  สถานะ
                  <select aria-label="สถานะ" value={edit.status || 'active'} onChange={(e) => setEdit({ ...edit, status: e.target.value })}>
                    <option value="active">ใช้งาน</option>
                    <option value="inactive">ปิดใช้งาน</option>
                  </select>
                </label>
              )}
              {tab === 'news' && (
                <>
                  <label>
                    หมวดหมู่
                    <input required className="form-control" value={edit.category} onChange={(e) => setEdit({ ...edit, category: e.target.value })} />
                  </label>
                  <label>
                    คำโปรย
                    <textarea className="form-control" value={edit.excerpt || ''} onChange={(e) => setEdit({ ...edit, excerpt: e.target.value })} />
                  </label>
                </>
              )}
              {tab === 'news' && (
                <NewsGalleryEditor
                  images={edit.gallery_images || []}
                  disabled={busy || readingImages}
                  onBusyChange={setReadingImages}
                  onChange={(gallery_images) => setEdit((previous) => ({ ...previous, gallery_images }))}
                />
              )}
              <RichTextEditor
                value={tab === 'news' ? edit.content : edit.answer}
                onChange={(value) => setEdit({ ...edit, [tab === 'news' ? 'content' : 'answer']: value })}
              />
              {tab === 'news' && (
                <label>
                  สถานะ
                  <select className="form-control" value={edit.workflow_status} onChange={(e) => setEdit({ ...edit, workflow_status: e.target.value })}>
                    <option value="draft">ร่าง</option>
                    <option value="submitted">ส่งตรวจเพื่อเผยแพร่</option>
                  </select>
                </label>
              )}
              {tab === 'news' && edit.id && (
                <p>การแก้ไขจะกลับเป็นร่าง / ส่งตรวจ และพักการแสดงข่าวนี้จนเผยแพร่อีกครั้ง</p>
              )}
              <div>
                <button className="btn btn-primary" disabled={busy || readingImages}>บันทึก</button>
                <button type="button" className="btn btn-outline" disabled={busy || readingImages} onClick={() => setEdit(null)}>ยกเลิก</button>
              </div>
            </form>
          )}
          {!items.length && <p>ยังไม่มีรายการ</p>}
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%' }}>
              <thead>
                <tr>
                  <th scope="col">หัวข้อ</th>
                  <th scope="col">สถานะ</th>
                  <th scope="col">ดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id}>
                    <td>{item.title || item.question}</td>
                    <td>{item.workflow_status || (item.status === 'inactive' ? 'ปิดใช้งาน' : 'ใช้งาน')}</td>
                    <td>
                      <button className="btn btn-outline btn-sm" onClick={() => setPreview(item)}>ดูตัวอย่าง</button>
                      {can(`${tab}.update`) && (
                        <button
                          className="btn btn-outline btn-sm"
                          disabled={busy || readingImages}
                          onClick={() => setEdit({ ...item, workflow_status: item.workflow_status === 'submitted' ? 'submitted' : 'draft' })}
                        >
                          แก้ไข
                        </button>
                      )}
                      {can(`${tab}.delete`) && (
                        <button className="btn btn-outline btn-sm" disabled={busy || readingImages} onClick={() => disable(item)}>
                          ปิดรายการ
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {preview && (
            <article className="surface-card" style={{ padding: 24, marginTop: 24 }}>
              <h2>{preview.title || preview.question}</h2>
              {tab === 'news' && <ActivitySlideshow images={preview.gallery_images || []} title={preview.title} />}
              <RichTextContent value={preview.content || preview.answer} />
              {tab === 'news' && can('news.publish') && preview.workflow_status === 'submitted' && (
                <button disabled={busy || readingImages} className="btn btn-primary" onClick={() => publish(preview)}>
                  อนุมัติและเผยแพร่ข่าวนี้
                </button>
              )}
            </article>
          )}
        </>
      )}
    </section>
  );
}
