import React, {useEffect,useState} from 'react';
import {Link} from 'react-router-dom';

export default function WelfarePage(){
 const [rows,setRows]=useState([]),[loading,setLoading]=useState(true),[error,setError]=useState('');
 useEffect(()=>{
  const ac=new AbortController();
  fetch('/api/public/welfare',{signal:ac.signal,credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'})
   .then(async r=>{if(!r.ok)throw new Error('ข้อมูลสวัสดิการยังไม่พร้อมเผยแพร่');const j=await r.json();if(!j.success||!Array.isArray(j.data))throw new Error('ข้อมูลตอบกลับไม่ถูกต้อง');return j.data;})
   .then(v=>setRows(v)).catch(e=>{if(e.name!=='AbortError')setError(e.message)}).finally(()=>{if(!ac.signal.aborted)setLoading(false)});
  return()=>ac.abort();
 },[]);
 return <section className="section"><div className="container">
  <div className="section-title-wrap"><span className="section-badge">กองทุนสวัสดิการ</span><h1 className="section-title">สวัสดิการสมาชิกและครอบครัว</h1><p className="section-subtitle">ข้อมูลสิทธิประโยชน์ที่ได้รับอนุมัติเผยแพร่จากสหกรณ์</p><div className="section-line"/></div>
  {loading?<p role="status">กำลังโหลดข้อมูล...</p>:error?<p role="alert">{error}</p>:!rows.length?<p>ยังไม่มีรายการสวัสดิการที่เปิดเผยแพร่</p>:
  <div className="grid-3">{rows.map(item=><article className="surface-card" key={item.id} style={{padding:'2rem'}}>
   <span className="badge badge-teal">{item.category}</span><h2 style={{fontSize:'1.2rem',marginTop:12}}>{item.title}</h2>
   <strong>{item.benefit_amount}</strong><p>{item.short_description}</p>
   {item.full_description&&<p>{item.full_description}</p>}
   {item.eligibility&&<p><strong>คุณสมบัติและเงื่อนไข</strong><br/>{item.eligibility}</p>}
   {item.required_documents&&<p><strong>เอกสารประกอบ</strong><br/>{item.required_documents}</p>}
   {item.application_process&&<p><strong>ขั้นตอนยื่นคำขอ</strong><br/>{item.application_process}</p>}
   {item.contact_info&&<p><strong>ติดต่อ</strong> {item.contact_info}</p>}
   <Link to="/documents">ดูเอกสารของสหกรณ์</Link>
  </article>)}</div>}
 </div></section>;
}
