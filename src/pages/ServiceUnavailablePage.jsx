import React from 'react';
import { Link } from 'react-router-dom';
import { Clock3 } from 'lucide-react';

export default function ServiceUnavailablePage() {
  return (
    <section className="section" style={{ minHeight: '65vh', display: 'grid', placeItems: 'center' }}>
      <div className="surface-card" style={{ maxWidth: 620, padding: '2.5rem', textAlign: 'center' }}>
        <Clock3 size={48} aria-hidden="true" style={{ color: 'var(--primary-600)', marginBottom: '1rem' }} />
        <h1 style={{ marginBottom: '0.75rem' }}>ระบบสมาชิกออนไลน์ยังไม่เปิดให้บริการ</h1>
        <p style={{ color: 'var(--text-muted)', lineHeight: 1.7 }}>
          ระบบสมาชิกออนไลน์อยู่ระหว่างดำเนินการและยังไม่เปิดให้บริการ
          กรุณาติดตามประกาศจากสหกรณ์อีกครั้ง
        </p>
        <Link className="btn btn-primary" to="/">กลับหน้าหลัก</Link>
      </div>
    </section>
  );
}
