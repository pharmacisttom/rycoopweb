import React from 'react';
import { Link } from 'react-router-dom';
import { Clock3 } from 'lucide-react';

/** Public holding screen only; original e-Services implementation remains intact. */
export default function EServiceUnavailablePage() {
  return <section className="section" style={{minHeight:'65vh',display:'grid',placeItems:'center'}}>
    <div className="surface-card" style={{maxWidth:620,padding:'2.5rem',textAlign:'center'}}>
      <Clock3 size={48} aria-hidden="true" style={{color:'var(--primary-600)',marginBottom:'1rem'}}/>
      <h1 style={{marginBottom:'0.75rem'}}>ระบบ e-Services ยังไม่เปิดให้บริการ</h1>
      <p style={{color:'var(--text-muted)',lineHeight:1.7}}>
        สหกรณ์อยู่ระหว่างพัฒนาระบบบริการออนไลน์ โปรดติดตามประกาศเปิดให้บริการอย่างเป็นทางการ
      </p>
      <Link className="btn btn-primary" to="/">กลับหน้าแรก</Link>
    </div>
  </section>;
}
