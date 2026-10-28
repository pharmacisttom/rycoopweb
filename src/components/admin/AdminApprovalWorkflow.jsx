import React, { useEffect, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { memberAdminApi } from '../../services/memberAdminApi';
import { useAdminAction } from './AdminActionProvider';
import { AdminCard, AdminDataTable, AdminStatusBadge, AdminFormField, AdminModal, AdminErrorState, AdminSkeleton } from './AdminUI';
const checkerPermissions={'backup.restore.request':['backup.restore.approve'],'backup.delete':['backup.restore.approve'],'roles.manage':['roles.manage','permissions.manage'],'permissions.manage':['permissions.manage'],'security.sessions.revoke':['security.sessions.revoke','security.view'],'settings.update':['settings.update']};
function asBusinessDate(value){return value?new Date(/[zZ]|[+-]\d\d:\d\d$/.test(value)?value:value.replace(' ','T')+'+07:00'):null;}
export function approvalIsExpired(request){const date=asBusinessDate(request.expires_at);return date&&!Number.isNaN(date.getTime())&&date.getTime()<=Date.now();}
export function canReviewApproval(request,user,can=()=>false){return !!user&&Number.isSafeInteger(Number(user.id))&&Number(user.id)>0&&Number(request.requested_by)!==Number(user.id)&&request.status==='pending'&&!approvalIsExpired(request)&&(checkerPermissions[request.action_key]||[]).some(permission=>can(permission));}
function time(value){const date=asBusinessDate(value);return date&&!Number.isNaN(date.getTime())?date.toLocaleString('th-TH',{timeZone:'Asia/Bangkok'}):'—';}
export default function AdminApprovalWorkflow({ backups = [] }){
  const {user,can}=useAuth();const {run,busy}=useAdminAction();
  const [data,setData]=useState(null),[status,setStatus]=useState('all'),[refresh,setRefresh]=useState(0),[loading,setLoading]=useState(true),[error,setError]=useState(''),[message,setMessage]=useState('');
  const [selection,setSelection]=useState(null),[reason,setReason]=useState('');
  const allowed=['backup.view','backup.restore.request','backup.restore.approve','security.view','audit.view','operations.view','dashboard.view'].some(can);
  useEffect(()=>{if(!allowed){setLoading(false);return;}const controller=new AbortController();setLoading(true);setError('');memberAdminApi(`/api/admin/approvals?status=${encodeURIComponent(status)}&limit=50`,{signal:controller.signal}).then(setData).catch(exception=>{if(exception.name!=='AbortError')setError(exception.message);}).finally(()=>{if(!controller.signal.aborted)setLoading(false);});return()=>controller.abort();},[allowed,status,refresh]);
  function choose(type,request){setError('');setReason('');setSelection({type,request});}
  async function submit(event){event.preventDefault();const {type,request}=selection;
    let path,body,scope='general';
    if(type==='create'){path='/api/admin/backups/restore-request';body={file:request.filename,reason};}
    else if(type==='execute'){path='/api/admin/backups/restore-execute';body={approval_request_id:request.id,file:request.target_id};scope='backup_restore_execute';}
    else{path=`/api/admin/approvals/${request.id}/${type}`;body={review_note:reason,reason,expected_action:request.action_key,expected_target:request.target_id};scope=`dual_control_approve:${request.action_key}`;}
    try{await run(signal=>memberAdminApi(path,{data:body,signal}),{label:`${type==='approve'?'อนุมัติ':type==='reject'?'ไม่อนุมัติ':type==='cancel'?'ยกเลิก':type==='execute'?'กู้คืนข้อมูล':'สร้างคำขอกู้คืน'} ${request.request_no||request.filename}`,scope});setSelection(null);setMessage('บันทึกผลและประวัติแล้ว');setRefresh(value=>value+1);}
    catch(exception){if(exception.code!=='ACTION_CANCELLED'&&exception.name!=='AbortError')setError(exception.code==='RESTORE_EXECUTION_DISABLED_BY_POLICY'?'ระบบล็อกการกู้คืนตามนโยบาย ผู้ดูแลต้องอนุมัติการเปิดใช้งานแยกต่างหาก':`${exception.message}${exception.reference?` (อ้างอิง ${exception.reference})`:''}`);}
  }
  if(!allowed)return null;
  const columns=[{key:'request_no',label:'คำขอ / ประเภท',width:'23%',render:request=><><strong>{request.request_no}</strong><small>{request.action_key}</small><small>{request.target_id}</small></>},
    {key:'maker',label:'ผู้สร้าง / ผู้ตรวจ',width:'20%',render:request=><>{request.requested_by_name||request.requested_by_username||`#${request.requested_by}`}<small>ผู้ตรวจ: {request.reviewed_by_name||'—'}</small></>},
    {key:'time',label:'เวลา (ไทย)',width:'24%',render:request=><>สร้าง: {time(request.created_at)}<small>หมดอายุ: {time(request.expires_at)}</small></>},
    {key:'status',label:'สถานะ',width:'13%',render:request=><AdminStatusBadge status={request.status==='pending'&&approvalIsExpired(request)?'expired':request.status}/>},
    {key:'actions',label:'ดำเนินการ',width:'20%',render:request=><div className="admin-actions"><button className="acc-btn" disabled={busy} onClick={()=>choose('detail',request)}>รายละเอียด</button>
      {canReviewApproval(request,user,can)&&<><button className="acc-btn primary" disabled={busy} onClick={()=>choose('approve',request)}>อนุมัติ</button><button className="acc-btn danger" disabled={busy} onClick={()=>choose('reject',request)}>ไม่อนุมัติ</button></>}
      {Number(request.requested_by)===Number(user?.id)&&request.status==='pending'&&<button className="acc-btn" disabled={busy} onClick={()=>choose('cancel',request)}>ยกเลิกคำขอ</button>}
      {request.action_key==='backup.restore.request'&&request.status==='approved'&&!request.executed_at&&can('backup.restore.request')&&<button className="acc-btn danger" disabled={busy||data?.restore_execution_enabled!==true||approvalIsExpired(request)} onClick={()=>choose('execute',request)}>กู้คืนตามคำขอ</button>}
    </div>},
  ];
  return <AdminCard title="คำขออนุมัติและการแยกหน้าที่ (Maker / Checker)" description="แสดงไม่เกิน 50 คำขอล่าสุดตามตัวกรอง ผู้สร้างคำขออนุมัติคำขอตนเองไม่ได้ รวมถึง Super Admin" actions={<button className="acc-btn" disabled={loading||busy} onClick={()=>setRefresh(value=>value+1)}>โหลดคำขอล่าสุด</button>}>
    <div className="admin-notice">{!data?'ยังไม่ได้รับข้อมูลนโยบายจากเซิร์ฟเวอร์':data.restore_execution_enabled===true?'เซิร์ฟเวอร์รายงานว่าเปิดการกู้คืนแล้ว ต้องตรวจคำอนุมัติและยืนยัน MFA ก่อนดำเนินการ':'การกู้คืนข้อมูลถูกล็อกตามนโยบาย (BACKUP_RESTORE_EXECUTION_ENABLED=false) หน้านี้ไม่เปิดใช้งานให้อัตโนมัติ'}</div>
    <AdminFormField label="สถานะคำขอ"><select value={status} onChange={event=>setStatus(event.target.value)}>{['all','pending','approved','rejected','expired','cancelled'].map(value=><option key={value} value={value}>{value}</option>)}</select></AdminFormField>
    {error&&<AdminErrorState message={error}/>} {message&&<p role="status">{message}</p>}{loading?<AdminSkeleton/>:<AdminDataTable label="คำขออนุมัติ" rows={data?.requests||[]} columns={columns}/>}
    {can('backup.restore.request')&&backups.length>0&&<div className="admin-actions">{backups.map(backup=><button key={backup.filename} className="acc-btn" disabled={busy} onClick={()=>choose('create',backup)}>ขอกู้คืน: {backup.filename}</button>)}</div>}
    <AdminModal open={!!selection} title={selection?.type==='detail'?'รายละเอียดและประวัติคำขอ':'ตรวจสอบคำขอสำคัญ'} busy={busy} onClose={()=>setSelection(null)}>
      {selection&&<><p>{selection.request.request_no||selection.request.filename} · {selection.request.action_key}</p>{error&&<AdminErrorState message={error}/>}
        {selection.type==='detail'?<><p>เหตุผล: {selection.request.reason}</p><ol className="admin-audit-timeline"><li>สร้างโดย {selection.request.requested_by_name||selection.request.requested_by} · {time(selection.request.created_at)}</li>
          {selection.request.reviewed_at&&<li>ตรวจโดย {selection.request.reviewed_by_name||selection.request.reviewed_by} · {time(selection.request.reviewed_at)}<p>{selection.request.review_note}</p></li>}
          {selection.request.executed_at&&<li>ดำเนินการแล้ว · {time(selection.request.executed_at)}</li>}</ol><p>แสดงเหตุการณ์จากข้อมูลคำขอที่ API ส่งกลับ ประวัติ Audit ฉบับเต็มตรวจได้ในเมนูบันทึกการตรวจสอบ</p></>:
          <form onSubmit={submit}><p className="admin-warning">ตรวจผู้สร้าง ผู้ตรวจ เป้าหมาย และเหตุผลก่อนยืนยัน เซิร์ฟเวอร์ตรวจสิทธิ์และสถานะคำขอซ้ำทุกครั้ง</p><AdminFormField label="เหตุผล / หมายเหตุ" required><textarea required minLength={selection.type==='create'?10:5} maxLength={1000} value={reason} onChange={event=>setReason(event.target.value)} disabled={busy}/></AdminFormField><button className="acc-btn primary" disabled={busy}>ยืนยันคำสั่งที่เลือก</button></form>}
      </>}
    </AdminModal>
  </AdminCard>;
}
