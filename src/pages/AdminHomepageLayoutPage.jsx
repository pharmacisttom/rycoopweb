import React, { useCallback, useEffect, useState } from 'react';
import AdminControlCenterShell from '../components/admin/AdminControlCenterShell';
import { memberAdminApi } from '../services/memberAdminApi';

const keys = ['hero', 'quick_services', 'statistics', 'products', 'calculator', 'welfare'];

export default function AdminHomepageLayoutPage() {
  const [state, setState] = useState(null);
  const [sections, setSections] = useState(null);
  const [totp, setTotp] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [dirty, setDirty] = useState(false);

  const load = useCallback(async () => {
    const result = await memberAdminApi('/api/admin/layout');
    const next = result.data;
    if (!next || !keys.every(key => typeof next.published?.[key] === 'boolean')) {
      throw new Error('รูปแบบข้อมูล Layout ไม่ถูกต้อง');
    }
    setState(next);
    setSections(next.draft || next.published);
    setDirty(false);
  }, []);

  useEffect(() => {
    let active = true;
    memberAdminApi('/api/admin/layout').then(result => {
      if (!active) return;
      const next = result.data;
      if (!next || !keys.every(key => typeof next.published?.[key] === 'boolean')) throw new Error('รูปแบบข้อมูล Layout ไม่ถูกต้อง');
      setState(next);
      setSections(next.draft || next.published);
    }).catch(e => { if (active) setError(e.message); });
    return () => { active = false; };
  }, []);

  const perform = async (action) => {
    if (!state || busy) return;
    setBusy(true); setError(''); setNotice('');
    try {
      const body = { version: state.version, totp_code: totp };
      if (action === 'draft') body.sections = sections;
      const result = await memberAdminApi('/api/admin/layout/' + action, { data: body });
      const next = result.data;
      setState(next);
      setSections(next.draft || next.published);
      setDirty(false);
      setTotp('');
      setNotice(action === 'draft' ? 'บันทึกร่างแล้ว หน้าเว็บจริงยังไม่เปลี่ยน' : 'เผยแพร่ Layout สำเร็จ');
    } catch(e) {
      setError(e.message || 'ดำเนินการไม่สำเร็จ');
    } finally { setBusy(false); }
  };

  return (
    <AdminControlCenterShell title="Website Layout Manager" subtitle="กำหนดส่วนที่แสดงบนหน้าแรกของสหกรณ์">
      <section className="admin-module" aria-label="จัดการ Layout หน้าแรก">
        <p>ตั้งค่าได้เฉพาะส่วนของหน้าแรกที่รองรับ ร่างจะไม่แสดงบนเว็บไซต์จนกว่าจะเผยแพร่ และการดำเนินการต้องยืนยัน MFA เพิ่มเติม</p>
        {error && <p role="alert">{error}</p>}
        {notice && <p role="status">{notice}</p>}
        {!state && !error && <p role="status">กำลังโหลด...</p>}
        {state && sections && (
          <div className="surface-card" style={{padding:'1.5rem',maxWidth:850}}>
            <h2>ส่วนประกอบหน้าแรก</h2>
            {keys.map(key => (
              <label key={key} style={{display:'flex',justifyContent:'space-between',alignItems:'center',gap:16,padding:'0.75rem',borderBottom:'1px solid var(--border-subtle)'}}>
                <span>{state.sections?.[key] || key}</span>
                <span><input type="checkbox" checked={sections[key]} disabled={busy} onChange={event => {setSections(prev => ({...prev,[key]:event.target.checked}));setDirty(true);}} /> {sections[key] ? 'แสดง' : 'ซ่อน'}</span>
              </label>
            ))}
            <p>สถานะ: {state.draft ? 'มีร่างรอเผยแพร่' : 'ข้อมูลที่เผยแพร่แล้ว'} · {dirty ? 'มีการแก้ไขที่ยังไม่บันทึก' : 'ไม่มีการแก้ไขค้าง'}</p>
            <label>รหัส TOTP ยืนยันก่อนบันทึก/เผยแพร่
              <input type="password" inputMode="numeric" autoComplete="one-time-code" value={totp} maxLength={8} onChange={e=>setTotp(e.target.value)} placeholder="รหัสยืนยัน MFA" />
            </label>
            <div style={{display:'flex',gap:12,flexWrap:'wrap',marginTop:16}}>
              <button type="button" disabled={busy||!dirty||!/^\\d{6,8}$/.test(totp)} onClick={()=>perform('draft')}>บันทึกร่าง</button>
              <button type="button" disabled={busy||dirty||!state.draft||!/^\\d{6,8}$/.test(totp)} onClick={()=>{if(window.confirm('ยืนยันเผยแพร่การเปิด–ปิดส่วนหน้าแรก?'))perform('publish');}}>อนุมัติเผยแพร่</button>
              <button type="button" disabled={busy} onClick={async()=>{try{await load();setNotice('โหลดข้อมูลล่าสุดแล้ว');setError('');}catch(e){setError(e.message);}}}>โหลดข้อมูลล่าสุด</button>
            </div>
          </div>
        )}
      </section>
    </AdminControlCenterShell>
  );
}
