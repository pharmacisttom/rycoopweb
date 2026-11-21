import React, { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { memberAdminApi } from '../services/memberAdminApi';
import { useAdminAction } from '../components/admin/AdminActionProvider';
import { AdminPageHeader, AdminCard, AdminDataTable, AdminFormField, AdminErrorState, AdminModal, AdminPagination, AdminStatusBadge } from '../components/admin/AdminUI';
import { showDeleteConfirm, showSuccess, showError } from '../utils/swal';
const labels={site_title:'ชื่อสหกรณ์',site_subtitle:'ชื่อภาษาอังกฤษ',site_phone:'โทรศัพท์',site_fax:'โทรสาร',site_email:'อีเมล',site_address:'ที่อยู่',office_hours:'เวลาทำการ',facebook_url:'ลิงก์ Facebook (HTTPS)',line_oa:'LINE OA'};

export default function AdminStoredContentPage({kind='documents'}){
 const settings=kind==='settings',path=settings?'/api/admin/settings/content':'/api/admin/documents';const{can}=useAuth();const{run,busy:actionBusy}=useAdminAction();
 const[categoryEdit,setCategoryEdit]=useState(null),[categoryFilter,setCategoryFilter]=useState('all'),[data,setData]=useState(null),[edit,setEdit]=useState(null),[file,setFile]=useState(null),[loading,setLoading]=useState(true),[busy,setBusy]=useState(false),[error,setError]=useState(''),[message,setMessage]=useState(''),[loadFailed,setLoadFailed]=useState(false),[revision,setRevision]=useState(0),[search,setSearch]=useState(''),[page,setPage]=useState(1);
 useEffect(()=>{const c=new AbortController();setLoading(true);setError('');setLoadFailed(false);memberAdminApi(path,{signal:c.signal}).then(r=>{if(settings?!r.settings||typeof r.version!=='string':!Array.isArray(r.data)||!Array.isArray(r.categories))throw new Error('ข้อมูลจากเซิร์ฟเวอร์ไม่ครบถ้วน');setData(r);if(settings)setEdit({...r.settings});}).catch(e=>{if(e.name!=='AbortError'){setError(e.message);setLoadFailed(true);}}).finally(()=>{if(!c.signal.aborted)setLoading(false);});return()=>c.abort();},[path,settings,revision]);
 async function save(event){event.preventDefault();if(busy||actionBusy)return;setBusy(true);setError('');setMessage('');try{
   if(settings)await run(signal=>memberAdminApi(path,{data:{settings:edit,version:data.version},signal}),{scope:'settings.update',label:'บันทึกข้อมูลเว็บไซต์',confirm:'ตรวจสอบข้อมูลก่อนบันทึก การตั้งค่านี้มีผลต่อเว็บไซต์ส่วนกลาง'});
   else{const form=new FormData();for(const key of ['title','category_id','document_number','year','tag','sort_order','status','version'])if(edit[key]!=null)form.set(key,String(edit[key]));if(file)form.set('file',file);await memberAdminApi(path+(edit.id?`/${edit.id}/update`:''),{data:form});setEdit(null);setFile(null);}
   const successMsg = 'บันทึกข้อมูลส่วนกลางสำเร็จ';
   setMessage(successMsg);
   showSuccess('บันทึกสำเร็จ!', successMsg);
   setRevision(v=>v+1);
 }catch(e){if(e.code!=='ACTION_CANCELLED'){setError(e.message);showError('เกิดข้อผิดพลาด', e.message);}}finally{setBusy(false);}}
 async function saveCategory(event){event.preventDefault();if(busy||!categoryEdit)return;setBusy(true);setError('');setMessage('');try{
   const id=categoryEdit.id;
   const url=id?`/api/admin/document-categories/${id}/update`:'/api/admin/document-categories';
   await memberAdminApi(url,{data:{name:categoryEdit.name,sort_order:categoryEdit.sort_order}});
   setCategoryEdit(null);
   const successMsg = 'บันทึกหมวดหมู่เอกสารแล้ว';
   setMessage(successMsg);
   showSuccess('บันทึกสำเร็จ!', successMsg);
   setRevision(v=>v+1);
 }catch(e){const err=e.message||'บันทึกหมวดหมู่ไม่ได้';setError(err);showError('เกิดข้อผิดพลาด', err);}finally{setBusy(false);}}
 async function remove(item){
   const confirmed = await showDeleteConfirm('ปิดรายการเอกสาร?', `ต้องการปิดรายการเอกสาร “${item.title}” ใช่หรือไม่?`);
   if(!confirmed)return;
   setBusy(true);setError('');try{
     await memberAdminApi(`${path}/${item.id}/delete`,{data:{version:item.version}});
     setMessage('ปิดรายการเอกสารแล้ว');
     showSuccess('ปิดรายการแล้ว', `ปิดรายการเอกสาร “${item.title}” เรียบร้อยแล้ว`);
     setRevision(v=>v+1);
   }catch(e){setError(e.message);showError('เกิดข้อผิดพลาด', e.message);}finally{setBusy(false);}}
 const rows=[...(data?.data||[])].filter(item=>categoryFilter==='all'||String(item.category_id)===categoryFilter).sort((a,b)=>(Number(a.sort_order??0)-Number(b.sort_order??0)) || String(b.created_at||'').localeCompare(String(a.created_at||'')) || Number(b.id)-Number(a.id)).filter(item=>`${item.title} ${item.document_number||''} ${item.year}`.toLowerCase().includes(search.toLowerCase()));const current=Math.min(page,Math.max(1,Math.ceil(rows.length/25)));
 return <section className="admin-module"><AdminPageHeader title={settings?'ข้อมูลเว็บไซต์ที่อนุญาตให้แก้ไข':'จัดการเอกสารสหกรณ์'} description={settings?'ข้อมูลติดต่อและชื่อองค์กร · ไม่รวม secrets, feature flags หรือการตั้งค่า MFA':'จัดลำดับเอกสารด้วยเลขน้อยอยู่ก่อน ใช้ลำดับเดียวกันทั้งหน้าบ้านและหลังบ้าน · รองรับฉบับร่างและปิดใช้งาน'} actions={<button className="acc-btn" disabled={busy||loading||actionBusy} onClick={()=>setRevision(v=>v+1)}>โหลดข้อมูลล่าสุด</button>}/>
 {error&&<AdminErrorState message={error}/>} {message&&<p role="status">{message}</p>}
 {loading?<p role="status">กำลังโหลด…</p>:!loadFailed&&data&&(settings?<AdminCard title="ข้อมูลเว็บไซต์"><form onSubmit={save}><fieldset disabled={busy||actionBusy||!can('settings.update')}><div className="admin-form-grid">{Object.entries(labels).map(([key,label])=><AdminFormField key={key} label={label}><input type={key==='site_email'?'email':key==='facebook_url'?'url':'text'} maxLength={2000} value={edit?.[key]||''} onChange={e=>setEdit({...edit,[key]:e.target.value})}/></AdminFormField>)}</div><button className="acc-btn primary">บันทึกข้อมูลเว็บไซต์</button></fieldset></form></AdminCard>:<AdminCard title="ศูนย์เอกสาร" actions={can('documents.create')&&<button className="acc-btn primary" disabled={busy} onClick={()=>{setEdit({title:'',category_id:data.categories[0]?.id||'',year:new Date().getFullYear()+543,status:'inactive',tag:'',document_number:'',sort_order:1000});setFile(null);}}>เพิ่มเอกสาร</button>}>
 <AdminCard title="หมวดหมู่เอกสารปัจจุบัน" actions={can('documents.create')&&<button className="acc-btn" type="button" disabled={busy} onClick={()=>setCategoryEdit({name:'',sort_order:1000})}>เพิ่มหมวดหมู่</button>}>
   <p>ใช้หมวดหมู่ที่มีอยู่ในระบบเดิม โดยไม่ย้ายเอกสารหรือเปลี่ยนหมวดหมู่อัตโนมัติ เจ้าหน้าที่สามารถแก้ไขภายหลังได้</p>
   <div className="admin-actions" style={{display:'flex',gap:10,flexWrap:'wrap'}}>
     {[...(data.categories||[])].sort((a,b)=>Number(a.sort_order??0)-Number(b.sort_order??0)||Number(a.id)-Number(b.id)).map(category=><div key={category.id} style={{border:'1px solid #dce3ed',borderRadius:10,padding:12}}>
       <strong>{category.name}</strong> <small>ลำดับ {category.sort_order??0}</small>
       {can('documents.edit')&&<button type="button" className="acc-btn" disabled={busy} onClick={()=>setCategoryEdit({id:category.id,name:category.name,sort_order:category.sort_order??0})}>แก้ไขหมวดหมู่</button>}
     </div>)}
   </div>
 </AdminCard>
 <AdminFormField label="กรองตามหมวดหมู่"><select value={categoryFilter} onChange={e=>{setCategoryFilter(e.target.value);setPage(1);}}>
   <option value="all">ทุกหมวดหมู่</option>
   {(data.categories||[]).map(c=><option key={c.id} value={String(c.id)}>{c.name}</option>)}
 </select></AdminFormField>
 <AdminFormField label="ค้นหาเอกสาร"><input type="search" value={search} onChange={e=>{setSearch(e.target.value);setPage(1);}}/></AdminFormField>
 <AdminDataTable label="รายการเอกสาร" rows={rows.slice((current-1)*25,current*25)} columns={[{key:'sort_order',label:'ลำดับ',render:item=><strong>{item.sort_order??0}</strong>},{key:'title',label:'ชื่อเอกสาร'},{key:'year',label:'ปี พ.ศ.'},{key:'status',label:'สถานะ',render:item=><AdminStatusBadge status={item.status}/>},{key:'actions',label:'จัดการ',render:item=><div className="admin-actions">{can('documents.edit')&&<button className="acc-btn" disabled={busy} onClick={()=>{setEdit({...item});setFile(null);}}>แก้ไขเอกสาร</button>}{can('documents.delete')&&<button className="acc-btn danger" disabled={busy} onClick={()=>remove(item)}>ปิดรายการ</button>}</div>}]}/><AdminPagination page={current} total={rows.length} onChange={setPage} disabled={busy}/>
 </AdminCard>)}
 {!settings&&<AdminModal open={!!categoryEdit} title={categoryEdit?.id?'แก้ไขหมวดหมู่เอกสาร':'เพิ่มหมวดหมู่เอกสาร'} busy={busy} onClose={()=>setCategoryEdit(null)}>
 {categoryEdit&&<form onSubmit={saveCategory}><fieldset disabled={busy}>
   <AdminFormField label="ชื่อหมวดหมู่" required><input required maxLength={150} value={categoryEdit.name} onChange={e=>setCategoryEdit({...categoryEdit,name:e.target.value})}/></AdminFormField>
   <AdminFormField label="ลำดับหมวดหมู่" help="เลขน้อยแสดงก่อนบนหน้าบ้าน โดยไม่เปลี่ยนลำดับเอกสารภายในหมวด"><input type="number" required min="0" max="99999" step="1" value={categoryEdit.sort_order} onChange={e=>setCategoryEdit({...categoryEdit,sort_order:e.target.value})}/></AdminFormField>
   <button type="submit" className="acc-btn primary">บันทึกหมวดหมู่</button>
 </fieldset></form>}
 </AdminModal>}
 {!settings&&<AdminModal open={!!edit} title={edit?.id?'แก้ไขเอกสาร':'เพิ่มเอกสาร'} busy={busy} onClose={()=>{setEdit(null);setFile(null);}}>{error&&<AdminErrorState message={error}/>} {edit&&<form onSubmit={save}><fieldset disabled={busy}><AdminFormField label="ชื่อเอกสาร" required><input required maxLength={255} value={edit.title} onChange={e=>setEdit({...edit,title:e.target.value})}/></AdminFormField><AdminFormField label="หมวดเอกสาร" required><select required value={edit.category_id} onChange={e=>setEdit({...edit,category_id:e.target.value})}>{data?.categories.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}</select></AdminFormField><AdminFormField label="ปี พ.ศ."><input type="number" min={2400} max={2800} required value={edit.year} onChange={e=>setEdit({...edit,year:e.target.value})}/></AdminFormField>{['document_number','tag'].map(key=><AdminFormField key={key} label={key==='tag'?'แท็ก':'เลขเอกสาร'}><input maxLength={100} value={edit[key]||''} onChange={e=>setEdit({...edit,[key]:e.target.value})}/></AdminFormField>)}<AdminFormField label="ลำดับการแสดง" help="เลขน้อยอยู่ก่อน เช่น 1, 2, 3 หากเลขซ้ำ ระบบจะแสดงรายการใหม่ก่อน"><input type="number" min="0" max="99999" step="1" required value={edit.sort_order??0} onChange={e=>setEdit({...edit,sort_order:e.target.value})}/></AdminFormField><AdminFormField label="ไฟล์เอกสาร" help="ใช้ไฟล์จริงตามชนิดและขนาดที่เซิร์ฟเวอร์อนุญาต บันทึกร่างได้ก่อนแนบไฟล์"><input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" onChange={e=>setFile(e.target.files[0]||null)}/></AdminFormField><AdminFormField label="สถานะ"><select value={edit.status} onChange={e=>setEdit({...edit,status:e.target.value})}><option value="inactive">ฉบับร่าง / ไม่ใช้งาน</option><option value="active">ใช้งาน</option></select></AdminFormField><button className="acc-btn primary">บันทึกเอกสาร</button></fieldset></form>}</AdminModal>}
 </section>;
}
