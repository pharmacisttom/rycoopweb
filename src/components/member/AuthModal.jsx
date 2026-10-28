import CoopBrandLogo from '../branding/CoopBrandLogo';
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { X, Lock, User, KeyRound, ShieldAlert, Eye, EyeOff } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { COOP_INFO } from '../../data/mockData';

export default function AuthModal() {
  const { showAuthModal, setShowAuthModal, login } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  if (!showAuthModal) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    const cleanUsername = username.trim();
    const cleanPassword = password.trim();

    if (!cleanUsername || !cleanPassword) {
      setError('กรุณากรอกชื่อผู้ใช้ / เลขทะเบียนสมาชิก และรหัสผ่าน');
      return;
    }

    setLoading(true);

    try {
      const result = await login(cleanUsername, cleanPassword);
      setLoading(false);

      if (result.success) {
        setShowAuthModal(false);
        if (result.user?.role === 'super_admin') {
          navigate('/admin/dashboard');
        } else if (result.user?.role === 'staff') {
          navigate('/staff/dashboard');
        } else {
          navigate('/member/dashboard');
        }
      } else {
        setError(result.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
      }
    } catch (err) {
      setLoading(false);
      setError('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง');
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
        zIndex: 2000,
        background: 'rgba(15, 23, 42, 0.7)',
        backdropFilter: 'blur(6px)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '1rem'
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget) setShowAuthModal(false);
      }}
    >
      <div
        className="surface-card animate-fade-in shadow-2xl"
        style={{
          width: '100%',
          maxWidth: '440px',
          background: 'var(--bg-surface)',
          borderRadius: 'var(--radius-xl)',
          overflow: 'hidden',
          border: '1px solid var(--border-subtle)',
          position: 'relative'
        }}
      >
        {/* Header Banner */}
        <div
          style={{
            background: 'var(--gradient-primary)',
            color: '#ffffff',
            padding: '1.5rem',
            textAlign: 'center',
            position: 'relative'
          }}
        >
          <button
            type="button"
            onClick={() => setShowAuthModal(false)}
            style={{
              position: 'absolute',
              top: '1rem',
              right: '1rem',
              color: 'rgba(255,255,255,0.85)',
              padding: '0.35rem',
              borderRadius: '50%',
              background: 'rgba(255,255,255,0.15)',
              border: 'none',
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center'
            }}
            aria-label="ปิดหน้าต่าง"
          >
            <X size={18} />
          </button>

          <div
            style={{
              width: '46px',
              height: '46px',
              margin: '0 auto 0.5rem auto',
              background: '#fff',
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              boxShadow: 'var(--shadow-md)'
            }}
          >
            <CoopBrandLogo variant="emblem" size={32} />
          </div>

          <h3 style={{ color: '#fff', fontSize: '1.15rem', fontWeight: 800, margin: '0 0 0.2rem 0' }}>
            เข้าสู่ระบบสมาชิกและเจ้าหน้าที่
          </h3>
          <p style={{ color: 'rgba(255,255,255,0.85)', fontSize: '0.75rem', margin: 0 }}>
            {COOP_INFO.nameTh}
          </p>
        </div>

        {/* Form Body */}
        <div style={{ padding: '1.75rem 1.5rem' }}>

          {error && (
            <div
              role="alert"
              style={{
                background: 'var(--accent-rose-light)',
                color: 'var(--accent-rose)',
                padding: '0.75rem 0.85rem',
                borderRadius: 'var(--radius-md)',
                fontSize: '0.82rem',
                marginBottom: '1.25rem',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '0.4rem',
                border: '1px solid rgba(225, 29, 72, 0.2)'
              }}
            >
              <ShieldAlert size={16} style={{ flexShrink: 0, marginTop: '2px' }} />
              <div>{error}</div>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div className="form-group" style={{ marginBottom: '1.15rem' }}>
              <label className="form-label" htmlFor="modal-username" style={{ fontSize: '0.82rem', fontWeight: 600 }}>
                ชื่อผู้ใช้ / เลขทะเบียนสมาชิก / อีเมล
              </label>
              <div style={{ position: 'relative' }}>
                <User
                  size={16}
                  style={{
                    position: 'absolute',
                    left: '12px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: 'var(--text-muted)'
                  }}
                />
                <input
                  id="modal-username"
                  type="text"
                  className="form-control"
                  style={{ paddingLeft: '2.4rem', fontSize: '0.9rem' }}
                  placeholder="กรอกชื่อผู้ใช้ หรือเลขสมาชิก"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  autoComplete="username"
                  required
                />
              </div>
            </div>

            <div className="form-group" style={{ marginBottom: '1.35rem' }}>
              <label className="form-label" htmlFor="modal-password" style={{ fontSize: '0.82rem', fontWeight: 600 }}>
                รหัสผ่าน (Password)
              </label>
              <div style={{ position: 'relative' }}>
                <KeyRound
                  size={16}
                  style={{
                    position: 'absolute',
                    left: '12px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: 'var(--text-muted)'
                  }}
                />
                <input
                  id="modal-password"
                  type={showPassword ? 'text' : 'password'}
                  className="form-control"
                  style={{ paddingLeft: '2.4rem', paddingRight: '2.4rem', fontSize: '0.9rem' }}
                  placeholder="กรอกรหัสผ่าน"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  autoComplete="current-password"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
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
                  aria-label={showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'}
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              className="btn btn-primary"
              style={{ width: '100%', padding: '0.75rem', fontSize: '0.95rem', fontWeight: 700 }}
              disabled={loading}
            >
              {loading ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ'}
            </button>
          </form>

          <div
            style={{
              marginTop: '1.25rem',
              textAlign: 'center',
              fontSize: '0.75rem',
              color: 'var(--text-muted)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '0.35rem'
            }}
          >
            <Lock size={12} style={{ color: 'var(--accent-emerald-dark)' }} />
            <span>ระบบเชื่อมต่อฐานข้อมูลจริง ปลอดภัยและได้มาตรฐาน</span>
          </div>

        </div>
      </div>
    </div>
  );
}
