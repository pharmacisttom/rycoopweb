import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { memberAdminApi } from '../../services/memberAdminApi';
import { AdminCard, AdminDataTable, AdminErrorState, AdminFormField, AdminStatusBadge } from './AdminUI';
import RichTextEditor from '../common/RichTextEditor';

export default function PrOfficerWorkspace() {
  const { can }=useAuth(); const readNews=can('news.view'),readAnnouncements=can('announcements.view'); const [news,setNews]=useState([]),[announcements,setAnnouncements]=useState([]),[mfa,setMfa]=useState(null);
  const [loading,setLoading]=useState(true),[error,setError]=useState(''),[edit,setEdit]=useState(null),[busy,setBusy]=useState(false),[revision,setRevision]=useState(0);
  useEffect(()=>{const c=new AbortController();setLoading(true);setError('');
    Promise.all([readNews?memberAdminApi('/api/admin/news',{signal:c.signal}):Promise.resolve({data:[]}),readAnnouncements?memberAdminApi('/api/admin/announcements',{signal:c.signal}):Promise.resolve({data:[]}),memberAdminApi('/api/auth/mfa/status',{signal:c.signal})])
      .then(([n,a,m])=>{if(!Array.isArray(n.data)||!Array.isArray(a.data))throw new Error('ข้อมูลเนื้อหาจาก API ไม่ครบถ้วน');setNews(n.data);setAnnouncements(a.data);setMfa(m);})
      .catch(e=>{if(e.name!=='AbortError')setError(e.message);}).finally(()=>{if(!c.signal.aborted)setLoading(false);});return()=>c.abort();
  },[revision,readNews,readAnnouncements]);
  async function save(event){event.preventDefault();if(busy)return;setBusy(true);setError('');try{await memberAdminApi(`/api/admin/announcements${edit.id?`/${edit.id}/update`:''}`,{data:edit});setEdit(null);setRevision(v=>v+1);}catch(e){setError(e.message);}finally{setBusy(false);}}
  const recent=[...news.map(n=>({...n,activity_key:`news-${n.id}`,kind:'ข่าว / กิจกรรม',state:n.workflow_status||n.status})),...announcements.map(a=>({...a,activity_key:`announcement-${a.id}`,kind:'ประกาศ',state:a.status}))].sort((a,b)=>String(b.updated_at||b.created_at||'').localeCompare(String(a.updated_at||a.created_at||''))).slice(0,10);
  return <>
    <AdminCard title="พื้นที่ประชาสัมพันธ์" description="ข่าว อัลบั้มกิจกรรม และสไลด์โชว์ใช้รายการข่าวและภาพชุดเดียวกัน">
      <div className="admin-actions">{can('news.view')&&<Link className="acc-btn primary" to="/admin/content?tab=news">ข่าว / อัลบั้มกิจกรรม / สไลด์โชว์</Link>}{can('faqs.view')&&<Link className="acc-btn" to="/admin/content?tab=faqs">คำถามที่พบบ่อย</Link>}{(can('coop_info.view')||can('people.view'))&&<Link className="acc-btn" to="/admin/public-content">ข้อมูลเว็บไซต์ที่ได้รับอนุมัติ</Link>}<button className="acc-btn" disabled={busy||loading} onClick={()=>setRevision(v=>v+1)}>โหลดข้อมูลล่าสุด</button></div>
      {mfa&&<p>2FA: {mfa.enrolled?'ลงทะเบียนแล้ว':'ต้องลงทะเบียน'} · รหัสกู้คืนคงเหลือ {mfa.recovery_codes_remaining} · เก็บรหัสกู้คืนส่วนตัว ห้ามส่ง OTP หรือ secret ให้ผู้อื่น</p>}
    </AdminCard>
    {error&&<AdminErrorState message={error} onRetry={()=>setRevision(v=>v+1)}/>}
    {loading&&<p role="status">กำลังโหลดข้อมูลจริง…</p>}
    {!loading&&!error&&<>
      <AdminCard title="ข่าวและกิจกรรม" description={`ฉบับร่าง ${news.filter(n=>n.workflow_status==='draft').length} · ส่งตรวจ ${news.filter(n=>n.workflow_status==='submitted').length} · เผยแพร่ ${news.filter(n=>n.workflow_status==='published').length} (นับจากรายการ API)`}>
        <AdminDataTable label="ข่าวประชาสัมพันธ์ล่าสุด" rows={news.slice(0,10)} columns={[{key:'title',label:'ชื่อเรื่อง'},{key:'workflow_status',label:'สถานะ',render:n=><AdminStatusBadge status={n.workflow_status}/>}]}/>
      </AdminCard>
      {can('announcements.view')&&<AdminCard title="ประกาศสำคัญ" actions={can('announcements.create')&&<button className="acc-btn" disabled={busy} onClick={()=>setEdit({title:'',content:'',publication_date:new Date().toISOString().slice(0,10),status:'draft'})}>สร้างประกาศ</button>}>
        <AdminDataTable label="ประกาศสำคัญ" rows={announcements} columns={[{key:'title',label:'ชื่อประกาศ'},{key:'status',label:'สถานะ',render:a=><AdminStatusBadge status={a.status}/>},{key:'actions',label:'จัดการ',render:a=>can('announcements.edit')&&<button className="acc-btn" onClick={()=>setEdit({...a,publication_date:String(a.publication_date).slice(0,10)})}>แก้ไขประกาศ</button>}]}/>
      </AdminCard>}
      <AdminCard title="กิจกรรมเนื้อหาล่าสุด" description="วันที่จากรายการข่าวและประกาศ ไม่ใช่บันทึกความปลอดภัยของระบบ"><AdminDataTable label="กิจกรรมเนื้อหาล่าสุด" rowKey="activity_key" rows={recent} columns={[{key:'title',label:'เนื้อหา'},{key:'kind',label:'ประเภท'},{key:'state',label:'สถานะ',render:r=><AdminStatusBadge status={r.state}/>},{key:'updated_at',label:'วันที่',render:r=>r.updated_at||r.created_at||'—'}]}/></AdminCard>
    </>}
    {edit&&<AdminCard title={edit.id?'แก้ไขประกาศ':'ประกาศใหม่'}><form onSubmit={save}>
      <AdminFormField label="ชื่อประกาศ" required><input required maxLength={255} value={edit.title} onChange={e=>setEdit({...edit,title:e.target.value})}/></AdminFormField>
      <RichTextEditor label="เนื้อหาประกาศ" value={edit.content} onChange={content=>setEdit({...edit,content})} disabled={busy}/>
      <AdminFormField label="วันที่ประกาศ"><input type="date" required value={edit.publication_date} onChange={e=>setEdit({...edit,publication_date:e.target.value})}/></AdminFormField>
      <AdminFormField label="สถานะ"><select value={edit.status} onChange={e=>setEdit({...edit,status:e.target.value})}><option value="draft">ฉบับร่าง</option>{can('announcements.publish')&&<option value="published">เผยแพร่</option>}</select></AdminFormField>
      <div className="admin-actions"><button className="acc-btn primary" disabled={busy}>บันทึกประกาศ</button><button type="button" className="acc-btn" disabled={busy} onClick={()=>setEdit(null)}>ยกเลิก</button></div>
    </form></AdminCard>}
  </>;
}
