import React, { useEffect, useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  ShieldCheck,
  Lock,
  AlertTriangle,
  LogOut,
  ArrowRight,
  ExternalLink,
  Building2,
  FileText,
  LoaderCircle,
  Phone,
  Mail
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { COOP_INFO } from '../data/mockData';
import './MemberPrivacyAckPage.css';

export default function MemberPrivacyAckPage() {
  const { user, sessionStatus, logout, refreshSession } = useAuth();
  const navigate = useNavigate();

  const [policyData, setPolicyData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [accepted, setAccepted] = useState(false);
  const [error, setError] = useState('');

  // Fetch active policy and session acknowledgement status
  const fetchPolicyStatus = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await fetch('/api/member/pdpa/status', {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        cache: 'no-store',
      });

      if (response.status === 401) {
        navigate('/member/login', { replace: true, state: { sessionRequired: true } });
        return;
      }

      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลประกาศความเป็นส่วนตัวได้');
      }

      setPolicyData(data);

      // If already acknowledged for this session, navigate directly to member portal
      if (data.acknowledged) {
        navigate('/member/dividends', { replace: true });
      }
    } catch (err) {
      setError(err.message || 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง');
    } finally {
      setLoading(false);
    }
  }, [navigate]);

  useEffect(() => {
    // If not authenticated, redirect to login
    if (sessionStatus !== 'checking' && (!user || sessionStatus === 'guest' || sessionStatus === 'expired')) {
      navigate('/member/login', { replace: true });
      return;
    }

    // Staff/admins should not be on member PDPA gate
    if (user && user.role !== 'member') {
      navigate(user.role === 'super_admin' ? '/admin/dashboard' : '/admin/workspace', { replace: true });
      return;
    }

    fetchPolicyStatus();
  }, [sessionStatus, user, navigate, fetchPolicyStatus]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!accepted || submitting || !policyData) return;

    setSubmitting(true);
    setError('');

    try {
      // 1. Fetch CSRF token
      const csrfRes = await fetch('/csrf-token', {
        headers: { Accept: 'application/json' },
        credentials: 'include',
      });
      const csrfData = csrfRes.ok ? await csrfRes.json() : {};

      // 2. Submit explicit acknowledgement
      const formData = new FormData();
      formData.append('acknowledgement', '1');
      formData.append('policy_version', policyData.current_policy_version || '3.0');
      formData.append('policy_hash', policyData.policy_hash || '');
      formData.append('_csrf_token', csrfData.token || '');

      const response = await fetch('/api/member/pdpa/acknowledge', {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(csrfData.token ? { 'X-CSRF-TOKEN': csrfData.token } : {}),
        },
        credentials: 'include',
        cache: 'no-store',
      });

      const result = await response.json();

      // If policy was updated between GET and POST (conflict)
      if (response.status === 409 || result.code === 'POLICY_VERSION_MISMATCH') {
        setError(result.message || 'นโยบายความเป็นส่วนตัวมีการปรับปรุง กรุณาตรวจสอบและรับทราบนโยบายฉบับล่าสุด');
        setAccepted(false);
        await fetchPolicyStatus();
        return;
      }

      if (!response.ok || !result.success) {
        throw new Error(result.message || 'ไม่สามารถบันทึกการรับทราบได้ กรุณาลองใหม่อีกครั้ง');
      }

      // Success: update auth context session and proceed
      try {
        await refreshSession();
      } catch (e) {}

      navigate('/member/dividends', { replace: true });
    } catch (err) {
      setError(err.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง');
    } finally {
      setSubmitting(false);
    }
  };

  const handleLogout = async () => {
    try {
      await logout();
    } catch (e) {}
    navigate('/member/login', { replace: true });
  };

  if (loading) {
    return (
      <main className="member-privacy-ack-page">
        <div className="member-privacy-ack-shell" style={{ padding: '3rem', textAlign: 'center' }}>
          <LoaderCircle className="member-entry-spin" size={36} style={{ margin: '0 auto 1rem', color: 'var(--primary-600, #2563eb)' }} />
          <p style={{ color: 'var(--text-muted, #64748b)' }}>กำลังตรวจสอบสถานะประกาศความเป็นส่วนตัว...</p>
        </div>
      </main>
    );
  }

  return (
    <main className="member-privacy-ack-page">
      <div className="member-privacy-ack-shell">
        <header className="member-privacy-ack-header">
          <div className="member-privacy-ack-badge">
            <ShieldCheck size={16} /> ระบบความปลอดภัยและการคุ้มครองข้อมูลสมาชิก
          </div>
          <h1 className="member-privacy-ack-title">
            {policyData?.policy_title || 'ประกาศความเป็นส่วนตัวสำหรับสมาชิก'}
          </h1>
          <p className="member-privacy-ack-subtitle">
            สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
          </p>
          <div className="member-privacy-ack-meta">
            <span>เวอร์ชัน {policyData?.current_policy_version || '3.0'}</span>
            <span>•</span>
            <span>มีผลบังคับใช้ทุกรอบการเข้าสู่ระบบ</span>
          </div>
        </header>

        <div className="member-privacy-ack-content">
          <div className="member-privacy-ack-highlight">
            <Lock size={22} style={{ flexShrink: 0, marginTop: '2px' }} />
            <div>
              <strong>มาตรการความปลอดภัยและข้อมูลส่วนบุคคลของคุณ</strong>
              <p style={{ margin: '0.25rem 0 0', lineHeight: 1.6 }}>
                สหกรณ์จัดให้มีมาตรการความปลอดภัยทางเทคนิคและการบริหารจัดการ เพื่อปกป้องข้อมูลทางการเงินและข้อมูลสมาชิกภาพของท่านอย่างเคร่งครัด
              </p>
            </div>
          </div>

          <section aria-label="เนื้อหาประกาศความเป็นส่วนตัว">
            <div className="member-privacy-ack-scrollbox" tabIndex={0} role="region" aria-label="ประกาศความเป็นส่วนตัวฉบับเต็ม">
              {policyData?.policy_content}
            </div>
          </section>

          <div className="member-privacy-ack-link-box">
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <FileText size={18} color="var(--primary-700, #1d4ed8)" />
              <span>อ่านนโยบายคุ้มครองข้อมูลส่วนบุคคล (PDPA) ฉบับเต็มของสหกรณ์</span>
            </div>
            <Link to="/privacy/policy" target="_blank" rel="noopener noreferrer" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem', color: 'var(--primary-600, #2563eb)', fontWeight: 600 }}>
              เปิดเอกสาร <ExternalLink size={14} />
            </Link>
          </div>

          <div style={{ background: '#f8fafc', padding: '1rem', borderRadius: '8px', fontSize: '0.85rem', color: '#64748b', marginBottom: '1.5rem', display: 'flex', flexWrap: 'wrap', gap: '1.5rem', alignItems: 'center' }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
              <Building2 size={15} /> สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
            </span>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
              <Phone size={15} /> {COOP_INFO.phone}
            </span>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
              <Mail size={15} /> dpo@rayongcoop.org
            </span>
          </div>

          {error && (
            <div role="alert" className="member-privacy-ack-error">
              <AlertTriangle size={18} />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="member-privacy-ack-form">
            <label className="member-privacy-ack-checkbox-label">
              <input
                type="checkbox"
                className="member-privacy-ack-checkbox"
                checked={accepted}
                onChange={(e) => setAccepted(e.target.checked)}
                disabled={submitting}
                id="pdpa-acknowledge-checkbox"
              />
              <span>
                <strong>ข้าพเจ้าได้อ่านและรับทราบประกาศความเป็นส่วนตัวและคำเตือนการใช้งานระบบ</strong>
              </span>
            </label>

            <div className="member-privacy-ack-actions">
              <button
                type="button"
                className="btn btn-outline"
                onClick={handleLogout}
                disabled={submitting}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}
              >
                <LogOut size={16} /> ยกเลิกและออกจากระบบ
              </button>

              <button
                type="submit"
                className="btn btn-primary"
                disabled={!accepted || submitting}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}
              >
                {submitting ? (
                  <>
                    <LoaderCircle className="member-entry-spin" size={16} /> กำลังบันทึก...
                  </>
                ) : (
                  <>
                    <ShieldCheck size={16} /> ยืนยันรับทราบและเข้าสู่ระบบ <ArrowRight size={16} />
                  </>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </main>
  );
}
