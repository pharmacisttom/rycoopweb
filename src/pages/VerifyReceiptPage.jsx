import { maskName } from '../utils/pdpa';
import React, { useState } from 'react';
import { ShieldCheck, QrCode, Search, CheckCircle2, FileText, Printer } from 'lucide-react';
import { COOP_INFO } from '../data/mockData';

export default function VerifyReceiptPage() {
  const [receiptToken, setReceiptToken] = useState('RC-67020084');
  const [verified, setVerified] = useState(true);
  const [loading, setLoading] = useState(false);

  const sampleReceipt = {
    receiptNo: 'RC-67020084',
    issueDate: '28 กุมภาพันธ์ 2567',
    period: 'กุมภาพันธ์ 2567',
    memberId: '04892',
    memberName: 'นายเกียรติศักดิ์ พูลเพิ่ม',
    department: 'โรงพยาบาลระยอง',
    items: [
      { name: 'ค่าหุ้นรายเดือน', principal: 3000, interest: 0, total: 3000 },
      { name: 'เงินกู้สามัญ (สัญญา ส.66/0129)', principal: 8900, interest: 3600, total: 12500 },
    ],
    totalAmount: 15500,
    digitalSignStatus: 'ผ่านการรับรองลายมือชื่อดิจิทัล (Valid Digital Signature)',
    signedBy: 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด (CA Certified)',
    timestamp: '2024-02-28 09:15:22 GMT+7'
  };

  const handleVerify = (e) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      setLoading(false);
      setVerified(true);
    }, 400);
  };

  return (
    <div className="section">
      <div className="container">
        
        <div className="section-title-wrap">
          <span className="section-badge">ความโปร่งใสและปลอดภัย</span>
          <h1 className="section-title">ระบบตรวจสอบใบเสร็จรับเงินอิเล็กทรอนิกส์ (e-Receipt)</h1>
          <p className="section-subtitle">ตรวจสอบความถูกต้องและสถานะลายมือชื่อดิจิทัลของใบเสร็จรับเงินสหกรณ์</p>
          <div className="section-line" />
        </div>

        {/* Verification Form */}
        <div className="glass-card" style={{ maxWidth: '650px', margin: '0 auto 2.5rem auto', padding: '2rem', borderRadius: 'var(--radius-xl)' }}>
          <form onSubmit={handleVerify}>
            <div className="form-group">
              <label className="form-label">กรอกเลขที่ใบเสร็จ หรือ รหัส Token จาก QR Code</label>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <input 
                  type="text" 
                  className="form-control" 
                  placeholder="เช่น RC-67020084" 
                  value={receiptToken} 
                  onChange={(e) => setReceiptToken(e.target.value)} 
                  required 
                />
                <button type="submit" className="btn btn-primary" disabled={loading}>
                  <Search size={16} />
                  <span>{loading ? 'กำลังตรวจ...' : 'ตรวจสอบ'}</span>
                </button>
              </div>
            </div>
          </form>
        </div>

        {/* Receipt Verification Result Card */}
        {verified && (
          <div className="surface-card animate-fade-in" style={{ maxWidth: '750px', margin: '0 auto', padding: '2.5rem', borderRadius: 'var(--radius-xl)', border: '2px solid var(--accent-emerald-light)' }}>
            
            {/* Status Banner */}
            <div style={{
              background: 'var(--accent-emerald-light)',
              color: 'var(--accent-emerald-dark)',
              padding: '1rem 1.5rem',
              borderRadius: '12px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              marginBottom: '2rem',
              flexWrap: 'wrap',
              gap: '0.5rem'
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                <ShieldCheck size={28} />
                <div>
                  <h4 style={{ fontSize: '1.05rem', fontWeight: 700 }}>ใบเสร็จรับเงินถูกต้องตามกฎหมาย</h4>
                  <p style={{ fontSize: '0.8rem' }}>{sampleReceipt.digitalSignStatus}</p>
                </div>
              </div>
              <button onClick={() => window.print()} className="btn btn-teal btn-sm">
                <Printer size={15} />
                <span>พิมพ์ใบเสร็จ</span>
              </button>
            </div>

            {/* Receipt Header */}
            <div style={{ textAlign: 'center', borderBottom: '1px solid var(--border-subtle)', paddingBottom: '1.5rem', marginBottom: '1.5rem' }}>
              <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-800)', marginBottom: '0.25rem' }}>{COOP_INFO.nameTh}</h3>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>{COOP_INFO.address}</p>
              <div style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--primary-700)', marginTop: '0.75rem' }}>
                ใบเสร็จรับเงินประจำงวด {sampleReceipt.period}
              </div>
            </div>

            {/* Member & Receipt Details */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
              <div>
                <span style={{ color: 'var(--text-muted)' }}>เลขที่ใบเสร็จ:</span> <strong>{sampleReceipt.receiptNo}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)' }}>วันที่ออกใบเสร็จ:</span> <strong>{sampleReceipt.issueDate}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)' }}>สมาชิก:</span> <strong>{maskName(sampleReceipt.memberName)}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)' }}>เลขทะเบียน:</span> <strong>{sampleReceipt.memberId}</strong> ({sampleReceipt.department})
              </div>
            </div>

            {/* Receipt Items Table */}
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.9rem', marginBottom: '1.5rem' }}>
              <thead>
                <tr style={{ background: 'var(--bg-subtle)', borderBottom: '1px solid var(--border-subtle)' }}>
                  <th style={{ padding: '0.75rem', textAlign: 'left' }}>รายการชำระ</th>
                  <th style={{ padding: '0.75rem', textAlign: 'right' }}>เงินต้น</th>
                  <th style={{ padding: '0.75rem', textAlign: 'right' }}>ดอกเบี้ย</th>
                  <th style={{ padding: '0.75rem', textAlign: 'right' }}>รวมเป็นเงิน</th>
                </tr>
              </thead>
              <tbody>
                {sampleReceipt.items.map((item, idx) => (
                  <tr key={idx} style={{ borderBottom: '1px solid var(--border-subtle)' }}>
                    <td style={{ padding: '0.75rem' }}>{item.name}</td>
                    <td style={{ padding: '0.75rem', textAlign: 'right' }}>{item.principal.toLocaleString()}</td>
                    <td style={{ padding: '0.75rem', textAlign: 'right' }}>{item.interest.toLocaleString()}</td>
                    <td style={{ padding: '0.75rem', textAlign: 'right', fontWeight: 600 }}>{item.total.toLocaleString()}</td>
                  </tr>
                ))}
                <tr style={{ fontWeight: 800, fontSize: '1.05rem', background: 'var(--bg-subtle)' }}>
                  <td colSpan={3} style={{ padding: '0.75rem', textAlign: 'right' }}>ยอดชำระสุทธิ:</td>
                  <td style={{ padding: '0.75rem', textAlign: 'right', color: 'var(--primary-700)' }}>
                    {sampleReceipt.totalAmount.toLocaleString()} บาท
                  </td>
                </tr>
              </tbody>
            </table>

            {/* Digital Signature Info */}
            <div style={{ background: 'var(--bg-subtle)', padding: '1rem', borderRadius: '8px', fontSize: '0.78rem', color: 'var(--text-muted)' }}>
              <div><strong>ผู้ลงลายมือชื่อดิจิทัล:</strong> {sampleReceipt.signedBy}</div>
              <div><strong>ประทับเวลา (Timestamp):</strong> {sampleReceipt.timestamp}</div>
            </div>

          </div>
        )}

      </div>
    </div>
  );
}
