import React, { useCallback, useEffect, useState } from 'react';
import AdminControlCenterShell from '../components/admin/AdminControlCenterShell';
import { memberAdminApi } from '../services/memberAdminApi';

const keys = ['about_group','about_title','about_desc','board_title','board_desc','staff_title','staff_desc','finance_group','finance_title','finance_desc','dividends_title','dividends_desc','documents_title','documents_desc','home_nav','about_nav','finance_nav','welfare_nav','news_nav','member_nav','finance_highlight_desc','finance_deposit_group','deposit_savings_title','deposit_savings_desc','deposit_rates_title','deposit_rates_desc','dividend_estimator_title','dividend_estimator_desc','loan_group','emergency_title','emergency_desc','ordinary_title','ordinary_desc','housing_title','housing_desc','loan_apply_title','loan_apply_desc','loan_checklist_title','loan_checklist_desc','welfare_fund_group','welfare_six_title','welfare_six_desc','welfare_education_title','welfare_education_desc','welfare_medical_title','welfare_medical_desc','welfare_protection_group','welfare_senior_title','welfare_senior_desc','welfare_debt_title','welfare_debt_desc','welfare_forms_title','welfare_forms_desc','news_group','news_activity_title','news_activity_desc','announcement_title','announcement_desc','calendar_title','calendar_desc','docs_services_group','forms_title','forms_desc','eservices_title','eservices_desc','receipt_title','receipt_desc','finance_report_button','calc_button','welfare_button','docs_button','home_hero_badge','home_rates_title','home_news_title','home_loans_title','home_loans_desc','home_welfare_title','docs_page_title','docs_page_desc'];

export default function AdminNavigationCopyPage() {
  const [state, setState] = useState(null);
  const [sections, setSections] = useState(null);
  const [totp, setTotp] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [dirty, setDirty] = useState(false);

  const load = useCallback(async () => {
    const result = await memberAdminApi('/api/admin/navigation-copy');
    const next = result.data;
    if (!next || !keys.every(key => typeof next.published?.[key] === 'string')) {
      throw new Error('รูปแบบข้อมูล Navigation Copy ไม่ถูกต้อง');
    }
    setState(next);
    setSections(next.draft || next.published);
    setDirty(false);
  }, []);

  useEffect(() => {
    let active = true;
    memberAdminApi('/api/admin/navigation-copy').then(result => {
      if (!active) return;
      const next = result.data;
      if (!next || !keys.every(key => typeof next.published?.[key] === 'string')) throw new Error('รูปแบบข้อมูล Navigation Copy ไม่ถูกต้อง');
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
      if (action === 'draft') body.values = sections;
      const result = await memberAdminApi('/api/admin/navigation-copy/' + action, { data: body });
      const next = result.data;
      setState(next);
      setSections(next.draft || next.published);
      setDirty(false);
      setTotp('');
      setNotice(action === 'draft' ? 'บันทึกร่างแล้ว หน้าเว็บจริงยังไม่เปลี่ยน' : 'เผยแพร่ข้อความเมนูสำเร็จ');
    } catch(e) {
      setError(e.message || 'ดำเนินการไม่สำเร็จ');
    } finally { setBusy(false); }
  };

  return (
    <AdminControlCenterShell title="Website Navigation Copy Manager" subtitle="แก้ไขข้อความเมนูเว็บไซต์โดยไม่ต้อง Build React">
      <section className="admin-module" aria-label="จัดการ Navigation Copy หน้าแรก">
        <p>แก้ไขได้เฉพาะข้อความในเมนูที่ลงทะเบียนไว้ ส่วนอื่นของเว็บไซต์จะเพิ่มในรุ่นถัดไป ร่างจะไม่แสดงบนเว็บไซต์จนกว่าจะเผยแพร่ และการดำเนินการต้องยืนยัน MFA เพิ่มเติม</p>
        {error && <p role="alert">{error}</p>}
        {notice && <p role="status">{notice}</p>}
        {!state && !error && <p role="status">กำลังโหลด...</p>}
        {state && sections && (
          <div className="surface-card" style={{padding:'1.5rem',maxWidth:850}}>
            <h2>ข้อความเมนูเกี่ยวกับสหกรณ์</h2>
            {keys.map(key => (
              <label key={key} style={{display:'flex',justifyContent:'space-between',alignItems:'center',gap:16,padding:'0.75rem',borderBottom:'1px solid var(--border-subtle)'}}>
                <span>{state.labels?.[key] || key}</span>
                <input style={{width:'min(100%, 460px)'}} maxLength={240} value={sections[key]||''} disabled={busy} onChange={event => {setSections(prev => ({...prev,[key]:event.target.value}));setDirty(true);}} />
              </label>
            ))}
            <p>สถานะ: {state.draft ? 'มีร่างรอเผยแพร่' : 'ข้อมูลที่เผยแพร่แล้ว'} · {dirty ? 'มีการแก้ไขที่ยังไม่บันทึก' : 'ไม่มีการแก้ไขค้าง'}</p>
            <label>รหัส TOTP ยืนยันก่อนบันทึก/เผยแพร่
              <input type="password" inputMode="numeric" autoComplete="one-time-code" value={totp} maxLength={8} onChange={e=>setTotp(e.target.value)} placeholder="รหัสยืนยัน MFA" />
            </label>
            <div style={{display:'flex',gap:12,flexWrap:'wrap',marginTop:16}}>
              <button type="button" disabled={busy||!dirty||!/^\d{6,8}$/.test(totp)} onClick={()=>perform('draft')}>บันทึกร่าง</button>
              <button type="button" disabled={busy||dirty||!state.draft||!/^\d{6,8}$/.test(totp)} onClick={()=>{if(window.confirm('ยืนยันเผยแพร่ข้อความในเมนู?'))perform('publish');}}>อนุมัติเผยแพร่</button>
              <button type="button" disabled={busy} onClick={async()=>{try{await load();setNotice('โหลดข้อมูลล่าสุดแล้ว');setError('');}catch(e){setError(e.message);}}}>โหลดข้อมูลล่าสุด</button>
            </div>
          </div>
        )}
      </section>
    </AdminControlCenterShell>
  );
}
