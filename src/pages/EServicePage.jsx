import React, { useState, useEffect } from 'react';
import {
  Laptop, FileCheck, Coins, FileText, HeartHandshake,
  MapPin, CheckCircle2, ArrowRight, ShieldCheck, Sparkles, Send,
  ExternalLink, AlertTriangle, Globe, Lock, Info
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';

const CATEGORY_NAMES = {
  all: 'ทั้งหมด',
  member: 'ข้อมูลสมาชิก',
  deposit: 'เงินฝาก',
  loan: 'สินเชื่อและเงินกู้',
  external: 'สมาคมฌาปนกิจและพันธมิตร',
  general: 'บริการทั่วไป',
};

function getServiceIcon(iconName) {
  switch (iconName) {
    case 'bi-person-badge':
    case 'user':
      return Laptop;
    case 'bi-wallet2':
    case 'wallet':
      return Coins;
    case 'bi-cash-coin':
    case 'cash':
      return FileCheck;
    case 'bi-shield-check':
    case 'shield':
      return ShieldCheck;
    case 'bi-heart-pulse':
    case 'heart':
      return HeartHandshake;
    default:
      return Globe;
  }
}

export default function EServicePage() {
  const { user } = useAuth();
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [confirmRedirectService, setConfirmRedirectService] = useState(null);
  const [maintenanceService, setMaintenanceService] = useState(null);

  useEffect(() => {
    let cancelled = false;
    fetch('/api/public/eservices')
      .then((res) => res.json())
      .then((resData) => {
        if (!cancelled && resData?.success && Array.isArray(resData.data)) {
          setServices(resData.data);
        }
      })
      .catch(() => {})
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  const categories = ['all', ...Array.from(new Set(services.map((s) => s.category).filter(Boolean)))];

  const filteredServices = services.filter((s) => {
    if (selectedCategory === 'all') return true;
    return s.category === selectedCategory;
  });

  const handleServiceClick = (srv) => {
    if (srv.is_maintenance) {
      setMaintenanceService(srv);
      return;
    }

    if (srv.confirm_before_redirect) {
      setConfirmRedirectService(srv);
      return;
    }

    if (srv.url) {
      window.open(srv.url, srv.open_new_tab ? '_blank' : '_self', 'noopener,noreferrer');
    }
  };

  const handleConfirmRedirect = () => {
    if (confirmRedirectService?.url) {
      window.open(
        confirmRedirectService.url,
        confirmRedirectService.open_new_tab ? '_blank' : '_self',
        'noopener,noreferrer'
      );
    }
    setConfirmRedirectService(null);
  };

  return (
    <div className="section">
      <div className="container">
        <div className="section-title-wrap">
          <span className="section-badge">บริการดิจิทัล 24 ชั่วโมง</span>
          <h1 className="section-title">ศูนย์บริการสมาชิกออนไลน์ (e-Services)</h1>
          <p className="section-subtitle">ทำธุรกรรม ตรวจสอบข้อมูล และเข้าถึงบริการดิจิทัลของสหกรณ์ได้ทุกที่ทุกเวลา</p>
          <div className="section-line" />
        </div>

        {/* Category Filters */}
        {categories.length > 1 && (
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', justifyContent: 'center', marginBottom: '2.5rem' }}>
            {categories.map((cat) => (
              <button
                key={cat}
                type="button"
                onClick={() => setSelectedCategory(cat)}
                className={`btn btn-sm ${selectedCategory === cat ? 'btn-primary' : 'btn-outline'}`}
                style={{ borderRadius: '9999px', padding: '0.4rem 1.1rem', fontSize: '0.85rem' }}
              >
                {CATEGORY_NAMES[cat] || cat}
              </button>
            ))}
          </div>
        )}

        {/* Loading state */}
        {loading && (
          <div style={{ textAlign: 'center', padding: '3rem 0', color: 'var(--text-muted)' }}>
            กำลังโหลดรายการบริการอิเล็กทรอนิกส์...
          </div>
        )}

        {/* Empty state */}
        {!loading && filteredServices.length === 0 && (
          <div className="surface-card" style={{ padding: '3rem', textAlign: 'center', maxWidth: '500px', margin: '0 auto 3rem auto' }}>
            <Info size={40} style={{ color: 'var(--primary-600)', margin: '0 auto 1rem auto' }} />
            <h3 style={{ fontSize: '1.1rem', marginBottom: '0.5rem' }}>ไม่พบรายการบริการ</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>ขณะนี้ยังไม่มีรายการบริการอิเล็กทรอนิกส์ในหมวดหมู่นี้</p>
          </div>
        )}

        {/* Services Grid */}
        {!loading && filteredServices.length > 0 && (
          <div className="grid-3" style={{ marginBottom: '3rem' }}>
            {filteredServices.map((srv) => {
              const Icon = getServiceIcon(srv.icon);
              const isMaint = Boolean(srv.is_maintenance);

              return (
                <div
                  key={srv.id}
                  className="surface-card"
                  style={{
                    padding: '2rem',
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'space-between',
                    opacity: isMaint ? 0.75 : 1,
                    border: isMaint ? '1px dashed var(--border-subtle)' : undefined,
                  }}
                >
                  <div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.25rem' }}>
                      <div
                        style={{
                          width: '48px',
                          height: '48px',
                          borderRadius: '12px',
                          background: isMaint ? '#F3F4F6' : 'var(--primary-100)',
                          color: isMaint ? '#9CA3AF' : 'var(--primary-600)',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                        }}
                      >
                        <Icon size={24} />
                      </div>
                      {isMaint ? (
                        <span className="badge badge-warning" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
                          <AlertTriangle size={12} /> ปรับปรุงระบบ
                        </span>
                      ) : (
                        <span className="badge badge-primary">
                          {CATEGORY_NAMES[srv.category] || 'บริการออนไลน์'}
                        </span>
                      )}
                    </div>

                    <h3 style={{ fontSize: '1.15rem', color: 'var(--primary-800)', marginBottom: '0.4rem' }}>
                      {srv.name || srv.title}
                    </h3>
                    <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', lineHeight: 1.5, marginBottom: '1.5rem' }}>
                      {srv.description}
                    </p>
                  </div>

                  <div>
                    <button
                      type="button"
                      onClick={() => handleServiceClick(srv)}
                      className={`btn ${isMaint ? 'btn-subtle' : 'btn-outline'}`}
                      style={{ width: '100%' }}
                    >
                      <span>{isMaint ? 'แจ้งเตือนการปรับปรุง' : 'เข้าสู่ระบบบริการ'}</span>
                      {srv.open_new_tab ? <ExternalLink size={16} /> : <ArrowRight size={16} />}
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* External Redirect Confirmation Modal */}
        {confirmRedirectService && (
          <div
            style={{
              position: 'fixed',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              zIndex: 2000,
              background: 'rgba(15, 23, 42, 0.75)',
              backdropFilter: 'blur(8px)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '1rem',
            }}
          >
            <div
              className="glass-card animate-fade-in"
              style={{
                width: '100%',
                maxWidth: '480px',
                background: 'var(--bg-surface)',
                borderRadius: 'var(--radius-xl)',
                padding: '2rem',
                position: 'relative',
              }}
            >
              <div style={{ textAlign: 'center', marginBottom: '1.5rem' }}>
                <div
                  style={{
                    width: '56px',
                    height: '56px',
                    borderRadius: '50%',
                    background: 'var(--primary-100)',
                    color: 'var(--primary-600)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    margin: '0 auto 1rem auto',
                  }}
                >
                  <ShieldCheck size={32} />
                </div>
                <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-900)', marginBottom: '0.5rem' }}>
                  ไปยังบริการภายนอกที่ปลอดภัย
                </h3>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.5 }}>
                  คุณกำลังจะเปิดหน้าต่างใหม่เพื่อเข้าสู่บริการ
                  <br />
                  <strong style={{ color: 'var(--text-main)' }}>{confirmRedirectService.name}</strong>
                </p>
                <div
                  style={{
                    background: 'var(--bg-subtle, #F8FAFC)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: '8px',
                    padding: '0.6rem 0.8rem',
                    marginTop: '1rem',
                    fontSize: '0.8rem',
                    color: 'var(--text-muted)',
                    wordBreak: 'break-all',
                  }}
                >
                  {confirmRedirectService.url}
                </div>
              </div>

              <div style={{ display: 'flex', gap: '0.75rem' }}>
                <button
                  type="button"
                  onClick={() => setConfirmRedirectService(null)}
                  className="btn btn-subtle"
                  style={{ flex: 1 }}
                >
                  ยกเลิก
                </button>
                <button
                  type="button"
                  onClick={handleConfirmRedirect}
                  className="btn btn-primary"
                  style={{ flex: 1 }}
                >
                  <span>ยืนยันเข้าสู่บริการ</span>
                  <ExternalLink size={16} />
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Maintenance Notice Modal */}
        {maintenanceService && (
          <div
            style={{
              position: 'fixed',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              zIndex: 2000,
              background: 'rgba(15, 23, 42, 0.75)',
              backdropFilter: 'blur(8px)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '1rem',
            }}
          >
            <div
              className="glass-card animate-fade-in"
              style={{
                width: '100%',
                maxWidth: '460px',
                background: 'var(--bg-surface)',
                borderRadius: 'var(--radius-xl)',
                padding: '2rem',
                position: 'relative',
              }}
            >
              <div style={{ textAlign: 'center', marginBottom: '1.5rem' }}>
                <div
                  style={{
                    width: '56px',
                    height: '56px',
                    borderRadius: '50%',
                    background: '#FEF3C7',
                    color: '#D97706',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    margin: '0 auto 1rem auto',
                  }}
                >
                  <AlertTriangle size={32} />
                </div>
                <h3 style={{ fontSize: '1.2rem', color: '#92400E', marginBottom: '0.5rem' }}>
                  ระบบอยู่ระหว่างการปรับปรุงชั่วคราว
                </h3>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', lineHeight: 1.5 }}>
                  บริการ <strong style={{ color: 'var(--text-main)' }}>{maintenanceService.name}</strong> กำลังได้รับการบำรุงรักษาเพื่อเพิ่มประสิทธิภาพ ขออภัยในความไม่สะดวก
                </p>
              </div>

              <div style={{ display: 'flex', justifyContent: 'center' }}>
                <button
                  type="button"
                  onClick={() => setMaintenanceService(null)}
                  className="btn btn-primary"
                  style={{ width: '100%' }}
                >
                  รับทราบ
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
