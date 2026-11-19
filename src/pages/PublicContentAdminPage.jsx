import { AdminPageHeader, AdminErrorState } from '../components/admin/AdminUI';
import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { memberAdminApi } from '../services/memberAdminApi';
import { mediaLibraryApi } from '../services/mediaLibraryApi';
import { usePublicContent } from '../context/PublicContentContext';
import './PublicContentAdminPage.css';
import './BoardTermAdminRC06.css';
import {auditBoardTerms,BOARD_TERMS,BOARD_POSITION_OPTIONS} from '../utils/boardTermAudit';

const fields = {
  profile: [['nameTh','ชื่อไทย'],['nameEn','ชื่ออังกฤษ'],['shortName','ชื่อย่อ'],['slogan','คำขวัญ'],['establishedYear','ปีจัดตั้ง พ.ศ.'],['address','ที่อยู่','textarea'],['phone','โทรศัพท์'],['fax','โทรสาร'],['email','อีเมล','email'],['workHours','เวลาทำการ'],['lineId','LINE ID ทางการ'],['facebook','Facebook (เช่น facebook.com/ชื่อเพจ)'],['mapUrl','ลิงก์แผนที่','url'],['mapEmbedUrl','ลิงก์แผนที่ฝัง','url']],
  boards: [['id','รหัสบุคลากร','number'],['name','ชื่อและนามสกุล'],['position','ตำแหน่ง','board-position'],['term','วาระ / ชุดที่','board-term'],['workplace','หน่วยงาน'],['image','ลิงก์รูปภาพ'],['status','สถานะกรรมการ','board-status']],
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
const safeRows = (value, section) => section === 'profile'
  ? (value && !Array.isArray(value) && typeof value === 'object' ? [value] : [])
  : (Array.isArray(value) ? value : []);
const safeRevisionValue = (payload, section) => {
  try {
    const value = typeof payload === 'string' ? JSON.parse(payload) : payload;
    return section === 'profile'
      ? (value && !Array.isArray(value) && typeof value === 'object' ? value : null)
      : (Array.isArray(value) ? value : []);
  } catch { return section === 'profile' ? null : []; }
};

function Preview({ value, section, boardNames }) {
  const rows = safeRows(value, section);
  return <div className="public-content-preview">{rows.map((original,index) => {
    const row=viewRow(original,section) || {};
    return <article key={index}><strong>{labelOf(row)}</strong><dl>{(fields[section] || []).map(([key,label]) => row[key] != null && row[key] !== '' && <React.Fragment key={key}><dt>{label}</dt><dd>{Array.isArray(row[key]) ? row[key].join(' / ') : typeof row[key] === 'boolean' ? (row[key] ? 'ใช่' : 'ไม่') : String(row[key])}</dd></React.Fragment>)}</dl>
      {section==='groups' && <ul>{(Array.isArray(row.members) ? row.members : []).map(([id,position],i)=><li key={`${id}-${i}`}>{boardNames[id] || `บุคลากร ${id}`} — {position}</li>)}</ul>}
    </article>;
  })}{!rows.length && <p>ไม่มีรายการ</p>}</div>;
}

export default function PublicContentAdminPage() {
  const { catalog, reload }=usePublicContent();
  const [searchParams, setSearchParams] = useSearchParams();
  const requestedSection = searchParams.get('section');
  const [sections,setSections]=useState([]),[section,setSection]=useState(''),[data,setData]=useState(null);
  const [value,setValue]=useState(null),[selected,setSelected]=useState(0),[refresh,setRefresh]=useState(0);
  const [note,setNote]=useState(''),[source,setSource]=useState('https://rayongcoop.com/'),[effective,setEffective]=useState(today),[expires,setExpires]=useState('');
  const [busy,setBusy]=useState(false),[loading,setLoading]=useState(true),[error,setError]=useState(''),[message,setMessage]=useState('');
  const [dirty,setDirty]=useState(false),[preview,setPreview]=useState(false);
  const [mediaBusy,setMediaBusy]=useState(false);
  const [carrySelection,setCarrySelection]=useState([]);
  const [boardTermFilter,setBoardTermFilter]=useState('ชุดที่ 39');
  const definition=sections.find(item=>item.key===section);
  const boardNames=Object.fromEntries((Array.isArray(catalog?.boardMembers) ? catalog.boardMembers : []).filter(p=>p && typeof p==='object').map(p=>[p.id,p.name]));
  useEffect(()=>{const controller=new AbortController();memberAdminApi('/api/admin/public-content/sections',{signal:controller.signal}).then(result=>{
    const available = result.sections || [];
    setSections(available);
    setSection(available.some(item => item.key === requestedSection) ? requestedSection : (available[0]?.key || ''));
    if (!available.length) setLoading(false);
  }).catch(e=>{if(e.name!=='AbortError'){setError(e.message);setLoading(false);}});return()=>controller.abort();},[]);
  useEffect(() => {
    if (!sections.length || !requestedSection || requestedSection === section || dirty) return;
    if (sections.some(item => item.key === requestedSection)) setSection(requestedSection);
  }, [requestedSection, sections, section, dirty]);
  useEffect(()=>{if(!section)return;const controller=new AbortController();setLoading(true);setError('');setData(null);setValue(null);setSelected(0);setDirty(false);setPreview(false);setNote('');setCarrySelection([]);
    memberAdminApi(`/api/admin/public-content/sections/${section}`,{signal:controller.signal}).then(result=>{setData(result);setValue(result.value);}).catch(e=>{if(e.name!=='AbortError')setError(e.message);}).finally(()=>{if(!controller.signal.aborted)setLoading(false);});
    return()=>controller.abort();
  },[section,refresh]);
  useEffect(()=>{const warn=e=>{if(dirty){e.preventDefault();e.returnValue='';}};window.addEventListener('beforeunload',warn);return()=>window.removeEventListener('beforeunload',warn);},[dirty]);
  const boardAudit=section==='boards'?auditBoardTerms(value):[];
  const rows=safeRows(value,section);
  const original=rows?.[selected];
  const row=original?viewRow(original,section):null;
  function change(key,newValue){setValue(current=>{
    if(section==='profile')return {...current,[key]:newValue};
    return current.map((item,index)=>index!==selected?item:tupleSection(section)?[key==='name'?newValue:item[0],key==='position'?newValue:item[1]]:{...item,[key]:newValue});
  });setDirty(true);}
  function add(){if(!Array.isArray(value) || section==='profile')return;let entry={};if(tupleSection(section))entry=['',''];else if(['boards','staff'].includes(section))entry={id:Math.max(0,...value.map(p=>Number(p.id)||0))+1,name:'',position:section==='boards'?'กรรมการ':'',image:'',...(section==='boards'?{term:BOARD_TERMS.find(term=>term===value[selected]?.term)||'ชุดที่ 39',status:'active'}:{})};else if(section==='groups')entry={title:'',members:[],term:(catalog.boardMembers||[]).some(person=>person?.term==='ชุดที่ 39') ? 'ชุดที่ 39' : (catalog.boardMembers?.[0]?.term || 'ชุดที่ 39')};else if(section==='products')entry={id:'',title:'',rateValue:0,maxLimit:1,maxTerm:1,guarantee:'',features:[]};else if(section==='deposits')entry={type:'',rate:'0%',minDeposit:'',payFreq:'',highlight:false};else entry={type:'',rate:'0%',maxTerm:'',condition:''};setValue([...value,entry]);setSelected(value.length);setDirty(true);}
  function copyBoardToTerm(){
    if(section!=='boards'||!row)return;
    const target=window.prompt('ระบุชุดกรรมการใหม่ เช่น ชุดที่ 39', 'ชุดที่ 39');
    if(!target?.trim() || target.trim()===row.term)return;
    const id=Math.max(0,...value.map(person=>Number(person.id)||0))+1;
    setValue(current=>[...current,{...row,id,term:target.trim()}]);
    setSelected(value.length);setDirty(true);
    setMessage('สร้างสำเนากรรมการในร่างใหม่แล้ว ชุดเดิมยังคงอยู่ ต้องบันทึกและเผยแพร่ร่างก่อนหน้าเว็บเปลี่ยน');
  }
  function carryBoardsTo39(){
    if(section!=='boards' || !Array.isArray(value) || !carrySelection.length || busy)return;
    const from38=value.filter(p=>p && p.term==='ชุดที่ 38' && p.status!=='former' && carrySelection.includes(Number(p.id)));
    const existing39=new Set(value.filter(p=>p && p.term==='ชุดที่ 39').map(p=>String(p.name||'').trim()));
    const eligible=from38.filter(p=>!existing39.has(String(p.name||'').trim()));
    if(!eligible.length){setMessage('ไม่มีกรรมการที่ต้องคัดลอกใหม่: รายชื่อที่เลือกมีในชุดที่ 39 แล้ว');return;}
    if(!window.confirm(`คัดลอกกรรมการ ${eligible.length} ท่านจากชุด 38 ไปเป็นร่างชุด 39 โดยคงชุด 38 ไว้ทั้งหมด? กรุณาตรวจตำแหน่งและรูปภาพก่อนเผยแพร่`))return;
    let nextId=Math.max(0,...value.map(p=>Number(p.id)||0));
    const additions=eligible.map(person=>({...person,id:++nextId,term:'ชุดที่ 39',status:'active'}));
    setValue(current=>[...current,...additions]);setSelected(value.length);setDirty(true);setCarrySelection([]);
    setMessage(`เพิ่มร่างกรรมการชุด 39 จำนวน ${additions.length} ท่านแล้ว ชุด 38 ไม่ถูกเปลี่ยน โปรดตรวจสอบรายชื่อ รูปภาพ ตำแหน่ง และเอกสารแต่งตั้งก่อนบันทึกร่าง`);
  }
  function startBoardReplacement(){
    if(section!=='boards'||!row)return;
    const id=Math.max(0,...value.map(person=>Number(person.id)||0))+1;
    setValue(current=>[...current,{id,name:'',position:row.position||'กรรมการ',term:row.term||'',workplace:row.workplace||'',image:''}]);
    setSelected(value.length);setDirty(true);
    setMessage('เพิ่มรายการกรรมการทดแทนเป็นร่างแล้ว: กรอกข้อมูลและเผยแพร่ก่อน จากนั้นปรับคณะอนุกรรมการให้ชี้รายการใหม่ แล้วจึงนำกรรมการเดิมออกตามขั้นตอนรักษาประวัติ');
  }
  async function uploadBoardPhoto(file){
    if(!file||section!=='boards'||!row)return;
    setMediaBusy(true);setError('');
    try {
      const response=await mediaLibraryApi.upload(file,{folder:'board',altText:row.name||'ภาพกรรมการ'});
      const photo=response.data?.url||response.data?.data?.url;
      if(!photo)throw new Error('ไม่พบ URL รูปภาพหลังอัปโหลด');
      change('image',photo);
      setMessage('อัปโหลดรูปแล้ว กรุณาบันทึกร่างและเผยแพร่ก่อนหน้าเว็บเปลี่ยน');
    } catch(e){setError(e.message||'อัปโหลดรูปไม่สำเร็จ กรุณาตรวจสิทธิ์ Media Library');}
    finally {setMediaBusy(false);}
  }
  async function save(e){e.preventDefault();if(busy||!definition?.canEdit)return;setBusy(true);setError('');setMessage('');try{
    const result=await memberAdminApi(`/api/admin/public-content/sections/${section}/drafts`,{data:{value,version:data.version,note,source_url:source,effective_on:effective,expires_on:expires||null}});
    setDirty(false);setMessage(`บันทึกร่าง #${result.id} แล้ว ยังไม่เปลี่ยนข้อมูลหน้าเว็บ`);setRefresh(n=>n+1);
  }catch(e){setError(e.message);}finally{setBusy(false);}}
  async function publish(revision){if(!window.confirm(`เผยแพร่ร่าง #${revision.id} มีผล ${revision.effective_on}?`))return;setBusy(true);setError('');try{
    await memberAdminApi(`/api/admin/public-content/revisions/${revision.id}/publish`,{data:{}});await reload();setMessage('อนุมัติแล้ว ข้อมูลจะแสดงตามวันที่มีผล');setRefresh(n=>n+1);
  }catch(e){setError(e.message);}finally{setBusy(false);}}
  return <section className="admin-module public-content-admin"><Link to="/admin/workspace">กลับพื้นที่ทำงาน</Link><AdminPageHeader title="จัดการข้อมูลเว็บไซต์สหกรณ์" description="บันทึกร่าง ตรวจข้อมูลก่อน–หลัง แล้วเผยแพร่ตามวันที่มีผล โดยไม่ต้อง build เว็บไซต์ใหม่"/>
    {error&&<AdminErrorState message={error}/>}{message&&<p role="status">{message}</p>}
    <label>หมวดข้อมูล <select value={section} disabled={busy} onChange={e=>{if(!dirty||window.confirm('มีข้อมูลที่ยังไม่ได้บันทึก ต้องการเปลี่ยนหมวดหรือไม่?')){setSection(e.target.value);setSearchParams(params=>{const next=new URLSearchParams(params);next.set('section',e.target.value);return next;});}}}>{sections.map(item=><option key={item.key} value={item.key}>{item.label}</option>)}</select></label>
    <button className="btn btn-outline" disabled={busy||loading||!section} onClick={()=>{if(!dirty||window.confirm('ทิ้งการแก้ไขและโหลดล่าสุด?'))setRefresh(n=>n+1);}}>โหลดล่าสุด</button>
    {loading&&<p role="status">กำลังโหลดข้อมูล...</p>}{!loading&&!sections.length&&!error&&<p>ยังไม่มีสิทธิ์ดูข้อมูลส่วนนี้ กรุณาติดต่อผู้ดูแลระบบ</p>}
    {value&&data&&<>
      {section!=='profile'&&Array.isArray(value)&&<div className="public-content-toolbar"><label>เลือกรายการ <select value={selected} disabled={busy} onChange={e=>setSelected(Number(e.target.value))}>{value.map((item,index)=><option key={index} value={index}>{index+1}. {labelOf(viewRow(item,section))}</option>)}</select></label>{definition?.canEdit&&<><button disabled={busy} onClick={add}>เพิ่มรายการ</button>{[-1,1].map(offset=><button key={offset} disabled={busy||selected+offset<0||selected+offset>=value.length} onClick={()=>{const next=[...value];[next[selected],next[selected+offset]]=[next[selected+offset],next[selected]];setValue(next);setSelected(selected+offset);setDirty(true);}}>{offset<0?'เลื่อนขึ้น':'เลื่อนลง'}</button>)}<button disabled={busy||!value.length} onClick={()=>{if(window.confirm('นำรายการนี้ออกจากร่าง? ข้อมูลจริงยังไม่เปลี่ยนจนกว่าจะเผยแพร่')){setValue(value.filter((_,index)=>index!==selected));setSelected(0);setDirty(true);}}}>นำออกจากร่าง</button></>}</div>}
      {section==='boards'&&Array.isArray(value)&&<section className="surface-card public-content-board-directory" aria-label="เลือกกรรมการเพื่อแก้ไขข้อมูลและรูปภาพ">
        <header className="public-content-board-tools-heading">
          <h3>ทำเนียบกรรมการแยกตามชุด · จัดการรูปทุกท่าน</h3>
          <p>เลือกกรรมการเพื่อแก้ชื่อ ตำแหน่ง รูปภาพและสถานะ โดยการเปลี่ยนแปลงจะบันทึกเป็นร่างและต้องอนุมัติเผยแพร่</p>
        </header>
        <div className="public-content-board-term-tabs">
          {['ชุดที่ 39','ชุดที่ 38','ทั้งหมด'].map(term=><button key={term} type="button"
            className={boardTermFilter===term?'is-active':''}
            aria-pressed={boardTermFilter===term} onClick={()=>boardTermFilter!==term&&setBoardTermFilter(term)}>{term}</button>)}
        </div>
        <div className="public-content-board-people-grid">
          {value.map((person,index)=>({...person,index})).filter(person=>boardTermFilter==='ทั้งหมด'||person.term===boardTermFilter).map(person=>
            <button type="button" key={person.id||person.index} className={'public-content-board-person-tile'+(selected===person.index?' is-selected':'')}
              aria-pressed={selected===person.index} onClick={()=>setSelected(person.index)}>
              {person.image?<img src={person.image} alt="" loading="lazy"/>:<span className="public-content-board-person-noimg">ยังไม่มีรูป</span>}
              <strong>{person.name||'รอกรอกชื่อ'}</strong>
              <small>{person.position||'ยังไม่ระบุตำแหน่ง'}</small>
              <span>{person.status==='former'?'พ้นตำแหน่ง':person.term}</span>
            </button>)}
        </div>
      </section>}
      {section==='boards'&&<section className="public-content-board-audit surface-card" aria-labelledby="board-audit-heading">
        <h3 id="board-audit-heading">ตรวจสอบคณะกรรมการชุดที่ 38 และ 39</h3>
        <p>เกณฑ์ตรวจรับชุดละ 15 คน (ไม่รวมผู้พ้นตำแหน่ง) รายชื่อชุดเก่ายังคงเดิม และไม่รวมที่ปรึกษาหรือผู้ตรวจสอบกิจการ</p>
        <div className="public-content-board-audit-grid">
          {boardAudit.map(term=><article key={term.term} className="public-content-board-audit-term">
            <h4>{term.term} — {term.count}/15 คน</h4>
            <ul>{term.assigned.map(role=><li key={role.position}>{role.position}: {role.count} คน</li>)}</ul>
            {term.complete?<p className="public-content-board-audit-ok">ผ่านเกณฑ์โครงสร้าง</p>:<div className="public-content-board-audit-warning">
              <strong>ต้องตรวจสอบข้อมูล:</strong>
              <ul>{term.issues.map(issue=><li key={issue}>{issue}</li>)}</ul>
            </div>}
          </article>)}
        </div>
        <p className="public-content-board-audit-note">เป็นการตรวจข้อมูลในตัวแก้ไขเท่านั้น ระบบไม่เปลี่ยนตำแหน่งหรือรายชื่ออัตโนมัติ กรุณาตรวจเอกสารแต่งตั้งก่อนเผยแพร่</p>
      </section>}
      {section==='boards'&&definition?.canEdit&&<section className="surface-card public-content-board-tools" aria-label="คัดเลือกกรรมการดำรงตำแหน่งต่อในชุด 39" style={{marginBottom:20}}>
        <h3>คัดเลือกกรรมการที่ดำรงตำแหน่งต่อ: ชุดที่ 38 → ชุดที่ 39</h3>
        <p>เลือกรายชื่อหลายท่านเพื่อสร้างสำเนาในร่างชุดที่ 39 โดยข้อมูลและรูปเดิมของชุด 38 ไม่ถูกแก้ไข ระบบจะไม่คัดลอกรายชื่อที่อยู่ในชุด 39 แล้ว</p>
        <div className="board-carry-grid">
          {value.filter(p=>p?.term==='ชุดที่ 38'&&p.status!=='former').map(person=>{
            const checked=carrySelection.includes(Number(person.id));
            return <label className="board-carry-person" key={person.id}>
              <input type="checkbox" checked={checked} disabled={busy||mediaBusy}
                onChange={e=>setCarrySelection(prev=>e.target.checked?[...prev,Number(person.id)]:prev.filter(id=>id!==Number(person.id)))}/>
              {person.image?<img src={person.image} alt="" loading="lazy"/>:<span className="board-carry-placeholder">ไม่มีรูป</span>}
              <span><strong>{person.name}</strong><small>{person.position}</small></span>
            </label>;
          })}
        </div>
        <button type="button" className="btn btn-primary" disabled={busy||mediaBusy||carrySelection.length===0}
          onClick={carryBoardsTo39}>คัดลอก {carrySelection.length} ท่านไปเป็นร่างชุดที่ 39</button>
        <p className="public-content-board-audit-note">ต้องตรวจรายชื่อและตำแหน่งจริงจากเอกสารแต่งตั้งก่อน Publish ห้ามถือว่าการคัดลอกเป็นคำสั่งแต่งตั้งอัตโนมัติ</p>
      </section>}
      {section==='boards'&&row&&definition?.canEdit&&<section className="surface-card public-content-board-tools" aria-labelledby="board-tools-heading">
        <header className="public-content-board-tools-heading">
          <h3 id="board-tools-heading">จัดการชุดกรรมการและรูปภาพ</h3>
          <p>สามารถคัดลอกกรรมการไปชุดใหม่โดยไม่เปลี่ยนชุดเดิม และเพิ่มกรรมการใหม่จากปุ่มเพิ่มรายการ</p>
        </header>
        <div className="public-content-board-tools-grid">
          <div className="public-content-board-actions">
            <h4>จัดการข้อมูลกรรมการ</h4>
            <div className="public-content-board-action-buttons">
              <button type="button" className="btn btn-outline" disabled={busy||mediaBusy} onClick={copyBoardToTerm}>คัดลอกกรรมการไปชุดใหม่</button>
              <button type="button" className="btn btn-outline" disabled={busy||mediaBusy} onClick={startBoardReplacement}>เพิ่มกรรมการทดแทนในชุดเดียวกัน</button>
            </div>
          </div>
          <div className="public-content-board-media">
            <h4>รูปภาพกรรมการ</h4>
            <label htmlFor="public-content-board-photo">อัปโหลดรูปภาพ (JPEG/PNG/WebP ไม่เกิน 10 MB)</label>
            <input id="public-content-board-photo" type="file" accept="image/jpeg,image/png,image/webp" disabled={busy||mediaBusy} onChange={e=>{const file=e.target.files?.[0];e.target.value='';if(file)uploadBoardPhoto(file);}} />
            {mediaBusy&&<p role="status">กำลังอัปโหลดรูปภาพ...</p>}
            {row.image ? <figure className="public-content-board-photo-preview">
              <img src={row.image} alt={'ตัวอย่างรูป '+row.name} loading="lazy"/>
              <figcaption>ตัวอย่างรูปภาพ: {row.name}</figcaption>
            </figure> : <p className="public-content-board-no-photo">ยังไม่มีภาพกรรมการในรายการนี้</p>}
          </div>
        </div>
      </section>}
      <form onSubmit={save}><fieldset disabled={busy||mediaBusy||!definition?.canEdit} className="public-content-fields"><legend>{definition?.label}</legend>
        {row&&(fields[section]||[]).map(([key,label,type='text'])=><label key={key}>{label}{['textarea','lines'].includes(type)?<textarea rows={3} value={type==='lines'?(row[key]||[]).join('\n'):row[key]||''} onChange={e=>change(key,type==='lines'?e.target.value.split('\n'):e.target.value)} />:type==='board-position'?<select value={row[key]||''} onChange={e=>change(key,e.target.value)}>
            {row[key]&&!BOARD_POSITION_OPTIONS.includes(row[key])&&<option value={row[key]}>ข้อมูลเดิม: {row[key]} (ควรตรวจสอบ)</option>}
            {BOARD_POSITION_OPTIONS.map(position=><option key={position} value={position}>{position}</option>)}
          </select>:type==='board-term'?<select value={row[key]||''} onChange={e=>change(key,e.target.value)}>
            {row[key]&&!BOARD_TERMS.includes(row[key])&&<option value={row[key]}>{row[key]} (ข้อมูลเดิม)</option>}
            {BOARD_TERMS.map(term=><option key={term} value={term}>{term}</option>)}
          </select>:type==='board-status'?<select value={row[key]||'active'} onChange={e=>change(key,e.target.value)}><option value="active">ดำรงตำแหน่ง</option><option value="former">พ้นตำแหน่ง</option></select>:type==='checkbox'?<input type="checkbox" checked={!!row[key]} onChange={e=>change(key,e.target.checked)}/>:<input type={type==='rate'?'number':type} disabled={section==='products'&&key==='rateValue'} step={type==='rate'?'0.001':undefined} min={['number','rate'].includes(type)?0:undefined} value={type==='rate'?String(row[key]??'').replace('%',''):row[key]??''} onChange={e=>change(key,type==='rate'&&key==='rate'?`${e.target.value}%`:e.target.value)} />}</label>)}
        {section==='products'&&<p>แก้อัตราดอกเบี้ยที่หมวดอัตราเงินกู้ เพื่อให้หน้าเว็บและเครื่องคำนวณใช้ตัวเลขเดียวกัน</p>}
        {section==='groups'&&row&&Array.isArray(row.members)&&<div><label>ชุดกรรมการ<input value={row.term||''} onChange={e=>change('term',e.target.value)} placeholder="ชุดที่ 39" /></label><h3>สมาชิกคณะย่อย</h3>{row.members.map(([id,position],index)=><div key={index} className="public-content-toolbar"><select aria-label="กรรมการ" value={id} onChange={e=>change('members',row.members.map((m,i)=>i===index?[Number(e.target.value),m[1]]:m))}>{catalog.boardMembers.filter(person=>!row.term||person.term===row.term).map(person=><option key={person.id} value={person.id}>{person.name}</option>)}</select><input aria-label="ตำแหน่งในคณะย่อย" value={position} onChange={e=>change('members',row.members.map((m,i)=>i===index?[m[0],e.target.value]:m))}/><button type="button" onClick={()=>change('members',row.members.filter((_,i)=>i!==index))}>นำออก</button></div>)}<button type="button" onClick={()=>change('members',[...row.members,[catalog.boardMembers.find(person=>!row.term||person.term===row.term)?.id,'กรรมการ']])}>เพิ่มกรรมการในคณะ</button></div>}
        <label>วันที่มีผล (ค.ศ.) <input type="date" required value={effective} onChange={e=>setEffective(e.target.value)}/></label><label>เอกสารหรือหน้าอ้างอิง (HTTPS) <input type="url" required value={source} onChange={e=>setSource(e.target.value)}/></label><label>เหตุผลการแก้ไข <textarea required maxLength={1000} value={note} onChange={e=>setNote(e.target.value)}/></label>
        <label>วันสิ้นสุด (ไม่บังคับ)<input type="date" min={effective} value={expires} onChange={e=>{setExpires(e.target.value);setDirty(true);}} /></label><button type="submit" className="btn btn-primary">บันทึกร่างเพื่อรอเผยแพร่</button>
      </fieldset></form>
      <button className="btn btn-outline" onClick={()=>setPreview(p=>!p)}>ดูตัวอย่างร่าง</button>{preview&&<Preview value={value} section={section} boardNames={boardNames}/>}
      <section><h2>ร่างและประวัติการเผยแพร่</h2>{!(Array.isArray(data.history) && data.history.length)&&<p>ยังไม่มีประวัติในระบบใหม่นี้</p>}{(Array.isArray(data.history) ? data.history : []).map(revision=><article className="surface-card public-content-revision" key={revision.id}><h3>#{revision.id} · {revision.state==='draft'?'รอเผยแพร่':revision.state==='published'?'อนุมัติแล้ว':'ไม่อนุมัติ'}</h3><p>{revision.note}</p><p>มีผล {revision.effective_on} · ผู้แก้ไข #{revision.created_by}{revision.published_by&&` · ผู้เผยแพร่ #${revision.published_by}`}</p><a href={revision.source_url} target="_blank" rel="noopener noreferrer">แหล่งอ้างอิง</a><details><summary>ตรวจข้อมูลก่อน–หลัง</summary><h4>ก่อนแก้ไข</h4><Preview value={safeRevisionValue(revision.previous_payload,section)} section={section} boardNames={boardNames}/><h4>หลังแก้ไข</h4><Preview value={safeRevisionValue(revision.payload,section)} section={section} boardNames={boardNames}/></details>
        <div className="public-content-toolbar">{revision.state==='draft'&&definition?.canPublish&&<button disabled={busy||dirty} className="btn btn-primary" onClick={()=>publish(revision)}>อนุมัติและเผยแพร่</button>}{definition?.canEdit&&<button disabled={busy} onClick={()=>{if(!dirty||window.confirm('แทนร่างปัจจุบันด้วยเวอร์ชันนี้?')){setValue(safeRevisionValue(revision.payload,section));setSelected(0);setNote(`นำข้อมูลเวอร์ชัน #${revision.id} มาจัดทำร่างใหม่`);setSource(revision.source_url);setEffective(today());setDirty(true);}}}>ใช้เวอร์ชันนี้สร้างร่างใหม่</button>}</div></article>)}</section>
    </>}
  </section>;
}
