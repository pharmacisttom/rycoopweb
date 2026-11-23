import React, { useCallback, useEffect, useMemo, useState } from 'react';
import AdminControlCenterShell from '../components/admin/AdminControlCenterShell';
import { memberAdminApi } from '../services/memberAdminApi';
import { showConfirm, showSuccess, showError } from '../utils/swal';

const ICONS = ['landmark','award','users','shield','wallet','calculator','file-text','newspaper','megaphone','heart','graduation','stethoscope','download','globe','calendar','receipt','piggy-bank','banknote','check-circle','book-open'];
const PATHS = ['/','/about','/board_committee','/board_officer','/statistics','/deposits','/rates','/loans','/calculator','/loan-calculator','/loans/calculator','/loan-checklist','/loan-readiness','/loans/checklist','/dividend-estimator','/welfare','/documents','/news','/announcements','/events','/calendar','/contact','/faqs','/verify-receipt','/privacy/policy','/privacy/cookies','/service-unavailable'];
const clone = value => JSON.parse(JSON.stringify(value));
const style = { display:'flex',alignItems:'center',gap:10,flexWrap:'wrap',margin:'8px 0' };
const btn = { padding:'8px 12px',border:'1px solid var(--border-strong)',borderRadius:8 };
const field = { padding:8,border:'1px solid var(--border-strong)',borderRadius:8,minWidth:160,maxWidth:'100%' };

export default function AdminMegaMenuPage() {
  const [state, setState] = useState(null);
  const [categories,setCategories] = useState([]);
  const [busy,setBusy] = useState(false);
  const [loading,setLoading] = useState(true);
  const [error,setError] = useState('');
  const [totp,setTotp] = useState('');
  const [preview,setPreview] = useState(false);
  const [dragSource,setDragSource] = useState(null);
  const [notice,setNotice] = useState('');
  const original = state?.draft || state?.published || state?.template;
  const dirty = !!original && JSON.stringify(original)!==JSON.stringify(categories);
  const validTotp = /^\d{6}$/.test(totp);
  const load = useCallback(async () => {
    setLoading(true);setError('');
    try {
      const response=await memberAdminApi('/api/admin/mega-menu');
      const next=response.data;
      if(!Array.isArray(next?.template)||!next.version)throw new Error('ไม่พบข้อมูลจัดการเมนู');
      setState(next);setCategories(clone(next.draft||next.published||next.template));setPreview(false);
    }catch(e){setError(e.message||'โหลดเมนูไม่สำเร็จ');}
    finally{setLoading(false);}
  },[]);
  useEffect(()=>{load();},[load]);
  const addCategory=()=>setCategories(old=>old.length>=8?old:[...old,{
    id:'group_'+Date.now().toString(36),title:'หมวดใหม่',enabled:false,items:[]
  }]);
  const removeCategory=index=>setCategories(old=>old.length<=1?old:old.filter((_,i)=>i!==index));
  const moveCategory=(index,delta)=>setCategories(old=>{
    const items=[...old],target=index+delta;
    if(target<0||target>=items.length)return old;
    [items[index],items[target]]=[items[target],items[index]];
    return items;
  });
  const editCategory = (index,key,value) => setCategories(old=>old.map((v,i)=>i===index?{...v,[key]:value}:v));
  const editItem = (cat,index,key,value) => setCategories(old=>old.map((v,i)=>i===cat?{
    ...v,items:v.items.map((item,j)=>j===index?{...item,[key]:value}:item)
  }:v));
  const addItem = cat => setCategories(old=>old.map((v,i)=>{
    if(i!==cat||v.items.length>=30)return v;
    const id='item_'+Date.now().toString(36);
    return {...v,items:[...v.items,{id,title:'รายการใหม่',description:'',group:'รายการทั่วไป',icon:'file-text',path:'/documents',enabled:false}]};
  }));
  const removeItem=(cat,index)=>setCategories(old=>old.map((v,i)=>i!==cat?v:{...v,items:v.items.filter((_,j)=>j!==index)}));
  const move=(cat,index,delta)=>setCategories(old=>old.map((v,i)=>{
    if(i!==cat)return v;
    const items=[...v.items],target=index+delta;
    if(target<0||target>=items.length)return v;
    [items[index],items[target]]=[items[target],items[index]];
    return {...v,items};
  }));
  const drop=(cat,index)=>{
    if(!dragSource||dragSource.cat!==cat||dragSource.index===index){setDragSource(null);return;}
    setCategories(old=>old.map((v,i)=>{
      if(i!==cat)return v;
      const items=[...v.items];
      const [picked]=items.splice(dragSource.index,1);
      items.splice(index,0,picked);
      return {...v,items};
    }));
    setDragSource(null);
  };
  const request=async(action)=>{
    if(busy||!state||!validTotp)return;
    setBusy(true);setError('');setNotice('');
    try{
      const data={version:state.version,totp_code:totp};
      if(action==='draft')data.categories=categories;
      const result=await memberAdminApi('/api/admin/mega-menu/'+action,{data});
      const next=result.data;
      if(!next?.version)throw new Error('ข้อมูลตอบกลับไม่ถูกต้อง');
      setState(next);setCategories(clone(next.draft||next.published||next.template));
      setTotp('');setPreview(false);
      setNotice(action==='draft'?'บันทึกร่างแล้ว เว็บไซต์จริงยังไม่เปลี่ยน':action==='rollback'?'ย้อนกลับชุดเมนูสำเร็จ':'เผยแพร่เมนูสำเร็จ');
      await showSuccess('บันทึกสำเร็จ',action==='draft'?'ร่างยังไม่เผยแพร่':'ตรวจผลที่เว็บไซต์หน้าบ้าน');
    }catch(e){setTotp('');setError(e.status===409?'ข้อมูลถูกแก้โดยผู้อื่น กรุณาโหลดล่าสุด':e.message||'บันทึกไม่สำเร็จ');}
    finally{setBusy(false);}
  };
  return <AdminControlCenterShell title="จัดการ Mega Menu" subtitle="เพิ่ม แก้ไข ซ่อน จัดลำดับ และเผยแพร่เมนูหน้าเว็บไซต์">
    <section className="admin-module" aria-label="จัดการ Mega Menu" style={{maxWidth:1150}}>
      <p>จัดการเมนูหลัก 1–8 หมวด โดยเพิ่ม/ลบรายการย่อย แก้ลิงก์ภายใน หมวดหมู่ ไอคอน และข้อความได้ ย้ายลำดับด้วย Drag & Drop หรือปุ่มขึ้น/ลง ทุกรายการเริ่มเป็นร่างก่อนเผยแพร่ ไม่มีผลต่อ Navbar เดิมจนกว่าจะอนุมัติ</p>
      <p><strong>ข้อควรทราบ:</strong> ลบรายการในตัวแก้ไขคือการลบจากร่าง (ไม่มีผลต่อข้อมูล CMS) และลิงก์เลือกได้เฉพาะหน้าที่อนุญาต ไม่อนุญาต URL ภายนอกหรือ /eservice ที่ยังไม่เปิดใช้</p>
      {error&&<p role="alert" style={{color:'#b42318'}}>{error}</p>}
      {notice&&<p role="status">{notice}</p>}
      {loading&&<p role="status">กำลังโหลดข้อมูล...</p>}
      {!loading&&!state&&<button type="button" onClick={load}>โหลดใหม่</button>}
      {state&&<div>
        <div style={{...style,marginBottom:18}}>
          <strong>เว็บไซต์จริง: {state.published?'มีเมนูที่เผยแพร่แล้ว':'ใช้ Navbar เดิม'}</strong>
          <span> · {state.draft?'มีร่างค้าง':'ยังไม่มีร่าง'}</span>
          <span> · {dirty?'มีข้อมูลยังไม่บันทึก':'ข้อมูลตรงกับที่บันทึก'}</span>
          <button type="button" style={btn} disabled={categories.length>=8} onClick={addCategory}>เพิ่มหมวดเมนูหลัก</button>
          <button type="button" style={btn} onClick={()=>setPreview(v=>!v)}>{preview?'ปิด Preview':'Preview ร่าง'}</button>
          <button type="button" style={btn} onClick={load} disabled={busy}>โหลดข้อมูลล่าสุด</button>
        </div>
        {categories.map((category,ci)=><section key={category.id} className="surface-card" style={{padding:18,margin:'16px 0'}}>
          <div style={style}>
            <strong style={{minWidth:135}}>หมวด {ci+1}</strong>
            <button type="button" aria-label={'เลื่อนหมวดขึ้น '+category.title} style={btn} disabled={ci===0} onClick={()=>moveCategory(ci,-1)}>↑</button>
            <button type="button" aria-label={'เลื่อนหมวดลง '+category.title} style={btn} disabled={ci===categories.length-1} onClick={()=>moveCategory(ci,1)}>↓</button>
            <input aria-label={'ชื่อหมวด '+category.id} style={{...field,flex:1}} maxLength={80} value={category.title} onChange={e=>editCategory(ci,'title',e.target.value)}/>
            <label><input type="checkbox" checked={category.enabled} onChange={e=>editCategory(ci,'enabled',e.target.checked)}/> แสดงหมวด</label>
            <button type="button" style={btn} disabled={category.items.length>=30} onClick={()=>addItem(ci)}>เพิ่มรายการ</button>
            <button type="button" style={{...btn,color:'#b42318'}} disabled={categories.length<=1} onClick={async()=>{
              if(await showConfirm('ลบหมวดจากร่าง?', 'การลบจะมีผลต่อหน้าบ้านเมื่ออนุมัติเผยแพร่เท่านั้น'))removeCategory(ci);
            }}>ลบหมวดจากร่าง</button>
          </div>
          {preview?<div style={{display:'flex',flexWrap:'wrap',gap:12}}>
            {category.enabled && category.items.filter(item=>item.enabled).map(item=><div key={item.id} style={{...btn,minWidth:200}}>
              <strong>{item.title}</strong><p>{item.group}</p><p>{item.description}</p><code>{item.path}</code>
            </div>)}
          </div>:
          <div style={{overflowX:'auto'}}>
          <table style={{width:'100%',minWidth:800,borderCollapse:'collapse'}}>
            <thead><tr><th>ลำดับ / แสดง</th><th>ชื่อ / คำอธิบาย</th><th>กลุ่ม / ไอคอน</th><th>เส้นทาง</th><th>จัดการ</th></tr></thead>
            <tbody>{category.items.map((item,ii)=><tr key={item.id} draggable
              onDragStart={e=>{e.dataTransfer.effectAllowed='move';setDragSource({cat:ci,index:ii});}}
              onDragOver={e=>{e.preventDefault();e.dataTransfer.dropEffect='move';}}
              onDrop={e=>{e.preventDefault();drop(ci,ii);}}
              onDragEnd={()=>setDragSource(null)}>
              <td><div style={style}>
                <button type="button" style={btn} disabled={ii===0} aria-label={'เลื่อนขึ้น '+item.title} onClick={()=>move(ci,ii,-1)}>↑</button>
                <button type="button" style={btn} disabled={ii===category.items.length-1} aria-label={'เลื่อนลง '+item.title} onClick={()=>move(ci,ii,1)}>↓</button>
                <label><input type="checkbox" checked={item.enabled} onChange={e=>editItem(ci,ii,'enabled',e.target.checked)}/> แสดง</label>
              </div></td>
              <td><input aria-label={'ชื่อรายการ '+item.id} style={field} maxLength={100} value={item.title} onChange={e=>editItem(ci,ii,'title',e.target.value)}/>
                <textarea aria-label={'คำอธิบาย '+item.id} style={{...field,display:'block',marginTop:5}} maxLength={240} value={item.description} onChange={e=>editItem(ci,ii,'description',e.target.value)}/>
              </td>
              <td><input aria-label={'กลุ่ม '+item.id} style={field} maxLength={90} value={item.group} onChange={e=>editItem(ci,ii,'group',e.target.value)}/>
                <select aria-label={'ไอคอน '+item.id} style={{...field,display:'block',marginTop:5}} value={item.icon} onChange={e=>editItem(ci,ii,'icon',e.target.value)}>{ICONS.map(x=><option key={x} value={x}>{x}</option>)}</select>
              </td>
              <td><select aria-label={'เส้นทาง '+item.id} style={field} value={item.path} onChange={e=>editItem(ci,ii,'path',e.target.value)}>{PATHS.map(x=><option key={x} value={x}>{x}</option>)}</select></td>
              <td><button type="button" style={{...btn,color:'#b42318'}} onClick={async()=>{
                if(await showConfirm('ลบรายการจากร่าง?', 'รายการนี้จะไม่แสดงหลังเผยแพร่ร่างเท่านั้น'))removeItem(ci,ii);
              }}>ลบจากร่าง</button></td>
            </tr>)}</tbody>
          </table></div>}
        </section>)}
        <div className="surface-card" style={{padding:18,margin:'20px 0'}}>
          <label>ยืนยัน MFA ด้วยรหัส TOTP 6 หลัก
            <input type="password" inputMode="numeric" autoComplete="one-time-code" style={{...field,display:'block',margin:'8px 0'}} value={totp} maxLength={6} onChange={e=>setTotp(e.target.value.replace(/\D/g,'').slice(0,6))}/>
          </label>
          <div style={style}>
            <button type="button" style={btn} disabled={busy||!dirty||!validTotp} onClick={()=>request('draft')}>1. บันทึกร่าง</button>
            <button type="button" style={btn} disabled={busy||dirty||!state.draft||!validTotp} onClick={async()=>{
              if(await showConfirm('ยืนยันเผยแพร่ Mega Menu?', 'เว็บไซต์หน้าบ้านจะเริ่มใช้ชุดเมนูใหม่ที่ตรวจแล้ว'))request('publish');
            }}>2. อนุมัติเผยแพร่</button>
            <button type="button" style={btn} disabled={busy||dirty||!state.published||!validTotp} onClick={async()=>{
              if(await showConfirm('ย้อนกลับเมนู?', 'ใช้ชุดเมนูที่เคยเผยแพร่ก่อนหน้าเมื่อมีข้อมูลย้อนหลัง'))request('rollback');
            }}>ย้อนกลับเวอร์ชันก่อนหน้า</button>
          </div>
        </div>
      </div>}
    </section>
  </AdminControlCenterShell>;
}
