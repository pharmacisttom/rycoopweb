import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { User, KeyRound, ShieldAlert, ArrowLeft, Eye, EyeOff, Lock, CheckCircle2 } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { COOP_INFO } from '../data/mockData';

export default function LoginPage() {
  const { login } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

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
        if (result.user?.role === 'super_admin') {
          navigate('/admin/dashboard');
        } else if (result.user?.role === 'staff') {
          navigate('/staff/dashboard');
        } else {
          setError('บัญชีนี้ไม่ได้รับอนุญาตให้ใช้งานระบบเจ้าหน้าที่');
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
    <div className="section" style={{ minHeight: '80vh', display: 'flex', alignItems: 'center', background: 'var(--bg-main)' }}>
      <div className="container" style={{ maxWidth: '460px' }}>

        <Link
          to="/"
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '0.4rem',
            color: 'var(--text-muted)',
            fontSize: '0.85rem',
            marginBottom: '1.5rem',
            textDecoration: 'none'
          }}
        >
          <ArrowLeft size={16} />
          <span>กลับสู่หน้าหลัก</span>
        </Link>

        <div
          className="surface-card shadow-lg"
          style={{
            padding: '2.5rem 2rem',
            borderRadius: 'var(--radius-xl)',
            border: '1px solid var(--border-subtle)'
          }}
        >
          {/* Header */}
          <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
            <div
              style={{
                width: '56px',
                height: '56px',
                margin: '0 auto 1rem auto',
                background: 'var(--bg-surface)',
                borderRadius: '50%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                boxShadow: 'var(--shadow-md)',
                border: '1px solid var(--border-subtle)'
              }}
            >
              <img
                src="/assets/img/logo.webp"
                alt="Logo"
                style={{ width: '40px', height: '40px', objectFit: 'contain' }}
                onError={(e) => { e.target.src = '/img/logo.webp'; }}
              />
            </div>
            <h2 style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--primary-900)', marginBottom: '0.25rem' }}>
              เข้าสู่ระบบสำหรับเจ้าหน้าที่
            </h2>
            <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: 0 }}>
              {COOP_INFO.nameTh}
            </p>
          </div>

          {/* Error Alert */}
          {error && (
            <div
              role="alert"
              style={{
                background: 'var(--accent-rose-light)',
                color: 'var(--accent-rose)',
                padding: '0.85rem 1rem',
                borderRadius: 'var(--radius-md)',
                fontSize: '0.85rem',
                marginBottom: '1.5rem',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '0.5rem',
                border: '1px solid rgba(225, 29, 72, 0.2)'
              }}
            >
              <ShieldAlert size={18} style={{ flexShrink: 0, marginTop: '2px' }} />
              <div>{error}</div>
            </div>
          )}

          {/* Login Form */}
          <form onSubmit={handleSubmit}>
            <div className="form-group" style={{ marginBottom: '1.25rem' }}>
              <label className="form-label" htmlFor="login-username" style={{ fontSize: '0.85rem', fontWeight: 600 }}>
                ชื่อผู้ใช้งาน หรืออีเมลเจ้าหน้าที่
              </label>
              <div style={{ position: 'relative' }}>
                <User
                  size={18}
                  style={{
                    position: 'absolute',
                    left: '12px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: 'var(--text-muted)'
                  }}
                />
                <input
                  id="login-username"
                  type="text"
                  className="form-control"
                  style={{ paddingLeft: '2.5rem', fontSize: '0.95rem' }}
                  placeholder="กรอกชื่อผู้ใช้ หรือเลขทะเบียนสมาชิก"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  autoComplete="username"
                  required
                />
              </div>
            </div>

            <div className="form-group" style={{ marginBottom: '1.5rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.35rem' }}>
                <label className="form-label" htmlFor="login-password" style={{ fontSize: '0.85rem', fontWeight: 600, margin: 0 }}>
                  รหัสผ่าน (Password)
                </label>
              </div>
              <div style={{ position: 'relative' }}>
                <KeyRound
                  size={18}
                  style={{
                    position: 'absolute',
                    left: '12px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: 'var(--text-muted)'
                  }}
                />
                <input
                  id="login-password"
                  type={showPassword ? 'text' : 'password'}
                  className="form-control"
                  style={{ paddingLeft: '2.5rem', paddingRight: '2.5rem', fontSize: '0.95rem' }}
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
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              className="btn btn-primary"
              style={{ width: '100%', padding: '0.85rem', fontSize: '0.95rem', fontWeight: 700 }}
              disabled={loading}
            >
              {loading ? 'กำลังตรวจสอบข้อมูล...' : 'เข้าสู่ระบบ'}
            </button>
          </form>

          {/* Security & System Info Footer */}
          <div
            style={{
              marginTop: '1.75rem',
              paddingTop: '1.25rem',
              borderTop: '1px solid var(--border-subtle)',
              textAlign: 'center',
              fontSize: '0.78rem',
              color: 'var(--text-muted)'
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.35rem', marginBottom: '0.25rem' }}>
              <Lock size={13} style={{ color: 'var(--accent-emerald-dark)' }} />
              <span>ระบบความปลอดภัยเข้ารหัสข้อมูล SSL TLS 1.3</span>
            </div>
            <div>หากลืมรหัสผ่านหรือต้องการสมัครสมาชิก กรุณาติดต่อเจ้าหน้าที่สหกรณ์</div>
          </div>

        </div>
      </div>
    </div>
  );
}
