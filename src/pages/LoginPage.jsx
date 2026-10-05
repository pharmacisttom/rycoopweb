import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { ArrowLeft, Eye, EyeOff, KeyRound, LoaderCircle, LockKeyhole, ShieldCheck, User } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { normalizeSameOriginRedirect } from '../utils/navigation';
import './LoginPage.css';

const STAFF_ROLES = new Set([
  'super_admin', 'staff', 'manager', 'executive', 'finance', 'loan_officer',
  'welfare_officer', 'pr_officer', 'document_officer', 'complaint_officer',
  'auditor', 'it_admin'
]);

export default function LoginPage() {
  const { login, user, sessionStatus } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
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

  const staffLogin = location.pathname === '/admin/login';
  return (
    <main className="member-entry">
      <div className="member-entry-shell">
        <Link to="/" className="member-entry-back"><ArrowLeft size={17} /> กลับสู่เว็บไซต์หลัก</Link>
        <div className="member-entry-card">
          <section className="member-entry-welcome" aria-label="ระบบสมาชิกสหกรณ์">
            <div className="member-entry-brand"><img src="/assets/img/logo.webp" alt="ตราสหกรณ์ออมทรัพย์สาธารณสุขระยอง" /><span>สหกรณ์ออมทรัพย์<br />สาธารณสุขระยอง จำกัด</span></div>
            <div className="member-entry-intro"><span className="member-entry-eyebrow">บริการออนไลน์สำหรับสมาชิก</span><h1>เรื่องของสมาชิก<br /><em>เข้าถึงได้ใกล้กว่าเดิม</em></h1><p>ตรวจสอบเงินปันผลและเงินเฉลี่ยคืนของคุณ<br />สะดวก ทุกที่ ทุกเวลา</p></div>
            <div className="member-entry-benefit"><ShieldCheck size={24} /><div><strong>ข้อมูลของคุณ สำหรับคุณ</strong><p>เข้าสู่ระบบเพื่อดูรายการของตนเอง<br />และตรวจสอบข้อมูลย้อนหลังรายปี</p></div></div>
            <span className="member-entry-caption">Rayong Public Health Savings and Credit Cooperative</span>
          </section>
          <section className="member-entry-form" aria-labelledby="member-login-title">
            <div className="member-entry-icon"><User size={25} /></div>
            <h2 id="member-login-title">{staffLogin ? 'เข้าสู่ระบบเจ้าหน้าที่' : 'เข้าสู่ระบบสมาชิก'}</h2>
            <p className="member-entry-subtitle">{staffLogin ? 'ใช้บัญชีเจ้าหน้าที่ที่ได้รับสิทธิ์จากสหกรณ์' : 'ยินดีต้อนรับสู่บริการสมาชิกออนไลน์'}</p>
            {location.state?.sessionRequired && <p role="status" className="member-entry-notice">กรุณาเข้าสู่ระบบเพื่อดูข้อมูลของคุณ หากเซสชันหมดอายุให้เข้าสู่ระบบอีกครั้ง</p>}
            {error && <p role="alert" className="member-entry-error">{error}</p>}
            <form onSubmit={handleSubmit} noValidate>
              <label htmlFor="login-username">{staffLogin ? 'ชื่อผู้ใช้งาน / อีเมล' : 'เลขบัตรประชาชน'}</label>
              <div className="member-entry-input"><User size={18} aria-hidden="true" /><input id="login-username" type="text" value={username} onChange={event => setUsername(event.target.value)} autoComplete="username" autoCapitalize="none" spellCheck="false" disabled={loading} placeholder={staffLogin ? 'กรอกชื่อผู้ใช้งาน' : 'กรอกเลขบัตรประชาชน 13 หลัก'} autoFocus /></div>
              <label htmlFor="login-password">รหัสผ่าน</label>
              <div className="member-entry-input"><KeyRound size={18} aria-hidden="true" /><input id="login-password" type={showPassword ? 'text' : 'password'} value={password} onChange={event => setPassword(event.target.value)} autoComplete="current-password" disabled={loading} placeholder="กรอกรหัสผ่านของคุณ" /><button type="button" className="member-entry-eye" onClick={() => setShowPassword(value => !value)} aria-label={showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'} aria-pressed={showPassword}>{showPassword ? <EyeOff size={19} /> : <Eye size={19} />}</button></div>
              {!staffLogin && <p className="member-entry-hint">รหัสผ่านเริ่มต้นคือเลขสมาชิก รวมเลขศูนย์นำหน้า เช่น <strong>00025</strong><br />หากเปลี่ยนรหัสผ่านแล้ว ให้ใช้รหัสใหม่</p>}
              <button className="member-entry-submit" type="submit" disabled={loading}>{loading ? <><LoaderCircle className="member-entry-spin" size={19} /> กำลังตรวจสอบ...</> : <><LockKeyhole size={19} /> เข้าสู่ระบบ{staffLogin ? '' : 'สมาชิก'}</>}</button>
            </form>
            <p className="member-entry-help">มีปัญหาในการเข้าสู่ระบบ? <Link to="/contact">ติดต่อสหกรณ์</Link></p>
            <div className="member-entry-footer"><ShieldCheck size={15} /><span>กรุณาออกจากระบบทุกครั้ง เมื่อใช้เครื่องร่วมกับผู้อื่น</span></div>
            {!staffLogin && <Link to="/admin/login" className="member-entry-staff">สำหรับเจ้าหน้าที่สหกรณ์</Link>}
          </section>
        </div>
      </div>
    </main>
  );
}
