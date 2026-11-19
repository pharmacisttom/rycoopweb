import React from 'react';
import { ShieldAlert } from 'lucide-react';

// Fail closed: there is no verified receipt API or signature validation
// integrated into this page. Never display a fake "verified" receipt.
export default function VerifyReceiptPage() {
  return (
    <div className="section">
      <div className="container">
        <div className="section-title-wrap">
          <span className="section-badge">ความโปร่งใสและปลอดภัย</span>
          <h1 className="section-title">ระบบตรวจสอบใบเสร็จรับเงินอิเล็กทรอนิกส์ (e-Receipt)</h1>
          <p className="section-subtitle">การตรวจสอบใบเสร็จต้องยืนยันข้อมูลกับระบบสหกรณ์และตรวจสอบลายมือชื่อดิจิทัลจริง</p>
          <div className="section-line" />
        </div>
        <div className="surface-card" role="status" style={{ maxWidth: 650, margin: '0 auto', padding: '2rem', textAlign: 'center' }}>
          <ShieldAlert size={36} aria-hidden="true" style={{ margin: '0 auto 1rem' }} />
          <h2 style={{ fontSize: '1.25rem', marginBottom: '0.75rem' }}>ยังไม่เปิดให้ตรวจสอบใบเสร็จผ่านเว็บไซต์</h2>
          <p>ระบบตรวจสอบใบเสร็จออนไลน์ยังไม่ได้เชื่อมต่อบริการยืนยันข้อมูลและลายมือชื่อดิจิทัล จึงไม่สามารถรับรองความถูกต้องของใบเสร็จผ่านหน้านี้ได้</p>
          <p>โปรดติดต่อเจ้าหน้าที่สหกรณ์ผ่านช่องทางที่ประกาศอย่างเป็นทางการเพื่อขอตรวจสอบเอกสาร</p>
        </div>
      </div>
    </div>
  );
}
