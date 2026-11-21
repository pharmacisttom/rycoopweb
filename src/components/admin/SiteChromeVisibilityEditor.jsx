import React,{useCallback,useEffect,useMemo,useState} from 'react';
import {memberAdminApi} from '../../services/memberAdminApi';
import {showConfirm, showSuccess, showError} from '../../utils/swal';

const keys=['nav_menu','footer_brand','footer_finance','footer_welfare','footer_contact','footer_counter'];
const equal=(a,b)=>keys.every(key=>a?.[key]===b?.[key]);

export default function SiteChromeVisibilityEditor(){
 const [state,setState]=useState(null),[settings,setSettings]=useState(null);
 const [totp,setTotp]=useState(''),[error,setError]=useState(''),[message,setMessage]=useState('');
 const [loading,setLoading]=useState(true),[busy,setBusy]=useState(false);
 const server=state?.draft||state?.published;
 const dirty=useMemo(()=>!!settings&&!!server&&!equal(settings,server),[settings,server]);
 const validTotp=/^\d{6}$/.test(totp);
 const apply=next=>{
   if(!next||!keys.every(key=>typeof next.published?.[key]==='boolean'))throw new Error('ข้อมูลการแสดงผลไม่ถูกต้อง');
   setState(next);setSettings({...next.draft||next.published});setTotp('');
 };
 const load=useCallback(async()=>{
   setLoading(true);setError('');
   try{const result=await memberAdminApi('/api/admin/site-chrome');apply(result.data);}
   catch(e){setError(e.message||'ไม่สามารถโหลดการตั้งค่าได้');}
   finally{setLoading(false);}
 },[]);
 useEffect(()=>{load();},[load]);
 const save=async action=>{
   if(busy||!validTotp||!state||(action==='draft'?!dirty:(dirty||!state.draft)))return;
   setBusy(true);setError('');setMessage('');
   try{
     const data={version:state.version,totp_code:totp};
     if(action==='draft')data.sections=settings;
     const result=await memberAdminApi('/api/admin/site-chrome/'+action,{data});
     apply(result.data);setMessage(action==='draft'?'บันทึกร่างแล้ว เว็บไซต์ยังไม่เปลี่ยน':'เผยแพร่การตั้งค่าส่วนเมนูและ Footer แล้ว');
   }catch(e){setTotp('');setError(e.status===409?'ข้อมูลมีการเปลี่ยนแปลง กรุณาโหลดล่าสุด':e.message||'ดำเนินการไม่สำเร็จ');}
   finally{setBusy(false);}
 };
 return <section className="admin-module surface-card" aria-labelledby="site-chrome-heading" style={{padding:'1.5rem',marginTop:24,maxWidth:900}}>
   <h2 id="site-chrome-heading">เปิด–ปิดเมนูเว็บไซต์และ Footer</h2>
   <p>เลือกเปิดหรือซ่อนเฉพาะส่วนบนเว็บไซต์สาธารณะ โลโก้ ทางเข้าระบบสมาชิกและเจ้าหน้าที่ รวมถึงลิงก์ PDPA ยังคงแสดงเพื่อให้เข้าถึงระบบได้เสมอ</p>
   {error&&<p role="alert" style={{color:'#b42318'}}>{error}</p>}
   {message&&<p role="status">{message}</p>}
   {loading&&<p role="status">กำลังโหลดการตั้งค่า...</p>}
   {!loading&&!state&&<button type="button" onClick={load}>ลองใหม่</button>}
   {state&&settings&&<>
      <div style={{border:'1px solid var(--border-subtle)',borderRadius:12,padding:'0 14px'}}>
        {keys.map(key=><label key={key} style={{display:'flex',gap:14,alignItems:'center',justifyContent:'space-between',padding:'12px 0',borderBottom:'1px solid var(--border-subtle)',flexWrap:'wrap'}}>
          <span>{state.sections?.[key]||key}</span>
          <span><input type="checkbox" disabled={busy} checked={settings[key]} onChange={e=>{setSettings(prev=>({...prev,[key]:e.target.checked}));setMessage('');}}/> {settings[key]?'แสดง':'ซ่อน'}</span>
        </label>)}
      </div>
      <p role="status">{dirty?'มีการแก้ไขที่ยังไม่ได้บันทึก':state.draft?'มีร่างรออนุมัติเผยแพร่':'ใช้ค่าที่เผยแพร่แล้ว'} · ค่าที่เผยแพร่: {keys.filter(key=>state.published[key]).length}/{keys.length} ส่วน</p>
      <label htmlFor="site-chrome-totp">รหัส MFA (TOTP) 6 หลัก</label>
      <input id="site-chrome-totp" type="password" inputMode="numeric" autoComplete="one-time-code" maxLength={6} pattern="[0-9]{6}" value={totp} onChange={e=>setTotp(e.target.value.replace(/\D/g,'').slice(0,6))} style={{display:'block',maxWidth:260,margin:'8px 0 14px',padding:10}}/>
      <div style={{display:'flex',flexWrap:'wrap',gap:10}}>
        <button type="button" disabled={busy||!dirty||!validTotp} onClick={()=>save('draft')}>1. บันทึกร่าง</button>
        <button type="button" disabled={busy||dirty||!state.draft||!validTotp} onClick={async ()=>{
          const confirmed = await showConfirm('ยืนยันเผยแพร่?', 'ยืนยันเผยแพร่การเปิด–ปิดเมนูและ Footer?');
          if(confirmed) save('publish');
        }}>2. อนุมัติเผยแพร่</button>
        <button type="button" disabled={busy} onClick={async ()=>{
          if(dirty){
            const confirmed = await showConfirm('ยกเลิกการแก้ไข?', 'ยกเลิกการแก้ไขที่ยังไม่ได้บันทึก?');
            if(!confirmed) return;
          }
          load();
        }}>โหลดล่าสุด</button>
      </div>
   </>}
 </section>;
}
