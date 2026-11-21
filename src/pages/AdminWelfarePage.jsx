import React, {useEffect,useState} from 'react';
import { AdminPageHeader, AdminCard, AdminErrorState, AdminEmptyState, AdminStatusBadge } from '../components/admin/AdminUI';
import './AdminWelfarePage.css';
import {Link} from 'react-router-dom';
import {memberAdminApi} from '../services/memberAdminApi';
import {showDeleteConfirm, showSuccess, showError} from '../utils/swal';
const blank={category:'',title:'',slug:'',short_description:'',full_description:'',benefit_amount:'',eligibility:'',required_documents:'',application_process:'',contact_info:'',sort_order:0,status:'inactive'};
const fields=[['category','หมวดหมู่'],['title','ชื่อสวัสดิการ'],['slug','รหัส URL (อังกฤษตัวเล็ก)'],['short_description','คำอธิบายสั้น'],['full_description','รายละเอียด'],['benefit_amount','วงเงิน / สิทธิประโยชน์'],['eligibility','คุณสมบัติ'],['required_documents','เอกสารที่ต้องใช้'],['application_process','ขั้นตอนขอรับ'],['contact_info','ช่องทางติดต่อ']];
export default function AdminWelfarePage(){
 const [rows,setRows]=useState([]),[editing,setEditing]=useState(null),[error,setError]=useState(''),[busy,setBusy]=useState(false),[revision,setRevision]=useState(0);
 useEffect(()=>{let active=true;memberAdminApi('/api/admin/welfare').then(r=>{if(active)setRows(r.data||[])}).catch(e=>{if(active)setError(e.message)});return()=>{active=false}},[revision]);
 async function save(e){e.preventDefault();setBusy(true);setError('');try{
  await memberAdminApi('/api/admin/welfare'+(editing.id?'/'+editing.id+'/update':''),{data:editing});
  showSuccess('บันทึกสำเร็จ', `บันทึกข้อมูลสวัสดิการ "${editing.title}" เรียบร้อยแล้ว`);
  setEditing(null);setRevision(v=>v+1)
 }catch(e){setError(e.message);showError('เกิดข้อผิดพลาด', e.message);}finally{setBusy(false)}}
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
      การแสดงรายการบนหน้าสาธารณะขึ้นกับสถานะ Active ของรายการและการเปิดใช้งานเส้นทาง Nginx Production ตรวจสอบหน้าสาธารณะหลังบันทึกทุกครั้ง
    </div>
    {error && <AdminErrorState message={error}/>}
    <AdminCard title="รายการสวัสดิการ" description="บริหารสถานะและข้อมูลสิทธิประโยชน์ของสมาชิก">
      <div className="welfare-admin-toolbar">
        <span>รายการทั้งหมด {rows.length.toLocaleString('th-TH')} รายการ</span>
        <button type="button" className="acc-btn primary" disabled={busy} onClick={()=>setEditing({...blank})}>
          เพิ่มรายการสวัสดิการ
        </button>
      </div>
      {rows.length === 0 ? <AdminEmptyState>ยังไม่มีรายการสวัสดิการ</AdminEmptyState> : (
        <div className="admin-table-scroll" role="region" aria-label="ตารางรายการสวัสดิการ" tabIndex={0}>
          <table className="admin-table">
            <thead><tr><th scope="col">ลำดับ</th><th scope="col">ชื่อสวัสดิการ</th><th scope="col">วงเงิน / สิทธิประโยชน์</th><th scope="col">สถานะ</th><th scope="col">จัดการ</th></tr></thead>
            <tbody>{rows.map(item=>(
              <tr key={item.id}>
                <td>{item.sort_order}</td>
                <td>{item.title}</td>
                <td>{item.benefit_amount || '—'}</td>
                <td><AdminStatusBadge status={item.status}>{item.status==='active'?'Active — ตรวจสอบการเปิด Public API':'ร่าง/ไม่ใช้งาน'}</AdminStatusBadge></td>
                <td>
                  <div className="welfare-admin-row-actions">
                    <button type="button" className="acc-btn" disabled={busy} onClick={()=>setEditing({...item})}>แก้ไข</button>
                    <button type="button" className="acc-btn" disabled={busy} onClick={()=>remove(item)}>ปิดรายการ</button>
                  </div>
                </td>
              </tr>
            ))}</tbody>
          </table>
        </div>
      )}
    </AdminCard>
    {editing && (
      <AdminCard title={editing.id?'แก้ไขสวัสดิการ':'เพิ่มสวัสดิการ'} description="ตรวจสอบข้อมูลให้ครบก่อนบันทึก">
        <form className="welfare-admin-form" onSubmit={save}>
          <div className="welfare-admin-fields">
            {fields.map(([key,label])=>(
              <label key={key} className="admin-field">{label}
                {['full_description','eligibility','required_documents','application_process'].includes(key)
                  ? <textarea className="form-control" rows={3} value={editing[key]||''} onChange={e=>setEditing(v=>({...v,[key]:e.target.value}))}/>
                  : <input className="form-control" required={['category','title','slug'].includes(key)} value={editing[key]||''} onChange={e=>setEditing(v=>({...v,[key]:e.target.value}))}/>}
              </label>
            ))}
            <label className="admin-field">ลำดับ
              <input type="number" min="0" max="100000" value={editing.sort_order??0} onChange={e=>setEditing(v=>({...v,sort_order:Number(e.target.value)}))}/>
            </label>
            <label className="admin-field">สถานะ
              <select value={editing.status} onChange={e=>setEditing(v=>({...v,status:e.target.value}))}>
                <option value="inactive">ร่าง/ไม่ใช้งาน</option>
                <option value="active">Active — แสดงเมื่อเปิด Public API</option>
              </select>
            </label>
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
