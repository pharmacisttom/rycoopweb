import { maskName } from '../utils/pdpa';
import React, { useState } from 'react';
import { ShieldCheck, Search, FileText, Printer, AlertCircle } from 'lucide-react';
import { usePublicInformation } from '../context/PublicContentContext';

export default function VerifyReceiptPage() {
  const { COOP_INFO } = usePublicInformation();
  const [receiptToken, setReceiptToken] = useState('');
  const [verified, setVerified] = useState(false);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [receiptData, setReceiptData] = useState(null);

  const handleVerify = async (e) => {
    e.preventDefault();
    const token = receiptToken.trim();
    if (!token) return;

    setLoading(true);
    setSearched(true);
    setVerified(false);
    setReceiptData(null);

    try {
      const res = await fetch(`/api/verify-receipt/${encodeURIComponent(token)}`);
      if (res.ok) {
        const json = await res.json();
        if (json?.success && json?.data) {
          setReceiptData(json.data);
          setVerified(true);
        } else {
          setReceiptData(null);
          setVerified(false);
        }
      } else {
        setReceiptData(null);
        setVerified(false);
      }
    } catch {
      setReceiptData(null);
      setVerified(false);
    } finally {
      setLoading(false);
    }
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
                  placeholder="ระบุเลขที่ใบเสร็จรับเงินเพื่อตรวจสอบ..." 
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

        {/* Not Found State */}
        {searched && !verified && !loading && (
          <div className="surface-card animate-fade-in" style={{ maxWidth: '650px', margin: '0 auto', padding: '2rem', textAlign: 'center', borderRadius: 'var(--radius-xl)', border: '1px solid var(--border-subtle)' }}>
            <div style={{ display: 'inline-flex', padding: '1rem', borderRadius: '50%', background: 'var(--bg-subtle)', color: 'var(--text-muted)', marginBottom: '1rem' }}>
              <AlertCircle size={32} />
            </div>
            <h3 style={{ fontSize: '1.2rem', color: 'var(--text-main)', marginBottom: '0.5rem' }}>
              ยังไม่มีข้อมูลที่ได้รับการบันทึกในระบบ
            </h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6 }}>
              ไม่พบข้อมูลใบเสร็จรับเงินตามรหัสที่ระบุ หรือยังไม่มีการนำเข้าใบเสร็จงวดดังกล่าวเข้าสู่ระบบ
            </p>
          </div>
        )}

        {/* Receipt Verification Result Card */}
        {verified && receiptData && (
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
                  <h4 style={{ fontSize: '1.05rem', fontWeight: 700 }}>ใบเสร็จรับเงินถูกต้องตามฐานข้อมูลสหกรณ์</h4>
                  <p style={{ fontSize: '0.8rem' }}>{receiptData.digitalSignStatus || 'ผ่านการตรวจสอบความถูกต้อง'}</p>
                </div>
              </div>
              <button onClick={() => window.print()} className="btn btn-teal btn-sm">
                <Printer size={15} />
                <span>พิมพ์ใบเสร็จ</span>
              </button>
            </div>

            {/* Receipt Header */}
            <div style={{ textAlign: 'center', borderBottom: '1px solid var(--border-subtle)', paddingBottom: '1.5rem', marginBottom: '1.5rem' }}>
              <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-800)', marginBottom: '0.25rem' }}>{COOP_INFO?.nameTh || 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด'}</h3>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>{COOP_INFO?.address || ''}</p>
              <div style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--primary-700)', marginTop: '0.75rem' }}>
                ใบเสร็จรับเงินประจำงวด {receiptData.period || ''}
              </div>
            </div>

            {/* Member & Receipt Details */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
              <div>
                <span style={{ color: 'var(--text-muted)' }}>เลขที่ใบเสร็จ:</span> <strong>{receiptData.receiptNo}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)' }}>วันที่ออกใบเสร็จ:</span> <strong>{receiptData.issueDate || '-'}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)' }}>สมาชิก:</span> <strong>{maskName(receiptData.memberName || '')}</strong>
              </div>
              <div>
                <span style={{ color: 'var(--text-muted)' }}>เลขทะเบียน:</span> <strong>{receiptData.memberId || '-'}</strong> ({receiptData.department || '-'})
              </div>
            </div>

            {/* Receipt Items Table */}
            {Array.isArray(receiptData.items) && receiptData.items.length > 0 && (
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
                  {receiptData.items.map((item, idx) => (
                    <tr key={idx} style={{ borderBottom: '1px solid var(--border-subtle)' }}>
                      <td style={{ padding: '0.75rem' }}>{item.name}</td>
                      <td style={{ padding: '0.75rem', textAlign: 'right' }}>{Number(item.principal || 0).toLocaleString()}</td>
                      <td style={{ padding: '0.75rem', textAlign: 'right' }}>{Number(item.interest || 0).toLocaleString()}</td>
                      <td style={{ padding: '0.75rem', textAlign: 'right', fontWeight: 600 }}>{Number(item.total || 0).toLocaleString()}</td>
                    </tr>
                  ))}
                  <tr style={{ fontWeight: 800, fontSize: '1.05rem', background: 'var(--bg-subtle)' }}>
                    <td colSpan={3} style={{ padding: '0.75rem', textAlign: 'right' }}>ยอดชำระสุทธิ:</td>
                    <td style={{ padding: '0.75rem', textAlign: 'right', color: 'var(--primary-700)' }}>
                      {Number(receiptData.totalAmount || 0).toLocaleString()} บาท
                    </td>
                  </tr>
                </tbody>
              </table>
            )}

            {/* Digital Signature Info */}
            <div style={{ background: 'var(--bg-subtle)', padding: '1rem', borderRadius: '8px', fontSize: '0.78rem', color: 'var(--text-muted)' }}>
              <div><strong>ผู้ลงนาม:</strong> {receiptData.signedBy || 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด'}</div>
              <div><strong>วันที่บันทึก:</strong> {receiptData.timestamp || '-'}</div>
            </div>

          </div>
        )}

      </div>
    </div>
  );
}
