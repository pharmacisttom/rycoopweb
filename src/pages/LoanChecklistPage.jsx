import React from 'react';
import LoanReadinessChecklist from '../components/calculators/LoanReadinessChecklist';

export default function LoanChecklistPage() {
  return (
    <div className="section">
      <div className="container">
        
        <div className="section-title-wrap">
          <span className="section-badge">เตรียมเอกสารเงินกู้</span>
          <h1 className="section-title">รายการตรวจสอบและดาวน์โหลดเอกสารเงินกู้</h1>
          <p className="section-subtitle">เช็กรายการเตรียมเอกสารเบื้องต้นและดาวน์โหลดแบบฟอร์มที่สหกรณ์เผยแพร่ โดยยืนยันรายละเอียดกับเจ้าหน้าที่ก่อนดำเนินการ</p>
          <div className="section-line" />
        </div>

        <LoanReadinessChecklist />

      </div>
    </div>
  );
}
