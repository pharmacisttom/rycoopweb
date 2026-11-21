import React, { useEffect, useState } from 'react';
import { Images, Plus, UploadCloud, RefreshCw } from 'lucide-react';
import { memberAdminApi } from '../services/memberAdminApi';
import { showConfirm, showDeleteConfirm, showSuccess, showError } from '../utils/swal';

const emptyForm = { title: '', description: '', location: '', event_date: '' };
const MAX_QUEUE = 200;
const MAX_BYTES = 20 * 1024 * 1024;
const MIME = ['image/jpeg','image/png','image/webp','image/gif'];

export default function AdminActivitiesPage() {
  const [albums, setAlbums] = useState([]);
  const [selected, setSelected] = useState(null);
  const [photos, setPhotos] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [queue, setQueue] = useState([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const refresh = async () => {
    const result = await memberAdminApi('/api/admin/activities');
    setAlbums(result.data || []);
  };
  useEffect(() => { refresh().catch((e) => setError(e.message)); }, []);

  const chooseAlbum = async (album) => {
    setQueue([]); setError(''); setNotice('');
    const result = await memberAdminApi(`/api/admin/activities/${album.id}`);
    setSelected(result.data.album);
    setPhotos(result.data?.photos || []);
    setForm({ title: result.data.album.title, description: result.data.album.description || '', location: result.data.album.location || '', event_date: result.data.album.event_date || '' });
  };

  const create = async (e) => {
    e.preventDefault(); setBusy(true); setError('');
    try {
      const result = await memberAdminApi('/api/admin/activities', { data: form });
      await refresh();
      await chooseAlbum({ id: result.id });
      setNotice('สร้างอัลบั้ม Draft แล้ว');
    } catch (err) { setError(err.message); }
    finally { setBusy(false); }
  };
  const save = async (e) => {
    e.preventDefault(); setBusy(true); setError('');
    try { await memberAdminApi(`/api/admin/activities/${selected.id}/update`, { data: form }); await chooseAlbum({ id: selected.id }); await refresh(); setNotice('บันทึกเรียบร้อยแล้ว'); }
    catch (err) { setError(err.message); } finally { setBusy(false); }
  };
  const setFiles = (list) => {
    const files = Array.from(list || []);
    if (files.length > MAX_QUEUE) { setError('เลือกได้สูงสุด 200 รูปต่อชุด'); return; }
    const invalid = files.filter((f) => !MIME.includes(f.type) || f.size > MAX_BYTES || f.size === 0);
    if (invalid.length) { setError(`มีไฟล์ไม่รองรับหรือเกิน 20 MB จำนวน ${invalid.length} ไฟล์`); return; }
    setQueue(files.map((file, index) => ({ file, key: `${index}-${file.name}-${file.size}`, state: 'pending' })));
    setError('');
  };
  const upload = async () => {
    if (!selected || !queue.length || selected.status !== 'draft') return;
    setBusy(true); setError('');
    const next = [...queue]; let failures = 0;
    // Deliberately sequential to avoid PHP-FPM overload; failed images can be retried.
    for (let i = 0; i < next.length; i++) {
      if (next[i].state === 'success') continue;
      next[i] = { ...next[i], state: 'uploading' }; setQueue([...next]);
      try {
        const fd = new FormData(); fd.append('file', next[i].file);
        await memberAdminApi(`/api/admin/activities/${selected.id}/photos`, { data: fd });
        next[i] = { ...next[i], state: 'success' };
      } catch (err) { failures++; next[i] = { ...next[i], state: 'failed', message: err.message }; }
      setQueue([...next]);
    }
    try {
      await chooseAlbum(selected);
      await refresh();
    } catch (err) {
      setError(`อัปโหลดเสร็จแล้วแต่โหลดรายการภาพใหม่ไม่สำเร็จ: ${err.message}`);
    } finally {
      setBusy(false);
    }
    if (failures) { setQueue(next.filter((row) => row.state === 'failed')); setError(`อัปโหลดไม่สำเร็จ ${failures} ภาพ สามารถลองใหม่ได้`); }
    else setNotice('ส่งไฟล์ครบแล้ว กรุณาตรวจภาพที่บันทึกในอัลบั้ม');
  };
  const publish = async () => {
    if (!selected) return;
    const confirmed = await showConfirm('ยืนยันเผยแพร่อัลบั้ม?', 'ยืนยันเผยแพร่อัลบั้มนี้ต่อสาธารณะหรือไม่?');
    if (!confirmed) return;
    setBusy(true); setError('');
    try {
      await memberAdminApi(`/api/admin/activities/${selected.id}/publish`, { data: {} });
      await refresh(); await chooseAlbum({ id: selected.id });
      setNotice('เผยแพร่อัลบั้มเรียบร้อยแล้ว');
      showSuccess('เผยแพร่อัลบั้มสำเร็จ!', 'อัลบั้มภาพกิจกรรมเผยแพร่สู่หน้าเว็บแล้ว');
    } catch (e) { setError(e.message); showError('ไม่สามารถเผยแพร่ได้', e.message); } finally { setBusy(false); }
  };

  const action = async (suffix, payload = {}) => {
    if (!selected || busy) return;
    setBusy(true); setError(''); setNotice('');
    try {
      await memberAdminApi(`/api/admin/activities/${selected.id}${suffix}`, { data: payload });
      if (suffix !== '/archive') await chooseAlbum({ id: selected.id });
      await refresh();
      setNotice('บันทึกการเปลี่ยนแปลงสำเร็จ');
      return true;
    } catch (e) { setError(e.message); return false; } finally { setBusy(false); }
  };
  const changeCaption = async (photo) => {
    const caption = window.prompt('คำบรรยายภาพ (สูงสุด 500 ตัวอักษร)', photo.caption || '');
    if (caption === null) return;
    await action(`/photos/${photo.id}/caption`, { caption });
  };
  const remove = async (photo) => {
    const confirmed = await showDeleteConfirm('ลบภาพนี้?', 'ลบภาพนี้จากอัลบั้ม Draft ใช่หรือไม่?');
    if (!confirmed) return;
    await action(`/photos/${photo.id}/remove`);
  };
  const move = async (index, delta) => {
    const destination = index + delta;
    if (destination < 0 || destination >= photos.length) return;
    const ids = photos.map((photo) => photo.id);
    [ids[index], ids[destination]] = [ids[destination], ids[index]];
    await action('/reorder', { ids });
  };
  const archive = async () => {
    if (!selected) return;
    const confirmed = await showDeleteConfirm('ซ่อนอัลบั้มภาพ?', 'ยืนยันซ่อนอัลบั้มและนำออกจากหน้าสาธารณะ?');
    if (!confirmed) return;
    const archived = await action('/archive');
    if (archived) {
      setSelected(null); setPhotos([]); setForm(emptyForm);
      showSuccess('ซ่อนอัลบั้มแล้ว', 'อัลบั้มภาพกิจกรรมถูกนำออกจากหน้าสาธารณะเรียบร้อยแล้ว');
    }
  };
  const isDraft = !selected || selected.status === 'draft';
  return <section style={{ padding: '1.25rem', maxWidth: 1280, margin: '0 auto' }}>
    <header style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
      <div><h1 style={{ marginBottom: 6 }}><Images style={{ verticalAlign: 'middle' }}/> จัดการอัลบั้มภาพกิจกรรม</h1><p>เพิ่มอัลบั้ม อัปโหลดภาพหลายไฟล์ และเผยแพร่เมื่อพร้อม</p></div>
      <button className="btn btn-outline" onClick={() => { setSelected(null); setPhotos([]); setQueue([]); setForm(emptyForm); }}><Plus size={16}/> อัลบั้มใหม่</button>
    </header>
    {error && <p role="alert" style={{ color: '#b91c1c' }}>{error}</p>}
    {notice && <p role="status" style={{ color: '#047857' }}>{notice}</p>}
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,330px),1fr))', gap: 20, marginTop: 20 }}>
      <aside className="surface-card" style={{ padding: 20, alignSelf: 'start' }}>
        <h2 style={{ fontSize: '1.1rem' }}>อัลบั้มทั้งหมด</h2>
        <button className="btn btn-outline btn-sm" onClick={() => refresh().catch((e)=>setError(e.message))}><RefreshCw size={14}/> รีเฟรช</button>
        <div style={{ display: 'grid', gap: 8, marginTop: 12 }}>
          {albums.map((a) => <button key={a.id} type="button" onClick={() => chooseAlbum(a).catch((e)=>setError(e.message))} style={{ padding: 12, textAlign: 'left', border: selected?.id===a.id?'2px solid #0284c7':'1px solid #cbd5e1', borderRadius: 10, background: 'transparent' }}>
            <strong>{a.title}</strong><small style={{ display: 'block' }}>{a.photo_count} ภาพ · {a.status==='draft'?'ฉบับร่าง':'เผยแพร่แล้ว'}</small>
          </button>)}
        </div>
      </aside>
      <div className="surface-card" style={{ padding: 20 }}>
        <h2 style={{ fontSize: '1.1rem' }}>{selected ? 'รายละเอียดอัลบั้ม' : 'สร้างอัลบั้มใหม่'}</h2>
        <form onSubmit={selected?save:create} style={{ display: 'grid', gap: 12 }}>
          <label>ชื่อกิจกรรม<input className="form-control" required maxLength={255} disabled={!isDraft||busy} value={form.title} onChange={(e)=>setForm({...form,title:e.target.value})}/></label>
          <label>วันที่จัดกิจกรรม<input className="form-control" type="date" disabled={!isDraft||busy} value={form.event_date} onChange={(e)=>setForm({...form,event_date:e.target.value})}/></label>
          <label>สถานที่<input className="form-control" maxLength={255} disabled={!isDraft||busy} value={form.location} onChange={(e)=>setForm({...form,location:e.target.value})}/></label>
          <label>คำอธิบาย<textarea className="form-control" rows={3} disabled={!isDraft||busy} value={form.description} onChange={(e)=>setForm({...form,description:e.target.value})}/></label>
          {isDraft && <button className="btn btn-primary" type="submit" disabled={busy}>{selected?'บันทึกการแก้ไข':'สร้างอัลบั้ม Draft'}</button>}
        </form>
        {selected && <div style={{ marginTop: 24 }}>
          <h3>รูปภาพ ({photos.length})</h3>
          {isDraft && <div style={{ border: '2px dashed #94a3b8', borderRadius: 12, padding: 18 }}>
            <label style={{ display: 'block' }}><UploadCloud size={20}/> เลือกรูปภาพหลายไฟล์ (สูงสุด 200 รูปต่อชุด)
              <input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" disabled={busy} onChange={(e)=>setFiles(e.target.files)} style={{ display: 'block', marginTop: 12 }}/>
            </label>
            {queue.length>0 && <><p>{queue.filter((q)=>q.state==='success').length}/{queue.length} สำเร็จ</p><button className="btn btn-primary" disabled={busy} onClick={upload}>เริ่มอัปโหลด / ลองใหม่</button>
              <ul style={{ maxHeight: 150, overflow: 'auto' }}>{queue.map((q)=><li key={q.key}>{q.file.name} — {q.state}{q.message? ` (${q.message})`:''}</li>)}</ul></>}
          </div>}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(95px,1fr))', gap: 8, marginTop: 14 }}>
            {photos.map((p,i)=><div key={p.id} style={{ border:'1px solid #cbd5e1',borderRadius:8,padding:5 }}>
              <img alt={p.caption||'ภาพกิจกรรม'} loading="lazy" src={`/api/admin/activities/${selected.id}/photos/${p.id}?size=thumb`} style={{ width:'100%',height:95,objectFit:'cover',borderRadius:8 }}/>
              {isDraft && <div style={{ display:'flex',flexWrap:'wrap',gap:4,marginTop:4 }}>
                <button type="button" disabled={busy} onClick={()=>action(`/photos/${p.id}/cover`)} title="ตั้งภาพปก">ปก</button>
                <button type="button" disabled={busy} onClick={()=>changeCaption(p)} title="คำบรรยาย">แก้</button>
                <button type="button" disabled={busy||i===0} onClick={()=>move(i,-1)} title="เลื่อนก่อนหน้า">↑</button>
                <button type="button" disabled={busy||i===photos.length-1} onClick={()=>move(i,1)} title="เลื่อนถัดไป">↓</button>
                <button type="button" disabled={busy} onClick={()=>remove(p)} title="ลบภาพ">ลบ</button>
              </div>}
            </div>)}
          </div>
          {isDraft && photos.length > 0 && <button className="btn btn-primary" style={{ marginTop: 18 }} disabled={busy} onClick={publish}>เผยแพร่อัลบั้มกิจกรรม</button>}
          <button className="btn btn-outline" style={{ marginTop: 18, marginLeft: 8 }} disabled={busy} onClick={archive}>ซ่อน/เก็บอัลบั้ม</button>
        </div>}
      </div>
    </div>
  </section>;
}
