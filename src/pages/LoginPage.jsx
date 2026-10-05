import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowLeft, Eye, EyeOff, KeyRound, LoaderCircle, LockKeyhole, ShieldCheck, User } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { normalizeSameOriginRedirect } from '../utils/navigation';

const STAFF_ROLES = new Set([
  'super_admin', 'staff', 'manager', 'executive', 'finance', 'loan_officer',
  'welfare_officer', 'pr_officer', 'document_officer', 'complaint_officer',
  'auditor', 'it_admin'
]);

export default function LoginPage() {
  const { login, user, sessionStatus } = useAuth();
  const navigate = useNavigate();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (sessionStatus !== 'online' || !user) return;
    navigate(user.role === 'member' ? '/member/dividends' : user.role === 'super_admin' ? '/admin/dashboard' : '/staff/dashboard', { replace: true });
  }, [navigate, sessionStatus, user]);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    const cleanUsername = username.trim();
    if (!cleanUsername || !password) {
      setError('กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน');
      return;
    }

    setLoading(true);
    try {
      const result = await login(cleanUsername, password);
      if (!result.success) {
        setError(result.message || 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
        return;
      }
      if (result.requiresTwoFactor) {
        window.location.assign(normalizeSameOriginRedirect(result.redirect, '/admin/2fa'));
        return;
      }
      if (result.user?.role !== 'member' && !STAFF_ROLES.has(result.user?.role)) {
        setError('บัญชีนี้ไม่มีสิทธิ์เข้าใช้งานระบบหลังบ้าน');
        return;
      }
      const fallback = result.user.role === 'member' ? '/member/dividends' : result.user.role === 'super_admin' ? '/admin/dashboard' : '/staff/dashboard';
      navigate(normalizeSameOriginRedirect(result.redirect, fallback), { replace: true });
    } catch {
      setError('ไม่สามารถเชื่อมต่อระบบยืนยันตัวตนได้ กรุณาลองใหม่อีกครั้ง');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ minHeight: 'calc(100vh - 150px)', background: 'linear-gradient(145deg, #f4f8f6 0%, #e9f4ef 100%)', display: 'grid', placeItems: 'center', padding: '2rem 1rem' }}>
      <div style={{ width: '100%', maxWidth: 440 }}>
        <Link to="/" style={{ display: 'inline-flex', alignItems: 'center', gap: 7, color: 'var(--text-muted)', textDecoration: 'none', marginBottom: 18 }}>
          <ArrowLeft size={17} /> กลับสู่เว็บไซต์หลัก
        </Link>

        <section className="surface-card shadow-lg" aria-labelledby="staff-login-title" style={{ borderRadius: 20, overflow: 'hidden', border: '1px solid var(--border-subtle)' }}>
          <header style={{ background: 'linear-gradient(135deg, var(--primary-900), var(--primary-700))', color: '#fff', padding: '2rem', textAlign: 'center' }}>
            <div style={{ width: 62, height: 62, margin: '0 auto 14px', borderRadius: 18, background: 'rgba(255,255,255,.14)', display: 'grid', placeItems: 'center' }}>
              <ShieldCheck size={34} aria-hidden="true" />
            </div>
            <h1 id="staff-login-title" style={{ color: '#fff', fontSize: '1.45rem', margin: 0 }}>เข้าสู่ระบบสมาชิก / เจ้าหน้าที่</h1>
            <p style={{ color: 'rgba(255,255,255,.78)', margin: '8px 0 0', fontSize: '.9rem' }}>สมาชิกใช้เลขบัตรประชาชน 13 หลัก เพื่อตรวจสอบเงินปันผล</p>
          </header>

          <div style={{ padding: '2rem' }}>
            {error && <div role="alert" aria-live="polite" style={{ padding: '12px 14px', marginBottom: 18, borderRadius: 10, color: '#9f1239', background: '#fff1f2', border: '1px solid #fecdd3', fontSize: '.9rem' }}>{error}</div>}

            <form onSubmit={handleSubmit} noValidate>
              <label htmlFor="login-username" className="form-label">เลขบัตรประชาชน / ชื่อผู้ใช้งาน</label>
              <div style={{ position: 'relative', marginBottom: 18 }}>
                <User size={18} aria-hidden="true" style={{ position: 'absolute', left: 13, top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                <input id="login-username" className="form-control" type="text" value={username} onChange={(event) => setUsername(event.target.value)} autoComplete="username" autoCapitalize="none" spellCheck="false" disabled={loading} style={{ paddingLeft: 42 }} placeholder="กรอกชื่อผู้ใช้งานหรืออีเมล" autoFocus />
              </div>

              <label htmlFor="login-password" className="form-label">รหัสผ่าน</label>
              <div style={{ position: 'relative', marginBottom: 22 }}>
                <KeyRound size={18} aria-hidden="true" style={{ position: 'absolute', left: 13, top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                <input id="login-password" className="form-control" type={showPassword ? 'text' : 'password'} value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" disabled={loading} style={{ paddingLeft: 42, paddingRight: 44 }} placeholder="กรอกรหัสผ่าน" />
                <button type="button" onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'} aria-pressed={showPassword} style={{ position: 'absolute', right: 8, top: '50%', transform: 'translateY(-50%)', border: 0, background: 'transparent', color: 'var(--text-muted)', padding: 7, cursor: 'pointer' }}>
                  {showPassword ? <EyeOff size={19} /> : <Eye size={19} />}
                </button>
              </div>

              <button className="btn btn-primary" type="submit" disabled={loading} style={{ width: '100%', minHeight: 48, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 9, fontWeight: 700 }}>
                {loading ? <><LoaderCircle size={19} /> กำลังตรวจสอบ...</> : <><LockKeyhole size={19} /> เข้าสู่ระบบ</>}
              </button>
            </form>

            <p style={{ margin: '18px 0 0', textAlign: 'center', color: 'var(--text-muted)', fontSize: '.8rem', lineHeight: 1.6 }}>
              ระบบจะจำกัดจำนวนครั้งในการเข้าสู่ระบบเพื่อความปลอดภัย<br />หากลืมรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ
            </p>
          </div>
        </section>
      </div>
    </div>
  );
}
