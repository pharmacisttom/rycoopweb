import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ShieldCheck, KeyRound, LoaderCircle, ArrowRight, LifeBuoy, AlertCircle, ArrowLeft } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';

export default function MfaVerifyPage() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    const isRec = useRecovery;
    const value = isRec ? recoveryCode.trim() : code.trim();

    if (!value) {
      setError(isRec ? 'กรุณากรอกรหัสสำรองฉุกเฉิน' : 'กรุณากรอกรหัส 2FA 6 หลัก');
      return;
    }

    if (!isRec && !/^\d{6}$/.test(value)) {
      setError('รหัส 2FA ต้องเป็นตัวเลข 6 หลัก');
      return;
    }

    setLoading(true);
    try {
      const csrfRes = await fetch('/csrf-token', { credentials: 'include', headers: { Accept: 'application/json' } });
      const csrf = csrfRes.ok ? await csrfRes.json() : {};

      const endpoint = isRec ? '/api/auth/mfa/recovery' : '/api/auth/mfa/verify';
      const body = isRec ? { recovery_code: value } : { code: value };

      const res = await fetch(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf.token || '',
        },
        body: JSON.stringify(body),
      });

      const data = await res.json();
      if (data.success) {
        toast.success(data.message || 'ยืนยันตัวตนสำเร็จ');
        // Force refresh session in context by reloading or redirecting
        const dest = data.redirect || '/staff/dashboard';
        window.location.assign(dest);
      } else {
        setError(data.message || 'รหัสไม่ถูกต้องหรือหมดอายุ');
      }
    } catch (err) {
      setError('ไม่สามารถเชื่อมต่อระบบยืนยันตัวตนได้ กรุณาลองใหม่อีกครั้ง');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container section" style={{ maxWidth: '480px', margin: '60px auto' }}>
      <div className="surface-card" style={{ padding: '36px', borderRadius: '24px', boxShadow: '0 20px 60px rgba(15, 43, 92, 0.08)' }}>
        
        <div style={{ textAlign: 'center', marginBottom: '28px' }}>
          <div style={{ 
            width: '60px', height: '60px', borderRadius: '18px', 
            background: 'var(--primary-50)', color: 'var(--primary-600)',
            display: 'inline-grid', placeItems: 'center', marginBottom: '14px' 
          }}>
            {useRecovery ? <LifeBuoy size={32} /> : <ShieldCheck size={32} />}
          </div>
          <h1 style={{ fontSize: '1.5rem', fontWeight: 800, color: 'var(--primary-800)', margin: '0 0 6px' }}>
            {useRecovery ? 'เข้าสู่ระบบด้วยรหัสสำรอง' : 'ยืนยันตัวตนสองขั้นตอน (2FA)'}
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', margin: 0 }}>
            {useRecovery 
              ? 'กรอกรหัสกู้คืน 8 หลักที่ท่านได้บันทึกไว้ตอนลงทะเบียน' 
              : 'เปิดแอป Authenticator แล้วกรอกรหัส 6 หลักเพื่อเข้าใช้งาน'}
          </p>
        </div>

        {error && (
          <div role="alert" className="member-entry-error" style={{ marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '10px' }}>
            <AlertCircle size={18} style={{ flexShrink: 0 }} />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} noValidate>
          {useRecovery ? (
            <div style={{ marginBottom: '20px' }}>
              <label htmlFor="recovery-code-input" style={{ display: 'block', fontWeight: 700, fontSize: '0.9rem', marginBottom: '8px' }}>
                รหัสสำรองฉุกเฉิน (Recovery Code):
              </label>
              <input
                id="recovery-code-input"
                type="text"
                autoComplete="off"
                spellCheck="false"
                maxLength={9}
                value={recoveryCode}
                onChange={(e) => setRecoveryCode(e.target.value.toUpperCase())}
                placeholder="XXXX-XXXX"
                disabled={loading}
                autoFocus
                style={{
                  width: '100%',
                  minHeight: '52px',
                  fontSize: '1.3rem',
                  fontWeight: 700,
                  letterSpacing: '0.15em',
                  textAlign: 'center',
                  borderRadius: '12px',
                  border: '2px solid var(--primary-500)',
                  outline: 'none',
                  fontFamily: 'monospace',
                }}
              />
            </div>
          ) : (
            <div style={{ marginBottom: '20px' }}>
              <label htmlFor="totp-code-input" style={{ display: 'block', fontWeight: 700, fontSize: '0.9rem', marginBottom: '8px' }}>
                รหัส 6 หลักจากแอป (TOTP Code):
              </label>
              <input
                id="totp-code-input"
                type="text"
                pattern="[0-9]*"
                inputMode="numeric"
                maxLength={6}
                autoComplete="one-time-code"
                value={code}
                onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                placeholder="000000"
                disabled={loading}
                autoFocus
                style={{
                  width: '100%',
                  minHeight: '52px',
                  fontSize: '1.6rem',
                  fontWeight: 700,
                  letterSpacing: '0.3em',
                  textAlign: 'center',
                  borderRadius: '12px',
                  border: '2px solid var(--primary-500)',
                  outline: 'none',
                  fontFamily: 'monospace',
                }}
              />
            </div>
          )}

          <button
            type="submit"
            disabled={loading || (useRecovery ? !recoveryCode.trim() : code.length !== 6)}
            style={{
              width: '100%',
              minHeight: '50px',
              borderRadius: '12px',
              border: 0,
              background: 'var(--primary-600)',
              color: '#fff',
              fontWeight: 700,
              fontSize: '1rem',
              cursor: loading ? 'wait' : 'pointer',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '10px',
              boxShadow: '0 4px 14px rgba(37, 99, 235, 0.25)',
            }}
          >
            {loading ? <><LoaderCircle className="member-entry-spin" size={19} /> กำลังตรวจสอบ...</> : <>ยืนยันตัวตน <ArrowRight size={18} /></>}
          </button>
        </form>

        <div style={{ marginTop: '24px', textAlign: 'center', borderTop: '1px solid #f1f5f9', paddingTop: '18px' }}>
          <button
            type="button"
            onClick={() => {
              setUseRecovery((val) => !val);
              setError('');
            }}
            style={{
              background: 'transparent',
              border: 0,
              color: 'var(--primary-700)',
              fontSize: '0.88rem',
              fontWeight: 600,
              cursor: 'pointer',
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
            }}
          >
            {useRecovery ? (
              <><ArrowLeft size={16} /> กลับไปใช้รหัส 6 หลักจากแอป (Authenticator)</>
            ) : (
              <><LifeBuoy size={16} /> เข้าแอปไม่ได้? ใช้รหัสสำรองฉุกเฉิน (Recovery Code)</>
            )}
          </button>
        </div>

      </div>
    </div>
  );
}
