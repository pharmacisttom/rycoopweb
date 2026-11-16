import React, { useEffect, useState } from 'react';
import { memberAdminApi as api } from '../../services/memberAdminApi';

export default function BoardTermWorkflowPanel() {
  const [terms, setTerms] = useState([]);
  const [sourceId, setSourceId] = useState('');
  const [candidates, setCandidates] = useState([]);
  const [selected, setSelected] = useState([]);
  const [preview, setPreview] = useState(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const load = async () => setTerms((await api('/api/admin/board-terms')).data || []);
  useEffect(() => { load().catch(e => setError(e.message)); }, []);
  const run = async work => {
    setBusy(true); setError(''); setMessage('');
    try { await work(); await load(); } catch (e) { setError(e.message || 'ดำเนินการไม่สำเร็จ'); }
    finally { setBusy(false); }
  };
  const copyData = { target_term_number: 39, selections: selected, transfer_groups: true };
  return <section className="card" aria-label="Board term workflow" style={{ padding: 20, marginBottom: 20 }}>
    <h2>วาระกรรมการ: Import → Draft → Approval → Publish</h2>
    <p>นำเข้าและคัดลอกเป็นร่างเท่านั้น ข้อมูลสาธารณะเปลี่ยนเมื่ออนุมัติและกดเผยแพร่แยกกัน วาระที่เผยแพร่มีลำดับเหนือ Public Content สำหรับกรรมการ ที่ปรึกษา ผู้ตรวจสอบ และคณะย่อย</p>
    {error && <p role="alert">{error}</p>}
    {message && <p role="status">{message}</p>}
    <button type="button" disabled={busy} onClick={() => run(async () => {
      setPreview({ kind: 'import', data: (await api('/api/admin/board-terms/import-38/preview')).data });
    })}>Import Preview: วาระ 38</button>
    <ul>{terms.map(t => <li key={t.id}>
      {t.term_name}: <strong>{t.status === 'published' ? 'Published / เผยแพร่' : t.status === 'draft' ? 'Draft / ร่าง' : t.status}</strong>
      {t.is_current ? ' — ปัจจุบัน' : ''}
      {t.status === 'draft' && <>
        <button type="button" disabled={busy} onClick={() => run(async () => {
          if (!window.confirm(`อนุมัติร่าง ${t.term_name}? การแก้ไขภายหลังต้องอนุมัติใหม่`)) return;
          await api(`/api/admin/board-terms/${t.id}/approve`, { data: {} }); setMessage('อนุมัติแล้ว กรุณากดเผยแพร่เมื่อต้องการเปลี่ยนข้อมูลสาธารณะ');
        })}>Approve / อนุมัติ</button>
        <button type="button" disabled={busy} onClick={() => run(async () => {
          if (!window.confirm(`เผยแพร่ ${t.term_name} เป็นวาระปัจจุบัน? ต้องอนุมัติร่างนี้แล้ว`)) return;
          await api(`/api/admin/board-terms/${t.id}/publish`, { data: {} }); setMessage('เผยแพร่สำเร็จ');
        })}>Publish / เผยแพร่</button>
      </>}
    </li>)}</ul>
    <label>ต้นทางสำหรับคัดลอกไป Draft วาระ 39
      <select value={sourceId} disabled={busy} onChange={e => {
        const value = e.target.value; setSourceId(value); setSelected([]); setCandidates([]); setPreview(null);
        if (value) run(async () => setCandidates((await api(`/api/admin/board-terms/${value}/selection-candidates?target_term=39`)).data.members));
      }}>
        <option value="">เลือกวาระ 38</option>
        {terms.filter(t => Number(t.term_number) === 38).map(t => <option key={t.id} value={t.id}>{t.term_name}</option>)}
      </select>
    </label>
    {candidates.map(m => <label key={m.id} style={{ display: 'block' }}>
      <input type="checkbox" disabled={busy} checked={selected.includes(Number(m.id))} onChange={e => {
        setPreview(null); setSelected(ids => e.target.checked ? [...ids, Number(m.id)] : ids.filter(id => id !== Number(m.id)));
      }}/>{m.name} — {m.position} ({m.category})
    </label>)}
    <button type="button" disabled={busy || !selected.length} onClick={() => run(async () => {
      setPreview({ kind: 'copy', data: (await api(`/api/admin/board-terms/${sourceId}/selective-copy/preview`, { data: copyData })).data });
    })}>ดูตัวอย่างการคัดลอก</button>
    {preview && <div role="region" aria-label="Commit preview">
      <h3>{preview.kind === 'import' ? 'Import Preview — Draft 38' : 'Selective Copy Preview — Draft 39'}</h3>
      {preview.kind === 'import' ? <>
        <p>กรรมการ {preview.data.directors}, ที่ปรึกษา {preview.data.advisors}, ผู้ตรวจสอบ {preview.data.auditors}, คณะย่อย {preview.data.groups}</p>
        <p>{preview.data.message}</p>
        <ul>{preview.data.boardMembers.map(m => <li key={m.id}>{m.name} — {m.position}</li>)}</ul>
      </> : <>
        <ul>{preview.data.members.map(m => <li key={m.id}>{m.name} — {m.position}; รูป: {m.image_url || 'ไม่มี'}</li>)}</ul>
        <p>คณะย่อย: {preview.data.groups.map(g => `${g.title} (${g.members.length})`).join(', ')}</p>
        <p>ต้นทางคงเดิม ไม่มีการย้ายหรือลบสมาชิก</p>
      </>}
      <button type="button" disabled={busy} onClick={() => run(async () => {
        if (!window.confirm('ยืนยันสร้างร่างตามตัวอย่าง? จะไม่เผยแพร่ทันที')) return;
        const result = preview.kind === 'import'
          ? await api('/api/admin/board-terms/import-38', { data: { preserve_custom_photos: true, preserve_admin_edits: true } })
          : await api(`/api/admin/board-terms/${sourceId}/selective-copy`, { data: { ...copyData, version: preview.data.version } });
        setPreview(null); setSelected([]); setMessage(result.message);
      })}>ยืนยันบันทึก Draft เท่านั้น</button>
      <button type="button" disabled={busy} onClick={() => setPreview(null)}>ยกเลิก</button>
    </div>}
  </section>;
}
