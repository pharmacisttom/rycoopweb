import { appUrl } from '../../utils/environment';
import React from 'react';
import { AlertTriangle, RefreshCw, Home, ShieldAlert } from 'lucide-react';

export default class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    // Log technical error safely without exposing user data
    console.error('Captured by Global ErrorBoundary:', error, errorInfo);
  }

  handleRetry = () => {
    this.setState({ hasError: false, error: null });
    window.location.reload();
  };

  handleGoHome = () => {
    this.setState({ hasError: false, error: null });
    window.location.href = appUrl('/');
  };

  render() {
    if (this.state.hasError) {
      return (
        <div
          role="alert"
          style={{
            minHeight: '80vh',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '1.5rem',
            background: 'var(--bg-main)'
          }}
        >
          <div
            className="surface-card shadow-2xl animate-fade-in"
            style={{
              maxWidth: '520px',
              width: '100%',
              borderRadius: 'var(--radius-xl)',
              padding: '2.5rem 2rem',
              textAlign: 'center',
              border: '1px solid var(--border-subtle)'
            }}
          >
            <div
              style={{
                width: '68px',
                height: '68px',
                margin: '0 auto 1.5rem auto',
                borderRadius: '50%',
                background: 'var(--accent-rose-light)',
                color: 'var(--accent-rose)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
              }}
            >
              <AlertTriangle size={36} />
            </div>

            <h2 style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--primary-900)', marginBottom: '0.6rem' }}>
              เกิดข้อผิดพลาดในการโหลดหน้าเว็บ
            </h2>

            <p style={{ color: 'var(--text-muted)', fontSize: '0.92rem', lineHeight: 1.6, marginBottom: '2rem' }}>
              ขออภัยในความไม่สะดวก ระบบกำลังดำเนินการกู้คืนสถานะ ท่านสามารถกดปุ่มลองใหม่อีกครั้ง หรือกลับสู่หน้าหลักเพื่อดำเนินการต่อ
            </p>

            <div style={{ display: 'flex', justifyContent: 'center', gap: '1rem', flexWrap: 'wrap' }}>
              <button
                type="button"
                onClick={this.handleRetry}
                className="btn btn-primary"
                style={{ minHeight: '44px', padding: '0.65rem 1.4rem', display: 'inline-flex', alignItems: 'center', gap: '0.5rem', fontWeight: 700 }}
              >
                <RefreshCw size={17} />
                <span>ลองใหม่อีกครั้ง</span>
              </button>

              <button
                type="button"
                onClick={this.handleGoHome}
                className="btn btn-subtle"
                style={{ minHeight: '44px', padding: '0.65rem 1.4rem', display: 'inline-flex', alignItems: 'center', gap: '0.5rem', fontWeight: 600 }}
              >
                <Home size={17} />
                <span>กลับสู่หน้าหลัก</span>
              </button>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
