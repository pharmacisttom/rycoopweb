import React, { useMemo, useState } from 'react';
import { Calculator, FileText, Printer } from 'lucide-react';

/** Manual simulation only: no bundled product, rate or eligibility claims. */
export default function ManualLoanCalculator() {
  const [principal, setPrincipal] = useState('');
  const [annualRate, setAnnualRate] = useState('');
  const [months, setMonths] = useState('');
  const [method, setMethod] = useState('annuity');
  const [showTable, setShowTable] = useState(false);
  const valid = principal !== '' && annualRate !== '' && months !== ''
    && Number.isFinite(Number(principal)) && Number(principal) > 0 && Number(principal) <= 100000000
    && Number.isFinite(Number(annualRate)) && Number(annualRate) >= 0 && Number(annualRate) <= 50
    && Number.isInteger(Number(months)) && Number(months) >= 1 && Number(months) <= 600;
  const result = useMemo(() => {
    if (!valid) return null;
    const p = Number(principal), r = Number(annualRate) / 1200, n = Number(months);
    const monthly = method === 'annuity' ? (r === 0 ? p / n : p * r / (1 - Math.pow(1 + r, -n))) : p / n;
    const schedule = [];
    let balance = p, totalInterest = 0, totalPayment = 0;
    for (let i = 1; i <= n; i++) {
      const interest = balance * r;
      const principalPaid = Math.min(balance, method === 'annuity' ? Math.max(0, monthly - interest) : p / n);
      const payment = interest + principalPaid;
      balance = Math.max(0, balance - principalPaid);
      totalInterest += interest;
      totalPayment += payment;
      if (i <= 24 || i % 12 === 0 || i === n) schedule.push({month:i,payment,interest,principalPaid,balance});
    }
    return { firstPayment: method === 'annuity' ? monthly : p/n + p*r,
      lastPayment: method === 'annuity' ? monthly : p/n + (p/n)*r,
      totalInterest, totalPayment, schedule };
  }, [valid, principal, annualRate, months, method]);
  const fmt = n => Number(n).toLocaleString('th-TH', {minimumFractionDigits:2,maximumFractionDigits:2});
  return <section className="glass-card" aria-label="โปรแกรมจำลองค่างวดเงินกู้" style={{padding:'clamp(18px,3vw,38px)',borderRadius:'var(--radius-xl)'}}>
    <h2 style={{display:'flex',alignItems:'center',gap:10,fontSize:'clamp(1.35rem,2vw,1.8rem)',marginBottom:14}}>
      <Calculator aria-hidden="true" /> โปรแกรมคำนวณเงินกู้สหกรณ์
    </h2>
    <div role="status" style={{padding:'14px 18px',marginBottom:22,background:'var(--bg-subtle)',borderRadius:12,border:'1px solid var(--border-subtle)',lineHeight:1.7}}>
      <strong>โหมดจำลองโดยกรอกข้อมูลเอง</strong>
      <p style={{margin:'6px 0 0'}}>ขณะนี้ไม่มีข้อมูลผลิตภัณฑ์สินเชื่อและอัตราดอกเบี้ยที่ได้รับอนุมัติให้เผยแพร่จากหลังบ้าน กรุณากรอกอัตราดอกเบี้ยตามประกาศที่ตรวจสอบแล้วด้วยตนเอง ผลลัพธ์นี้ไม่ใช่ข้อเสนอสินเชื่อหรือยอดชำระจริงจากสหกรณ์</p>
    </div>
    <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(min(100%,300px),1fr))',gap:'clamp(20px,3vw,40px)'}}>
      <div style={{display:'grid',gap:18,alignContent:'start'}}>
        <label className="form-group">จำนวนเงินต้น (บาท)
          <input className="form-control" type="number" min="1" max="100000000" step="1000" inputMode="decimal"
            placeholder="กรอกวงเงินกู้" value={principal} onChange={e=>setPrincipal(e.target.value)} />
        </label>
        <label className="form-group">อัตราดอกเบี้ยต่อปี (%)
          <input className="form-control" type="number" min="0" max="50" step="0.01" inputMode="decimal"
            placeholder="กรอกอัตราจากประกาศจริง" value={annualRate} onChange={e=>setAnnualRate(e.target.value)} />
        </label>
        <label className="form-group">จำนวนงวด (เดือน)
          <input className="form-control" type="number" min="1" max="600" step="1" inputMode="numeric"
            placeholder="กรอกจำนวนงวด" value={months} onChange={e=>setMonths(e.target.value)} />
        </label>
        <label className="form-group">วิธีการผ่อนชำระ
          <select className="form-control" value={method} onChange={e=>setMethod(e.target.value)}>
            <option value="annuity">ค่างวดเท่ากัน (ลดต้นลดดอก)</option>
            <option value="equal-principal">เงินต้นเท่ากันทุกงวด (ลดต้นลดดอก)</option>
          </select>
        </label>
        <button type="button" className="btn btn-outline" onClick={()=>{setPrincipal('');setAnnualRate('');setMonths('');setShowTable(false);}}>ล้างข้อมูล</button>
      </div>
      <div style={{background:'var(--gradient-hero)',color:'#fff',borderRadius:18,padding:24,display:'grid',gap:15,alignContent:'start'}}>
        <strong style={{fontSize:'1.1rem',color:'#fff'}}>ผลการคำนวณประมาณการ</strong>
        {!result ? <p style={{color:'#e2e8f0'}}>กรอกเงินต้น อัตราดอกเบี้ย และจำนวนงวดให้ครบเพื่อแสดงผล</p> : <>
          <div><small style={{color:'#e2e8f0'}}>ค่างวดแรก</small><div style={{fontSize:'clamp(1.7rem,3vw,2.4rem)',fontWeight:800,color:'#facc15'}}>{fmt(result.firstPayment)} บาท</div></div>
          {method==='equal-principal' && <div>ค่างวดสุดท้ายประมาณ <strong>{fmt(result.lastPayment)} บาท</strong></div>}
          <div>ดอกเบี้ยรวมประมาณ <strong>{fmt(result.totalInterest)} บาท</strong></div>
          <div>ยอดชำระรวมประมาณ <strong>{fmt(result.totalPayment)} บาท</strong></div>
          <small style={{lineHeight:1.7,color:'#e2e8f0'}}>คำนวณดอกเบี้ยรายเดือนแบบประมาณการ ไม่รวมค่าธรรมเนียม เงื่อนไขคุณสมบัติ การปัดเศษ และวิธีคิดตามวันจริงของสหกรณ์</small>
          <div style={{display:'flex',gap:10,flexWrap:'wrap'}}>
            <button className="btn btn-teal btn-sm" type="button" onClick={()=>setShowTable(v=>!v)}><FileText size={16}/>{showTable?'ซ่อนตาราง':'ดูตารางผ่อนชำระ'}</button>
            <button className="btn btn-subtle btn-sm" type="button" onClick={()=>window.print()}><Printer size={16}/>พิมพ์</button>
          </div>
        </>}
      </div>
    </div>
    {result && showTable && <div style={{overflowX:'auto',marginTop:26}} role="region" aria-label="ตารางผ่อนชำระตัวอย่าง" tabIndex={0}>
      <h3>ตารางผ่อนชำระบางงวด (ตัวอย่าง)</h3>
      <table style={{width:'100%',minWidth:620,textAlign:'right',borderCollapse:'collapse'}}>
        <thead><tr><th>งวด</th><th>ยอดชำระ</th><th>เงินต้น</th><th>ดอกเบี้ย</th><th>ยอดคงเหลือ</th></tr></thead>
        <tbody>{result.schedule.map(row=><tr key={row.month}>
          <td>{row.month}</td><td>{fmt(row.payment)}</td><td>{fmt(row.principalPaid)}</td>
          <td>{fmt(row.interest)}</td><td>{fmt(row.balance)}</td></tr>)}</tbody>
      </table>
    </div>}
  </section>;
}
