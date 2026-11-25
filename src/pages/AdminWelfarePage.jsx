import React, {useEffect,useMemo,useState} from 'react';
import { AdminPageHeader, AdminCard, AdminErrorState, AdminEmptyState, AdminStatusBadge } from '../components/admin/AdminUI';
import './AdminWelfarePage.css';
import {Link} from 'react-router-dom';
import {memberAdminApi} from '../services/memberAdminApi';
import {showDeleteConfirm, showSuccess, showError, confirmPublish, showConfirm} from '../utils/swal';
const blank={category:'',title:'',slug:'',short_description:'',full_description:'',benefit_amount:'',eligibility:'',required_documents:'',application_process:'',contact_info:'',sort_order:0,status:'inactive'};
const toSlug = value => String(value||'').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,80);
const fields=[['category','หมวดหมู่'],['title','ชื่อสวัสดิการ'],['slug','รหัส URL (อังกฤษตัวเล็ก)'],['short_description','คำอธิบายสั้น'],['full_description','รายละเอียด'],['benefit_amount','วงเงิน / สิทธิประโยชน์'],['eligibility','คุณสมบัติ'],['required_documents','เอกสารที่ต้องใช้'],['application_process','ขั้นตอนขอรับ'],['contact_info','ช่องทางติดต่อ']];
export default function AdminWelfarePage(){
 const [rows,setRows]=useState([]),[editing,setEditing]=useState(null),[error,setError]=useState(''),[busy,setBusy]=useState(false),[revision,setRevision]=useState(0);
 const [query,setQuery]=useState(''),[statusFilter,setStatusFilter]=useState('all'),[loading,setLoading]=useState(true);
 const visible=useMemo(()=>rows.filter(item=>(statusFilter==='all'||item.status===statusFilter) && [item.title,item.category,item.slug,item.benefit_amount].some(value=>String(value||'').toLocaleLowerCase('th').includes(query.trim().toLocaleLowerCase('th')))),[rows,query,statusFilter]);
 useEffect(()=>{let active=true;setLoading(true);memberAdminApi('/api/admin/welfare').then(r=>{if(active){setRows(r.data||[]);setError('');}}).catch(e=>{if(active)setError(e.message)}).finally(()=>{if(active)setLoading(false)});return()=>{active=false}},[revision]);
 async function save(e){e.preventDefault();if(busy||!editing)return;
  if(!editing.category?.trim()||!editing.title?.trim()||!editing.slug?.trim()){showError('ข้อมูลไม่ครบ','กรุณาระบุหมวดหมู่ ชื่อสวัสดิการ และรหัส URL');return;}
  if(!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(editing.slug)){showError('รหัส URL ไม่ถูกต้อง','ใช้ภาษาอังกฤษตัวเล็ก ตัวเลข และเครื่องหมายขีดกลางเท่านั้น');return;}
  setBusy(true);setError('');try{
  await memberAdminApi('/api/admin/welfare'+(editing.id?'/'+editing.id+'/update':''),{data:editing});
  showSuccess('บันทึกสำเร็จ', `บันทึกข้อมูลสวัสดิการ "${editing.title}" เรียบร้อยแล้ว`);
  setEditing(null);setRevision(v=>v+1)
 }catch(e){setError(e.message);showError('เกิดข้อผิดพลาด', e.message);}finally{setBusy(false)}}
 async function changePublication(item){
  if(busy)return;
  const activate=item.status!=='active';
  const confirmed=activate
    ? await confirmPublish('เผยแพร่สวัสดิการสู่เว็บไซต์?',`ต้องการแสดง “${item.title}” บนหน้า /app/welfare หรือไม่? ตรวจสอบสิทธิประโยชน์และเงื่อนไขก่อนเผยแพร่`)
    : await showConfirm('หยุดแสดงสวัสดิการบนเว็บไซต์?',`รายการ “${item.title}” จะไม่ปรากฏบนหน้าสาธารณะ แต่ยังคงข้อมูลในหลังบ้าน`);
  if(!confirmed)return;
  setBusy(true);setError('');
  try{
    await memberAdminApi('/api/admin/welfare/'+item.id+'/update',{data:{...item,status:activate?'active':'inactive',version:item.version}});
    await showSuccess(activate?'เผยแพร่แล้ว':'หยุดเผยแพร่แล้ว',activate?'กรุณาตรวจสอบรายการบนหน้าสาธารณะ':'ข้อมูลยังคงอยู่และสามารถเผยแพร่ใหม่ได้');
    setRevision(v=>v+1);
  }catch(e){setError(e.message);showError('เปลี่ยนสถานะไม่สำเร็จ',e.message);}
  finally{setBusy(false);}
 }
 async function remove(item){
  const confirmed = await showDeleteConfirm('ยืนยันปิดรายการสวัสดิการ?', `ต้องการปิดรายการสวัสดิการ "${item.title}" ใช่หรือไม่`);
  if(!confirmed)return;
  setBusy(true);setError('');try{
   await memberAdminApi('/api/admin/welfare/'+item.id+'/delete',{data:{version:item.version}});
   showSuccess('ปิดรายการสำเร็จ', `ปิดรายการสวัสดิการ "${item.title}" แล้ว`);
   setRevision(v=>v+1)
  }catch(e){setError(e.message);showError('เกิดข้อผิดพลาด', e.message);}finally{setBusy(false)}
 }
 return (
  <section className="admin-module welfare-admin-page">
    <AdminPageHeader
      title="จัดการสวัสดิการสมาชิก"
      description="เพิ่ม แก้ไข และจัดการข้อมูลสวัสดิการ โดยยังคงการอนุมัติและสิทธิ์จากระบบเดิม"
      actions={<Link className="acc-btn" to="/admin/workspace">กลับพื้นที่ทำงาน</Link>}
    />
    <div className="welfare-admin-notice" role="status">
      เพิ่มรายการใหม่จะเป็นร่างก่อนเสมอ กดเผยแพร่เมื่อข้อมูลได้รับการตรวจสอบแล้วเท่านั้น หน้าสาธารณะแสดงเฉพาะรายการ Active ที่ยังไม่ถูกปิด
    </div>
    {error && <AdminErrorState message={error}/>}
    <AdminCard title="รายการสวัสดิการ" description="บริหารสถานะและข้อมูลสิทธิประโยชน์ของสมาชิก">
      <div className="welfare-admin-toolbar">
        <span>รายการทั้งหมด {rows.length.toLocaleString('th-TH')} รายการ · เผยแพร่ {rows.filter(item=>item.status==='active').length.toLocaleString('th-TH')} รายการ</span>
        <label className="welfare-admin-search">ค้นหาสวัสดิการ <input type="search" value={query} onChange={e=>setQuery(e.target.value)} placeholder="ค้นหาชื่อ หมวดหมู่ หรือรหัส URL"/></label>
        <label className="welfare-admin-filter">สถานะ <select value={statusFilter} onChange={e=>setStatusFilter(e.target.value)}><option value="all">ทั้งหมด</option><option value="active">เผยแพร่แล้ว</option><option value="inactive">ร่าง / ไม่ใช้งาน</option></select></label>
        <button type="button" className="acc-btn" disabled={busy||loading} onClick={()=>setRevision(n=>n+1)}>โหลดล่าสุด</button>
        <button type="button" className="acc-btn primary" disabled={busy} onClick={()=>setEditing({...blank})}>
          เพิ่มรายการสวัสดิการ
        </button>
      </div>
      {loading ? <p role="status">กำลังโหลดข้อมูล...</p> : visible.length === 0 ? <AdminEmptyState>ไม่พบรายการสวัสดิการที่ตรงกับเงื่อนไข</AdminEmptyState> : (
        <div className="admin-table-scroll" role="region" aria-label="ตารางรายการสวัสดิการ" tabIndex={0}>
          <table className="admin-table">
            <thead><tr><th scope="col">ลำดับ</th><th scope="col">ชื่อสวัสดิการ</th><th scope="col">วงเงิน / สิทธิประโยชน์</th><th scope="col">สถานะ</th><th scope="col">จัดการ</th></tr></thead>
            <tbody>{visible.map(item=>(
              <tr key={item.id}>
                <td>{item.sort_order}</td>
                <td>{item.title}</td>
                <td>{item.benefit_amount || '—'}</td>
                <td><AdminStatusBadge status={item.status}>{item.status==='active'?'เผยแพร่แล้ว':'ร่าง/ไม่ใช้งาน'}</AdminStatusBadge></td>
                <td>
                  <div className="welfare-admin-row-actions">
                    <button type="button" className="acc-btn" disabled={busy} onClick={()=>setEditing({...item})}>แก้ไข</button>
                    <button type="button" className="acc-btn" disabled={busy} onClick={()=>changePublication(item)}>{item.status==='active'?'หยุดเผยแพร่':'เผยแพร่'}</button>
                    <button type="button" className="acc-btn" disabled={busy} onClick={()=>remove(item)}>ปิดรายการ</button>
                  </div>
                </td>
              </tr>
            ))}</tbody>
          </table>
        </div>
      )}
    </AdminCard>
    <p className="welfare-admin-public-link"><Link to="/welfare">ดูหน้าสวัสดิการสาธารณะ</Link> — รายการที่เพิ่งเพิ่มจะไม่แสดงจนกว่าจะเผยแพร่</p>
    {editing && (
      <AdminCard title={editing.id?'แก้ไขสวัสดิการ':'เพิ่มสวัสดิการ'} description="ตรวจสอบข้อมูลให้ครบก่อนบันทึก">
        <form className="welfare-admin-form" onSubmit={save}>
          <div className="welfare-admin-fields">
            {fields.map(([key,label])=>(
              <label key={key} className="admin-field">{label}
                {['full_description','eligibility','required_documents','application_process'].includes(key)
                  ? <textarea className="form-control" rows={3} maxLength={key==='full_description'?20000:5000} value={editing[key]||''} onChange={e=>setEditing(v=>({...v,[key]:e.target.value}))}/>
                  : <input className="form-control" required={['category','title','slug'].includes(key)} maxLength={key==='category'?100:255} value={editing[key]||''} onChange={e=>setEditing(v=>({...v,[key]:e.target.value,...(key==='title'&&!v.id&&!v.slug?{slug:toSlug(e.target.value)}:{})}))}/>}
              </label>
            ))}
            <label className="admin-field">ลำดับ
              <input type="number" min="0" max="100000" value={editing.sort_order??0} onChange={e=>setEditing(v=>({...v,sort_order:Number(e.target.value)}))}/>
            </label>
            <p className="welfare-admin-state-note">สถานะปัจจุบัน: {editing.status==='active'?'เผยแพร่แล้ว':'ร่าง/ไม่ใช้งาน'} — ใช้ปุ่ม “เผยแพร่” หรือ “หยุดเผยแพร่” จากตารางเพื่อยืนยันการเปลี่ยนสถานะ</p>
          </div>
          <div className="welfare-admin-form-actions">
            <button className="acc-btn primary" disabled={busy} type="submit">{busy?'กำลังบันทึก…':'บันทึก'}</button>
            <button type="button" className="acc-btn" disabled={busy} onClick={()=>setEditing(null)}>ยกเลิก</button>
          </div>
        </form>
      </AdminCard>
    )}
  </section>
 );
}
