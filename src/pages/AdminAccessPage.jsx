import React, { useEffect, useMemo, useState } from 'react';
import { memberAdminApi } from '../services/memberAdminApi';
import { useAuth } from '../context/AuthContext';
import { useAdminAction } from '../components/admin/AdminActionProvider';
import { AdminPageHeader, AdminCard, AdminDataTable, AdminStatusBadge, AdminFormField, AdminPagination, AdminModal, AdminPermissionMatrix, AdminErrorState, AdminSkeleton } from '../components/admin/AdminUI';
const emptyRole=()=>({name:'',slug:'',description:'',permissions:[]});
const emptyUser=()=>({name:'',username:'',email:'',status:'active',role_id:'',password:''});
export default function AdminAccessPage(){
  const {user,can}=useAuth();const {run,busy}=useAdminAction();
  const [catalog,setCatalog]=useState(null),[accounts,setAccounts]=useState(null),[directory,setDirectory]=useState([]),[mfaError,setMfaError]=useState('');
  const [role,setRole]=useState(null),[account,setAccount]=useState(null),[search,setSearch]=useState(''),[query,setQuery]=useState(''),[page,setPage]=useState(1),[refresh,setRefresh]=useState(0);
  const [roleSearch,setRoleSearch]=useState(''),[roleFilter,setRoleFilter]=useState('all'),[status,setStatus]=useState('all'),[loading,setLoading]=useState(true),[error,setError]=useState(''),[message,setMessage]=useState('');
  useEffect(()=>{
    const controller=new AbortController();setLoading(true);setError('');
    Promise.all([memberAdminApi('/api/admin/access/roles',{signal:controller.signal}),memberAdminApi(`/api/admin/access/users?q=${encodeURIComponent(query)}&page=${page}`,{signal:controller.signal})])
      .then(([roles,users])=>{if(!controller.signal.aborted){setCatalog(roles);setAccounts(users);}}).catch(exception=>{if(exception.name!=='AbortError')setError(exception.message);}).finally(()=>{if(!controller.signal.aborted)setLoading(false);});
    return()=>controller.abort();
  },[query,page,refresh]);
  useEffect(()=>{const controller=new AbortController();setMfaError('');memberAdminApi('/api/admin/staff',{signal:controller.signal}).then(result=>setDirectory(result.staff||[])).catch(exception=>{if(exception.name!=='AbortError'){setDirectory([]);setMfaError('สถานะ MFA ไม่พร้อมใช้งาน');}});return()=>controller.abort();},[refresh]);
  async function mutate(path,data,label,scope,onSuccess,confirmation){
    setError('');setMessage('');
    try{await run(signal=>memberAdminApi(path,{data,signal}),{label,scope,confirm:confirmation});onSuccess?.();setMessage('บันทึกสำเร็จ ระบบเก็บประวัติการเปลี่ยนแปลงแล้ว');setRefresh(value=>value+1);}
    catch(exception){if(!['ACTION_CANCELLED','AbortError'].includes(exception.code||exception.name))setError(`${exception.message}${exception.reference?` (อ้างอิง ${exception.reference})`:''}`);}
  }
  const selectableRoles=(catalog?.roles||[]).filter(item=>item.slug!=='member');
  const roles=(catalog?.roles||[]).filter(item=>(`${item.name} ${item.slug}`.toLowerCase().includes(roleSearch.toLowerCase()))&&(roleFilter==='all'||(roleFilter==='protected'?item.protected:!item.builtin)));
  const users=(accounts?.users||[]).filter(item=>status==='all'||item.status===status);
  const mfaById=useMemo(()=>new Map(directory.map(item=>[Number(item.id),item.mfa])),[directory]);
  const knownPermissions=new Set(Object.entries(catalog?.catalog||{}).flatMap(([module,actions])=>actions.map(action=>`${module}.${action}`)));
  const unsupported=role?.permissions.filter(permission=>!knownPermissions.has(permission))||[];
  const roleColumns=[
    {key:'name',label:'บทบาท',width:'27%',render:item=><><strong>{item.name}</strong><small>{item.slug}</small></>},
    {key:'users',label:'ผู้ใช้',numeric:true,width:'10%'},
    {key:'permissions',label:'สิทธิ์',numeric:true,width:'10%',render:item=>item.permissions.length},
    {key:'type',label:'ประเภท',width:'20%',render:item=><AdminStatusBadge>{item.protected?'ป้องกันการแก้ไข':item.builtin?'บทบาทมาตรฐาน':'กำหนดเอง'}</AdminStatusBadge>},
    {key:'actions',label:'ดำเนินการ',width:'33%',render:item=><div className="admin-actions"><button className="acc-btn" disabled={busy||loading} onClick={()=>setRole({...item,permissions:[...item.permissions]})}>{item.protected?'ดูสิทธิ์':'แก้ไข'}</button>{!item.builtin&&can('roles.manage')&&<button className="acc-btn danger" disabled={busy||loading||item.users>0} onClick={()=>mutate(`/api/admin/access/roles/${item.id}/delete`,{version:item.version},`ลบบทบาท ${item.name}`,'roles.delete',()=>setRole(null),'ลบบทบาทนี้เฉพาะเมื่อไม่มีผู้ใช้งาน การดำเนินการมีผลต่อการควบคุมสิทธิ์ของระบบ')}>ลบ</button>}</div>},
  ];
  const userColumns=[
    {key:'name',label:'เจ้าหน้าที่',width:'27%',render:item=><><strong>{item.name}</strong><small>{item.username}</small><small>{item.email}</small></>},
    {key:'roles',label:'บทบาท',width:'21%',render:item=>item.roles.map(entry=>entry.name).join(', ')},
    {key:'status',label:'สถานะ / MFA',width:'19%',render:item=><><AdminStatusBadge status={item.status}/><small>{mfaById.has(Number(item.id))?(mfaById.get(Number(item.id))?.enrolled?'MFA ยืนยันแล้ว':'MFA ยังไม่ยืนยัน'):'MFA: ไม่มีข้อมูล'}</small></>},
    {key:'actions',label:'ดำเนินการ',width:'33%',render:item=><div className="admin-actions"><button className="acc-btn" disabled={busy||loading||!can('users.update')} onClick={()=>setAccount({...item,role_id:item.roles.length===1?item.roles[0].id:'',password:''})}>แก้ไข</button>
      {can('users.suspend')&&<button className="acc-btn" disabled={busy||Number(item.id)===Number(user.id)} onClick={()=>mutate(`/api/admin/staff/${item.id}/${item.status==='active'?'suspend':'activate'}`,{},`${item.status==='active'?'ระงับ':'เปิดใช้'}บัญชี ${item.username}`,item.status==='active'?'staff.suspend':'staff.activate',null,'การระงับบัญชีจะเพิกถอนเซสชันด้วย ตรวจสอบชื่อบัญชีก่อนยืนยัน')}>{item.status==='active'?'ระงับ':'เปิดใช้'}</button>}
      {can('security.sessions.revoke')&&<button className="acc-btn" disabled={busy} onClick={()=>mutate(`/api/admin/staff/${item.id}/revoke-sessions`,{},`เพิกถอนเซสชัน ${item.username}`,'security.sessions.revoke',null,'เซสชันของบัญชีนี้จะใช้ต่อไม่ได้ รวมถึงหน้าต่างที่เปิดอยู่')}>เพิกถอนเซสชัน</button>}
      {can('users.update')&&<button className="acc-btn danger" disabled={busy||Number(item.id)===Number(user.id)} onClick={()=>mutate(`/api/admin/access/users/${item.id}/delete`,{version:item.version},`ปิดบัญชี ${item.username}`,'users.delete',null,'บัญชีจะถูกปิดและนำออกจากรายการเจ้าหน้าที่ ตรวจสอบให้แน่ใจก่อนดำเนินการ')}>ปิดบัญชี</button>}
    </div>},
  ];
  return <section className="admin-access-page">
    <AdminPageHeader title="จัดการผู้ใช้งานและสิทธิ์" description="บทบาท เจ้าหน้าที่ และการยืนยันคำสั่งสำคัญ · สมาชิกใช้โมดูลดูแลสมาชิก" actions={<button className="acc-btn" disabled={busy||loading} onClick={()=>setRefresh(value=>value+1)}>โหลดข้อมูลล่าสุด</button>}/>
    {error&&<AdminErrorState message={error}/>} {message&&<p className="admin-notice" role="status">{message}</p>}{loading&&<AdminSkeleton/>}
    <AdminCard title="บทบาทและสิทธิ์" description="สิทธิ์มีผลกับผู้ใช้ทุกคนที่ได้รับบทบาทนี้" actions={can('roles.manage')&&<button className="acc-btn primary" disabled={busy||loading} onClick={()=>setRole(emptyRole())}>เพิ่มบทบาท</button>}>
      <div className="admin-toolbar"><AdminFormField label="ค้นหาบทบาท"><input type="search" value={roleSearch} onChange={event=>setRoleSearch(event.target.value)}/></AdminFormField><AdminFormField label="ประเภทบทบาท"><select value={roleFilter} onChange={event=>setRoleFilter(event.target.value)}><option value="all">ทั้งหมด</option><option value="protected">ป้องกันการแก้ไข</option><option value="custom">กำหนดเอง</option></select></AdminFormField></div>
      <AdminDataTable label="บทบาทและจำนวนผู้ใช้งาน" columns={roleColumns} rows={roles} emptyMessage={loading?'กำลังโหลดบทบาท…':'ไม่พบบทบาทตามเงื่อนไข'}/>
    </AdminCard>
    <AdminCard title="บัญชีเจ้าหน้าที่" description="ค้นหาทั้งระบบด้วยชื่อ ชื่อผู้ใช้ หรืออีเมล" actions={can('users.update')&&<button className="acc-btn primary" disabled={busy||loading} onClick={()=>setAccount(emptyUser())}>เพิ่มเจ้าหน้าที่</button>}>
      <form className="admin-toolbar" onSubmit={event=>{event.preventDefault();setPage(1);setQuery(search.trim());}}><AdminFormField label="ค้นหาเจ้าหน้าที่"><input type="search" maxLength={100} value={search} onChange={event=>setSearch(event.target.value)}/></AdminFormField><button className="acc-btn primary" disabled={loading||busy}>ค้นหา</button><AdminFormField label="สถานะ (เฉพาะหน้าปัจจุบัน)"><select value={status} onChange={event=>setStatus(event.target.value)}><option value="all">ทั้งหมด</option><option value="active">ใช้งาน</option><option value="inactive">ไม่ใช้งาน</option><option value="suspended">ระงับ</option></select></AdminFormField></form>
      {mfaError&&<p role="status">{mfaError}</p>}<AdminDataTable label="รายชื่อเจ้าหน้าที่" columns={userColumns} rows={users} emptyMessage={loading?'กำลังโหลดเจ้าหน้าที่…':'ไม่พบเจ้าหน้าที่ตามเงื่อนไข'}/>
      <AdminPagination page={accounts?.page||page} total={Number(accounts?.total||0)} onChange={setPage} disabled={busy||loading}/>
    </AdminCard>
    <AdminModal open={!!role} title={role?.id?'รายละเอียดบทบาท':'เพิ่มบทบาท'} busy={busy} onClose={()=>setRole(null)}>
      {error&&<AdminErrorState message={error}/>}
      {role&&catalog&&<form onSubmit={event=>{event.preventDefault();mutate(role.id?`/api/admin/access/roles/${role.id}/update`:'/api/admin/access/roles',role,`บันทึกบทบาท ${role.name}`,role.id?'roles.update':'roles.create',()=>setRole(null),'ตรวจสอบสิทธิ์ที่เพิ่มหรือลด โดยเฉพาะการเผยแพร่และเข้าถึงข้อมูลสมาชิก ห้ามใช้บทบาทเดียวอนุมัติคำขอของตนเอง');}}><fieldset disabled={busy||role.protected||unsupported.length>0||!can('roles.manage')}>
        <div className="admin-warning">{role.slug==='super_admin'?'Super Admin เป็นบทบาทสำคัญที่ป้องกันการแก้ไข':'การเปลี่ยนสิทธิ์มีผลกับผู้ใช้ทุกคนในบทบาทนี้ และอาจเพิ่มสิทธิ์เข้าถึงข้อมูลสำคัญ'}</div>
        {unsupported.length>0&&<p className="admin-warning">บทบาทนี้มีสิทธิ์นอก catalog ของ API จัดการบทบาทเดิม จึงแสดงให้อ่านเท่านั้นเพื่อรักษาสิทธิ์เดิม: {unsupported.join(', ')}</p>}
        <div className="admin-form-grid"><AdminFormField label="ชื่อบทบาท" required><input required maxLength={50} value={role.name} onChange={event=>setRole({...role,name:event.target.value})}/></AdminFormField><AdminFormField label="รหัสภาษาอังกฤษ" required><input required pattern="[a-z][a-z0-9_]*" maxLength={50} disabled={!!role.id} value={role.slug} onChange={event=>setRole({...role,slug:event.target.value})}/></AdminFormField></div>
        <AdminFormField label="คำอธิบาย"><input maxLength={255} value={role.description||''} onChange={event=>setRole({...role,description:event.target.value})}/></AdminFormField>
        <AdminPermissionMatrix catalog={catalog.catalog} modules={catalog.modules} actions={catalog.actions} value={role.permissions} onChange={permissions=>setRole({...role,permissions})}/>
        <button className="acc-btn primary">บันทึกบทบาท</button>
      </fieldset></form>}
    </AdminModal>
    <AdminModal open={!!account} title={account?.id?'แก้ไขเจ้าหน้าที่':'เพิ่มเจ้าหน้าที่'} busy={busy} onClose={()=>setAccount(null)}>
      {error&&<AdminErrorState message={error}/>}
      {account&&<form onSubmit={event=>{event.preventDefault();const{password,...fields}=account;mutate(account.id?`/api/admin/access/users/${account.id}/update`:'/api/admin/access/users',account.id?fields:{...fields,password},`บันทึกบัญชี ${account.username}`,account.id?'users.update':'users.create',()=>setAccount(null),'ตรวจสอบชื่อบัญชี บทบาท และสถานะก่อนยืนยัน การเพิ่มบทบาทอาจเพิ่มสิทธิ์เข้าถึงข้อมูล');}}><fieldset disabled={busy}>
        <div className="admin-form-grid"><AdminFormField label="ชื่อ" required><input required maxLength={100} value={account.name} onChange={event=>setAccount({...account,name:event.target.value})}/></AdminFormField><AdminFormField label="ชื่อผู้ใช้" required><input required pattern="[A-Za-z][A-Za-z0-9_.\-]{2,49}" maxLength={50} value={account.username} onChange={event=>setAccount({...account,username:event.target.value})}/></AdminFormField><AdminFormField label="อีเมล" required><input required type="email" maxLength={100} value={account.email} onChange={event=>setAccount({...account,email:event.target.value})}/></AdminFormField>
        <AdminFormField label="บทบาท" required><select required value={account.role_id} onChange={event=>setAccount({...account,role_id:event.target.value})}><option value="">เลือกบทบาท</option>{selectableRoles.map(item=><option key={item.id} value={item.id}>{item.name}</option>)}</select></AdminFormField><AdminFormField label="สถานะ"><select value={account.status} onChange={event=>setAccount({...account,status:event.target.value})}><option value="active">ใช้งาน</option><option value="inactive">ไม่ใช้งาน</option><option value="suspended">ระงับ</option></select></AdminFormField>
        {!account.id&&<AdminFormField label="รหัสผ่านเริ่มต้น" required><input type="password" required minLength={12} maxLength={200} autoComplete="new-password" value={account.password} onChange={event=>setAccount({...account,password:event.target.value})}/></AdminFormField>}</div>
        {String(account.role_id)===String(selectableRoles.find(item=>item.slug==='super_admin')?.id)&&<p className="admin-warning">กำลังเลือก Super Admin โปรดตรวจสอบความจำเป็นและหน้าที่ Maker/Checker</p>}
        <button className="acc-btn primary">บันทึกบัญชี</button>
      </fieldset></form>}
    </AdminModal>
  </section>;
}
