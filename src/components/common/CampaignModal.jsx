import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { X, Sparkles, ArrowRight, EyeOff, ChevronRight } from 'lucide-react';
import SafeImage from './SafeImage';

export default function CampaignModal() {
  const location = useLocation();
  const [popups, setPopups] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(-1);
  const [isOpen, setIsOpen] = useState(false);
  const modalRef = useRef(null);
  const triggerElementRef = useRef(null);

  // Safety rule: Never interrupt active Login, PDPA, member authentication, or administrative workflows
  const isProtectedWorkflow = 
    location.pathname.startsWith('/admin') ||
    location.pathname.startsWith('/staff') ||
    location.pathname.startsWith('/member') ||
    location.pathname.startsWith('/dashboard') ||
    location.pathname.startsWith('/profile') ||
    location.pathname === '/login' ||
    location.pathname === '/service-unavailable';

  // Fetch active scheduled popups from API
  useEffect(() => {
    if (isProtectedWorkflow) return;

    // Check if user dismissed all popups for this browser session
    try {
      if (sessionStorage.getItem('coop_popups_dismiss_all') === 'true') {
        return;
      }
    } catch {
      // Storage unavailable
    }

    const controller = new AbortController();

    fetch('/api/public/popups', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      signal: controller.signal
    })
      .then((res) => {
        if (!res.ok) throw new Error('POPUP_UNAVAILABLE');
        return res.json();
      })
      .then((res) => {
        if (res.success && Array.isArray(res.data) && res.data.length > 0) {
          // Read dismissed popups for this session
          let dismissed = [];
          try {
            dismissed = JSON.parse(sessionStorage.getItem('coop_dismissed_popups') || '[]');
          } catch {
            dismissed = [];
          }

          // Filter out already dismissed popups
          const eligible = res.data.filter((p) => !dismissed.includes(p.id));
          if (eligible.length > 0) {
            setPopups(eligible);
            setCurrentIndex(0);

            // Gentle delay before opening
            const timer = setTimeout(() => {
              triggerElementRef.current = document.activeElement;
              setIsOpen(true);
            }, 1000);
            return () => clearTimeout(timer);
          }
        }
      })
      .catch(() => {
        // Safe fallback: show no popup on API error
      });

    return () => controller.abort();
  }, [isProtectedWorkflow]);

  // Current active popup item
  const currentPopup = popups[currentIndex] || null;

  // Dismiss current popup and proceed to next in sequence (if any)
  const handleDismissCurrent = useCallback(() => {
    if (!currentPopup) return;

    try {
      const dismissed = JSON.parse(sessionStorage.getItem('coop_dismissed_popups') || '[]');
      if (!dismissed.includes(currentPopup.id)) {
        dismissed.push(currentPopup.id);
        sessionStorage.setItem('coop_dismissed_popups', JSON.stringify(dismissed));
      }
    } catch {}

    if (currentIndex + 1 < popups.length) {
      // Advance to next popup sequentially
      setCurrentIndex((prev) => prev + 1);
    } else {
      // All popups completed
      setIsOpen(false);
      if (triggerElementRef.current && typeof triggerElementRef.current.focus === 'function') {
        triggerElementRef.current.focus();
      }
    }
  }, [currentPopup, currentIndex, popups.length]);

  // Dismiss all popups for current session
  const handleDismissAll = useCallback(() => {
    try {
      sessionStorage.setItem('coop_popups_dismiss_all', 'true');
    } catch {}
    setIsOpen(false);
    if (triggerElementRef.current && typeof triggerElementRef.current.focus === 'function') {
      triggerElementRef.current.focus();
    }
  }, []);

  // Keyboard navigation & accessibility (Escape key, Focus trap)
  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        handleDismissCurrent();
      } else if (e.key === 'Tab' && modalRef.current) {
        const focusables = modalRef.current.querySelectorAll(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    // Initial focus on modal
    const focusTimer = setTimeout(() => {
      if (modalRef.current) {
        const primaryBtn = modalRef.current.querySelector('.popup-primary-btn') || modalRef.current;
        primaryBtn?.focus();
      }
    }, 50);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      clearTimeout(focusTimer);
    };
  }, [isOpen, handleDismissCurrent]);

  if (!isOpen || !currentPopup || isProtectedWorkflow) {
    return null;
  }

  const layoutMode = currentPopup.layout_mode || 'banner_only';
  const hasNext = currentIndex + 1 < popups.length;
  const isExternal = currentPopup.button_url?.startsWith('http://') || currentPopup.button_url?.startsWith('https://');

  return (
    <div
      role="presentation"
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: 2500,
        background: 'rgba(15, 23, 42, 0.78)',
        backdropFilter: 'blur(8px)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '16px',
        animation: 'fadeIn 0.25s ease-out'
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget) handleDismissCurrent();
      }}
    >
      <div
        ref={modalRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="campaign-modal-title"
        aria-describedby="campaign-modal-desc"
        className="campaign-modal-card"
        style={{
          width: 'min(1000px, calc(100vw - 32px))',
          maxHeight: '90dvh',
          background: 'var(--bg-surface, #ffffff)',
          borderRadius: '1.25rem',
          overflow: 'hidden',
          boxShadow: '0 25px 50px -12px rgba(15, 23, 42, 0.35)',
          border: '1px solid rgba(226, 232, 240, 0.8)',
          position: 'relative',
          display: 'flex',
          flexDirection: 'column'
        }}
      >
        {/* Top Header Controls: Step indicator & Close button */}
        <div style={{
          position: 'absolute',
          top: '0.75rem',
          right: '0.75rem',
          zIndex: 20,
          display: 'flex',
          alignItems: 'center',
          gap: '0.5rem'
        }}>
          {popups.length > 1 && (
            <span style={{
              background: 'rgba(15, 23, 42, 0.7)',
              color: '#ffffff',
              fontSize: '0.75rem',
              fontWeight: 600,
              padding: '0.25rem 0.65rem',
              borderRadius: '9999px',
              backdropFilter: 'blur(4px)'
            }}>
              แคมเปญ {currentIndex + 1} จาก {popups.length}
            </span>
          )}

          <button
            onClick={handleDismissCurrent}
            style={{
              color: '#ffffff',
              background: 'rgba(15, 23, 42, 0.7)',
              border: 'none',
              padding: '0.4rem',
              borderRadius: '50%',
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              transition: 'background 0.2s',
              backdropFilter: 'blur(4px)'
            }}
            aria-label="ปิดหน้าต่างประชาสัมพันธ์"
          >
            <X size={20} />
          </button>
        </div>

        {/* Modal Scrollable Body */}
        <div style={{
          overflowY: 'auto',
          overflowX: 'hidden',
          flex: 1,
          display: 'flex',
          flexDirection: 'column'
        }}>
          {/* Main Layout Container (Desktop 2-columns / Mobile 1-column via CSS) */}
          <div className="popup-layout-grid" style={{
            display: 'grid',
            gridTemplateColumns: layoutMode === 'banner_only' ? '1fr' : 'repeat(auto-fit, minmax(280px, 1fr))',
            flex: 1
          }}>
            
            {/* Visual Media Area (Banner / Mascot) */}
            {layoutMode === 'banner_only' && (
              <div style={{
                position: 'relative',
                maxHeight: '340px',
                minHeight: '180px',
                background: 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)',
                overflow: 'hidden',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
              }}>
                <SafeImage
                  src={currentPopup.banner_image || '/assets/img/popup_dividend.jpg'}
                  alt={currentPopup.title}
                  style={{
                    width: '100%',
                    height: '100%',
                    objectFit: 'cover',
                    maxHeight: '340px'
                  }}
                />
              </div>
            )}

            {layoutMode === 'mascot_only' && (
              <div style={{
                background: 'linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%)',
                padding: '2rem 1.5rem',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                textAlign: 'center',
                borderRight: '1px solid #e2e8f0',
                minHeight: '260px'
              }}>
                <img
                  src={currentPopup.mascot_image || '/assets/img/logo.webp'}
                  alt={currentPopup.title || "สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด"}
                  style={{
                    width: 'min(180px, 60%)',
                    height: 'auto',
                    objectFit: 'contain',
                    filter: 'drop-shadow(0 4px 12px rgba(0, 0, 0, 0.1))'
                  }}
                />
                <span style={{
                  marginTop: '1rem',
                  fontSize: '0.85rem',
                  fontWeight: 700,
                  color: '#0369a1',
                  background: '#ffffff',
                  padding: '0.35rem 0.85rem',
                  borderRadius: '9999px',
                  boxShadow: '0 2px 6px rgba(0, 0, 0, 0.06)'
                }}>
                  สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
                </span>
              </div>
            )}

            {layoutMode === 'banner_and_mascot' && (
              <div style={{
                background: 'linear-gradient(135deg, #0369a1 0%, #0c4a6e 100%)',
                position: 'relative',
                overflow: 'hidden',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '2rem 1.5rem',
                minHeight: '260px'
              }}>
                {/* Background Banner Overlay if available */}
                {currentPopup.banner_image && (
                  <img
                    src={currentPopup.banner_image}
                    alt=""
                    aria-hidden="true"
                    style={{
                      position: 'absolute',
                      top: 0,
                      left: 0,
                      width: '100%',
                      height: '100%',
                      objectFit: 'cover',
                      opacity: 0.25,
                      filter: 'blur(2px)'
                    }}
                  />
                )}
                <img
                  src={currentPopup.mascot_image || '/assets/img/logo.webp'}
                  alt={currentPopup.title || "สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด"}
                  style={{
                    position: 'relative',
                    zIndex: 2,
                    width: 'min(180px, 60%)',
                    height: 'auto',
                    objectFit: 'contain',
                    filter: 'drop-shadow(0 8px 16px rgba(0, 0, 0, 0.25))'
                  }}
                />
                <span style={{
                  position: 'relative',
                  zIndex: 2,
                  marginTop: '1rem',
                  fontSize: '0.85rem',
                  fontWeight: 700,
                  color: '#ffffff',
                  background: 'rgba(15, 23, 42, 0.75)',
                  padding: '0.35rem 0.85rem',
                  borderRadius: '9999px',
                  backdropFilter: 'blur(4px)'
                }}>
                  น้องออมสุขชวนร่วมกิจกรรม
                </span>
              </div>
            )}

            {/* Content & Action Area (~65% width on desktop) */}
            <div style={{
              padding: 'clamp(1.5rem, 4vw, 2.5rem)',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between',
              background: '#ffffff'
            }}>
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.75rem' }}>
                  <span className="badge badge-gold" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
                    <Sparkles size={13} />
                    <span>ข่าวสารและสิทธิประโยชน์</span>
                  </span>
                  {currentPopup.priority === 1 && (
                    <span className="badge badge-primary" style={{ fontSize: '0.75rem' }}>
                      ประชาสัมพันธ์สำคัญ
                    </span>
                  )}
                </div>

                <h2
                  id="campaign-modal-title"
                  style={{
                    fontSize: 'clamp(1.25rem, 2.5vw, 1.65rem)',
                    fontWeight: 800,
                    color: 'var(--primary-900, #0f172a)',
                    lineHeight: 1.35,
                    marginBottom: '1rem'
                  }}
                >
                  {currentPopup.title}
                </h2>

                <div
                  id="campaign-modal-desc"
                  style={{
                    fontSize: '0.98rem',
                    color: 'var(--text-muted, #475569)',
                    lineHeight: 1.75,
                    marginBottom: '1.75rem',
                    whiteSpace: 'pre-line'
                  }}
                >
                  {currentPopup.subtitle}
                </div>
              </div>

              {/* Action Buttons Section */}
              <div style={{
                display: 'flex',
                flexDirection: 'column',
                gap: '0.75rem',
                borderTop: '1px solid #f1f5f9',
                paddingTop: '1.25rem'
              }}>
                <div style={{
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: '0.75rem',
                  alignItems: 'center'
                }}>
                  {/* Secondary Close / Next Button */}
                  <button
                    type="button"
                    onClick={handleDismissCurrent}
                    className="btn btn-outline"
                    style={{
                      flex: '1 1 120px',
                      padding: '0.65rem 1.25rem',
                      fontSize: '0.92rem',
                      fontWeight: 600,
                      borderRadius: '0.5rem'
                    }}
                  >
                    {hasNext ? (
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
                        <span>ถัดไป</span>
                        <ChevronRight size={16} />
                      </span>
                    ) : (
                      'ปิดหน้าต่าง'
                    )}
                  </button>

                  {/* Primary CTA Link */}
                  {isExternal ? (
                    <a
                      href={currentPopup.button_url || '#'}
                      target={currentPopup.button_target || '_blank'}
                      rel="noopener noreferrer"
                      onClick={handleDismissCurrent}
                      className="btn btn-primary popup-primary-btn"
                      style={{
                        flex: '2 1 180px',
                        padding: '0.65rem 1.5rem',
                        fontSize: '0.95rem',
                        fontWeight: 700,
                        borderRadius: '0.5rem',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '0.5rem'
                      }}
                    >
                      <span>{currentPopup.button_text || 'ดูรายละเอียด'}</span>
                      <ArrowRight size={16} />
                    </a>
                  ) : (
                    <Link
                      to={currentPopup.button_url || '/'}
                      onClick={handleDismissCurrent}
                      className="btn btn-primary popup-primary-btn"
                      style={{
                        flex: '2 1 180px',
                        padding: '0.65rem 1.5rem',
                        fontSize: '0.95rem',
                        fontWeight: 700,
                        borderRadius: '0.5rem',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '0.5rem'
                      }}
                    >
                      <span>{currentPopup.button_text || 'ดูรายละเอียด'}</span>
                      <ArrowRight size={16} />
                    </Link>
                  )}
                </div>

                {/* Dismiss all remaining popups option */}
                <button
                  type="button"
                  onClick={handleDismissAll}
                  style={{
                    background: 'none',
                    border: 'none',
                    color: '#64748b',
                    fontSize: '0.82rem',
                    cursor: 'pointer',
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '0.35rem',
                    padding: '0.35rem',
                    textDecoration: 'underline'
                  }}
                >
                  <EyeOff size={14} />
                  <span>ไม่ต้องแสดงหน้าต่างประชาสัมพันธ์อีกในเซสชันนี้</span>
                </button>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
