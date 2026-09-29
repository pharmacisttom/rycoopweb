import React, { useState } from 'react';
import { X, Lock, KeyRound, Eye, EyeOff, ShieldCheck, AlertCircle, CheckCircle2 } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';

export default function ChangePasswordModal({ isOpen, onClose }) {
  const { user } = useAuth();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);

  if (!isOpen || !user) return null;

  // Password strength helper
  const getPasswordStrength = (pass) => {
    if (!pass) return { level: 0, text: '', color: '' };
    let score = 0;
    if (pass.length >= 6) score++;
    if (pass.length >= 8) score++;
    if (/[A-Z]/.test(pass) && /[a-z]/.test(pass)) score++;
    if (/[0-9]/.test(pass)) score++;
    if (/[^A-Za-z0-9]/.test(pass)) score++;

    if (score <= 2) return { level: 1, text: 'ระดับ: พอใช้ (ควรเพิ่มตัวเลขหรืออักษรพิเศษ)', color: 'var(--accent-gold)' };
    if (score <= 4) return { level: 2, text: 'ระดับ: ปานกลาง (ปลอดภัยดี)', color: 'var(--primary-600)' };
    return { level: 3, text: 'ระดับ: แข็งแรงมาก (ปลอดภัยสูงสุด)', color: 'var(--accent-emerald)' };
  };

  const strength = getPasswordStrength(newPassword);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    const cur = currentPassword.trim();
    const np = newPassword.trim();
    const cp = confirmPassword.trim();

    if (!cur) {
      setError('กรุณาระบุรหัสผ่านปัจจุบัน');
      return;
    }
    if (!np || np.length < 8) {
      setError('รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร');
      return;
    }
    if (np !== cp) {
      setError('รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน');
      return;
    }
    if (cur === np) {
      setError('รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านปัจจุบัน');
      return;
    }

    setLoading(true);

    try {
      let csrfToken = '';
      try {
        const tokenRes = await fetch('/csrf-token', { credentials: 'include' });
        const tokenData = await tokenRes.json();
        csrfToken = tokenData?.token || '';
      } catch (e) {}

      const formData = new FormData();
      formData.append('username', user?.username || user?.memberId || '');
      if (user?.id) {
        formData.append('user_id', String(user.id));
      }
      formData.append('current_password', cur);
      formData.append('new_password', np);
      formData.append('confirm_password', cp);
      formData.append('ajax', '1');
      if (csrfToken) {
        formData.append('_csrf_token', csrfToken);
      }

      const endpoints = ['/change-password'];

      let apiSuccess = false;
      let serverErrorMsg = '';

      for (const ep of endpoints) {
        try {
          const res = await fetch(ep, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
            },
            credentials: 'include'
          });

          const data = await res.json().catch(() => null);

          if (res.ok && data?.success === true) {
            apiSuccess = true;
            break;
          } else if (data?.message) {
            serverErrorMsg = data.message;
            break;
          }
        } catch (err) {}
      }

      if (!apiSuccess) {
        setLoading(false);
        setError(serverErrorMsg || 'ไม่สามารถเปลี่ยนรหัสผ่านได้ กรุณาตรวจสอบรหัสผ่านเดิมอีกครั้ง');
        return;
      }

      setLoading(false);
      setSuccess(true);
      setTimeout(() => {
        setSuccess(false);
        setCurrentPassword('');
        setNewPassword('');
        setConfirmPassword('');
        onClose();
      }, 1500);

    } catch (err) {
      setLoading(false);
      setError('เกิดข้อผิดพลาดในการบันทึกรหัสผ่าน กรุณาลองใหม่อีกครั้ง');
    }
  };

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: 2600,
        background: 'rgba(15, 23, 42, 0.75)',
        backdropFilter: 'blur(8px)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '1rem',
        overflowY: 'auto'
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget && !loading) onClose();
      }}
    >
      <div
        className="surface-card animate-fade-in shadow-2xl"
        style={{
          width: '100%',
          maxWidth: '480px',
          background: 'var(--bg-surface)',
          borderRadius: 'var(--radius-xl)',
          overflow: 'hidden',
          border: '1px solid var(--border-subtle)',
          position: 'relative'
        }}
      >
        {/* Header */}
        <div
          style={{
            background: 'var(--gradient-primary)',
            color: '#ffffff',
            padding: '1.5rem',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center'
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <div style={{ width: '42px', height: '42px', borderRadius: '10px', background: 'rgba(255,255,255,0.2)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <KeyRound size={22} color="#ffffff" />
            </div>
            <div>
              <h3 style={{ color: '#fff', fontSize: '1.15rem', fontWeight: 800, margin: 0 }}>
                เปลี่ยนรหัสผ่าน (Change Password)
              </h3>
              <p style={{ color: 'rgba(255,255,255,0.85)', fontSize: '0.75rem', margin: '0.2rem 0 0 0' }}>
                ผู้ใช้งาน: <strong>{user.name}</strong> ({user.roleBadge || user.roleName})
              </p>
            </div>
          </div>

          <button
            type="button"
            onClick={onClose}
            style={{
              background: 'rgba(255,255,255,0.15)',
              border: 'none',
              borderRadius: '50%',
              padding: '0.35rem',
              color: '#ffffff',
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center'
            }}
            disabled={loading}
          >
            <X size={18} />
          </button>
        </div>

        {/* Body */}
        <div style={{ padding: '1.75rem 1.5rem' }}>

          {error && (
            <div
              role="alert"
              style={{
                background: 'var(--accent-rose-light)',
                color: 'var(--accent-rose)',
                padding: '0.75rem 1rem',
                borderRadius: 'var(--radius-md)',
                fontSize: '0.85rem',
                marginBottom: '1.25rem',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '0.5rem',
                border: '1px solid rgba(225, 29, 72, 0.2)'
              }}
            >
              <AlertCircle size={18} style={{ flexShrink: 0, marginTop: '2px' }} />
              <div>{error}</div>
            </div>
          )}

          {success && (
            <div
              className="animate-fade-in"
              style={{
                background: 'var(--accent-emerald-light)',
                color: 'var(--accent-emerald-dark)',
                padding: '0.85rem 1rem',
                borderRadius: 'var(--radius-md)',
                fontSize: '0.88rem',
                marginBottom: '1.25rem',
                display: 'flex',
                alignItems: 'center',
                gap: '0.5rem',
                fontWeight: 700
              }}
            >
              <CheckCircle2 size={20} />
              <span>เปลี่ยนรหัสผ่านสำเร็จเรียบร้อยแล้ว!</span>
            </div>
          )}

          <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1.15rem' }}>

            {/* Current Password */}
            <div className="form-group" style={{ margin: 0 }}>
              <label className="form-label" style={{ fontSize: '0.82rem', fontWeight: 600 }}>
                รหัสผ่านปัจจุบัน (Current Password) <span style={{ color: 'var(--accent-rose)' }}>*</span>
              </label>
              <div style={{ position: 'relative' }}>
                <Lock size={16} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                <input
                  type={showCurrentPassword ? 'text' : 'password'}
                  className="form-control"
                  style={{ paddingLeft: '2.4rem', paddingRight: '2.4rem', fontSize: '0.9rem' }}
                  placeholder="กรอกรหัสผ่านปัจจุบันของท่าน"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                  style={{
                    position: 'absolute',
                    right: '10px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    background: 'none',
                    border: 'none',
                    color: 'var(--text-muted)',
                    cursor: 'pointer',
                    padding: '4px'
                  }}
                  tabIndex={-1}
                >
                  {showCurrentPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            {/* New Password */}
            <div className="form-group" style={{ margin: 0 }}>
              <label className="form-label" style={{ fontSize: '0.82rem', fontWeight: 600 }}>
                รหัสผ่านใหม่ (New Password) <span style={{ color: 'var(--accent-rose)' }}>*</span>
              </label>
              <div style={{ position: 'relative' }}>
                <KeyRound size={16} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                <input
                  type={showNewPassword ? 'text' : 'password'}
                  className="form-control"
                  style={{ paddingLeft: '2.4rem', paddingRight: '2.4rem', fontSize: '0.9rem' }}
                  placeholder="ความยาวอย่างน้อย 8 ตัวอักษร"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowNewPassword(!showNewPassword)}
                  style={{
                    position: 'absolute',
                    right: '10px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    background: 'none',
                    border: 'none',
                    color: 'var(--text-muted)',
                    cursor: 'pointer',
                    padding: '4px'
                  }}
                  tabIndex={-1}
                >
                  {showNewPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>

              {/* Password Strength Meter */}
              {newPassword && (
                <div style={{ marginTop: '0.4rem', fontSize: '0.75rem', color: strength.color }}>
                  {strength.text}
                </div>
              )}
            </div>

            {/* Confirm New Password */}
            <div className="form-group" style={{ margin: 0 }}>
              <label className="form-label" style={{ fontSize: '0.82rem', fontWeight: 600 }}>
                ยืนยันรหัสผ่านใหม่ (Confirm Password) <span style={{ color: 'var(--accent-rose)' }}>*</span>
              </label>
              <div style={{ position: 'relative' }}>
                <KeyRound size={16} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                <input
                  type={showConfirmPassword ? 'text' : 'password'}
                  className="form-control"
                  style={{ paddingLeft: '2.4rem', paddingRight: '2.4rem', fontSize: '0.9rem' }}
                  placeholder="กรอกรหัสผ่านใหม่อีกครั้งเพื่อยืนยัน"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  style={{
                    position: 'absolute',
                    right: '10px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    background: 'none',
                    border: 'none',
                    color: 'var(--text-muted)',
                    cursor: 'pointer',
                    padding: '4px'
                  }}
                  tabIndex={-1}
                >
                  {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            {/* Buttons */}
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '0.5rem' }}>
              <button
                type="button"
                onClick={onClose}
                className="btn btn-subtle"
                disabled={loading}
              >
                ยกเลิก
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                style={{ padding: '0.65rem 1.75rem', fontWeight: 700, display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
                disabled={loading}
              >
                {loading ? (
                  <span>กำลังบันทึก...</span>
                ) : (
                  <>
                    <ShieldCheck size={16} />
                    <span>บันทึกรหัสผ่านใหม่</span>
                  </>
                )}
              </button>
            </div>

          </form>

        </div>
      </div>
    </div>
  );
}
