import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {Link} from 'react-router-dom';
import {useAuth} from '../context/AuthContext';
import {HeartHandshake, FileText, RefreshCw, ChevronDown, Search, ShieldCheck, PhoneCall} from 'lucide-react';
import './WelfarePage.css';

const normalize = value => Array.isArray(value)
  ? value.filter(item => item && typeof item === 'object' && item.id != null && typeof item.title === 'string' && item.title.trim())
  : [];

function WelfareCard({item}) {
  const [expanded,setExpanded] = useState(false);
  const details=[
    ['รายละเอียดสิทธิประโยชน์',item.full_description],
    ['คุณสมบัติและเงื่อนไข',item.eligibility],
    ['เอกสารประกอบการยื่นคำขอ',item.required_documents],
    ['ขั้นตอนการขอรับสวัสดิการ',item.application_process],
    ['ช่องทางติดต่อ',item.contact_info]
  ].filter(([,value]) => typeof value==='string' && value.trim());
  return <article className="welfare-item-card">
    <div className="welfare-item-symbol" aria-hidden="true"><HeartHandshake size={29}/></div>
    <div className="welfare-item-top">
      <span className="welfare-category-label">{item.category || 'สวัสดิการสมาชิก'}</span>
      <h2>{item.title}</h2>
      {item.benefit_amount && <div className="welfare-benefit"><span>สิทธิประโยชน์</span><strong>{item.benefit_amount}</strong></div>}
      {item.short_description && <p className="welfare-item-summary">{item.short_description}</p>}
    </div>
    <div className="welfare-item-footer">
      <button type="button" className="welfare-details-button" aria-expanded={expanded}
        aria-controls={'welfare-details-'+item.id} onClick={()=>setExpanded(v=>!v)}>
        {expanded?'ย่อรายละเอียด':'อ่านเงื่อนไขและวิธีขอรับสิทธิ'}<ChevronDown size={18} className={expanded?'is-open':''}/>
      </button>
      {expanded && <div id={'welfare-details-'+item.id} className="welfare-item-details">
        {details.length ? details.map(([label,value])=><section key={label}><h3>{label}</h3><p>{value}</p></section>)
          : <p>โปรดติดต่อสหกรณ์เพื่อตรวจสอบรายละเอียดและเงื่อนไขล่าสุด</p>}
      </div>}
    </div>
  </article>;
}

export default function WelfarePage() {
  const {user,sessionStatus}=useAuth();
  const canManage=sessionStatus==='online' && (user?.role==='super_admin'||user?.role_slug==='super_admin');
  const [rows,setRows]=useState([]),[loading,setLoading]=useState(true),[error,setError]=useState('');
  const [refresh,setRefresh]=useState(0),[category,setCategory]=useState('ทั้งหมด'),[search,setSearch]=useState('');
  useEffect(()=>{
    const ac=new AbortController();
    setLoading(true);setError('');
    fetch('/api/public/welfare',{signal:ac.signal,credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'})
      .then(async response=>{
        if(!response.ok)throw new Error(response.status===503?'ระบบสวัสดิการยังไม่พร้อมให้บริการในขณะนี้':'ไม่สามารถโหลดข้อมูลสวัสดิการได้');
        const result=await response.json();
        if(!result.success||!Array.isArray(result.data))throw new Error('รูปแบบข้อมูลสวัสดิการไม่ถูกต้อง');
        return normalize(result.data);
      })
      .then(setRows)
      .catch(err=>{if(err.name!=='AbortError'){setRows([]);setError(err.message||'ไม่สามารถโหลดข้อมูลได้');}})
      .finally(()=>{if(!ac.signal.aborted)setLoading(false)});
    return()=>ac.abort();
  },[refresh]);
  const categories=useMemo(()=>['ทั้งหมด',...new Set(rows.map(item=>String(item.category||'สวัสดิการสมาชิก').trim()))],[rows]);
  const shown=useMemo(()=>rows.filter(item=>(category==='ทั้งหมด'||(item.category||'สวัสดิการสมาชิก')===category)
    && [item.title,item.short_description,item.benefit_amount].some(value=>String(value||'').toLocaleLowerCase('th').includes(search.trim().toLocaleLowerCase('th')))),[rows,category,search]);
  return <main className="section welfare-page">
    <div className="container">
      <div className="welfare-hero">
        <span className="welfare-hero-label"><HeartHandshake size={16}/> สิทธิประโยชน์สมาชิก</span>
        <h1>สวัสดิการสมาชิกและครอบครัว</h1>
        <p>ตรวจสอบสวัสดิการและเงื่อนไขที่สหกรณ์ประกาศเผยแพร่ พร้อมเตรียมเอกสารก่อนยื่นคำขอ</p>
      </div>
      <div className="welfare-service-bar">
        <div><ShieldCheck size={22}/><span>แสดงเฉพาะข้อมูลที่เผยแพร่จากระบบสหกรณ์</span></div>
        {canManage && <Link to="/admin/welfare"><HeartHandshake size={18}/> จัดการสวัสดิการ (หลังบ้าน)</Link>}
        <Link to="/documents"><FileText size={18}/> ศูนย์เอกสารและแบบฟอร์ม</Link>
        <Link to="/contact"><PhoneCall size={18}/> ติดต่อสหกรณ์</Link>
      </div>
      {loading ? <div className="welfare-status-panel" role="status"><div className="welfare-loading-mark"/><h2>กำลังโหลดข้อมูลสวัสดิการ</h2><p>กำลังตรวจสอบรายการที่เผยแพร่ล่าสุด</p></div>
      : error ? <div className="welfare-status-panel" role="alert"><HeartHandshake size={40}/><h2>ยังไม่สามารถแสดงข้อมูลสวัสดิการได้</h2><p>{error}</p><button type="button" className="btn btn-primary" onClick={()=>setRefresh(n=>n+1)}><RefreshCw size={17}/> ลองโหลดข้อมูลอีกครั้ง</button></div>
      : !rows.length ? <div className="welfare-status-panel" role="status"><HeartHandshake size={40}/><h2>ยังไม่มีรายการสวัสดิการที่เผยแพร่</h2><p>โปรดติดตามประกาศอย่างเป็นทางการจากสหกรณ์ ขณะนี้ไม่แสดงข้อมูลตัวอย่างหรือสิทธิประโยชน์ที่ยังไม่อนุมัติ</p><Link className="btn btn-outline" to="/documents"><FileText size={17}/> ดูเอกสารและประกาศ</Link></div>
      : <>
        <div className="welfare-filters">
          <div><h2>รายการสวัสดิการ</h2><p>ข้อมูลเผยแพร่ {rows.length.toLocaleString('th-TH')} รายการ</p></div>
          <label className="welfare-search"><Search size={19}/><span className="sr-only">ค้นหาสวัสดิการ</span><input type="search" value={search} onChange={e=>setSearch(e.target.value)} placeholder="ค้นหาชื่อหรือสิทธิประโยชน์"/></label>
        </div>
        <nav className="welfare-categories" aria-label="เลือกหมวดสวัสดิการ">{categories.map(name=><button type="button" key={name} aria-pressed={category===name} className={category===name?'is-active':''} onClick={()=>setCategory(name)}>{name}</button>)}</nav>
        {shown.length ? <div className="welfare-card-grid">{shown.map(item=><WelfareCard key={item.id} item={item}/>)}</div>
          : <div className="welfare-status-panel" role="status"><h2>ไม่พบรายการที่ค้นหา</h2><p>ลองใช้คำค้นอื่นหรือเลือกหมวดทั้งหมด</p><button className="btn btn-outline" type="button" onClick={()=>{setSearch('');setCategory('ทั้งหมด')}}>ล้างตัวกรอง</button></div>}
      </>}
      <p className="welfare-disclaimer">ข้อมูลการขอรับสิทธิขึ้นอยู่กับระเบียบและประกาศที่มีผลใช้บังคับ กรุณายืนยันรายละเอียดกับเจ้าหน้าที่ก่อนยื่นคำขอ</p>
    </div>
  </main>;
}
