import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  AlertCircle,
  ArrowLeft,
  Check,
  CheckCircle2,
  Copy,
  KeyRound,
  LoaderCircle,
  LockKeyhole,
  LogOut,
  QrCode,
  RefreshCw,
  ShieldAlert,
  ShieldCheck,
  Smartphone,
} from 'lucide-react';
import { memberAdminApi } from '../services/memberAdminApi';
import { normalizeSameOriginRedirect } from '../utils/navigation';
import { useAuth } from '../context/AuthContext';
import MfaAuthShell from '../components/auth/MfaAuthShell';
import OtpInput, { isValidSixDigitOtp, sanitizeOtpDigits } from '../components/auth/OtpInput';
import './LoginPage.css';
import './MfaPage.css';

/**
 * Normalize recovery code input (uppercase, alphanumeric + hyphen only, max 40 chars).
 * Never logs or stores recovery codes in browser storage.
 */
export function sanitizeRecoveryCodeInput(raw) {
  if (typeof raw !== 'string') return '';
  return raw.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 40);
}

/**
 * Map API errors (400, 401, 403, 409, 422, 429, 500) to safe Thai messages
 * without leaking stack traces or server internals.
 */
export function resolveSafeMfaError(error, isRecovery = false) {
  const status = Number(error?.status || 0);
  const code = String(error?.code || '');
  const rawMessage = typeof error?.message === 'string' ? error.message.trim() : '';

  if (status === 403 && (code === 'MFA_ENROLLMENT_REQUIRED' || code === 'TWO_FACTOR_REQUIRED')) {
    return {
      status,
      expired: false,
      redirectMode: code === 'MFA_ENROLLMENT_REQUIRED' ? 'enroll' : 'verify',
      message: '',
    };
  }

  // Strip any accidental stack traces, SQL fragments, or file paths
  const isSafeThaiMessage =
    rawMessage !== '' &&
    !/exception|stack trace|sqlstate|pdo|syntax error|\/var\/www|\.php/i.test(rawMessage);

  if (status === 401) {
    return {
      status: 401,
      expired: true,
      message: isSafeThaiMessage
        ? rawMessage
        : 'เซสชันยืนยันตัวตนหมดอายุหรือไม่ถูกต้อง กรุณาเริ่มเข้าสู่ระบบใหม่',
    };
  }

  if (status === 429) {
    return {
      status: 429,
      expired: false,
      message: isSafeThaiMessage
        ? rawMessage
        : 'คุณกรอกรหัสผิดเกินจำนวนที่กำหนด กรุณารอ 5 นาทีก่อนลองใหม่อีกครั้ง',
    };
  }

  if (status === 422) {
    return {
      status: 422,
      expired: false,
      message: isSafeThaiMessage
        ? rawMessage
        : isRecovery
          ? 'กรุณากรอกรหัสสำรอง (Recovery Code) ให้ครบถ้วน'
          : 'กรุณากรอกรหัสยืนยันตัวเลข 6 หลักให้ครบถ้วน',
    };
  }

  if (status === 400 || status === 409) {
    return {
      status,
      expired: false,
      message: isSafeThaiMessage
        ? rawMessage
        : isRecovery
          ? 'รหัสสำรองไม่ถูกต้องหรือถูกใช้งานไปแล้ว'
          : 'รหัสความปลอดภัย 6 หลักไม่ถูกต้องหรือหมดอายุ กรุณาตรวจสอบเวลาบนสมาร์ตโฟนแล้วลองใหม่',
    };
  }

  if (status === 403) {
    return {
      status: 403,
      expired: false,
      message: 'คำขอไม่ผ่านการตรวจสอบความปลอดภัย (CSRF หรือเซสชันเปลี่ยนสถานะ) กรุณาลองใหม่อีกครั้ง',
    };
  }

  if (status >= 500) {
    return {
      status: 500,
      expired: false,
      message: 'ระบบยืนยันตัวตนขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในภายหลัง',
    };
  }

  return {
    status: status || 0,
    expired: false,
    message: isSafeThaiMessage
      ? rawMessage
      : 'ไม่สามารถดำเนินการยืนยันตัวตนได้ กรุณาตรวจสอบการเชื่อมต่อแล้วลองใหม่',
  };
}

export default function MfaPage({ mode = 'verify' }) {
  const navigate = useNavigate();
  const { refreshSession, logout } = useAuth();

  const [status, setStatus] = useState(null);
  const [setup, setSetup] = useState(null);
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState(false);
  const [codes, setCodes] = useState(null);
  const [destination, setDestination] = useState('/admin/workspace');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [copiedSecret, setCopiedSecret] = useState(false);
  const [copiedRecoveryAll, setCopiedRecoveryAll] = useState(false);

  // Authoritative server-driven expiry tracking (never resets on re-render or activity)
  const [expiresAtMs, setExpiresAtMs] = useState(null);
  const [remainingSeconds, setRemainingSeconds] = useState(null);
  const [isExpired, setIsExpired] = useState(false);
  const submittingRef = useRef(false);

  const syncServerExpiry = useCallback((payload) => {
    if (!payload || typeof payload !== 'object') return;
    if (typeof payload.remaining_seconds === 'number') {
      const rem = Math.max(0, Math.floor(payload.remaining_seconds));
      setRemainingSeconds(rem);
      setExpiresAtMs(Date.now() + rem * 1000);
      setIsExpired(rem <= 0);
    } else if (typeof payload.challenge_expires_at === 'number') {
      const serverNow = typeof payload.server_time === 'number'
        ? payload.server_time
        : Math.floor(Date.now() / 1000);
      const rem = Math.max(0, Math.floor(payload.challenge_expires_at - serverNow));
      setRemainingSeconds(rem);
      setExpiresAtMs(Date.now() + rem * 1000);
      setIsExpired(rem <= 0);
    }
  }, []);

  // Tick countdown based on authoritative server target timestamp
  useEffect(() => {
    if (expiresAtMs === null) return undefined;
    const tick = () => {
      const diffSec = Math.max(0, Math.ceil((expiresAtMs - Date.now()) / 1000));
      setRemainingSeconds(diffSec);
      if (diffSec <= 0) {
        setIsExpired(true);
      }
    };
    tick();
    const timerId = setInterval(tick, 1000);
    return () => clearInterval(timerId);
  }, [expiresAtMs]);

  // Clear sensitive enrollment setup key when leaving enrollment or unmounting
  useEffect(() => {
    return () => {
      setSetup(null);
      setCode('');
    };
  }, [mode]);

  useEffect(() => {
    const controller = new AbortController();
    setError('');
    setSetup(null);
    setCodes(null);
    setCode('');
    setRecovery(false);
    setIsExpired(false);

    memberAdminApi('/api/auth/mfa/status', { signal: controller.signal })
      .then((result) => {
        if (!result.pending_enrollment && !result.pending_verification) {
          navigate('/admin/workspace', { replace: true });
          return;
        }
        if (mode === 'enroll' && !result.pending_enrollment) {
          navigate('/admin/mfa/verify', { replace: true });
          return;
        }
        if (mode === 'verify' && result.pending_enrollment) {
          navigate('/admin/mfa/enroll', { replace: true });
          return;
        }
        setStatus(result);
        syncServerExpiry(result);
      })
      .catch((e) => {
        if (e?.name === 'AbortError') return;
        const resolved = resolveSafeMfaError(e, false);
        if (resolved.redirectMode) {
          navigate(`/admin/mfa/${resolved.redirectMode}`, { replace: true });
          return;
        }
        if (resolved.expired) {
          setIsExpired(true);
        }
        setError(resolved.message);
      });

    return () => controller.abort();
  }, [mode, navigate, syncServerExpiry]);

  async function finish(target) {
    if (submittingRef.current) return;
    submittingRef.current = true;
    setBusy(true);
    try {
      await refreshSession();
      navigate(target, { replace: true });
    } catch (e) {
      const resolved = resolveSafeMfaError(e, false);
      setError(resolved.message);
    } finally {
      submittingRef.current = false;
      setBusy(false);
    }
  }

  async function startEnrollment() {
    if (busy || submittingRef.current || isExpired) return;
    submittingRef.current = true;
    setBusy(true);
    setError('');
    try {
      const enrollData = await memberAdminApi('/api/auth/mfa/enroll', { data: {} });
      setSetup(enrollData);
      setCode('');
    } catch (e) {
      const resolved = resolveSafeMfaError(e, false);
      if (resolved.expired) setIsExpired(true);
      setError(resolved.message);
    } finally {
      submittingRef.current = false;
      setBusy(false);
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (busy || submittingRef.current) return;
    if (isExpired) {
      setError('เซสชันการยืนยันตัวตนหมดอายุแล้ว กรุณากดเริ่มเข้าสู่ระบบใหม่');
      return;
    }

    if (recovery) {
      const cleanRecovery = sanitizeRecoveryCodeInput(code);
      if (cleanRecovery.length < 8) {
        setError('กรุณากรอกรหัสสำรอง (Recovery Code) ให้ครบถ้วน');
        return;
      }
    } else {
      const cleanOtp = sanitizeOtpDigits(code);
      if (!isValidSixDigitOtp(cleanOtp)) {
        setError('กรุณากรอกรหัสยืนยันตัวเลข 6 หลักให้ครบถ้วน');
        return;
      }
    }

    submittingRef.current = true;
    setBusy(true);
    setError('');

    try {
      const endpoint = mode === 'enroll' ? 'confirm' : recovery ? 'recovery' : 'verify';
      const payload = recovery
        ? { recovery_code: sanitizeRecoveryCodeInput(code) }
        : { code: sanitizeOtpDigits(code) };

      const result = await memberAdminApi(`/api/auth/mfa/${endpoint}`, { data: payload });
      const target = normalizeSameOriginRedirect(result.redirect, '/admin/workspace');

      if (result.recovery_codes) {
        setCodes(result.recovery_codes);
        setDestination(target);
        setSetup(null);
        setCode('');
      } else {
        setSetup(null);
        setCode('');
        submittingRef.current = false;
        await finish(target);
        return;
      }
    } catch (e) {
      const resolved = resolveSafeMfaError(e, recovery);
      if (resolved.redirectMode) {
        navigate(`/admin/mfa/${resolved.redirectMode}`, { replace: true });
        return;
      }
      if (resolved.expired) {
        setIsExpired(true);
      }
      setError(resolved.message);
    } finally {
      submittingRef.current = false;
      setBusy(false);
    }
  }

  async function handleRestartLogin() {
    if (busy) return;
    setBusy(true);
    setSetup(null);
    setCode('');
    setCodes(null);
    try {
      await logout();
    } catch {
      // Proceed to login even if session was already cleared
    } finally {
      setBusy(false);
      navigate('/admin/login', { replace: true });
    }
  }

  const handleToggleRecoveryMode = () => {
    if (busy) return;
    setRecovery((prev) => !prev);
    setCode('');
    setError('');
  };

  const handleCopySetupSecret = async () => {
    if (!setup?.formatted_secret && !setup?.secret) return;
    const textToCopy = (setup.secret || setup.formatted_secret).replace(/\s+/g, '');
    try {
      if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(textToCopy);
        setCopiedSecret(true);
        setTimeout(() => setCopiedSecret(false), 2500);
      }
    } catch {
      // Ignore clipboard permission errors
    }
  };

  const handleCopyAllRecoveryCodes = async () => {
    if (!Array.isArray(codes) || codes.length === 0) return;
    try {
      if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(codes.join('\n'));
        setCopiedRecoveryAll(true);
        setTimeout(() => setCopiedRecoveryAll(false), 2500);
      }
    } catch {
      // Ignore clipboard permission errors
    }
  };

  const isEnrollMode = mode === 'enroll';
  const shellTitle = isEnrollMode ? 'ตั้งค่าการยืนยันตัวตนสองขั้นตอน' : 'ยืนยันตัวตนสองขั้นตอน';
  const shellSubtitle = 'Two-Factor Authentication';
  const shellInstruction = codes
    ? 'บันทึกรหัสสำรองฉุกเฉินของคุณไว้ในที่ปลอดภัยก่อนเข้าสู่ระบบ'
    : isEnrollMode
      ? 'สแกน QR Code ด้วยแอป Authenticator แล้วกรอกรหัสยืนยัน 6 หลักเพื่อเปิดใช้งาน'
      : recovery
        ? 'กรอกรหัสสำรอง (Recovery Code) ที่คุณได้รับเมื่อตั้งค่า 2FA'
        : 'กรอกรหัสยืนยัน 6 หลักจากแอป Authenticator ของคุณ';

  const shellIcon = recovery ? KeyRound : isEnrollMode ? QrCode : ShieldCheck;

  return (
    <MfaAuthShell
      title={shellTitle}
      subtitle={shellSubtitle}
      instruction={shellInstruction}
      icon={shellIcon}
      remainingSeconds={remainingSeconds}
      isExpired={isExpired}
      userEmail={status?.user?.email || null}
    >
      {/* Error Alert Banner */}
      {error && (
        <div role="alert" aria-live="assertive" className="mfa-alert error" id="mfa-error-message">
          <AlertCircle size={18} style={{ flexShrink: 0, marginTop: '2px' }} aria-hidden="true" />
          <span>{error}</span>
        </div>
      )}

      {/* Initial Session Checking Indicator */}
      {!status && !error && !codes && (
        <div role="status" aria-live="polite" className="mfa-alert info">
          <LoaderCircle size={18} className="mfa-spin" style={{ flexShrink: 0 }} aria-hidden="true" />
          <span>กำลังตรวจสอบเซสชัน…</span>
        </div>
      )}

      {/* One-Time Recovery Codes Display (Post-Enrollment Confirmation) */}
      {codes ? (
        <div>
          <div className="mfa-alert info" role="status">
            <CheckCircle2 size={18} style={{ color: 'var(--mfa-teal)', flexShrink: 0, marginTop: '2px' }} />
            <div>
              <strong style={{ display: 'block', color: 'var(--mfa-text)' }}>
                บันทึกรหัสสำรองก่อนดำเนินการต่อ
              </strong>
              <span>
                แสดงครั้งนี้ครั้งเดียว แต่ละรหัสใช้ได้หนึ่งครั้ง เก็บไว้ในที่ปลอดภัยและห้ามเปิดเผยแก่ผู้อื่น
              </span>
            </div>
          </div>

          <ul className="mfa-codes-grid" aria-label="รายการรหัสสำรองแบบใช้ครั้งเดียว">
            {codes.map((c, idx) => (
              <li key={c}>
                <span style={{ fontSize: '0.72rem', color: 'var(--mfa-text-muted)' }}>#{idx + 1}</span>
                <code>{c}</code>
              </li>
            ))}
          </ul>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
            <button
              type="button"
              className="mfa-btn-secondary"
              onClick={handleCopyAllRecoveryCodes}
            >
              {copiedRecoveryAll ? <Check size={16} /> : <Copy size={16} />}
              <span>{copiedRecoveryAll ? 'คัดลอกรหัสสำรองทั้งหมดแล้ว' : 'คัดลอกรหัสสำรองทั้งหมด'}</span>
            </button>

            <button
              type="button"
              className="mfa-btn-primary"
              disabled={busy}
              onClick={() => finish(destination)}
            >
              {busy ? (
                <>
                  <LoaderCircle size={18} className="mfa-spin" />
                  <span>กำลังเข้าสู่ระบบ…</span>
                </>
              ) : (
                <>
                  <LockKeyhole size={18} />
                  <span>บันทึกรหัสแล้ว เข้าพื้นที่ทำงาน</span>
                </>
              )}
            </button>
          </div>
        </div>
      ) : (
        status && (
          <>
            {/* Enrollment Step 1: Request QR & Setup Key */}
            {isEnrollMode && !setup && (
              <div className="mfa-enroll-panel">
                <Smartphone
                  size={34}
                  style={{ color: 'var(--mfa-teal)', margin: '0 auto 0.5rem auto' }}
                  aria-hidden="true"
                />
                <h2 style={{ fontSize: '1rem', fontWeight: 800, margin: '0 0 0.35rem 0', color: 'var(--mfa-text)' }}>
                  เตรียมแอป Authenticator บนสมาร์ตโฟนของคุณ
                </h2>
                <p style={{ fontSize: '0.82rem', color: 'var(--mfa-text-muted)', lineHeight: 1.55, margin: '0 0 1rem 0' }}>
                  รองรับ Google Authenticator, Microsoft Authenticator หรือแอป TOTP มาตรฐาน เพื่อรับรหัสความปลอดภัย 6 หลักที่เปลี่ยนทุก 30 วินาที
                </p>
                <button
                  type="button"
                  className="mfa-btn-primary"
                  disabled={busy || isExpired}
                  onClick={startEnrollment}
                >
                  {busy ? (
                    <>
                      <LoaderCircle size={18} className="mfa-spin" />
                      <span>กำลังสร้างรหัสตั้งค่า…</span>
                    </>
                  ) : (
                    <>
                      <QrCode size={18} />
                      <span>เริ่มตั้งค่าด้วยแอป Authenticator</span>
                    </>
                  )}
                </button>
              </div>
            )}

            {/* Enrollment Step 2: Display Local SVG QR Code + Manual Setup Key */}
            {setup && (
              <div className="mfa-enroll-panel">
                <p style={{ fontSize: '0.84rem', fontWeight: 700, color: 'var(--mfa-text)', margin: '0 0 0.4rem 0' }}>
                  1. สแกน QR ด้วยแอป Authenticator หรือกรอกรหัสตั้งค่าด้านล่าง
                </p>

                <div className="mfa-qr-frame">
                  <img
                    src={setup.qr_code_uri}
                    alt="QR ตั้งค่า MFA สำหรับบัญชีของคุณ"
                    className="mfa-qr-image"
                  />
                </div>

                <div className="mfa-secret-card">
                  <span style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--mfa-text-muted)' }}>
                    รหัสตั้งค่าด้วยตนเอง (Manual Setup Key — Base32):
                  </span>
                  <code className="mfa-secret-code">{setup.formatted_secret}</code>
                  <button
                    type="button"
                    className="mfa-btn-secondary"
                    style={{ minHeight: '36px', fontSize: '0.8rem' }}
                    onClick={handleCopySetupSecret}
                  >
                    {copiedSecret ? <Check size={15} /> : <Copy size={15} />}
                    <span>{copiedSecret ? 'คัดลอกรหัสตั้งค่าแล้ว' : 'คัดลอกรหัสตั้งค่า (Copy Setup Key)'}</span>
                  </button>
                </div>
              </div>
            )}

            {/* Verification / Enrollment Confirmation Form */}
            {(setup || mode === 'verify') && (
              <form onSubmit={handleSubmit} noValidate>
                {recovery ? (
                  <div className="mfa-recovery-box">
                    <label htmlFor="mfa-code" className="mfa-field-label">
                      <span>รหัสสำรอง (Recovery Code)</span>
                      {typeof status?.recovery_codes_remaining === 'number' && (
                        <span style={{ fontSize: '0.75rem', color: 'var(--mfa-teal)', fontWeight: 700 }}>
                          คงเหลือ {status.recovery_codes_remaining} รหัส
                        </span>
                      )}
                    </label>
                    <input
                      id="mfa-code"
                      name="recovery_code"
                      type="text"
                      inputMode="text"
                      autoComplete="off"
                      autoCapitalize="characters"
                      spellCheck="false"
                      value={code}
                      onChange={(e) => setCode(sanitizeRecoveryCodeInput(e.target.value))}
                      maxLength={40}
                      disabled={busy || isExpired}
                      required
                      placeholder="กรอกรหัสสำรองฉุกเฉินของคุณ"
                      aria-invalid={error ? 'true' : 'false'}
                      aria-describedby={error ? 'mfa-error-message mfa-recovery-hint' : 'mfa-recovery-hint'}
                      className="mfa-recovery-input"
                    />
                    <p id="mfa-recovery-hint" className="mfa-recovery-note">
                      แต่ละรหัสสำรองสามารถใช้ได้เพียง 1 ครั้งเท่านั้น เมื่อใช้แล้วรหัสนั้นจะถูกเพิกถอนทันที
                    </p>
                  </div>
                ) : (
                  <OtpInput
                    id="mfa-code"
                    value={code}
                    onChange={setCode}
                    disabled={busy || isExpired}
                    invalid={Boolean(error)}
                    describedBy={error ? 'mfa-error-message mfa-otp-hint' : 'mfa-otp-hint'}
                    label={
                      isEnrollMode
                        ? '2. กรอกรหัสยืนยัน 6 หลักจากแอปเพื่อเปิดใช้งาน'
                        : 'รหัสยืนยัน 6 หลัก'
                    }
                  />
                )}

                <button
                  type="submit"
                  className="mfa-btn-primary"
                  disabled={busy || isExpired}
                >
                  {busy ? (
                    <>
                      <LoaderCircle size={18} className="mfa-spin" aria-hidden="true" />
                      <span>กำลังตรวจสอบ…</span>
                    </>
                  ) : (
                    <>
                      <LockKeyhole size={18} aria-hidden="true" />
                      <span>ยืนยันและเข้าสู่ระบบ</span>
                    </>
                  )}
                </button>
              </form>
            )}
          </>
        )
      )}

      {/* Secondary & Session Reset Actions */}
      <div className="mfa-secondary-actions">
        {mode === 'verify' && !codes && status && (
          <button
            type="button"
            className="mfa-btn-secondary"
            disabled={busy || isExpired}
            onClick={handleToggleRecoveryMode}
          >
            {recovery ? (
              <>
                <Smartphone size={16} aria-hidden="true" />
                <span>ใช้แอป Authenticator</span>
              </>
            ) : (
              <>
                <KeyRound size={16} aria-hidden="true" />
                <span>ใช้รหัสสำรอง</span>
              </>
            )}
          </button>
        )}

        <button
          type="button"
          className={isExpired ? 'mfa-btn-primary' : 'mfa-btn-ghost'}
          disabled={busy}
          onClick={handleRestartLogin}
        >
          <LogOut size={15} aria-hidden="true" />
          <span>เริ่มเข้าสู่ระบบใหม่</span>
        </button>
      </div>
    </MfaAuthShell>
  );
}
