import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { memberAdminApi } from '../../services/memberAdminApi';
import { AdminFormField, AdminModal } from './AdminUI';

const Context=createContext(null);
const stepUpCodes=new Set(['STEP_UP_MFA_REQUIRED','STEP_UP_MFA_EXPIRED','STEP_UP_MFA_ALREADY_CONSUMED']);
export function useAdminAction(){const context=useContext(Context);if(!context)throw new Error('Admin action provider is required');return context;}
export default function AdminActionProvider({children}){
  const location=useLocation();
  const [prompt,setPrompt]=useState(null),[code,setCode]=useState(''),[error,setError]=useState(''),[verifying,setVerifying]=useState(false),[busy,setBusy]=useState(false);
  const resolver=useRef(null),locked=useRef(false),active=useRef(true),controller=useRef(null),verificationLock=useRef(false);
  useEffect(()=>{active.current=true;return()=>{active.current=false;controller.current?.abort();resolver.current?.(false);resolver.current=null;};},[]);
  useEffect(()=>{controller.current?.abort();resolver.current?.(false);resolver.current=null;setPrompt(null);setCode('');setError('');},[location.pathname,location.search]);
  function finish(value){resolver.current?.(value);resolver.current=null;setPrompt(null);setCode('');setError('');}
  const ask=useCallback(options=>new Promise(resolve=>{resolver.current=resolve;setError('');setCode('');setPrompt(options);}),[]);
  const run=useCallback(async(operation,{label='ดำเนินการ',scope='general',confirm}={})=>{
    if(locked.current)throw Object.assign(new Error('มีคำสั่งกำลังดำเนินการ กรุณารอสักครู่'),{code:'ACTION_IN_PROGRESS'});
    locked.current=true;setBusy(true);const invocation=new AbortController();controller.current=invocation;
    try{
      if(confirm&&!await ask({type:'confirm',label,description:confirm}))throw Object.assign(new Error('ยกเลิกการดำเนินการแล้ว'),{code:'ACTION_CANCELLED'});
      if(!active.current||invocation.signal.aborted)throw new DOMException('Aborted','AbortError');
      try{return await operation(invocation.signal);}
      catch(exception){
        // Retry exactly once, only after an explicit security-gate rejection, never after network/500 errors.
        if(invocation.signal.aborted)throw new DOMException('Aborted','AbortError');
        if(exception.status!==403||!stepUpCodes.has(exception.code))throw exception;
        if(!await ask({type:'mfa',label,scope}))throw Object.assign(new Error('ยกเลิกการยืนยันตัวตนแล้ว'),{code:'ACTION_CANCELLED'});
        if(!active.current||invocation.signal.aborted)throw new DOMException('Aborted','AbortError');
        return await operation(invocation.signal);
      }
    }finally{locked.current=false;controller.current=null;if(active.current)setBusy(false);}
  },[ask]);
  async function verify(event){
    event.preventDefault();if(verificationLock.current||!/^\d{6}$/.test(code))return;
    verificationLock.current=true;setVerifying(true);setError('');
    const submitted=code;setCode('');
    try{await memberAdminApi('/api/auth/mfa/step-up',{data:{code:submitted,scope:prompt.scope},signal:controller.current?.signal});if(active.current)finish(true);}
    catch(exception){if(active.current&&exception.name!=='AbortError')setError(exception.status===401?'เซสชันหมดอายุหรือถูกเพิกถอน กรุณาเข้าสู่ระบบใหม่':exception.status===429?'ลองหลายครั้งเกินกำหนด กรุณารอแล้วลองใหม่':exception.code==='STEP_UP_MFA_REPLAY_BLOCKED'?'รหัสนี้ถูกใช้แล้ว กรุณารอรหัสรอบใหม่':exception.message||'ยืนยันตัวตนไม่สำเร็จ');}
    finally{verificationLock.current=false;if(active.current)setVerifying(false);}
  }
  return <Context.Provider value={{run,busy}}>{children}
    <AdminModal open={!!prompt} title={prompt?.type==='mfa'?'ยืนยันตัวตนสำหรับคำสั่งสำคัญ':'ตรวจสอบก่อนดำเนินการ'} busy={verifying} onClose={()=>finish(false)}>
      <p><strong>{prompt?.label}</strong></p>
      {prompt?.type==='confirm'?<><p>{prompt.description}</p><div className="admin-actions"><button type="button" className="acc-btn primary" onClick={()=>finish(true)}>ยืนยันดำเนินการ</button><button type="button" className="acc-btn" onClick={()=>finish(false)}>ยกเลิก</button></div></>:
        <form onSubmit={verify}><p>กรอกรหัสปัจจุบันจาก Authenticator การยืนยันนี้ใช้สำหรับคำสั่งที่ระบุเพียงครั้งเดียว</p>
          <AdminFormField label="รหัสยืนยัน 6 หลัก" required><input type="text" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} required value={code} disabled={verifying} onChange={event=>setCode(event.target.value.replace(/\D/g,'').slice(0,6))}/></AdminFormField>
          {error&&<p role="alert" className="admin-error">{error}</p>}
          <div className="admin-actions"><button className="acc-btn primary" disabled={verifying||code.length!==6}>{verifying?'กำลังตรวจสอบ…':'ยืนยันและทำคำสั่งนี้'}</button><button type="button" className="acc-btn" disabled={verifying} onClick={()=>finish(false)}>ยกเลิก</button></div>
        </form>}
    </AdminModal>
  </Context.Provider>;
}
