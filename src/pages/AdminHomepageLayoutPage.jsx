import React, { useCallback, useEffect, useMemo, useState } from 'react';
import AdminControlCenterShell from '../components/admin/AdminControlCenterShell';
import { memberAdminApi } from '../services/memberAdminApi';

const keys = ['hero', 'quick_services', 'statistics', 'products', 'calculator', 'welfare'];
const sameVisibility = (a, b) => keys.every(key => a?.[key] === b?.[key]);

export default function AdminHomepageLayoutPage() {
  const [state, setState] = useState(null);
  const [sections, setSections] = useState(null);
  const [totp, setTotp] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [loading, setLoading] = useState(true);

  const serverValues = state?.draft || state?.published;
  const dirty = useMemo(() => !!sections && !!serverValues && !sameVisibility(sections, serverValues), [sections, serverValues]);
  const validTotp = /^\d{6}$/.test(totp);
  const canSave = !!state && dirty && validTotp && !busy;
  const canPublish = !!state?.draft && !dirty && validTotp && !busy;

  const applyServerState = useCallback(next => {
    if (!next || !keys.every(key => typeof next.published?.[key] === 'boolean')) {
      throw new Error('รูปแบบข้อมูล Layout ไม่ถูกต้อง');
    }
    setState(next);
    setSections({ ...(next.draft || next.published) });
    setTotp('');
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await memberAdminApi('/api/admin/layout');
      applyServerState(result.data);
      setNotice('โหลดข้อมูลล่าสุดจากฐานข้อมูลแล้ว');
    } catch (e) {
      setError(e.message || 'โหลดข้อมูล Layout ไม่สำเร็จ');
    } finally {
      setLoading(false);
    }
  }, [applyServerState]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    const warn = event => {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  const perform = async action => {
    if (busy || !state || !validTotp) return;
    if (action === 'draft' ? !dirty : dirty || !state.draft) return;
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const body = { version: state.version, totp_code: totp };
      if (action === 'draft') body.sections = sections;
      const result = await memberAdminApi('/api/admin/layout/' + action, { data: body });
      applyServerState(result.data);
      setNotice(action === 'draft'
        ? 'บันทึกร่างสำเร็จแล้ว ยังไม่เปลี่ยนเว็บไซต์ กรุณาใช้รหัส MFA ใหม่แล้วกดอนุมัติเผยแพร่'
        : 'เผยแพร่สำเร็จแล้ว หน้าแรกจะอ่านข้อมูลที่เผยแพร่จากฐานข้อมูล');
    } catch (e) {
      setTotp('');
      setError(e.status === 409
        ? 'ข้อมูลเปลี่ยนไปแล้ว กรุณาโหลดข้อมูลล่าสุดและแก้ไขอีกครั้ง'
        : e.status === 403 || e.status === 422
          ? 'ระบบไม่อนุญาตให้ทำรายการ โปรดตรวจสิทธิ์ Super Admin และใช้รหัส MFA 6 หลักใหม่จากแอปยืนยันตัวตน'
          : (e.message || 'บันทึกข้อมูลไม่สำเร็จ'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <AdminControlCenterShell title="Website Layout Manager" subtitle="จัดการการแสดงผลส่วนต่าง ๆ ของหน้าแรก">
      <section className="admin-module" aria-label="จัดการ Layout หน้าแรก">
        <p>เลือกแสดงหรือซ่อนแต่ละส่วน บันทึกร่างก่อน แล้วอนุมัติเผยแพร่เพื่อให้หน้าเว็บเปลี่ยนจริง ระบบจะตรวจสอบสิทธิ์และรหัส MFA ทุกครั้ง</p>
        {error && <p role="alert" style={{color:'var(--danger, #b42318)'}}>{error}</p>}
        {notice && <p role="status">{notice}</p>}
        {loading && <p role="status">กำลังโหลดข้อมูลจากฐานข้อมูล TEST...</p>}
        {!loading && !state && <button type="button" onClick={load}>ลองโหลดอีกครั้ง</button>}
        {state && sections && (
          <div className="surface-card" style={{padding:'1.5rem',maxWidth:850}}>
            <h2>ส่วนประกอบหน้าแรก</h2>
            <p style={{fontWeight:600}} role="status">
              {dirty ? 'ขั้นตอน 1/2: มีการแก้ไข รอการบันทึกร่าง' :
                state.draft ? 'ขั้นตอน 2/2: มีร่างแล้ว รออนุมัติเผยแพร่' : 'เผยแพร่แล้ว: ยังไม่มีรายการแก้ไขใหม่'}
            </p>
            {keys.map(key => (
              <label key={key} style={{display:'flex',justifyContent:'space-between',alignItems:'center',gap:16,padding:'0.75rem',borderBottom:'1px solid var(--border-subtle)'}}>
                <span>{state.sections?.[key] || key}</span>
                <span>
                  <input type="checkbox" checked={sections[key]} disabled={busy} onChange={event => {
                    const checked = event.target.checked;
                    setSections(prev => ({...prev,[key]:checked}));
                    setError('');setNotice('');
                  }} /> {sections[key] ? 'แสดง' : 'ซ่อน'}
                </span>
              </label>
            ))}
            <p style={{marginTop:12}}>
              เว็บไซต์ปัจจุบัน: เปิด {keys.filter(key => state.published[key]).length} จาก {keys.length} ส่วน
              {state.draft && ' · มีร่างที่ยังไม่เผยแพร่'}
            </p>
            <label htmlFor="layout-mfa-code">รหัส MFA (TOTP) 6 หลักจากแอป Authenticator</label>
            <input id="layout-mfa-code" type="password" inputMode="numeric" pattern="[0-9]{6}"
              autoComplete="one-time-code" value={totp} maxLength={6} disabled={busy}
              onChange={e => setTotp(e.target.value.replace(/\D/g,'').slice(0,6))}
              placeholder="กรอกรหัส 6 หลักก่อนบันทึกหรือเผยแพร่"
              aria-describedby="layout-mfa-help" />
            <p id="layout-mfa-help" style={{fontSize:'0.85rem',color:'var(--text-muted)'}}>
              {dirty && !validTotp ? 'กรอกรหัส MFA 6 หลักเพื่อเปิดปุ่มบันทึกร่าง' :
                state.draft && !dirty && !validTotp ? 'กรอกรหัส MFA ใหม่เพื่อเปิดปุ่มอนุมัติเผยแพร่' :
                  !dirty && !state.draft ? 'เริ่มจากเลือกเปิด/ปิดส่วนที่ต้องการแก้ไข' :
                    'พร้อมดำเนินการ'}
            </p>
            <div style={{display:'flex',gap:12,flexWrap:'wrap',marginTop:16}}>
              <button type="button" disabled={!canSave} onClick={() => perform('draft')}>1. บันทึกร่าง</button>
              <button type="button" disabled={!canPublish} onClick={() => {
                if (window.confirm('ยืนยันเผยแพร่ Layout จากร่างล่าสุดไปยังหน้าเว็บ TEST?')) perform('publish');
              }}>2. อนุมัติเผยแพร่</button>
              <button type="button" disabled={busy || loading} onClick={() => {
                if (dirty && !window.confirm('มีข้อมูลแก้ไขที่ยังไม่บันทึก ต้องการทิ้งการแก้ไขแล้วโหลดล่าสุด?')) return;
                load();
              }}>โหลดข้อมูลล่าสุด</button>
              {dirty && <button type="button" disabled={busy} onClick={() => {
                setSections({ ...serverValues });setTotp('');setError('');setNotice('ยกเลิกการแก้ไขที่ยังไม่ได้บันทึกแล้ว');
              }}>ยกเลิกการแก้ไข</button>}
            </div>
          </div>
        )}
      </section>
    </AdminControlCenterShell>
  );
}
