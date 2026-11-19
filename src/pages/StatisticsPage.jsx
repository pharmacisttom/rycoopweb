import React, { useEffect, useState } from 'react';
import { fetchHomeData } from '../services/api';

export default function StatisticsPage() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    let active = true;
    fetchHomeData().then((response) => {
      if (active && response?.success && response?.data?.latestStats) {
        setStats(response.data.latestStats);
      }
    }).catch(() => {}).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, []);

  const fields = [
    ['จำนวนสมาชิก', 'total_members', 'คน'],
    ['สินทรัพย์รวม', 'total_assets', 'บาท'],
    ['ทุนเรือนหุ้น', 'total_shares', 'บาท'],
    ['เงินฝากรวม', 'total_deposits', 'บาท'],
    ['เงินกู้คงเหลือ', 'total_loans', 'บาท'],
    ['อัตราเงินปันผล', 'dividend_rate', '%'],
    ['อัตราเฉลี่ยคืน', 'loan_refund_rate', '%']
  ];
  const available = stats && fields.some(([, key]) => stats[key] !== null && stats[key] !== undefined && stats[key] !== '');
  return <div className="section"><div className="container">
    <div className="section-title-wrap">
      <span className="section-badge">ข้อมูลสถิติ</span>
      <h1 className="section-title">สถิติและข้อมูลทางการเงิน</h1>
      <p className="section-subtitle">แสดงเฉพาะข้อมูลที่เจ้าหน้าที่เผยแพร่ผ่านระบบจริง</p>
    </div>
    {loading && <p role="status">กำลังโหลดข้อมูล...</p>}
    {!loading && !available && <p role="status" className="surface-card" style={{padding:24}}>ยังไม่มีข้อมูลสถิติที่ได้รับการยืนยันและเผยแพร่ กรุณารอเจ้าหน้าที่สหกรณ์ปรับปรุงข้อมูล</p>}
    {!loading && available && <>
      <p>งวดข้อมูล {stats.month ?? '—'}/{stats.year ?? '—'} (กรุณาตรวจสอบความถูกต้องของข้อมูลกับสหกรณ์)</p>
      <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(220px,1fr))',gap:20,marginTop:24}}>
        {fields.filter(([,key])=>stats[key]!==null && stats[key]!==undefined && stats[key]!=='').map(([label,key,unit])=>(
          <article className="surface-card" key={key} style={{padding:24}}>
            <h2 style={{fontSize:'1rem'}}>{label}</h2>
            <strong style={{fontSize:'1.6rem'}}>{Number(stats[key]).toLocaleString('th-TH',{maximumFractionDigits:3})}</strong> {unit}
          </article>
        ))}
      </div>
    </>}
  </div></div>;
}
