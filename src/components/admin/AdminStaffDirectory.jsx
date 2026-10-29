import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { memberAdminApi } from '../../services/memberAdminApi';
import { useAdminAction } from './AdminActionProvider';
import { AdminCard, AdminDataTable, AdminStatusBadge, AdminFormField, AdminPagination, AdminErrorState } from './AdminUI';
export default function AdminStaffDirectory({ staff = [], onRefresh }) {
  const {user,can}=useAuth();const {run,busy}=useAdminAction();
  const [search,setSearch]=useState(''),[status,setStatus]=useState('all'),[page,setPage]=useState(1),[error,setError]=useState(''),[message,setMessage]=useState('');
  const rows=useMemo(()=>{const grouped=new Map();for(const entry of staff){const previous=grouped.get(Number(entry.id));if(previous)previous.roleLabels.push(entry.role_name||entry.role_slug);else grouped.set(Number(entry.id),{...entry,roleLabels:[entry.role_name||entry.role_slug]});}return [...grouped.values()].filter(entry=>`${entry.name} ${entry.username} ${entry.email}`.toLowerCase().includes(search.toLowerCase())&&(status==='all'||entry.status===status));},[staff,search,status]);
  const current=Math.min(page,Math.max(1,Math.ceil(rows.length/25)));
  async function action(entry,suffix,scope,label){setError('');setMessage('');try{await run(signal=>memberAdminApi(`/api/admin/staff/${entry.id}/${suffix}`,{data:{},signal}),{label:`${label}: ${entry.name}`,scope,confirm:`ตรวจสอบบัญชี ${entry.username} ก่อนดำเนินการ คำสั่งนี้มีผลต่อการเข้าใช้ระบบของเจ้าหน้าที่`});setMessage(`${label}สำเร็จ`);onRefresh?.();}catch(exception){if(exception.code!=='ACTION_CANCELLED'&&exception.name!=='AbortError')setError(exception.message);}}
  const columns=[{key:'name',label:'เจ้าหน้าที่',width:'25%',render:entry=><><strong>{entry.name}</strong><small>{entry.username} · {entry.email}</small></>},
    {key:'roleLabels',label:'บทบาท',width:'18%',render:entry=>entry.roleLabels.join(', ')},
    {key:'status',label:'สถานะ',width:'15%',render:entry=><AdminStatusBadge status={entry.status}/>},
    {key:'mfa',label:'MFA / Google',width:'18%',render:entry=><>{entry.mfa?.enrolled?'MFA ยืนยันแล้ว':'MFA ยังไม่ยืนยัน'}<small>{entry.google?.linked?'ผูก Google แล้ว':'ยังไม่ผูก Google'}</small></>},
    {key:'actions',label:'คำสั่งสำคัญ',width:'24%',render:entry=><div className="admin-actions">
      {user?.role==='super_admin'&&can('users.suspend')&&entry.status==='active'&&<button className="acc-btn danger" disabled={busy||Number(entry.id)===Number(user.id)} onClick={()=>action(entry,'suspend','staff.suspend','ระงับบัญชี')}>ระงับ</button>}
      {user?.role==='super_admin'&&can('users.update')&&entry.status!=='active'&&<button className="acc-btn" disabled={busy} onClick={()=>action(entry,'activate','staff.activate','เปิดใช้บัญชี')}>เปิดใช้</button>}
      {user?.role==='super_admin'&&can('security.sessions.revoke')&&<button className="acc-btn" disabled={busy} onClick={()=>action(entry,'revoke-sessions','security.sessions.revoke','เพิกถอนเซสชัน')}>เพิกถอนเซสชัน</button>}
    </div>},
  ];
  return <AdminCard title="เจ้าหน้าที่และสถานะการยืนยันตัวตน" description="ข้อมูลจริงจาก API เจ้าหน้าที่ รวมบทบาทของบัญชีเดียวกันก่อนแสดง" actions={user?.role==='super_admin'&&<Link className="acc-btn" to="/admin/access">เพิ่ม / แก้ไขเจ้าหน้าที่</Link>}>
    <div className="admin-toolbar"><AdminFormField label="ค้นหาชื่อ ชื่อผู้ใช้ อีเมล"><input type="search" value={search} onChange={event=>{setSearch(event.target.value);setPage(1);}}/></AdminFormField><AdminFormField label="สถานะเจ้าหน้าที่"><select value={status} onChange={event=>{setStatus(event.target.value);setPage(1);}}><option value="all">ทั้งหมด</option><option value="active">ใช้งาน</option><option value="inactive">ไม่ใช้งาน</option><option value="suspended">ระงับ</option></select></AdminFormField></div>
    {error&&<AdminErrorState message={error}/>} {message&&<p role="status">{message}</p>}
    <AdminDataTable label="ทะเบียนเจ้าหน้าที่และ MFA" rows={rows.slice((current-1)*25,current*25)} columns={columns}/><AdminPagination page={current} total={rows.length} onChange={setPage} disabled={busy}/>
  </AdminCard>;
}
