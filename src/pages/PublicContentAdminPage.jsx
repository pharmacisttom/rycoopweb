import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { memberAdminApi } from '../services/memberAdminApi';
import { usePublicContent } from '../context/PublicContentContext';
import './PublicContentAdminPage.css';

const fields = {
  profile: [['nameTh','ชื่อไทย'],['nameEn','ชื่ออังกฤษ'],['shortName','ชื่อย่อ'],['slogan','คำขวัญ'],['establishedYear','ปีจัดตั้ง พ.ศ.'],['address','ที่อยู่','textarea'],['phone','โทรศัพท์'],['fax','โทรสาร'],['email','อีเมล','email'],['workHours','เวลาทำการ'],['lineId','LINE ID ทางการ'],['facebook','Facebook (เช่น facebook.com/ชื่อเพจ)'],['mapUrl','ลิงก์แผนที่','url'],['mapEmbedUrl','ลิงก์แผนที่ฝัง','url']],
  boards: [['id','รหัสบุคลากร','number'],['name','ชื่อและนามสกุล'],['position','ตำแหน่ง'],['term','วาระ / ชุดที่'],['workplace','หน่วยงาน'],['image','ลิงก์รูปภาพ']],
  staff: [['id','รหัสบุคลากร','number'],['name','ชื่อและนามสกุล'],['position','ตำแหน่ง'],['department','ฝ่าย'],['image','ลิงก์รูปภาพ']],
  advisors: [['name','ชื่อและนามสกุล'],['position','ตำแหน่ง']],
  auditors: [['name','ชื่อและนามสกุล'],['position','ตำแหน่ง']],
  groups: [['title','ชื่อคณะกรรมการย่อย']],
  deposits: [['type','ประเภทเงินฝาก'],['rate','ดอกเบี้ย % ต่อปี','rate'],['minDeposit','เงินเปิดบัญชีขั้นต่ำ'],['payFreq','เงื่อนไขการจ่ายดอกเบี้ย'],['highlight','เน้นรายการ','checkbox']],
  loan_rates: [['productId','รหัสผลิตภัณฑ์ที่เชื่อม (ไม่เปลี่ยนเมื่อแก้ชื่อ)'],['type','ประเภทเงินกู้'],['rate','ดอกเบี้ย % ต่อปี','rate'],['maxTerm','ระยะเวลาผ่อนชำระ'],['condition','เงื่อนไข','textarea']],
  products: [['id','รหัสผลิตภัณฑ์ (อังกฤษ)'],['title','ชื่อผลิตภัณฑ์'],['subtitle','คำบรรยาย'],['rateValue','ดอกเบี้ย % ต่อปี','rate'],['maxLimit','วงเงินสูงสุด (บาท)','number'],['maxTerm','ระยะเวลาสูงสุด (งวด)','number'],['guarantee','หลักประกัน / ข้อจำกัดสมาชิก','textarea'],['features','รายละเอียด (หนึ่งข้อต่อบรรทัด)','lines']],
};
const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const tupleSection = section => ['advisors','auditors'].includes(section);
const viewRow = (row, section) => tupleSection(section) ? { name: row[0], position: row[1] } : row;
const labelOf = row => row?.name || row?.title || row?.type || 'รายการใหม่';

function Preview({ value, section, boardNames }) {
  const rows = section === 'profile' ? [value] : value;
  return <div className="public-content-preview">{rows.map((original,index) => {
    const row=viewRow(original,section);
    return <article key={index}><strong>{labelOf(row)}</strong><dl>{fields[section].map(([key,label]) => row[key] != null && row[key] !== '' && <React.Fragment key={key}><dt>{label}</dt><dd>{Array.isArray(row[key]) ? row[key].join(' / ') : typeof row[key] === 'boolean' ? (row[key] ? 'ใช่' : 'ไม่') : String(row[key])}</dd></React.Fragment>)}</dl>
      {section==='groups' && <ul>{row.members.map(([id,position])=><li key={id}>{boardNames[id] || `บุคลากร ${id}`} — {position}</li>)}</ul>}
    </article>;
  })}{!rows.length && <p>ไม่มีรายการ</p>}</div>;
}

export default function PublicContentAdminPage() {
  const { catalog, reload }=usePublicContent();
  const [sections,setSections]=useState([]),[section,setSection]=useState(''),[data,setData]=useState(null);
  const [value,setValue]=useState(null),[selected,setSelected]=useState(0),[refresh,setRefresh]=useState(0);
  const [note,setNote]=useState(''),[source,setSource]=useState('https://rayongcoop.com/'),[effective,setEffective]=useState(today),[expires,setExpires]=useState('');
  const [busy,setBusy]=useState(false),[loading,setLoading]=useState(true),[error,setError]=useState(''),[message,setMessage]=useState('');
  const [dirty,setDirty]=useState(false),[preview,setPreview]=useState(false);
  const definition=sections.find(item=>item.key===section);
  const boardNames=Object.fromEntries(catalog.boardMembers.map(p=>[p.id,p.name]));
  useEffect(()=>{const controller=new AbortController();memberAdminApi('/api/admin/public-content/sections',{signal:controller.signal}).then(result=>{
    setSections(result.sections);setSection(result.sections[0]?.key||'');if(!result.sections.length)setLoading(false);
  }).catch(e=>{if(e.name!=='AbortError'){setError(e.message);setLoading(false);}});return()=>controller.abort();},[]);
  useEffect(()=>{if(!section)return;const controller=new AbortController();setLoading(true);setError('');setData(null);setValue(null);setSelected(0);setDirty(false);setPreview(false);setNote('');
    memberAdminApi(`/api/admin/public-content/sections/${section}`,{signal:controller.signal}).then(result=>{setData(result);setValue(result.value);}).catch(e=>{if(e.name!=='AbortError')setError(e.message);}).finally(()=>{if(!controller.signal.aborted)setLoading(false);});
    return()=>controller.abort();
  },[section,refresh]);
  useEffect(()=>{const warn=e=>{if(dirty){e.preventDefault();e.returnValue='';}};window.addEventListener('beforeunload',warn);return()=>window.removeEventListener('beforeunload',warn);},[dirty]);
  const rows=section==='profile'?[value]:value;
  const original=rows?.[selected];
  const row=original?viewRow(original,section):null;
  function change(key,newValue){setValue(current=>{
    if(section==='profile')return {...current,[key]:newValue};
    return current.map((item,index)=>index!==selected?item:tupleSection(section)?[key==='name'?newValue:item[0],key==='position'?newValue:item[1]]:{...item,[key]:newValue});
  });setDirty(true);}
  function add(){let entry={};if(tupleSection(section))entry=['',''];else if(['boards','staff'].includes(section))entry={id:Math.max(0,...value.map(p=>Number(p.id)||0))+1,name:'',position:'',image:''};else if(section==='groups')entry={title:'',members:[]};else if(section==='products')entry={id:'',title:'',rateValue:0,maxLimit:1,maxTerm:1,guarantee:'',features:[]};else if(section==='deposits')entry={type:'',rate:'0%',minDeposit:'',payFreq:'',highlight:false};else entry={type:'',rate:'0%',maxTerm:'',condition:''};setValue([...value,entry]);setSelected(value.length);setDirty(true);}
  async function save(e){e.preventDefault();if(busy||!definition?.canEdit)return;setBusy(true);setError('');setMessage('');try{
    const result=await memberAdminApi(`/api/admin/public-content/sections/${section}/drafts`,{data:{value,version:data.version,note,source_url:source,effective_on:effective,expires_on:expires||null}});
    setDirty(false);setMessage(`บันทึกร่าง #${result.id} แล้ว ยังไม่เปลี่ยนข้อมูลหน้าเว็บ`);setRefresh(n=>n+1);
  }catch(e){setError(e.message);}finally{setBusy(false);}}
  async function publish(revision){if(!window.confirm(`เผยแพร่ร่าง #${revision.id} มีผล ${revision.effective_on}?`))return;setBusy(true);setError('');try{
    await memberAdminApi(`/api/admin/public-content/revisions/${revision.id}/publish`,{data:{}});await reload();setMessage('อนุมัติแล้ว ข้อมูลจะแสดงตามวันที่มีผล');setRefresh(n=>n+1);
  }catch(e){setError(e.message);}finally{setBusy(false);}}
  return <main className="container section public-content-admin"><Link to="/admin/workspace">กลับพื้นที่ทำงาน</Link><h1>จัดการข้อมูลเว็บไซต์สหกรณ์</h1><p>บันทึกร่าง ตรวจข้อมูลก่อน–หลัง แล้วเผยแพร่ตามวันที่มีผล โดยไม่ต้อง build เว็บไซต์ใหม่</p>
    {error&&<p role="alert" className="public-content-error">{error}</p>}{message&&<p role="status">{message}</p>}
    <label>หมวดข้อมูล <select value={section} disabled={busy} onChange={e=>{if(!dirty||window.confirm('มีข้อมูลที่ยังไม่ได้บันทึก ต้องการเปลี่ยนหมวดหรือไม่?'))setSection(e.target.value);}}>{sections.map(item=><option key={item.key} value={item.key}>{item.label}</option>)}</select></label>
    <button className="btn btn-outline" disabled={busy||loading||!section} onClick={()=>{if(!dirty||window.confirm('ทิ้งการแก้ไขและโหลดล่าสุด?'))setRefresh(n=>n+1);}}>โหลดล่าสุด</button>
    {loading&&<p role="status">กำลังโหลดข้อมูล...</p>}{!loading&&!sections.length&&!error&&<p>ยังไม่มีสิทธิ์ดูข้อมูลส่วนนี้ กรุณาติดต่อผู้ดูแลระบบ</p>}
    {value&&data&&<>
      {section!=='profile'&&<div className="public-content-toolbar"><label>เลือกรายการ <select value={selected} disabled={busy} onChange={e=>setSelected(Number(e.target.value))}>{value.map((item,index)=><option key={index} value={index}>{index+1}. {labelOf(viewRow(item,section))}</option>)}</select></label>{definition?.canEdit&&<><button disabled={busy} onClick={add}>เพิ่มรายการ</button>{[-1,1].map(offset=><button key={offset} disabled={busy||selected+offset<0||selected+offset>=value.length} onClick={()=>{const next=[...value];[next[selected],next[selected+offset]]=[next[selected+offset],next[selected]];setValue(next);setSelected(selected+offset);setDirty(true);}}>{offset<0?'เลื่อนขึ้น':'เลื่อนลง'}</button>)}<button disabled={busy||!value.length} onClick={()=>{if(window.confirm('นำรายการนี้ออกจากร่าง? ข้อมูลจริงยังไม่เปลี่ยนจนกว่าจะเผยแพร่')){setValue(value.filter((_,index)=>index!==selected));setSelected(0);setDirty(true);}}}>นำออกจากร่าง</button></>}</div>}
      <form onSubmit={save}><fieldset disabled={busy||!definition?.canEdit} className="public-content-fields"><legend>{definition?.label}</legend>
        {row&&fields[section].map(([key,label,type='text'])=><label key={key}>{label}{['textarea','lines'].includes(type)?<textarea rows={3} value={type==='lines'?(row[key]||[]).join('\n'):row[key]||''} onChange={e=>change(key,type==='lines'?e.target.value.split('\n'):e.target.value)} />:type==='checkbox'?<input type="checkbox" checked={!!row[key]} onChange={e=>change(key,e.target.checked)}/>:<input type={type==='rate'?'number':type} disabled={section==='products'&&key==='rateValue'} step={type==='rate'?'0.001':undefined} min={['number','rate'].includes(type)?0:undefined} value={type==='rate'?String(row[key]??'').replace('%',''):row[key]??''} onChange={e=>change(key,type==='rate'&&key==='rate'?`${e.target.value}%`:e.target.value)} />}</label>)}
        {section==='products'&&<p>แก้อัตราดอกเบี้ยที่หมวดอัตราเงินกู้ เพื่อให้หน้าเว็บและเครื่องคำนวณใช้ตัวเลขเดียวกัน</p>}
        {section==='groups'&&row&&<div><h3>สมาชิกคณะย่อย</h3>{row.members.map(([id,position],index)=><div key={index} className="public-content-toolbar"><select aria-label="กรรมการ" value={id} onChange={e=>change('members',row.members.map((m,i)=>i===index?[Number(e.target.value),m[1]]:m))}>{catalog.boardMembers.map(person=><option key={person.id} value={person.id}>{person.name}</option>)}</select><input aria-label="ตำแหน่งในคณะย่อย" value={position} onChange={e=>change('members',row.members.map((m,i)=>i===index?[m[0],e.target.value]:m))}/><button type="button" onClick={()=>change('members',row.members.filter((_,i)=>i!==index))}>นำออก</button></div>)}<button type="button" onClick={()=>change('members',[...row.members,[catalog.boardMembers[0]?.id,'กรรมการ']])}>เพิ่มกรรมการในคณะ</button></div>}
        <label>วันที่มีผล (ค.ศ.) <input type="date" required value={effective} onChange={e=>setEffective(e.target.value)}/></label><label>เอกสารหรือหน้าอ้างอิง (HTTPS) <input type="url" required value={source} onChange={e=>setSource(e.target.value)}/></label><label>เหตุผลการแก้ไข <textarea required maxLength={1000} value={note} onChange={e=>setNote(e.target.value)}/></label>
        <label>วันสิ้นสุด (ไม่บังคับ)<input type="date" min={effective} value={expires} onChange={e=>{setExpires(e.target.value);setDirty(true);}} /></label><button type="submit" className="btn btn-primary">บันทึกร่างเพื่อรอเผยแพร่</button>
      </fieldset></form>
      <button className="btn btn-outline" onClick={()=>setPreview(p=>!p)}>ดูตัวอย่างร่าง</button>{preview&&<Preview value={value} section={section} boardNames={boardNames}/>}
      <section><h2>ร่างและประวัติการเผยแพร่</h2>{!data.history.length&&<p>ยังไม่มีประวัติในระบบใหม่นี้</p>}{data.history.map(revision=><article className="surface-card public-content-revision" key={revision.id}><h3>#{revision.id} · {revision.state==='draft'?'รอเผยแพร่':revision.state==='published'?'อนุมัติแล้ว':'ไม่อนุมัติ'}</h3><p>{revision.note}</p><p>มีผล {revision.effective_on} · ผู้แก้ไข #{revision.created_by}{revision.published_by&&` · ผู้เผยแพร่ #${revision.published_by}`}</p><a href={revision.source_url} target="_blank" rel="noopener noreferrer">แหล่งอ้างอิง</a><details><summary>ตรวจข้อมูลก่อน–หลัง</summary><h4>ก่อนแก้ไข</h4><Preview value={JSON.parse(revision.previous_payload)} section={section} boardNames={boardNames}/><h4>หลังแก้ไข</h4><Preview value={JSON.parse(revision.payload)} section={section} boardNames={boardNames}/></details>
        <div className="public-content-toolbar">{revision.state==='draft'&&definition?.canPublish&&<button disabled={busy||dirty} className="btn btn-primary" onClick={()=>publish(revision)}>อนุมัติและเผยแพร่</button>}{definition?.canEdit&&<button disabled={busy} onClick={()=>{if(!dirty||window.confirm('แทนร่างปัจจุบันด้วยเวอร์ชันนี้?')){setValue(JSON.parse(revision.payload));setSelected(0);setNote(`นำข้อมูลเวอร์ชัน #${revision.id} มาจัดทำร่างใหม่`);setSource(revision.source_url);setEffective(today());setDirty(true);}}}>ใช้เวอร์ชันนี้สร้างร่างใหม่</button>}</div></article>)}</section>
    </>}
  </main>;
}
