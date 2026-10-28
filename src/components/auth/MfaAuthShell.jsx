import React, { useState } from 'react';
import { Clock, Moon, ShieldCheck, Sun } from 'lucide-react';
import { useTheme } from '../../context/ThemeContext';

/**
 * Format remaining seconds as MM:SS.
 */
export function formatMfaCountdown(seconds) {
  if (seconds === null || seconds === undefined || Number.isNaN(Number(seconds))) {
    return null;
  }
  const clamped = Math.max(0, Math.floor(Number(seconds)));
  const mins = Math.floor(clamped / 60);
  const secs = clamped % 60;
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

/**
 * Dedicated Minimal Authentication Layout for MFA Verification & Enrollment (RYCOOPWEB 3.0)
 *
 * Isolates MFA challenges from Admin/Member sidebars and private dashboards.
 */
export default function MfaAuthShell({
  title = 'ยืนยันตัวตนสองขั้นตอน',
  subtitle = 'Two-Factor Authentication',
  instruction = 'กรอกรหัสยืนยัน 6 หลักจากแอป Authenticator ของคุณ',
  icon: IconComponent = ShieldCheck,
  remainingSeconds = null,
  isExpired = false,
  userEmail = null,
  children,
}) {
  const themeContext = useTheme();
  const [localTheme, setLocalTheme] = useState('light');
  const activeTheme = themeContext?.theme || localTheme;

  const handleToggleTheme = () => {
    if (typeof themeContext?.toggleTheme === 'function') {
      themeContext.toggleTheme();
    } else {
      const next = activeTheme === 'dark' ? 'light' : 'dark';
      setLocalTheme(next);
      if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-theme', next);
      }
    }
  };

  const formattedRemaining = formatMfaCountdown(remainingSeconds);
  const isWarning = !isExpired && remainingSeconds !== null && remainingSeconds > 0 && remainingSeconds <= 120;

  return (
    <div className="mfa-auth-viewport" data-theme={activeTheme}>
      {/* Top Cooperative Brand Header */}
      <header className="mfa-auth-topbar">
        <div className="mfa-auth-brand">
          <img
            src="/assets/img/logo.webp"
            alt="ตราสัญลักษณ์สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด"
            className="mfa-auth-logo"
            onError={(e) => {
              e.currentTarget.src = '/img/logo.webp';
            }}
          />
          <div className="mfa-auth-brand-text">
            <span className="mfa-auth-coop-name">สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด</span>
            <span className="mfa-auth-system-tag">RYCOOPWEB Secure Login</span>
          </div>
        </div>

        <button
          type="button"
          onClick={handleToggleTheme}
          className="mfa-theme-toggle"
          aria-label={activeTheme === 'dark' ? 'เปลี่ยนเป็นโหมดสว่าง' : 'เปลี่ยนเป็นโหมดมืด'}
          title={activeTheme === 'dark' ? 'โหมดสว่าง (Light Mode)' : 'โหมดมืด (Dark Mode)'}
        >
          {activeTheme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}
        </button>
      </header>

      {/* Main Centered Authentication Card */}
      <main className="mfa-auth-main">
        <section className="mfa-auth-card" aria-labelledby="mfa-card-heading">
          <div className="mfa-card-header">
            <div className="mfa-security-badge-icon" aria-hidden="true">
              <IconComponent size={28} />
            </div>
            <h1 id="mfa-card-heading" className="mfa-card-title">
              {title}
            </h1>
            <p className="mfa-card-subtitle">{subtitle}</p>
            {instruction && <p className="mfa-card-instruction">{instruction}</p>}
            {userEmail && (
              <p
                style={{
                  margin: '0.45rem 0 0 0',
                  fontSize: '0.78rem',
                  color: 'var(--mfa-text-muted)',
                  fontWeight: 600,
                }}
              >
                บัญชีผู้ใช้: <span style={{ color: 'var(--mfa-text)' }}>{userEmail}</span>
              </p>
            )}
          </div>

          {/* Authoritative Server-Driven Session Expiry Indicator */}
          <div
            className={[
              'mfa-expiry-bar',
              isExpired ? 'expired' : isWarning ? 'warning' : '',
            ]
              .filter(Boolean)
              .join(' ')}
            role="status"
            aria-live="polite"
          >
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}>
              <Clock size={15} aria-hidden="true" />
              {isExpired ? (
                <span>เซสชันการยืนยันตัวตนหมดอายุแล้ว (เกิน 10 นาที)</span>
              ) : isWarning ? (
                <span>เซสชันกำลังจะหมดอายุ กรุณายืนยันภายใน</span>
              ) : formattedRemaining !== null ? (
                <span>เวลาที่เหลือสำหรับขั้นตอนนี้</span>
              ) : (
                <span>ขั้นตอนนี้มีอายุ 10 นาที นับจากเวลาที่เริ่มเข้าสู่ระบบ</span>
              )}
            </span>
            {formattedRemaining !== null && !isExpired && (
              <span className="mfa-expiry-time">{formattedRemaining} นาที</span>
            )}
          </div>

          {children}
        </section>
      </main>

      {/* Security Footer */}
      <footer className="mfa-auth-footer">
        <ShieldCheck size={15} aria-hidden="true" />
        <span>ระบบรักษาความปลอดภัยสำหรับเจ้าหน้าที่สหกรณ์</span>
      </footer>
    </div>
  );
}

