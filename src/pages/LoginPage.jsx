import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { ArrowLeft, Eye, EyeOff, KeyRound, LoaderCircle, LockKeyhole, ShieldCheck, User, Mail, Sparkles, CheckCircle2 } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { normalizeSameOriginRedirect } from '../utils/navigation';
import './LoginPage.css';

const STAFF_ROLES = new Set([
  'member_admin', 'super_admin', 'staff', 'manager', 'executive', 'finance', 'loan_officer',
  'welfare_officer', 'pr_officer', 'document_officer', 'complaint_officer',
  'auditor', 'it_admin'
]);

function GoogleIcon() {
  return (
    <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
      <path
        fill="#4285F4"
        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
      />
      <path
        fill="#34A853"
        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
      />
      <path
        fill="#FBBC05"
        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"
      />
      <path
        fill="#EA4335"
        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"
      />
    </svg>
  );
}

export default function LoginPage() {
  const { login, user, sessionStatus } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  // URL Query Parameters
  const queryParams = new URLSearchParams(location.search);
  const invitationToken = queryParams.get('invitation') || '';
  const urlError = queryParams.get('error') || '';

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState(urlError);
  const [loading, setLoading] = useState(false);

  const staffLogin = location.pathname === '/admin/login';

  useEffect(() => {
    if (sessionStatus !== 'online' || !user) return;
    const target = user.role === 'member'
      ? '/member/dividends'
      : user.role === 'member_admin'
      ? '/admin/members/dashboard'
      : user.role === 'super_admin'
      ? '/admin/dashboard'
      : '/staff/dashboard';
    navigate(target, { replace: true });
  }, [navigate, sessionStatus, user]);

  const handleGoogleLogin = () => {
    setLoading(true);
    const startUrl = '/auth/google/start' + (invitationToken ? `?invitation=${encodeURIComponent(invitationToken)}` : '');
    window.location.assign(startUrl);
  };

  const handleMemberSubmit = async (event) => {
    event.preventDefault();
    setError('');
    const cleanUsername = username.trim();
    if (!cleanUsername || !password) {
      setError('กรุณากรอกเลขบัตรประชาชน / ชื่อผู้ใช้งาน และรหัสผ่าน');
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
        navigate(normalizeSameOriginRedirect(result.redirect, '/admin/mfa/verify'));
        return;
      }
      if (result.requiresMfaEnrollment) {
        navigate(normalizeSameOriginRedirect(result.redirect, '/admin/mfa/enroll'));
        return;
      }
      const fallback = result.user?.role === 'member'
        ? '/member/dividends'
        : result.user?.role === 'member_admin'
        ? '/admin/members/dashboard'
        : result.user?.role === 'super_admin'
        ? '/admin/dashboard'
        : '/staff/dashboard';

      navigate(normalizeSameOriginRedirect(result.redirect, fallback), { replace: true });
    } catch {
      setError('ไม่สามารถเชื่อมต่อระบบยืนยันตัวตนได้ กรุณาลองใหม่อีกครั้ง');
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="member-entry">
      <div className="member-entry-shell">
        <Link to="/" className="member-entry-back"><ArrowLeft size={17} /> กลับสู่เว็บไซต์หลัก</Link>
        <div className="member-entry-card">
          {/* Welcome / Brand Banner */}
          <section className="member-entry-welcome" aria-label="ระบบสมาชิกสหกรณ์">
            <div className="member-entry-brand">
              <img src="/assets/img/logo.webp" alt="ตราสหกรณ์ออมทรัพย์สาธารณสุขระยอง" />
              <span>สหกรณ์ออมทรัพย์<br />สาธารณสุขระยอง จำกัด</span>
            </div>
            <div className="member-entry-intro">
              <span className="member-entry-eyebrow">
                {staffLogin ? 'ระบบบริหารจัดการสำหรับเจ้าหน้าที่' : 'บริการออนไลน์สำหรับสมาชิก'}
              </span>
              <h1>
                {staffLogin ? <>ระบบเจ้าหน้าที่<br /><em>ความปลอดภัยขั้นสูง</em></> : <>เรื่องของสมาชิก<br /><em>เข้าถึงได้ใกล้กว่าเดิม</em></>}
              </h1>
              <p>
                {staffLogin
                  ? 'ยืนยันตัวตนด้วย Google Workspace / Google Account ที่ได้รับอนุมัติ และระบบความปลอดภัย 2FA'
                  : 'ตรวจสอบเงินปันผลและเงินเฉลี่ยคืนของคุณ สะดวก ทุกที่ ทุกเวลา'}
              </p>
            </div>
            <div className="member-entry-benefit">
              <ShieldCheck size={24} />
              <div>
                <strong>{staffLogin ? 'Google Sign-In + Mandatory 2FA' : 'ข้อมูลของคุณ สำหรับคุณ'}</strong>
                <p>
                  {staffLogin
                    ? 'สิทธิ์การเข้าถึงถูกควบคุมอย่างเข้มงวดตามมาตรฐาน RBAC และบันทึก Audit Logs ทุกขั้นตอน'
                    : 'เข้าสู่ระบบเพื่อดูรายการของตนเอง และตรวจสอบข้อมูลย้อนหลังรายปี'}
                </p>
              </div>
            </div>
            <span className="member-entry-caption">Rayong Public Health Savings and Credit Cooperative Limited</span>
          </section>

          {/* Form / Actions */}
          <section className="member-entry-form" aria-labelledby="member-login-title">
            <div className="member-entry-icon">
              {staffLogin ? <ShieldCheck size={26} /> : <User size={25} />}
            </div>
            <h2 id="member-login-title">{staffLogin ? 'เข้าสู่ระบบเจ้าหน้าที่' : 'เข้าสู่ระบบสมาชิก'}</h2>
            <p className="member-entry-subtitle">
              {staffLogin ? 'ใช้บัญชี Google ที่ได้รับสิทธิ์อนุมัติจากสหกรณ์' : 'ยินดีต้อนรับสู่บริการสมาชิกออนไลน์'}
            </p>

            {invitationToken && (
              <div role="status" className="member-entry-notice" style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '16px' }}>
                <Sparkles size={18} style={{ color: 'var(--primary-600)', flexShrink: 0 }} />
                <span>ตรวจพบคำเชิญเข้าร่วมงาน: กรุณากดเข้าสู่ระบบด้วยบัญชี Google เพื่อเปิดใช้งานบัญชีเจ้าหน้าที่</span>
              </div>
            )}

            {location.state?.sessionRequired && (
              <p role="status" className="member-entry-notice">
                กรุณาเข้าสู่ระบบเพื่อดำเนินการต่อ หากเซสชันหมดอายุให้เข้าสู่ระบบใหม่อีกครั้ง
              </p>
            )}

            {error && <p role="alert" className="member-entry-error">{error}</p>}

            {staffLogin ? (
              /* Staff Google Sign-In Section */
              <div className="staff-google-login-box" style={{ marginTop: '1.5rem' }}>
                <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '14px', padding: '16px', marginBottom: '24px' }}>
                  <p style={{ margin: 0, fontSize: '0.88rem', color: '#475569', lineHeight: 1.6 }}>
                    <strong>ข้อกำหนดความปลอดภัย:</strong> เจ้าหน้าที่และผู้ดูแลระบบทุกคนต้องเข้าสู่ระบบผ่านบัญชี Google ที่ได้รับอนุมัติ และต้องยืนยันตัวตนด้วยรหัส OTP 2 ขั้นตอน (Mandatory MFA) ทุกครั้ง
                  </p>
                </div>

                <button
                  type="button"
                  className="google-signin-btn"
                  onClick={handleGoogleLogin}
                  disabled={loading}
                  style={{
                    width: '100%',
                    minHeight: '54px',
                    border: '1px solid #cbd5e1',
                    borderRadius: '12px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '12px',
                    background: '#ffffff',
                    color: '#1e293b',
                    fontSize: '1rem',
                    fontWeight: 600,
                    cursor: loading ? 'wait' : 'pointer',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.06)',
                    transition: 'all 0.2s ease',
                  }}
                >
                  {loading ? (
                    <><LoaderCircle className="member-entry-spin" size={20} /> กำลังเชื่อมต่อ Google...</>
                  ) : (
                    <><GoogleIcon /> เข้าสู่ระบบด้วยบัญชี Google</>
                  )}
                </button>

                <p className="member-entry-help" style={{ marginTop: '28px' }}>
                  ยังไม่ได้รับคำเชิญเข้าใช้งาน? <Link to="/contact">ติดต่อผู้ดูแลระบบสหกรณ์</Link>
                </p>

                <div className="member-entry-footer" style={{ marginTop: '24px' }}>
                  <ShieldCheck size={16} />
                  <span>ระบบรองรับการยืนยันตัวตนแบบ OpenID Connect พร้อมการป้องกัน OTP Replay</span>
                </div>

                <Link to="/login" className="member-entry-staff" style={{ display: 'block', marginTop: '18px', textAlign: 'center' }}>
                  สำหรับสมาชิกสหกรณ์ทั่วไป (ตรวจสอบเงินปันผล/เงินกู้)
                </Link>
              </div>
            ) : (
              /* Member Local / ID Card Login Form */
              <form onSubmit={handleMemberSubmit} noValidate>
                <label htmlFor="login-username">เลขบัตรประชาชน 13 หลัก</label>
                <div className="member-entry-input">
                  <User size={18} aria-hidden="true" />
                  <input
                    id="login-username"
                    type="text"
                    value={username}
                    onChange={(event) => setUsername(event.target.value)}
                    autoComplete="username"
                    autoCapitalize="none"
                    spellCheck="false"
                    disabled={loading}
                    placeholder="กรอกเลขบัตรประชาชน 13 หลัก"
                    autoFocus
                  />
                </div>

                <label htmlFor="login-password">รหัสผ่าน</label>
                <div className="member-entry-input">
                  <KeyRound size={18} aria-hidden="true" />
                  <input
                    id="login-password"
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    autoComplete="current-password"
                    disabled={loading}
                    placeholder="กรอกรหัสผ่านของคุณ"
                  />
                  <button
                    type="button"
                    className="member-entry-eye"
                    onClick={() => setShowPassword((value) => !value)}
                    aria-label={showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'}
                    aria-pressed={showPassword}
                  >
                    {showPassword ? <EyeOff size={19} /> : <Eye size={19} />}
                  </button>
                </div>

                <button className="member-entry-submit" type="submit" disabled={loading}>
                  {loading ? (
                    <><LoaderCircle className="member-entry-spin" size={19} /> กำลังตรวจสอบ...</>
                  ) : (
                    <><LockKeyhole size={19} /> เข้าสู่ระบบสมาชิก</>
                  )}
                </button>

                <p className="member-entry-help">
                  มีปัญหาในการเข้าสู่ระบบ? <Link to="/contact">ติดต่อสหกรณ์</Link>
                </p>

                <div className="member-entry-footer">
                  <ShieldCheck size={15} />
                  <span>กรุณาออกจากระบบทุกครั้ง เมื่อใช้เครื่องร่วมกับผู้อื่น</span>
                </div>

                <Link to="/admin/login" className="member-entry-staff">
                  สำหรับเจ้าหน้าที่สหกรณ์ (เข้าสู่ระบบด้วย Google)
                </Link>
              </form>
            )}
          </section>
        </div>
      </div>
    </main>
  );
}
