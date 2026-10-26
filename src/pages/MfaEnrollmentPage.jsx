import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  ShieldCheck, Smartphone, KeyRound, Copy, Check, Download, 
  Printer, ArrowRight, AlertTriangle, LoaderCircle, CheckCircle2 
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';

export default function MfaEnrollmentPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const { toast } = useToast();

  const [step, setStep] = useState(1); // 1: QR & Manual, 2: Recovery Codes
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [enrollData, setEnrollData] = useState(null);
  const [code, setCode] = useState('');
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState([]);
  const [redirectPath, setRedirectPath] = useState('/staff/dashboard');

  // Load enrollment data
  useEffect(() => {
    let cancelled = false;

    async function fetchEnrollment() {
      setLoading(true);
      setError('');
      try {
        const csrfRes = await fetch('/csrf-token', { credentials: 'include', headers: { Accept: 'application/json' } });
        const csrf = csrfRes.ok ? await csrfRes.json() : {};

        const res = await fetch('/api/auth/mfa/enroll', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf.token || '',
          },
        });

        const data = await res.json();
        if (cancelled) return;

        if (data.success) {
          setEnrollData(data);
        } else {
          setError(data.message || 'ไม่สามารถโหลดข้อมูลลงทะเบียน 2FA ได้');
        }
      } catch (err) {
        if (!cancelled) setError('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    fetchEnrollment();
    return () => { cancelled = true; };
  }, []);

  const handleCopySecret = () => {
    if (!enrollData?.secret) return;
    navigator.clipboard.writeText(enrollData.secret);
    setCopied(true);
    toast.success('คัดลอกรหัส Secret เรียบร้อยแล้ว');
    setTimeout(() => setCopied(false), 3000);
  };

  const handleConfirmOtp = async (e) => {
    e.preventDefault();
    setError('');
    const cleanCode = code.trim();
    if (!/^\d{6}$/.test(cleanCode)) {
      setError('กรุณากรอกรหัสความปลอดภัย 6 หลักที่เป็นตัวเลข');
      return;
    }

    setSubmitting(true);
    try {
      const csrfRes = await fetch('/csrf-token', { credentials: 'include', headers: { Accept: 'application/json' } });
      const csrf = csrfRes.ok ? await csrfRes.json() : {};

      const res = await fetch('/api/auth/mfa/confirm', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf.token || '',
        },
        body: JSON.stringify({ code: cleanCode }),
      });

      const data = await res.json();
      if (data.success) {
        setRecoveryCodes(data.recovery_codes || []);
        if (data.redirect) setRedirectPath(data.redirect);
        setStep(2);
        toast.success('เปิดใช้งาน 2FA สำเร็จ กรุณาเก็บบันทึกรหัสสำรอง');
      } else {
        setError(data.message || 'รหัส 2FA ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
      }
    } catch (err) {
      setError('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์เพื่อยืนยันรหัสได้');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDownloadRecoveryCodes = () => {
    const text = [
      '===========================================================',
      'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด (RYCOOPWEB)',
      'รหัสสำรองฉุกเฉินสำหรับการยืนยันตัวตน (MFA Recovery Codes)',
      '===========================================================',
      `สำหรับผู้ใช้: ${user?.name || ''} (${user?.email || ''})`,
      `วันที่สร้าง: ${new Date().toLocaleString('th-TH')}`,
      '',
      'คำแนะนำการใช้งาน:',
      '1. รหัสแต่ละชุดสามารถใช้เข้าสู่ระบบได้เพียง 1 ครั้งเท่านั้น',
      '2. หากทำสมาร์ตโฟนสูญหาย ให้ใช้รหัสนี้แทน OTP เพื่อเข้าสู่ระบบ',
      '3. กรุณาเก็บรักษาไฟล์นี้ในที่ปลอดภัยและเป็นความลับอย่างยิ่ง',
      '',
      'รหัสสำรองของท่าน (8 รหัส):',
      ...recoveryCodes.map((c, i) => `  [${i + 1}]  ${c}`),
      '===========================================================',
    ].join('\n');

    const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `rycoop_recovery_codes_${Date.now()}.txt`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success('ดาวน์โหลดไฟล์รหัสสำรองเรียบร้อยแล้ว');
  };

  const handlePrint = () => {
    window.print();
  };

  const handleProceedToDashboard = () => {
    navigate(redirectPath, { replace: true });
  };

  return (
    <div className="container section" style={{ maxWidth: '780px', margin: '40px auto' }}>
      <div className="surface-card" style={{ padding: '36px', borderRadius: '24px', boxShadow: '0 20px 60px rgba(15, 43, 92, 0.08)' }}>
        
        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: '32px' }}>
          <div style={{ 
            width: '64px', height: '64px', borderRadius: '20px', 
            background: step === 2 ? '#ecfdf5' : 'var(--primary-50)', 
            color: step === 2 ? '#059669' : 'var(--primary-600)',
            display: 'inline-grid', placeItems: 'center', marginBottom: '16px' 
          }}>
            {step === 2 ? <CheckCircle2 size={36} /> : <ShieldCheck size={36} />}
          </div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--primary-800)', margin: '0 0 8px' }}>
            {step === 1 ? 'ตั้งค่าการยืนยันตัวตนสองขั้นตอน (MFA)' : 'บันทึกรหัสสำรองฉุกเฉิน (Recovery Codes)'}
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem', margin: 0, lineHeight: 1.6 }}>
            {step === 1 
              ? 'สหกรณ์กำหนดให้เจ้าหน้าที่และผู้ดูแลระบบทุกคนต้องเปิดใช้งาน 2FA เพื่อความปลอดภัยขั้นสูงสุด'
              : 'เปิดใช้งาน 2FA เรียบร้อยแล้ว กรุณาเก็บบันทึกรหัสสำรองด้านล่างนี้ไว้ในที่ปลอดภัย'}
          </p>
        </div>

        {error && (
          <div role="alert" className="member-entry-error" style={{ marginBottom: '24px', display: 'flex', alignItems: 'center', gap: '10px' }}>
            <AlertTriangle size={20} style={{ flexShrink: 0 }} />
            <span>{error}</span>
          </div>
        )}

        {loading ? (
          <div style={{ textAlign: 'center', padding: '60px 0' }}>
            <LoaderCircle className="member-entry-spin" size={32} style={{ color: 'var(--primary-600)', margin: 'auto' }} />
            <p style={{ marginTop: '16px', color: 'var(--text-muted)' }}>กำลังจัดเตรียมคีย์ความปลอดภัยสำหรับคุณ...</p>
          </div>
        ) : step === 1 ? (
          <div>
            {/* Step 1: Scan QR or Manual Key */}
            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(240px, 280px) 1fr', gap: '32px', alignItems: 'center' }}>
              
              {/* QR Code display */}
              <div style={{ textAlign: 'center', padding: '20px', background: '#f8fafc', borderRadius: '16px', border: '1px solid #e2e8f0' }}>
                {enrollData?.qr_code_uri ? (
                  <img 
                    src={enrollData.qr_code_uri} 
                    alt="2FA QR Code" 
                    style={{ width: '220px', height: '220px', display: 'block', margin: 'auto', borderRadius: '8px' }} 
                  />
                ) : (
                  <div style={{ width: '220px', height: '220px', display: 'grid', placeItems: 'center', background: '#eee' }}>
                    QR Code
                  </div>
                )}
                <span style={{ display: 'inline-block', marginTop: '12px', fontSize: '0.78rem', color: '#64748b' }}>
                  สแกนด้วย Google Authenticator หรือ Microsoft Authenticator
                </span>
              </div>

              {/* Instructions & Manual Key */}
              <div>
                <h3 style={{ fontSize: '1.05rem', fontWeight: 700, margin: '0 0 12px', color: '#1e293b' }}>
                  ขั้นตอนการติดตั้ง:
                </h3>
                <ol style={{ paddingLeft: '20px', fontSize: '0.9rem', color: '#475569', lineHeight: 1.8, margin: '0 0 20px' }}>
                  <li>เปิดแอปพลิเคชัน <strong>Google Authenticator</strong> บนมือถือของคุณ</li>
                  <li>เลือกเมนู <strong>สแกนคิวอาร์โค้ด (Scan QR)</strong> แล้วส่องกล้องมาที่รูปด้านซ้าย</li>
                  <li>หากสแกนไม่ได้ สามารถเลือกกรอกคีย์ด้วยตนเอง (Manual Entry)</li>
                </ol>

                <div style={{ background: '#f1f5f9', padding: '14px 16px', borderRadius: '12px', border: '1px solid #cbd5e1', marginBottom: '24px' }}>
                  <div style={{ fontSize: '0.8rem', color: '#64748b', marginBottom: '6px' }}>คีย์สำหรับกรอกด้วยตนเอง (Secret Key):</div>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px' }}>
                    <code style={{ fontSize: '1.1rem', fontWeight: 700, letterSpacing: '0.1em', color: '#0f172a', fontFamily: 'monospace' }}>
                      {enrollData?.formatted_secret || enrollData?.secret}
                    </code>
                    <button 
                      type="button" 
                      onClick={handleCopySecret}
                      style={{ 
                        border: '1px solid #cbd5e1', background: '#fff', borderRadius: '8px', 
                        padding: '6px 12px', display: 'flex', alignItems: 'center', gap: '6px', 
                        cursor: 'pointer', fontSize: '0.82rem', fontWeight: 600, color: '#334155' 
                      }}
                    >
                      {copied ? <><Check size={14} style={{ color: '#059669' }} /> คัดลอกแล้ว</> : <><Copy size={14} /> คัดลอก</>}
                    </button>
                  </div>
                </div>

                {/* Form to submit 6-digit confirmation code */}
                <form onSubmit={handleConfirmOtp}>
                  <label htmlFor="confirm-otp-input" style={{ display: 'block', fontWeight: 700, fontSize: '0.92rem', marginBottom: '8px', color: '#1e293b' }}>
                    กรอกรหัส 6 หลักจากแอป เพื่อยืนยัน:
                  </label>
                  <div style={{ display: 'flex', gap: '12px' }}>
                    <input
                      id="confirm-otp-input"
                      type="text"
                      pattern="[0-9]*"
                      inputMode="numeric"
                      maxLength={6}
                      autoComplete="one-time-code"
                      value={code}
                      onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                      placeholder="000000"
                      disabled={submitting}
                      autoFocus
                      style={{
                        flex: 1,
                        minHeight: '50px',
                        fontSize: '1.4rem',
                        fontWeight: 700,
                        letterSpacing: '0.25em',
                        textAlign: 'center',
                        borderRadius: '12px',
                        border: '2px solid var(--primary-500)',
                        outline: 'none',
                        fontFamily: 'monospace',
                      }}
                    />
                    <button
                      type="submit"
                      disabled={submitting || code.length !== 6}
                      style={{
                        padding: '0 24px',
                        minHeight: '50px',
                        borderRadius: '12px',
                        border: 0,
                        background: 'var(--primary-600)',
                        color: '#fff',
                        fontWeight: 700,
                        cursor: submitting || code.length !== 6 ? 'not-allowed' : 'pointer',
                        opacity: submitting || code.length !== 6 ? 0.6 : 1,
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        transition: 'all 0.15s ease',
                      }}
                    >
                      {submitting ? <LoaderCircle className="member-entry-spin" size={18} /> : <>ยืนยัน <ArrowRight size={18} /></>}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        ) : (
          /* Step 2: Recovery Codes */
          <div>
            <div style={{ background: '#ecfdf5', border: '1px solid #a7f3d0', borderRadius: '14px', padding: '16px', marginBottom: '24px' }}>
              <p style={{ margin: 0, color: '#065f46', fontSize: '0.9rem', lineHeight: 1.6 }}>
                <strong>สำคัญมาก:</strong> รหัสสำรองฉุกเฉินด้านล่างนี้ สามารถใช้เข้าสู่ระบบได้เมื่อท่านไม่สามารถใช้งานแอปพลิเคชัน 2FA ได้ แต่ละรหัสสามารถใช้งานได้ 1 ครั้งเท่านั้น กรุณาดาวน์โหลดหรือพิมพ์เก็บไว้
              </p>
            </div>

            <div style={{ 
              display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', 
              gap: '12px', padding: '24px', background: '#f8fafc', borderRadius: '16px', 
              border: '1px solid #e2e8f0', marginBottom: '24px' 
            }}>
              {recoveryCodes.map((item, idx) => (
                <div 
                  key={idx} 
                  style={{ 
                    padding: '10px 14px', background: '#fff', border: '1px solid #cbd5e1', 
                    borderRadius: '8px', textAlign: 'center', fontFamily: 'monospace', 
                    fontSize: '1.05rem', fontWeight: 700, letterSpacing: '0.08em', color: '#1e293b' 
                  }}
                >
                  {item}
                </div>
              ))}
            </div>

            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '14px', justifyContent: 'space-between', alignItems: 'center' }}>
              <div style={{ display: 'flex', gap: '10px' }}>
                <button
                  type="button"
                  onClick={handleDownloadRecoveryCodes}
                  className="btn btn-outline"
                  style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 18px', borderRadius: '10px' }}
                >
                  <Download size={18} /> ดาวน์โหลดรหัสสำรอง (.txt)
                </button>
                <button
                  type="button"
                  onClick={handlePrint}
                  className="btn btn-outline"
                  style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 18px', borderRadius: '10px' }}
                >
                  <Printer size={18} /> พิมพ์หน้านี้
                </button>
              </div>

              <button
                type="button"
                onClick={handleProceedToDashboard}
                className="btn btn-primary"
                style={{ 
                  display: 'flex', alignItems: 'center', gap: '10px', 
                  padding: '12px 28px', borderRadius: '12px', fontWeight: 700 
                }}
              >
                เข้าสู่หน้าการทำงาน (Dashboard) <ArrowRight size={18} />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
