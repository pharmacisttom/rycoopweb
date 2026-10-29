import React, { useState } from 'react';
import { memberAdminApi } from '../../services/memberAdminApi';
import { AdminCard, AdminFormField, AdminDataTable, AdminStatusBadge, AdminErrorState } from './AdminUI';

export default function PrOfficerInvitations({ roles = [], invitations = [], onRefresh }) {
  const role = roles.find(item => item.slug === 'pr_officer');
  const [open,setOpen]=useState(false),[busy,setBusy]=useState(false),[error,setError]=useState(''),[link,setLink]=useState('');
  const [form,setForm]=useState({name:'',email:'',department:''});
  async function submit(event) {
    event.preventDefault(); if(busy||!role)return;
    setBusy(true);setError('');setLink('');
    try { const result=await memberAdminApi('/api/admin/staff/invite',{data:{...form,role_id:Number(role.id)}});setLink(result.invitation.invitation_url);setForm({name:'',email:'',department:''});setOpen(false); }
    catch(exception){setError(exception.message);}finally{setBusy(false);}
  }
  async function change(invitation,action) {
    if(busy)return;setBusy(true);setError('');setLink('');
    try {const result=await memberAdminApi(`/api/admin/staff/invitations/${invitation.id}/${action}`,{data:{}});setLink(result.invitation?.invitation_url||'');if(!result.invitation?.invitation_url)onRefresh?.();}
    catch(exception){setError(exception.message);}finally{setBusy(false);}
  }
  return <AdminCard title="คำเชิญเจ้าหน้าที่ประชาสัมพันธ์" description="ใช้ Google ตามอีเมลที่เชิญ และต้องยืนยัน 2FA ก่อนใช้งาน CMS" actions={<button className="acc-btn primary" disabled={!role||busy} onClick={()=>{setOpen(!open);setLink('');}}>เชิญเจ้าหน้าที่ประชาสัมพันธ์</button>}>
    {!role&&<AdminErrorState message="ไม่พบบทบาท pr_officer กรุณาตรวจสอบการตั้งค่าสิทธิ์เดิม ห้ามใช้บทบาทอื่นแทน"/>}
    {error&&<AdminErrorState message={error}/>}
    {open&&<form onSubmit={submit} className="admin-toolbar">
      <AdminFormField label="ชื่อ-นามสกุล" required><input required maxLength={100} value={form.name} onChange={e=>setForm({...form,name:e.target.value})}/></AdminFormField>
      <AdminFormField label="อีเมล Google" required><input type="email" required maxLength={100} value={form.email} onChange={e=>setForm({...form,email:e.target.value})}/></AdminFormField>
      <AdminFormField label="หน่วยงาน"><input maxLength={100} value={form.department} onChange={e=>setForm({...form,department:e.target.value})}/></AdminFormField>
      <p>บทบาท: PR Officer (pr_officer) · คำเชิญมีอายุ 7 วัน</p><button className="acc-btn primary" disabled={busy}>สร้างคำเชิญ</button>
    </form>}
    {link&&<div role="status"><p>ส่งลิงก์นี้ให้เจ้าหน้าที่ผ่านช่องทางที่เชื่อถือได้ ระบบยังไม่ได้ส่งอีเมลอัตโนมัติ ลิงก์จะแสดงเฉพาะครั้งนี้</p><input aria-label="ลิงก์คำเชิญ" readOnly value={link} onFocus={e=>e.target.select()}/><button className="acc-btn" onClick={()=>{setLink('');onRefresh?.();}}>ซ่อนลิงก์</button></div>}
    <AdminDataTable label="คำเชิญประชาสัมพันธ์" rows={invitations.filter(item=>item.role_slug==='pr_officer')} columns={[
      {key:'name',label:'เจ้าหน้าที่',render:item=><>{item.name}<small>{item.email} · {item.department}</small></>},
      {key:'status',label:'สถานะ',render:item=><AdminStatusBadge status={item.status}>{({pending:'รอยืนยัน',accepted:'รับคำเชิญแล้ว',revoked:'ยกเลิก',expired:'หมดอายุ'})[item.status]||item.status}</AdminStatusBadge>},
      {key:'expires_at',label:'หมดอายุ'},
      {key:'actions',label:'จัดการ',render:item=>item.status!=='accepted'&&<div className="admin-actions"><button className="acc-btn" disabled={busy} onClick={()=>change(item,'resend')}>ต่ออายุ / สร้างลิงก์ใหม่</button>{item.status==='pending'&&<button className="acc-btn danger" disabled={busy} onClick={()=>change(item,'revoke')}>ยกเลิกคำเชิญ</button>}</div>}
    ]}/>
    <p>การกู้คืน 2FA: เจ้าหน้าที่ใช้ Recovery code แบบใช้ครั้งเดียวในหน้ายืนยัน 2FA หากไม่มีทั้งเครื่องและรหัสกู้คืน ให้ตรวจสอบตัวตนกับผู้รับผิดชอบความปลอดภัยตามขั้นตอนอนุมัติ ห้ามส่ง OTP หรือ secret ให้แอดมิน</p>
  </AdminCard>;
}
