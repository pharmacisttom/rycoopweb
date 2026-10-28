import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { memberAdminApi } from '../services/memberAdminApi';
import { normalizeSameOriginRedirect } from '../utils/navigation';
import { useAuth } from '../context/AuthContext';
import './LoginPage.css';

export default function MfaPage({ mode }) {
  const navigate = useNavigate();
  const { refreshSession, logout } = useAuth();
  async function finish(target) { setBusy(true); try { await refreshSession(); navigate(target, { replace: true }); } catch(e) { setError(e.message); } finally { setBusy(false); } }
  const [status,setStatus]=useState(null),[setup,setSetup]=useState(null),[code,setCode]=useState('');
  const [recovery,setRecovery]=useState(false),[codes,setCodes]=useState(null),[destination,setDestination]=useState('/admin/workspace');
  const [busy,setBusy]=useState(false),[error,setError]=useState('');
  useEffect(()=>{const controller=new AbortController();setError('');setSetup(null);setCodes(null);setCode('');setRecovery(false);
    memberAdminApi('/api/auth/mfa/status',{signal:controller.signal}).then(result=>{
      if(!result.pending_enrollment&&!result.pending_verification){navigate('/admin/workspace',{replace:true});return;}
      if(mode==='enroll'&&!result.pending_enrollment){navigate('/admin/mfa/verify',{replace:true});return;}
      if(mode==='verify'&&result.pending_enrollment){navigate('/admin/mfa/enroll',{replace:true});return;}
      setStatus(result);
    }).catch(e=>{if(e.name!=='AbortError')setError(e.message);});return()=>controller.abort();
  },[mode,navigate]);
  async function start(){setBusy(true);setError('');try{setSetup(await memberAdminApi('/api/auth/mfa/enroll',{data:{}}));}catch(e){setError(e.message);}finally{setBusy(false);}}
  async function submit(e){e.preventDefault();if(busy)return;setBusy(true);setError('');try{
    const endpoint=mode==='enroll'?'confirm':recovery?'recovery':'verify';
    const result=await memberAdminApi(`/api/auth/mfa/${endpoint}`,{data:recovery?{recovery_code:code}:{code}});
    const target=normalizeSameOriginRedirect(result.redirect,'/admin/workspace');
    if(result.recovery_codes){setCodes(result.recovery_codes);setDestination(target);setSetup(null);setCode('');}
    else await finish(target);
  }catch(e){setError(e.message);}finally{setBusy(false);}}
  return <section className="container section" style={{maxWidth:620}}>
    <h1>{mode==='enroll'?'ตั้งค่าการยืนยันตัวตนสองขั้นตอน':'ยืนยันตัวตนสองขั้นตอน'}</h1>
    <p>ขั้นตอนนี้หมดอายุใน 10 นาที หากหมดอายุให้เข้าสู่ระบบใหม่</p>
    {error&&<p role="alert" className="member-entry-error">{error}</p>}
    {!status&&!error&&<p role="status">กำลังตรวจสอบเซสชัน…</p>}
    {codes?<><h2>บันทึกรหัสสำรองก่อนดำเนินการต่อ</h2><p>แสดงครั้งนี้ครั้งเดียว แต่ละรหัสใช้ได้หนึ่งครั้ง เก็บไว้ในที่ปลอดภัย</p><ul>{codes.map(c=><li key={c}><code>{c}</code></li>)}</ul><button className="btn btn-primary" disabled={busy} onClick={()=>finish(destination)}>บันทึกรหัสแล้ว เข้าพื้นที่ทำงาน</button></>:
      status&&<>
        {mode==='enroll'&&!setup&&<button className="btn btn-primary" disabled={busy} onClick={start}>เริ่มตั้งค่าด้วยแอป Authenticator</button>}
        {setup&&<><p>สแกน QR ด้วยแอป Authenticator หรือกรอกรหัสตั้งค่าด้านล่าง</p><img src={setup.qr_code_uri} style={{maxWidth:'100%',height:'auto'}} alt="QR ตั้งค่า MFA สำหรับบัญชีของคุณ"/><p><code>{setup.formatted_secret}</code></p></>}
        {(setup||mode==='verify')&&<form onSubmit={submit}>
          <label htmlFor="mfa-code">{recovery?'รหัสสำรอง':'รหัสยืนยัน 6 หลัก'}</label>
          <input id="mfa-code" className="form-control" autoComplete="one-time-code" inputMode={recovery?'text':'numeric'} value={code} onChange={e=>setCode(e.target.value)} maxLength={recovery?40:6} required pattern={recovery?undefined:'[0-9]{6}'} />
          <button className="btn btn-primary" disabled={busy}>{busy?'กำลังตรวจสอบ…':'ยืนยัน'}</button>
        </form>}
        {mode==='verify'&&<button className="btn btn-outline" disabled={busy} onClick={()=>{setRecovery(v=>!v);setCode('');setError('');}}>{recovery?'ใช้แอป Authenticator':'ใช้รหัสสำรอง'}</button>}
      </>}
    <p><button type="button" className="btn btn-outline" disabled={busy} onClick={async()=>{await logout();navigate('/admin/login',{replace:true});}}>เริ่มเข้าสู่ระบบใหม่</button></p>
  </section>;
}
