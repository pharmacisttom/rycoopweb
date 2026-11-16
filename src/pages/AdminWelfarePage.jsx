import React, {useEffect,useState} from 'react';
import {Link} from 'react-router-dom';
import {memberAdminApi} from '../services/memberAdminApi';
const blank={category:'',title:'',slug:'',short_description:'',full_description:'',benefit_amount:'',eligibility:'',required_documents:'',application_process:'',contact_info:'',sort_order:0,status:'inactive'};
const fields=[['category','หมวดหมู่'],['title','ชื่อสวัสดิการ'],['slug','รหัส URL (อังกฤษตัวเล็ก)'],['short_description','คำอธิบายสั้น'],['full_description','รายละเอียด'],['benefit_amount','วงเงิน / สิทธิประโยชน์'],['eligibility','คุณสมบัติ'],['required_documents','เอกสารที่ต้องใช้'],['application_process','ขั้นตอนขอรับ'],['contact_info','ช่องทางติดต่อ']];
export default function AdminWelfarePage(){
 const [rows,setRows]=useState([]),[editing,setEditing]=useState(null),[error,setError]=useState(''),[busy,setBusy]=useState(false),[revision,setRevision]=useState(0);
 useEffect(()=>{let active=true;memberAdminApi('/api/admin/welfare').then(r=>{if(active)setRows(r.data||[])}).catch(e=>{if(active)setError(e.message)});return()=>{active=false}},[revision]);
 async function save(e){e.preventDefault();setBusy(true);setError('');try{
  await memberAdminApi('/api/admin/welfare'+(editing.id?'/'+editing.id+'/update':''),{data:editing});
  setEditing(null);setRevision(v=>v+1)
 }catch(e){setError(e.message)}finally{setBusy(false)}}
 async function remove(item){if(!window.confirm('ยืนยันปิดรายการสวัสดิการนี้?'))return;setBusy(true);setError('');try{await memberAdminApi('/api/admin/welfare/'+item.id+'/delete',{data:{version:item.version}});setRevision(v=>v+1)}catch(e){setError(e.message)}finally{setBusy(false)}}
 return <section className="admin-module container" style={{padding:'2rem 1rem'}}>
  <Link to="/admin/workspace">กลับหลังบ้าน</Link>
  <h1>จัดการสวัสดิการสมาชิก</h1>
  <p role="status">หน้าสาธารณะยังถูกระงับด้วย Nginx — การบันทึกที่นี่ไม่เปิดเผยแพร่หน้าสาธารณะอัตโนมัติ</p>
  {error&&<p role="alert">{error}</p>}
  <button className="btn btn-primary" disabled={busy} onClick={()=>setEditing({...blank})}>เพิ่มรายการสวัสดิการ</button>
  <div style={{overflowX:'auto',marginTop:20}}><table style={{width:'100%'}}><thead><tr><th>ลำดับ</th><th>ชื่อ</th><th>วงเงิน</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
  {rows.map(item=><tr key={item.id}><td>{item.sort_order}</td><td>{item.title}</td><td>{item.benefit_amount}</td><td>{item.status==='active'?'เผยแพร่เมื่อเปิดโมดูล':'ร่าง/ไม่ใช้งาน'}</td><td><button className="btn btn-outline" disabled={busy} onClick={()=>setEditing({...item})}>แก้ไข</button> <button className="btn btn-outline" disabled={busy} onClick={()=>remove(item)}>ปิดรายการ</button></td></tr>)}
  </tbody></table></div>
  {editing&&<form onSubmit={save} className="surface-card" style={{padding:24,marginTop:24,display:'grid',gap:12}}>
  <h2>{editing.id?'แก้ไขสวัสดิการ':'เพิ่มสวัสดิการ'}</h2>
  {fields.map(([key,label])=><label key={key} style={{display:'grid',gap:4}}>{label}
   {['full_description','eligibility','required_documents','application_process'].includes(key)
    ?<textarea className="form-control" rows={3} value={editing[key]||''} onChange={e=>setEditing(v=>({...v,[key]:e.target.value}))}/>
    :<input className="form-control" required={['category','title','slug'].includes(key)} value={editing[key]||''} onChange={e=>setEditing(v=>({...v,[key]:e.target.value}))}/>}
   </label>)}
  <label>ลำดับ<input type="number" min="0" max="100000" value={editing.sort_order??0} onChange={e=>setEditing(v=>({...v,sort_order:Number(e.target.value)}))}/></label>
  <label>สถานะ<select value={editing.status} onChange={e=>setEditing(v=>({...v,status:e.target.value}))}><option value="inactive">ร่าง/ไม่ใช้งาน</option><option value="active">พร้อมเผยแพร่หลังเปิดโมดูล</option></select></label>
  <div><button className="btn btn-primary" disabled={busy}>บันทึก</button> <button type="button" className="btn btn-outline" disabled={busy} onClick={()=>setEditing(null)}>ยกเลิก</button></div>
  </form>}
 </section>
}
