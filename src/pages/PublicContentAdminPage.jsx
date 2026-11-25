import { AdminPageHeader, AdminErrorState } from '../components/admin/AdminUI';
import React, { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Search, ArrowUpDown, ArrowUp, ArrowDown, ExternalLink,
  GitCompare, Eye, CheckCircle2, Clock, XCircle, RotateCcw,
  ChevronLeft, ChevronRight, X, Upload
} from 'lucide-react';
import { memberAdminApi } from '../services/memberAdminApi';
import { mediaLibraryApi } from '../services/mediaLibraryApi';
import { applyPersonPhotoToDraft } from '../utils/personPhotoDraft';
import { usePublicContent } from '../context/PublicContentContext';
import { showConfirm, showDeleteConfirm, showSuccess, showError, showToast, showInfo, confirmPublish, showPrompt } from '../utils/swal';
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

function RevisionStatusBadge({ state }) {
  if (state === 'published') {
    return <span className="pcr-badge is-published"><CheckCircle2 size={13} aria-hidden="true" /> อนุมัติแล้ว</span>;
  }
  if (state === 'draft') {
    return <span className="pcr-badge is-draft"><Clock size={13} aria-hidden="true" /> รอเผยแพร่</span>;
  }
  return <span className="pcr-badge is-rejected"><XCircle size={13} aria-hidden="true" /> {state || 'ไม่อนุมัติ'}</span>;
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
  const staffPhotoFileRef=useRef(null);
  const sectionRef=useRef(section);
  sectionRef.current=section;
  const [carrySelection,setCarrySelection]=useState([]);
  const [boardTermFilter,setBoardTermFilter]=useState('ชุดที่ 39');
  const [historySearch, setHistorySearch] = useState('');
  const [historyStatus, setHistoryStatus] = useState('all');
  const [historyEditor, setHistoryEditor] = useState('all');
  const [historySortField, setHistorySortField] = useState('id');
  const [historySortOrder, setHistorySortOrder] = useState('desc');
  const [historyPage, setHistoryPage] = useState(1);
  const [historyPerPage, setHistoryPerPage] = useState(10);
  const [detailModalRevision, setDetailModalRevision] = useState(null);
  const [diffModalRevision, setDiffModalRevision] = useState(null);
  const definition=sections.find(item=>item.key===section);
  const boardNames=Object.fromEntries((Array.isArray(catalog?.boardMembers) ? catalog.boardMembers : []).filter(p=>p && typeof p==='object').map(p=>[p.id,p.name]));
  useEffect(()=>{const controller=new AbortController();memberAdminApi('/api/admin/public-content/sections',{signal:controller.signal}).then(result=>{
    const available = result.sections || [];
    setSections(available);
    if (requestedSection && !available.some(item => item.key === requestedSection)) {
      setSection('');
      setError('ไม่พบหมวดข้อมูลที่ร้องขอ หรือบัญชีนี้ไม่มีสิทธิ์เข้าถึงหมวด ' + requestedSection + ' — ระบบจะไม่เปลี่ยนไปแก้หมวดอื่นโดยอัตโนมัติ');
      setLoading(false);
    } else {
      setSection(requestedSection || available[0]?.key || '');
      if (!available.length) setLoading(false);
    }
  }).catch(e=>{if(e.name!=='AbortError'){setError(e.message);setLoading(false);}});return()=>controller.abort();},[]);
  useEffect(() => {
    if (!sections.length || !requestedSection || requestedSection === section) return;
    if (dirty && section) {
      // Browser Back/Forward while editing must not leave URL and editable data
      // pointing to different sections. Restore the currently edited section.
      setMessage('ยังมีข้อมูลที่ไม่ได้บันทึก ระบบคงหมวดเดิมไว้เพื่อป้องกันแก้ไขผิดหมวด');
      setSearchParams(params => {
        const current = new URLSearchParams(params);
        current.set('section', section);
        return current;
      }, {replace:true});
      return;
    }
    if (!sections.some(item => item.key === requestedSection)) {
      // Remove the previously loaded section's editor before showing access error.
      setSection('');
      setData(null);
      setValue(null);
      setError('หมวดข้อมูลที่ระบุใน URL ไม่สามารถเข้าถึงได้: ' + requestedSection);
      return;
    }
    setSection(requestedSection);
  }, [requestedSection, sections, section, dirty, setSearchParams]);
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
  async function copyBoardToTerm(){
    if(section!=='boards'||!row)return;
    const target = await showPrompt('ระบุชุดกรรมการใหม่', 'เช่น ชุดที่ 39', 'ชุดที่ 39');
    if(!target?.trim() || target.trim()===row.term)return;
    const id=Math.max(0,...value.map(person=>Number(person.id)||0))+1;
    setValue(current=>[...current,{...row,id,term:target.trim()}]);
    setSelected(value.length);setDirty(true);
    setMessage('สร้างสำเนากรรมการในร่างใหม่แล้ว ชุดเดิมยังคงอยู่ ต้องบันทึกและเผยแพร่ร่างก่อนหน้าเว็บเปลี่ยน');
  }
  async function carryBoardsTo39(){
    if(section!=='boards' || !Array.isArray(value) || !carrySelection.length || busy)return;
    const from38=value.filter(p=>p && p.term==='ชุดที่ 38' && p.status!=='former' && carrySelection.includes(Number(p.id)));
    const existing39=new Set(value.filter(p=>p && p.term==='ชุดที่ 39').map(p=>String(p.name||'').trim()));
    const eligible=from38.filter(p=>!existing39.has(String(p.name||'').trim()));
    if(!eligible.length){showInfo('ไม่มีกรรมการที่ต้องคัดลอกใหม่', 'รายชื่อที่เลือกมีในชุดที่ 39 แล้ว');return;}
    const confirmed = await showConfirm(
      `คัดลอกกรรมการ ${eligible.length} ท่าน?`,
      `คัดลอกกรรมการ ${eligible.length} ท่านจากชุด 38 ไปเป็นร่างชุด 39 โดยคงชุด 38 ไว้ทั้งหมด? กรุณาตรวจตำแหน่งและรูปภาพก่อนเผยแพร่`
    );
    if(!confirmed)return;
    let nextId=Math.max(0,...value.map(p=>Number(p.id)||0));
    const additions=eligible.map(person=>({...person,id:++nextId,term:'ชุดที่ 39',status:'active'}));
    setValue(current=>[...current,...additions]);setSelected(value.length);setDirty(true);setCarrySelection([]);
    setMessage(`เพิ่มร่างกรรมการชุด 39 จำนวน ${additions.length} ท่านแล้ว ชุด 38 ไม่ถูกเปลี่ยน โปรดตรวจสอบรายชื่อ รูปภาพ ตำแหน่ง และเอกสารแต่งตั้งก่อนบันทึกร่าง`);
    showSuccess('คัดลอกสำเร็จ', `เพิ่มร่างกรรมการชุด 39 จำนวน ${additions.length} ท่านแล้ว`);
  }
  function startBoardReplacement(){
    if(section!=='boards'||!row)return;
    const id=Math.max(0,...value.map(person=>Number(person.id)||0))+1;
    setValue(current=>[...current,{id,name:'',position:row.position||'กรรมการ',term:row.term||'',workplace:row.workplace||'',image:''}]);
    setSelected(value.length);setDirty(true);
    setMessage('เพิ่มรายการกรรมการทดแทนเป็นร่างแล้ว: กรอกข้อมูลและเผยแพร่ก่อน จากนั้นปรับคณะอนุกรรมการให้ชี้รายการใหม่ แล้วจึงนำกรรมการเดิมออกตามขั้นตอนรักษาประวัติ');
  }
  async function uploadPersonPhoto(file){
    if(!file||!['boards','staff'].includes(section)||!row||!definition?.canEdit||busy||mediaBusy)return;
    const targetSection=section, targetId=row.id, targetIndex=selected;
    if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size<1||file.size>10*1024*1024){
      setError('รองรับเฉพาะ JPEG, PNG, WebP ขนาดไม่เกิน 10 MB');
      return;
    }
    setMediaBusy(true);setError('');
    try{
      const response=await mediaLibraryApi.uploadPeople(file,{folder:targetSection==='boards'?'board':'staff',altText:row.name||'รูปบุคลากร'});
      const photo=response.data?.url||response.data?.data?.url;
      if(!photo)throw new Error('ไม่พบ URL รูปภาพหลังอัปโหลด');
      if(sectionRef.current!==targetSection){
        setMessage('อัปโหลดสำเร็จแต่หมวดข้อมูลเปลี่ยนแล้ว กรุณากลับไปเลือกภาพในรายการเดิม');
        return;
      }
      // Lock the response to the exact row ID and array index used at upload start.
      setValue(current=>applyPersonPhotoToDraft(current,{currentSection:sectionRef.current,targetSection,targetId,targetIndex,url:photo}));
      setDirty(true);
      setMessage('อัปโหลดรูปบุคลากรแล้ว กรุณาบันทึกร่างและอนุมัติเผยแพร่ก่อนภาพเปลี่ยนบนเว็บไซต์');
      showToast('อัปโหลดรูปภาพสำเร็จ');
    }catch(e){
      setError(e.message||'อัปโหลดรูปไม่สำเร็จ กรุณาตรวจสิทธิ์ Media Library');
      showError('อัปโหลดรูปไม่สำเร็จ',e.message);
    }finally{setMediaBusy(false);}
  }
  async function save(e){
    e.preventDefault();
    if(busy||!definition?.canEdit)return;
    setBusy(true);setError('');setMessage('');
    try{
      const result=await memberAdminApi(`/api/admin/public-content/sections/${section}/drafts`,{data:{value,version:data.version,note,source_url:source,effective_on:effective,expires_on:expires||null}});
      setDirty(false);
      setMessage(`บันทึกร่าง #${result.id} แล้ว ยังไม่เปลี่ยนข้อมูลหน้าเว็บ`);
      showSuccess('บันทึกร่างสำเร็จ!', `บันทึกร่าง #${result.id} แล้ว (ยังไม่เปลี่ยนข้อมูลหน้าเว็บจนกว่าจะอนุมัติเผยแพร่)`);
      setRefresh(n=>n+1);
    }catch(e){
      setError(e.message);
      showError('ไม่สามารถบันทึกร่างได้', e.message);
    }finally{
      setBusy(false);
    }
  }
  async function publish(revision){
    const confirmed = await confirmPublish(
      `ยืนยันเผยแพร่ร่าง #${revision.id}?`,
      `มีผลตั้งแต่วันที่ ${revision.effective_on} ข้อมูลหน้าเว็บจะเปลี่ยนตามเนื้อหาร่างนี้ทันทีหรือตามวันที่มีผล`
    );
    if(!confirmed)return;
    setBusy(true);setError('');
    try{
      await memberAdminApi(`/api/admin/public-content/revisions/${revision.id}/publish`,{data:{}});
      await reload();
      setMessage('อนุมัติแล้ว ข้อมูลจะแสดงตามวันที่มีผล');
      showSuccess('อนุมัติและเผยแพร่สำเร็จ!', `ร่าง #${revision.id} ได้รับการเผยแพร่เรียบร้อยแล้ว`);
      setRefresh(n=>n+1);
    }catch(e){
      setError(e.message);
      showError('ไม่สามารถเผยแพร่ได้', e.message);
    }finally{
      setBusy(false);
    }
  }
  return <section className="admin-module public-content-admin"><Link to="/admin/workspace">กลับพื้นที่ทำงาน</Link><AdminPageHeader title="จัดการข้อมูลเว็บไซต์สหกรณ์" description="บันทึกร่าง ตรวจข้อมูลก่อน–หลัง แล้วเผยแพร่ตามวันที่มีผล โดยไม่ต้อง build เว็บไซต์ใหม่"/>
    {error&&<AdminErrorState message={error}/>}{message&&<p role="status">{message}</p>}
    <label>หมวดข้อมูล <select value={section} disabled={busy||mediaBusy} onChange={async e=>{
      const nextVal = e.target.value;
      if(dirty){
        const confirmed = await showConfirm('เปลี่ยนหมวดข้อมูล?', 'มีข้อมูลที่ยังไม่ได้บันทึก ต้องการเปลี่ยนหมวดหรือไม่?');
        if(!confirmed) return;
      }
      setSection(nextVal);
      setSearchParams(params=>{const next=new URLSearchParams(params);next.set('section',nextVal);return next;});
    }}>{sections.map(item=><option key={item.key} value={item.key}>{item.label}</option>)}</select></label>
    <button className="btn btn-outline" disabled={busy||loading||!section} onClick={async ()=>{
      if(dirty){
        const confirmed = await showConfirm('โหลดข้อมูลล่าสุด?', 'ทิ้งการแก้ไขและโหลดล่าสุด?');
        if(!confirmed) return;
      }
      setRefresh(n=>n+1);
    }}>โหลดล่าสุด</button>
    {loading&&<p role="status">กำลังโหลดข้อมูล...</p>}{!loading&&!sections.length&&!error&&<p>ยังไม่มีสิทธิ์ดูข้อมูลส่วนนี้ กรุณาติดต่อผู้ดูแลระบบ</p>}
    {value&&data&&<>
      {section!=='profile'&&Array.isArray(value)&&<div className="public-content-toolbar"><label>เลือกรายการ <select value={selected} disabled={busy||mediaBusy} onChange={e=>setSelected(Number(e.target.value))}>{value.map((item,index)=><option key={index} value={index}>{index+1}. {labelOf(viewRow(item,section))}</option>)}</select></label>{definition?.canEdit&&<><button disabled={busy||mediaBusy} onClick={add}>เพิ่มรายการ</button>{[-1,1].map(offset=><button key={offset} disabled={busy||mediaBusy||selected+offset<0||selected+offset>=value.length} onClick={()=>{const next=[...value];[next[selected],next[selected+offset]]=[next[selected+offset],next[selected]];setValue(next);setSelected(selected+offset);setDirty(true);}}>{offset<0?'เลื่อนขึ้น':'เลื่อนลง'}</button>)}<button disabled={busy||mediaBusy||!value.length} onClick={async ()=>{
        const confirmed = await showDeleteConfirm('นำรายการออกจากร่าง?', 'นำรายการนี้ออกจากร่าง? ข้อมูลจริงยังไม่เปลี่ยนจนกว่าจะเผยแพร่');
        if(!confirmed) return;
        setValue(value.filter((_,index)=>index!==selected));setSelected(0);setDirty(true);
      }}>นำออกจากร่าง</button></>}</div>}
      {section==='staff'&&Array.isArray(value)&&<section className="surface-card public-content-board-directory" aria-label="เลือกเจ้าหน้าที่เพื่อแก้ไขข้อมูลและรูปภาพ">
        <header className="public-content-board-tools-heading">
          <h3>ทำเนียบเจ้าหน้าที่ · จัดการรูปบุคลากร</h3>
          <p>เลือกรายการเพื่อแก้ไข รูปและข้อมูลอยู่ในร่างของหมวดเจ้าหน้าที่เท่านั้น ไม่เปลี่ยนข้อมูลกรรมการหรือหมวดอื่น</p>
        </header>
        <div className="public-content-board-people-grid">
          {value.map((person,index)=><button type="button" key={person.id||index}
            className={'public-content-board-person-tile'+(selected===index?' is-selected':'')}
            aria-pressed={selected===index} disabled={busy||mediaBusy} onClick={()=>setSelected(index)}>
            {person.image?<img src={person.image} alt="" loading="lazy"/>:<span className="public-content-board-person-noimg">ยังไม่มีรูป</span>}
            <strong>{person.name||'รอกรอกชื่อ'}</strong>
            <small>{person.position||'ยังไม่ระบุตำแหน่ง'}</small>
            <span>{person.department||'ฝ่ายจัดการ'}</span>
          </button>)}
        </div>
      </section>}
      {section==='staff'&&row&&definition?.canEdit&&<section className="surface-card public-content-board-tools" aria-label="อัปโหลดรูปเจ้าหน้าที่">
        <header className="public-content-board-tools-heading">
          <h3>รูปภาพเจ้าหน้าที่: {row.name||'รายการใหม่'}</h3>
          <p>รองรับ JPEG, PNG, WebP ไม่เกิน 10 MB; รูปจะอยู่ในโฟลเดอร์ staff และผูกกับบุคลากรที่เลือกเท่านั้น</p>
        </header>
        <div className="public-content-board-media">
          <p className="public-content-photo-note">เลือกรูปจากอุปกรณ์เพื่ออัปโหลดเข้าสู่ระบบ ไม่ต้องกรอกลิงก์หรือเปิดคลังสื่ออื่น</p>
          <button type="button" className="btn btn-primary public-content-staff-upload-button"
            disabled={busy||mediaBusy} onClick={()=>staffPhotoFileRef.current?.click()}>
            <Upload size={20} aria-hidden="true"/> {row.image?'เลือกไฟล์เปลี่ยนรูปเจ้าหน้าที่':'เลือกไฟล์อัปโหลดรูปเจ้าหน้าที่'}
          </button>
          <label className="public-content-file-help" htmlFor="public-content-staff-photo">ไฟล์ JPEG, PNG หรือ WebP ขนาดไม่เกิน 10 MB</label>
          <input ref={staffPhotoFileRef} id="public-content-staff-photo" className="public-content-visually-hidden-file"
            type="file" accept="image/jpeg,image/png,image/webp" aria-label="เลือกรูปเจ้าหน้าที่จากไฟล์"
            disabled={busy||mediaBusy}
            onChange={e=>{const file=e.target.files?.[0];e.target.value='';if(file)uploadPersonPhoto(file);}}/>
          {mediaBusy&&<p role="status">กำลังอัปโหลดรูปภาพเจ้าหน้าที่...</p>}
          {row.image&&<button type="button" className="btn btn-outline" disabled={busy||mediaBusy}
            onClick={async()=>{const ok=await showDeleteConfirm('นำรูปเจ้าหน้าที่ออกจากร่าง?', 'ภาพบนเว็บไซต์จริงจะยังอยู่จนกว่าจะอนุมัติเผยแพร่ร่างใหม่');if(ok)change('image','');}}>
            นำรูปออกจากร่าง
          </button>}
          {row.image?<figure className="public-content-board-photo-preview">
            <img src={row.image} alt={'ตัวอย่างรูป '+row.name} loading="lazy"/>
            <figcaption>ตัวอย่างรูป: {row.name}</figcaption>
          </figure>:<p className="public-content-board-no-photo">ยังไม่มีรูปเจ้าหน้าที่ในรายการนี้</p>}
        </div>
      </section>}
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
              aria-pressed={selected===person.index} disabled={busy||mediaBusy} onClick={()=>setSelected(person.index)}>
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
            <label htmlFor="public-content-board-photo">เพิ่มหรือเปลี่ยนรูปกรรมการในระบบโดยตรง (JPEG/PNG/WebP ไม่เกิน 10 MB)</label>
            <input id="public-content-board-photo" type="file" accept="image/jpeg,image/png,image/webp" disabled={busy||mediaBusy} onChange={e=>{const file=e.target.files?.[0];e.target.value='';if(file)uploadPersonPhoto(file);}} />
            {mediaBusy&&<p role="status">กำลังอัปโหลดรูปภาพ...</p>}
            {row.image&&<button type="button" className="btn btn-outline" disabled={busy||mediaBusy}
              onClick={async()=>{const ok=await showDeleteConfirm('นำรูปกรรมการออกจากร่าง?', 'ภาพเดิมยังอยู่บนเว็บไซต์จริงจนกว่าจะเผยแพร่ร่างใหม่');if(ok)change('image','');}}>
              นำรูปออกจากร่าง
            </button>}
            {row.image ? <figure className="public-content-board-photo-preview">
              <img src={row.image} alt={'ตัวอย่างรูป '+row.name} loading="lazy"/>
              <figcaption>ตัวอย่างรูปภาพ: {row.name}</figcaption>
            </figure> : <p className="public-content-board-no-photo">ยังไม่มีภาพกรรมการในรายการนี้</p>}
          </div>
        </div>
      </section>}
      <form onSubmit={save}><fieldset disabled={busy||mediaBusy||!definition?.canEdit} className="public-content-fields"><legend>{definition?.label}</legend>
        {row&&(fields[section]||[]).filter(([key])=>!(key==='image' && ['staff','boards'].includes(section))).map(([key,label,type='text'])=><label key={key}>{label}{['textarea','lines'].includes(type)?<textarea rows={3} value={type==='lines'?(row[key]||[]).join('\n'):row[key]||''} onChange={e=>change(key,type==='lines'?e.target.value.split('\n'):e.target.value)} />:type==='board-position'?<select value={row[key]||''} onChange={e=>change(key,e.target.value)}>
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
      {/* -------------------------------------------------------------
          ร่างและประวัติการเผยแพร่ (Data Table)
          ------------------------------------------------------------- */}
      {(() => {
        const handleSort = (field) => {
          if (historySortField === field) {
            setHistorySortOrder(current => current === 'asc' ? 'desc' : 'asc');
          } else {
            setHistorySortField(field);
            setHistorySortOrder('desc');
          }
          setHistoryPage(1);
        };

        const rawHistory = (Array.isArray(data.history) ? data.history : []).map(revision=>{
          return {
            ...revision,
            sortId: Number(revision.id) || 0,
            creatorDisplay: revision.creator_name || (revision.created_by ? `ผู้ใช้ #${revision.created_by}` : 'ระบบ'),
            publisherDisplay: revision.published_by ? (revision.publisher_name || `ผู้ใช้ #${revision.published_by}`) : '—',
          };
        });

        const editors = Array.from(new Set(rawHistory.map(r => r.creatorDisplay).filter(Boolean)));

        const filteredHistory = rawHistory.filter(item => {
          if (historyStatus !== 'all' && item.state !== historyStatus) return false;
          if (historyEditor !== 'all' && item.creatorDisplay !== historyEditor) return false;
          if (historySearch.trim()) {
            const q = historySearch.toLowerCase().trim();
            const matchNote = String(item.note || '').toLowerCase().includes(q);
            const matchId = String(item.id || '').includes(q);
            const matchCreator = item.creatorDisplay.toLowerCase().includes(q);
            const matchPublisher = item.publisherDisplay.toLowerCase().includes(q);
            const matchDate = String(item.effective_on || '').includes(q);
            if (!matchNote && !matchId && !matchCreator && !matchPublisher && !matchDate) return false;
          }
          return true;
        });

        const sortedHistory = [...filteredHistory].sort((a, b) => {
          let cmp = 0;
          if (historySortField === 'id') {
            cmp = a.sortId - b.sortId;
          } else if (historySortField === 'effective_on') {
            cmp = String(a.effective_on || '').localeCompare(String(b.effective_on || ''));
          } else if (historySortField === 'created_at') {
            cmp = String(a.created_at || '').localeCompare(String(b.created_at || ''));
          }
          return historySortOrder === 'asc' ? cmp : -cmp;
        });

        const totalPages = Math.max(1, Math.ceil(sortedHistory.length / historyPerPage));
        const safePage = Math.min(Math.max(1, historyPage), totalPages);
        const paginatedHistory = sortedHistory.slice((safePage - 1) * historyPerPage, safePage * historyPerPage);

        return (
          <>
            <section className="pcr-table-card" aria-labelledby="history-table-heading">
              <header className="pcr-table-header">
                <div>
                  <h2 id="history-table-heading">ร่างและประวัติการเผยแพร่</h2>
                  <p className="pcr-table-subtitle">
                    ตรวจสอบประวัติการแก้ไข การอนุมัติ และเปรียบเทียบข้อมูลก่อน–หลังของแต่ละเวอร์ชัน
                  </p>
                </div>
                <div className="pcr-stats-badges">
                  <span
                    className={`pcr-stat-pill ${historyStatus === 'all' ? 'is-active' : ''}`}
                    onClick={() => { setHistoryStatus('all'); setHistoryPage(1); }}
                    style={{ cursor: 'pointer' }}
                  >
                    ทั้งหมด: {rawHistory.length}
                  </span>
                  <span
                    className={`pcr-stat-pill ${historyStatus === 'published' ? 'is-active' : ''}`}
                    onClick={() => { setHistoryStatus('published'); setHistoryPage(1); }}
                    style={{ cursor: 'pointer' }}
                  >
                    เผยแพร่แล้ว: {rawHistory.filter(h => h.state === 'published').length}
                  </span>
                  <span
                    className={`pcr-stat-pill ${historyStatus === 'draft' ? 'is-active' : ''}`}
                    onClick={() => { setHistoryStatus('draft'); setHistoryPage(1); }}
                    style={{ cursor: 'pointer' }}
                  >
                    รออนุมัติ: {rawHistory.filter(h => h.state === 'draft').length}
                  </span>
                </div>
              </header>

              {/* Filter & Search Bar */}
              <div className="pcr-controls-bar">
                <div className="pcr-search-box">
                  <Search size={16} className="pcr-search-icon" aria-hidden="true" />
                  <input
                    type="search"
                    className="pcr-search-input form-control"
                    placeholder="ค้นหา Version, เหตุผล, ผู้แก้ไข..."
                    value={historySearch}
                    onChange={e => { setHistorySearch(e.target.value); setHistoryPage(1); }}
                  />
                </div>

                <div className="pcr-filters-group">
                  <select
                    className="pcr-filter-select form-control"
                    value={historyStatus}
                    onChange={e => { setHistoryStatus(e.target.value); setHistoryPage(1); }}
                    aria-label="กรองตามสถานะ"
                  >
                    <option value="all">ทุกสถานะ</option>
                    <option value="draft">รอเผยแพร่ (Draft)</option>
                    <option value="published">อนุมัติแล้ว (Published)</option>
                    <option value="rejected">ไม่อนุมัติ (Rejected)</option>
                  </select>

                  {editors.length > 1 && (
                    <select
                      className="pcr-filter-select form-control"
                      value={historyEditor}
                      onChange={e => { setHistoryEditor(e.target.value); setHistoryPage(1); }}
                      aria-label="กรองตามผู้แก้ไข"
                    >
                      <option value="all">ผู้แก้ไขทุกคน</option>
                      {editors.map(ed => (
                        <option key={ed} value={ed}>{ed}</option>
                      ))}
                    </select>
                  )}

                  <select
                    className="pcr-filter-select form-control"
                    value={historyPerPage}
                    onChange={e => { setHistoryPerPage(Number(e.target.value)); setHistoryPage(1); }}
                    aria-label="จำนวนต่อหน้า"
                  >
                    <option value={5}>5 รายการ / หน้า</option>
                    <option value={10}>10 รายการ / หน้า</option>
                    <option value={25}>25 รายการ / หน้า</option>
                  </select>
                </div>
              </div>

              {/* Responsive Table */}
              <div className="pcr-scroll-container">
                <table className="pcr-data-table">
                  <thead>
                    <tr>
                      <th className="pcr-th-sortable" onClick={() => handleSort('id')}>
                        Version {historySortField === 'id' ? (historySortOrder === 'asc' ? <ArrowUp size={13} className="pcr-sort-indicator" /> : <ArrowDown size={13} className="pcr-sort-indicator" />) : <ArrowUpDown size={13} className="pcr-sort-indicator" />}
                      </th>
                      <th>Status</th>
                      <th>Change Summary</th>
                      <th className="pcr-th-sortable" onClick={() => handleSort('effective_on')}>
                        Effective Date {historySortField === 'effective_on' ? (historySortOrder === 'asc' ? <ArrowUp size={13} className="pcr-sort-indicator" /> : <ArrowDown size={13} className="pcr-sort-indicator" />) : <ArrowUpDown size={13} className="pcr-sort-indicator" />}
                      </th>
                      <th>Editor</th>
                      <th>Publisher</th>
                      <th>Reference</th>
                      <th>Before/After Diff</th>
                      <th style={{ textAlign: 'center' }}>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {paginatedHistory.length === 0 ? (
                      <tr>
                        <td colSpan={9} className="pcr-empty-cell">
                          {rawHistory.length === 0
                            ? 'ยังไม่มีประวัติในระบบใหม่นี้'
                            : 'ไม่พบรายการที่ตรงกับเงื่อนไขการค้นหา'}
                        </td>
                      </tr>
                    ) : (
                      paginatedHistory.map(revision => (
                        <tr key={revision.id}>
                          <td className="pcr-version-col">
                            <span className="pcr-version-badge">#{revision.id}</span>
                          </td>
                          <td>
                            <RevisionStatusBadge state={revision.state} />
                          </td>
                          <td className="pcr-summary-cell" title={revision.note}>
                            <span className="pcr-summary-text">{revision.note || '—'}</span>
                          </td>
                          <td className="pcr-effective-col">
                            <div>{revision.effective_on || '—'}</div>
                            {revision.expires_on && (
                              <small style={{ color: 'var(--text-muted)' }}>ถึง {revision.expires_on}</small>
                            )}
                          </td>
                          <td className="pcr-user-col">
                            {revision.creatorDisplay}
                          </td>
                          <td className="pcr-user-col">
                            {revision.publisherDisplay}
                          </td>
                          <td className="pcr-ref-col">
                            {revision.source_url ? (
                              <a
                                href={revision.source_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="pcr-btn-link"
                                title={revision.source_url}
                              >
                                <ExternalLink size={13} /> แหล่งอ้างอิง
                              </a>
                            ) : '—'}
                          </td>
                          <td className="pcr-diff-col-cell">
                            <button
                              type="button"
                              className="pcr-btn-diff"
                              onClick={() => setDiffModalRevision(revision)}
                              title="ดูความแตกต่างก่อนและหลังแก้ไข"
                            >
                              <GitCompare size={14} /> เปรียบเทียบ
                            </button>
                          </td>
                          <td>
                            <div className="pcr-actions-cell">
                              <button
                                type="button"
                                className="btn btn-outline pcr-btn-sm"
                                onClick={() => setDetailModalRevision(revision)}
                                title="ดูรายละเอียดฉบับสมบูรณ์"
                              >
                                <Eye size={13} /> ดูรายละเอียด
                              </button>
                              {revision.state === 'draft' && definition?.canPublish && (
                                <button
                                  type="button"
                                  disabled={busy || dirty}
                                  className="btn btn-primary pcr-btn-sm"
                                  onClick={() => publish(revision)}
                                  title="อนุมัติและเผยแพร่ข้อมูลนี้"
                                >
                                  <CheckCircle2 size={13} /> อนุมัติและเผยแพร่
                                </button>
                              )}
                              {definition?.canEdit && (
                                <button
                                  type="button"
                                  disabled={busy}
                                  className="btn btn-outline pcr-btn-sm"
                                  onClick={() => useRevisionAsDraft(revision)}
                                  title="นำข้อมูลนี้ไปเป็นร่างใหม่ในฟอร์ม"
                                >
                                  <RotateCcw size={13} /> ใช้เป็นร่างใหม่
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>

              {/* Pagination Bar */}
              {sortedHistory.length > 0 && (
                <div className="pcr-pagination-bar">
                  <div>
                    แสดง {(safePage - 1) * historyPerPage + 1} - {Math.min(safePage * historyPerPage, sortedHistory.length)} จากทั้งหมด {sortedHistory.length} รายการ
                  </div>
                  <div className="pcr-pagination-nav">
                    <button
                      type="button"
                      className="pcr-page-btn"
                      disabled={safePage <= 1}
                      onClick={() => setHistoryPage(p => Math.max(1, p - 1))}
                    >
                      <ChevronLeft size={16} /> ก่อนหน้า
                    </button>
                    <span className="pcr-page-info">
                      หน้า {safePage} / {totalPages}
                    </span>
                    <button
                      type="button"
                      className="pcr-page-btn"
                      disabled={safePage >= totalPages}
                      onClick={() => setHistoryPage(p => Math.min(totalPages, p + 1))}
                    >
                      ถัดไป <ChevronRight size={16} />
                    </button>
                  </div>
                </div>
              )}
            </section>

            {/* Detail Modal */}
            {detailModalRevision && (
              <div
                className="pcr-modal-overlay"
                onMouseDown={e => { if (e.target === e.currentTarget) setDetailModalRevision(null); }}
              >
                <div className="pcr-modal-card" role="dialog" aria-modal="true" aria-labelledby="pcr-detail-title">
                  <header className="pcr-modal-header">
                    <h3 id="pcr-detail-title">รายละเอียดร่างและประวัติเวอร์ชัน #{detailModalRevision.id}</h3>
                    <button
                      type="button"
                      className="pcr-modal-close-btn"
                      onClick={() => setDetailModalRevision(null)}
                      aria-label="ปิดหน้าต่าง"
                    >
                      <X size={20} />
                    </button>
                  </header>
                  <div className="pcr-modal-body">
                    <div className="pcr-meta-grid">
                      <div className="pcr-meta-item">
                        <label>เวอร์ชัน / ลำดับ</label>
                        <div>#{detailModalRevision.id}</div>
                      </div>
                      <div className="pcr-meta-item">
                        <label>สถานะ</label>
                        <div><RevisionStatusBadge state={detailModalRevision.state} /></div>
                      </div>
                      <div className="pcr-meta-item">
                        <label>วันที่มีผล</label>
                        <div>{detailModalRevision.effective_on || '—'}</div>
                      </div>
                      <div className="pcr-meta-item">
                        <label>วันสิ้นสุด</label>
                        <div>{detailModalRevision.expires_on || 'ไม่มีกำหนด'}</div>
                      </div>
                      <div className="pcr-meta-item">
                        <label>ผู้จัดทำร่าง (Editor)</label>
                        <div>{detailModalRevision.creatorDisplay}</div>
                      </div>
                      <div className="pcr-meta-item">
                        <label>วันที่บันทึกร่าง</label>
                        <div>{detailModalRevision.created_at || '—'}</div>
                      </div>
                      <div className="pcr-meta-item">
                        <label>ผู้อนุมัติ / เผยแพร่ (Publisher)</label>
                        <div>{detailModalRevision.publisherDisplay}</div>
                      </div>
                      <div className="pcr-meta-item">
                        <label>วันที่เผยแพร่</label>
                        <div>{detailModalRevision.published_at || '—'}</div>
                      </div>
                    </div>

                    <div style={{ marginBottom: '1.25rem' }}>
                      <label style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-muted)' }}>แหล่งอ้างอิง</label>
                      <div style={{ marginTop: '0.25rem' }}>
                        {detailModalRevision.source_url ? (
                          <a
                            href={detailModalRevision.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="pcr-btn-link"
                          >
                            <ExternalLink size={14} /> {detailModalRevision.source_url}
                          </a>
                        ) : 'ไม่มีระบุ'}
                      </div>
                    </div>

                    <div>
                      <label style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-muted)' }}>เหตุผลการแก้ไข (Change Summary)</label>
                      <div className="pcr-note-block">{detailModalRevision.note || 'ไม่มีเหตุผลระบุ'}</div>
                    </div>
                  </div>
                  <footer className="pcr-modal-footer">
                    <button
                      type="button"
                      className="btn btn-outline"
                      onClick={() => {
                        const targetRev = detailModalRevision;
                        setDetailModalRevision(null);
                        setDiffModalRevision(targetRev);
                      }}
                    >
                      <GitCompare size={14} /> ตรวจข้อมูลก่อน–หลัง
                    </button>
                    {detailModalRevision.state === 'draft' && definition?.canPublish && (
                      <button
                        type="button"
                        disabled={busy || dirty}
                        className="btn btn-primary"
                        onClick={() => {
                          const targetRev = detailModalRevision;
                          setDetailModalRevision(null);
                          publish(targetRev);
                        }}
                      >
                        <CheckCircle2 size={14} /> อนุมัติและเผยแพร่
                      </button>
                    )}
                    {definition?.canEdit && (
                      <button
                        type="button"
                        disabled={busy}
                        className="btn btn-outline"
                        onClick={() => useRevisionAsDraft(detailModalRevision)}
                      >
                        <RotateCcw size={14} /> ใช้เวอร์ชันนี้สร้างร่างใหม่
                      </button>
                    )}
                    <button type="button" className="btn btn-outline" onClick={() => setDetailModalRevision(null)}>
                      ปิด
                    </button>
                  </footer>
                </div>
              </div>
            )}

            {/* Before / After Diff Modal */}
            {diffModalRevision && (() => {
              const revision = diffModalRevision;
              return (
                <div
                  className="pcr-modal-overlay"
                  onMouseDown={e => { if (e.target === e.currentTarget) setDiffModalRevision(null); }}
                >
                  <div className="pcr-modal-card is-wide" role="dialog" aria-modal="true" aria-labelledby="pcr-diff-title">
                    <header className="pcr-modal-header">
                      <div>
                        <h3 id="pcr-diff-title">เปรียบเทียบข้อมูลก่อน–หลังแก้ไข: เวอร์ชัน #{revision.id}</h3>
                        <small style={{ color: 'var(--text-muted)' }}>{revision.note}</small>
                      </div>
                      <button
                        type="button"
                        className="pcr-modal-close-btn"
                        onClick={() => setDiffModalRevision(null)}
                        aria-label="ปิดหน้าต่าง"
                      >
                        <X size={20} />
                      </button>
                    </header>
                    <div className="pcr-modal-body">
                      <div className="pcr-diff-split">
                        <div className="pcr-diff-col is-before">
                          <h4>ข้อมูลก่อนแก้ไข</h4>
                          <Preview value={safeRevisionValue(revision.previous_payload,section)} section={section} boardNames={boardNames}/>
                        </div>
                        <div className="pcr-diff-col is-after">
                          <h4>ข้อมูลหลังแก้ไข</h4>
                          <Preview value={safeRevisionValue(revision.payload,section)} section={section} boardNames={boardNames}/>
                        </div>
                      </div>
                    </div>
                    <footer className="pcr-modal-footer">
                      <button type="button" className="btn btn-outline" onClick={() => setDiffModalRevision(null)}>
                        ปิด
                      </button>
                    </footer>
                  </div>
                </div>
              );
            })()}
          </>
        );
      })()}
    </>}
  </section>;
}
