import React, {useCallback, useEffect, useState} from 'react';
import {Link} from 'react-router-dom';
import {memberAdminApi} from '../services/memberAdminApi';
import './AdminPopupCampaignPage.css';

const empty = {title:'',content:'',button_text:'',button_url:'',desktop_image:'',priority:'normal',status:'inactive'};
const priorities = ['critical','high','normal','low'];
const asForm = row => Object.fromEntries(Object.keys(empty).map(key=>[key,row?.[key] ?? empty[key]]));

export default function AdminPopupCampaignPage() {
  const [rows,setRows] = useState([]);
  const [form,setForm] = useState(null);
  const [editingId,setEditingId] = useState(null);
  const [loading,setLoading] = useState(true);
  const [busy,setBusy] = useState(false);
  const [error,setError] = useState('');
  const [message,setMessage] = useState('');

  const load = useCallback(async()=>{
    setLoading(true);setError('');
    try {
      const result=await memberAdminApi('/api/admin/popup-campaigns');
      setRows(Array.isArray(result.data)?result.data:[]);
    } catch(e) {setError(e.message||'ไม่สามารถโหลดรายการ Popup ได้');}
    finally {setLoading(false);}
  },[]);

  useEffect(()=>{load();},[load]);

  const openForm = row => {setEditingId(row?.id ?? null);setForm(asForm(row));setError('');setMessage('');};
  const change=(name,value)=>setForm(previous=>({...previous,[name]:value}));

  const save = async event => {
    event.preventDefault();
    if(!form?.title?.trim()){setError('กรุณาระบุหัวข้อ Popup');return;}
    setBusy(true);setError('');setMessage('');
    try {
      const endpoint=editingId?'/api/admin/popup-campaigns/'+editingId+'/update':'/api/admin/popup-campaigns';
      const response=await memberAdminApi(endpoint,{data:form});
      setMessage(response.message||'บันทึกสำเร็จ');
      setForm(null);setEditingId(null);
      await load();
    } catch(e) {setError(e.message||'บันทึกไม่สำเร็จ');}
    finally {setBusy(false);}
  };

  const disable = async row => {
    if (!window.confirm('ปิดการแสดง Popup "'+row.title+'" หรือไม่?')) return;
    setBusy(true);setError('');setMessage('');
    try {
      const result=await memberAdminApi('/api/admin/popup-campaigns/'+row.id+'/disable',{data:{}});
      setMessage(result.message||'ปิดใช้งานแล้ว');
      await load();
    } catch(e){setError(e.message||'ปิดใช้งานไม่สำเร็จ');}
    finally {setBusy(false);}
  };

  return <section className="popup-admin">
    <header className="popup-admin__header">
      <div>
        <p><Link to="/admin/cms">← กลับศูนย์จัดการเว็บไซต์</Link></p>
        <h1>Popup ประชาสัมพันธ์</h1>
        <p>จัดการประกาศที่เผยแพร่ผ่าน Backend กลาง (ต้องมีสิทธิ์ super_admin)</p>
      </div>
      <button className="popup-admin__primary" type="button" disabled={busy} onClick={()=>openForm(null)}>+ เพิ่ม Popup</button>
    </header>
    {error && <div className="popup-admin__error" role="alert">{error}</div>}
    {message && <div className="popup-admin__success" role="status">{message}</div>}
    <div className="popup-admin__panel">
      {loading ? <p role="status">กำลังโหลดรายการ...</p> :
        !rows.length ? <p role="status">ยังไม่มี Popup ในระบบกลาง</p> :
        <div className="popup-admin__scroll"><table>
          <thead><tr><th>หัวข้อ</th><th>สถานะ</th><th>ความสำคัญ</th><th>ภาพ</th><th>จัดการ</th></tr></thead>
          <tbody>{rows.map(row=><tr key={row.id}>
            <td><strong>{row.title}</strong><div className="popup-admin__muted">{String(row.content||'').slice(0,110)}</div></td>
            <td>{row.status==='active'?'เผยแพร่':'ปิดการแสดง'}</td>
            <td>{row.priority}</td>
            <td>{row.desktop_image?<a href={row.desktop_image} target="_blank" rel="noopener noreferrer">ดูภาพ</a>:'—'}</td>
            <td className="popup-admin__actions">
              <button type="button" disabled={busy} onClick={()=>openForm(row)}>แก้ไข</button>
              {row.status==='active'&&<button type="button" disabled={busy} onClick={()=>disable(row)}>ปิดใช้งาน</button>}
            </td>
          </tr>)}</tbody>
        </table></div>}
    </div>
    {form && <div className="popup-admin__overlay" role="presentation" onMouseDown={event=>{if(event.target===event.currentTarget&&!busy)setForm(null);}}>
      <form className="popup-admin__dialog" onSubmit={save} aria-labelledby="popup-form-title" role="dialog" aria-modal="true">
        <h2 id="popup-form-title">{editingId?'แก้ไข Popup':'เพิ่ม Popup'}</h2>
        <label>หัวข้อประกาศ <span aria-hidden="true">*</span>
          <input required maxLength={200} value={form.title} onChange={event=>change('title',event.target.value)}/>
        </label>
        <label>รายละเอียด
          <textarea rows={4} maxLength={4000} value={form.content} onChange={event=>change('content',event.target.value)}/>
        </label>
        <label>ข้อความปุ่ม
          <input maxLength={100} value={form.button_text} onChange={event=>change('button_text',event.target.value)}/>
        </label>
        <label>ลิงก์ปุ่ม (เฉพาะ Path ภายในเว็บไซต์ เช่น /loans)
          <input maxLength={300} value={form.button_url} onChange={event=>change('button_url',event.target.value)}/>
        </label>
        <label>URL ภาพจาก Media Library (เช่น /storage/uploads/...webp)
          <input maxLength={400} value={form.desktop_image} onChange={event=>change('desktop_image',event.target.value)}/>
        </label>
        <div className="popup-admin__fields">
          <label>ความสำคัญ
            <select value={form.priority} onChange={event=>change('priority',event.target.value)}>
              {priorities.map(value=><option key={value} value={value}>{value}</option>)}
            </select>
          </label>
          <label>สถานะ
            <select value={form.status} onChange={event=>change('status',event.target.value)}>
              <option value="inactive">ปิดการแสดง (Draft)</option>
              <option value="active">เผยแพร่</option>
            </select>
          </label>
        </div>
        <p className="popup-admin__muted">ระบบจะแสดง Popup ที่ Active ล่าสุดตามระดับความสำคัญเพียงรายการเดียว โดยไม่เผยแพร่รายการที่ปิดใช้งาน</p>
        {error&&<div className="popup-admin__error" role="alert">{error}</div>}
        <div className="popup-admin__actions">
          <button type="button" disabled={busy} onClick={()=>setForm(null)}>ยกเลิก</button>
          <button className="popup-admin__primary" disabled={busy} type="submit">{busy?'กำลังบันทึก...':'บันทึกข้อมูล'}</button>
        </div>
      </form>
    </div>}
  </section>;
}
