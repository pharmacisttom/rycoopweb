import React, { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, ClipboardCheck, Download, FileText, Search, ShieldCheck } from 'lucide-react';
import { Link } from 'react-router-dom';
import { fetchDocuments } from '../../services/api';
import './LoanDocumentChecklist.css';

// Preparation guidance only. These are examples to confirm with cooperative staff,
// not mandatory eligibility rules or an authorization to submit a loan request.
const checklistGroups = [
  {
    title: 'เอกสารผู้ขอกู้ — ตรวจสอบก่อนเดินทาง',
    items: [
      ['identity', 'เอกสารยืนยันตัวตนที่สหกรณ์กำหนด', 'สอบถามว่าต้องใช้ตัวจริงหรือสำเนา และจำนวนชุด'],
      ['income', 'หลักฐานรายได้หรือรายการหักเงินเดือน (ถ้ามีการขอ)', 'ตรวจสอบช่วงเวลาของเอกสารที่ต้องใช้กับเจ้าหน้าที่'],
      ['account', 'ข้อมูลบัญชีธนาคารสำหรับรับเงิน (ถ้าประเภทเงินกู้กำหนด)', 'ตรวจสอบชื่อบัญชีและรายการที่สหกรณ์รองรับ'],
    ],
  },
  {
    title: 'เอกสารเฉพาะประเภท — ใช้เมื่อเจ้าหน้าที่ระบุ',
    items: [
      ['application', 'แบบฟอร์มคำขอหรือเอกสารประกอบประเภทเงินกู้', 'ดาวน์โหลดฉบับที่เผยแพร่จากศูนย์เอกสารด้านล่าง'],
      ['guarantor', 'เอกสารผู้ค้ำประกันหรือหลักประกัน (ถ้ามี)', 'ขึ้นอยู่กับประเภทเงินกู้และระเบียบที่มีผล'],
      ['purpose', 'หลักฐานวัตถุประสงค์ เช่น เอกสารที่ดิน ค่าใช้จ่าย หรือการศึกษา (ถ้ามี)', 'เตรียมเฉพาะกรณีที่สหกรณ์ร้องขอ'],
    ],
  },
];

const isLoanDocument = (doc) => /กู้|สินเชื่อ|ค้ำประกัน|หลักประกัน|loan/i.test(
  [doc?.title,doc?.category].filter(Boolean).join(' ')
);

export default function LoanReadinessChecklist() {
  const [checked,setChecked] = useState({});
  const [documents,setDocuments] = useState([]);
  const [loading,setLoading] = useState(true);
  const [error,setError] = useState('');
  const [term,setTerm] = useState('');
  const total=checklistGroups.reduce((n,g)=>n+g.items.length,0);
  const complete=Object.values(checked).filter(Boolean).length;

  useEffect(()=>{
    let active=true;
    fetchDocuments().then(result=>{
      if(!active)return;
      if(!result?.success||!Array.isArray(result.data))throw new Error(result?.message||'ไม่สามารถโหลดเอกสารได้');
      setDocuments(result.data.filter(doc=>doc&&doc.id!=null&&typeof doc.title==='string'));
      setError('');
    }).catch(err=>{if(active)setError(err.message||'ไม่สามารถโหลดรายการเอกสารได้');})
      .finally(()=>{if(active)setLoading(false);});
    return ()=>{active=false};
  },[]);

  const loanDocs=useMemo(()=>documents.filter(isLoanDocument),[documents]);
  const visibleDocs=useMemo(()=>{
    const q=term.trim().toLocaleLowerCase('th');
    return loanDocs.filter(doc=>!q||[doc.title,doc.category].some(v=>String(v||'').toLocaleLowerCase('th').includes(q)));
  },[loanDocs,term]);

  return <div className="loan-preparation" id="loan-document-checklist">
    <div className="loan-preparation-heading">
      <ClipboardCheck size={30} aria-hidden="true"/>
      <div>
        <h2>Checklist เตรียมเอกสารเงินกู้</h2>
        <p>ใช้สำหรับจัดเตรียมและตรวจสอบเอกสารก่อนติดต่อเจ้าหน้าที่สหกรณ์เท่านั้น ไม่ใช่การยื่นคำขอหรือการตรวจรับรองสิทธิ</p>
      </div>
    </div>

    <div className="loan-preparation-summary" role="status">
      <span><CheckCircle2 size={20} aria-hidden="true"/> ติ๊กเอกสารที่ได้เตรียมไว้แล้ว</span>
      <strong>{complete} / {total} รายการ</strong>
      <button type="button" onClick={()=>setChecked({})} disabled={complete===0}>ล้างเครื่องหมาย</button>
    </div>
    <div className="loan-preparation-groups">
      {checklistGroups.map(group=><section className="loan-preparation-group" key={group.title}>
        <h3>{group.title}</h3>
        {group.items.map(([id,label,note])=><label key={id} className="loan-preparation-check">
          <input type="checkbox" checked={Boolean(checked[id])}
            onChange={event=>setChecked(current=>({...current,[id]:event.target.checked}))}/>
          <span><strong>{label}</strong><small>{note}</small></span>
        </label>)}
      </section>)}
    </div>

    <p className="loan-preparation-caution"><ShieldCheck size={20} aria-hidden="true"/> รายการนี้เป็นแนวทางเตรียมตัวเบื้องต้น ข้อกำหนด จำนวนชุด ลายเซ็น และเอกสารเฉพาะประเภทอาจเปลี่ยนแปลงได้ โปรดยืนยันกับสหกรณ์ก่อนนำเอกสารไปยื่นต่อเจ้าหน้าที่</p>

    <section className="loan-preparation-downloads" aria-labelledby="loan-preparation-doc-heading">
      <div className="loan-preparation-download-header">
        <div>
          <h3 id="loan-preparation-doc-heading">ดาวน์โหลดแบบฟอร์มและเอกสารที่เผยแพร่</h3>
          <p>แสดงเฉพาะรายการจากศูนย์เอกสารสหกรณ์ที่มีชื่อหรือหมวดหมู่เกี่ยวกับเงินกู้หรือหลักประกัน</p>
        </div>
        <Link className="btn btn-outline" to="/documents"><FileText size={18} aria-hidden="true"/> ดูเอกสารทั้งหมด</Link>
      </div>
      <label className="loan-preparation-search"><Search size={20} aria-hidden="true"/>
        <span className="sr-only">ค้นหาเอกสารเงินกู้</span>
        <input type="search" placeholder="ค้นหาชื่อแบบฟอร์ม เช่น กู้สามัญ กู้ฉุกเฉิน" value={term} onChange={event=>setTerm(event.target.value)}/>
      </label>
      {loading && <p role="status">กำลังโหลดเอกสารที่สหกรณ์เผยแพร่…</p>}
      {error && <p role="alert">ไม่สามารถตรวจสอบรายการดาวน์โหลดได้: {error} กรุณาเปิดศูนย์เอกสารแทน</p>}
      {!loading&&!error&&loanDocs.length===0&&<p role="status">ยังไม่พบเอกสารหมวดเงินกู้ที่เผยแพร่บนระบบ กรุณาตรวจสอบกับเจ้าหน้าที่หรือเปิดศูนย์เอกสาร</p>}
      {!loading&&!error&&loanDocs.length>0&&visibleDocs.length===0&&<p role="status">ไม่พบเอกสารที่ตรงกับคำค้นหา</p>}
      {!loading&&!error&&visibleDocs.length>0&&<div className="loan-preparation-document-list">
        {visibleDocs.map(doc=><article className="loan-preparation-document" key={doc.id}>
          <FileText size={23} aria-hidden="true"/>
          <div><strong>{doc.title}</strong><small>{doc.category||'เอกสารสหกรณ์'}</small></div>
          <a className="btn btn-primary" href={`/api/public/documents/${encodeURIComponent(doc.id)}/download`}
            target="_blank" rel="noopener noreferrer" aria-label={`เปิดหรือดาวน์โหลดเอกสาร ${doc.title}`}>
            <Download size={18} aria-hidden="true"/> เปิด/ดาวน์โหลด
          </a>
        </article>)}
      </div>}
    </section>
  </div>;
}
