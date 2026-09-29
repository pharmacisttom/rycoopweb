import React, { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { 
  Calculator, TrendingUp, ShieldCheck, Coins, Users, Building2, 
  ArrowRight, Landmark, FileText, HeartHandshake, PhoneCall, 
  ChevronLeft, ChevronRight, Sparkles, CheckCircle2, Award
} from 'lucide-react';
import { fetchHomeData, fetchCoopInfo } from '../services/api';
import LoanCalculator from '../components/calculators/LoanCalculator';
import DividendEstimator from '../components/calculators/DividendEstimator';
import heroPortrait from '../assets/hero-portrait-official.png';
import { normalizeNewsItem } from '../utils/news';
import { useAuth } from '../context/AuthContext';

// Fallback defaults when API is unreachable (development/offline mode)
const FALLBACK_COOP = {
  nameTh: 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
  slogan: 'มั่นคง โปร่งใส ใส่ใจบริการ',
};

export default function HomePage() {
  const { setShowAuthModal, isLoggedIn } = useAuth();
  const [activeRateTab, setActiveRateTab] = useState('deposits');
  const [activeNewsTab, setActiveNewsTab] = useState('news');
  const [activeNewsSlide, setActiveNewsSlide] = useState(0);
  const [isNewsSlideshowPaused, setIsNewsSlideshowPaused] = useState(false);
  const [isNewsSlideDragging, setIsNewsSlideDragging] = useState(false);
  const newsSlideDragStartRef = useRef(null);
  const newsSlideWasDraggedRef = useRef(false);
  const [loading, setLoading] = useState(true);

  // API-fetched data state
  const [coopInfo, setCoopInfo] = useState(FALLBACK_COOP);
  const [keyStats, setKeyStats] = useState([]);
  const [depositRates, setDepositRates] = useState([]);
  const [loanRates, setLoanRates] = useState([]);
  const [loanProducts, setLoanProducts] = useState([]);
  const [newsList, setNewsList] = useState([]);
  const [announcements, setAnnouncements] = useState([]);
  const [welfareItems, setWelfareItems] = useState([]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const [homeRes, coopRes] = await Promise.all([
          fetchHomeData(),
          fetchCoopInfo(),
        ]);
        if (cancelled) return;
        if (homeRes?.success && homeRes.data) {
          const d = homeRes.data;
          setDepositRates(d.depositRates || []);
          setLoanRates(d.loanRates || []);
          setLoanProducts(d.featuredLoans || []);
          setNewsList((d.latestNews || []).map(normalizeNewsItem));
          setAnnouncements(d.importantAnnouncements || []);
          setWelfareItems(d.heroSlides || []); // welfare comes from separate endpoint if needed

          // Build key stats from latestStats
          if (d.latestStats) {
            const s = d.latestStats;
            setKeyStats([
              { label: 'จำนวนสมาชิก', value: Number(s.total_members || 0).toLocaleString(), unit: 'คน', change: `ข้อมูล ${s.month}/${s.year}` },
              { label: 'สินทรัพย์รวม', value: (Number(s.total_assets || 0) / 1e6).toFixed(0), unit: 'ล้านบาท', change: 'มั่นคง' },
              { label: 'ทุนเรือนหุ้น', value: (Number(s.total_shares || 0) / 1e6).toFixed(0), unit: 'ล้านบาท', change: 'เติบโตต่อเนื่อง' },
              { label: 'เงินฝากรวม', value: (Number(s.total_deposits || 0) / 1e6).toFixed(0), unit: 'ล้านบาท', change: 'สูงสุดเป็นประวัติการณ์' },
              { label: 'เงินกู้คงเหลือ', value: (Number(s.total_loans || 0) / 1e6).toFixed(0), unit: 'ล้านบาท', change: 'คุณภาพดี' },
              { label: 'อัตราปันผล', value: s.dividend_rate || '-', unit: '% ต่อปี', change: `เฉลี่ยคืน ${s.loan_refund_rate || '-'}%` },
            ]);
          }
        }
        if (coopRes?.success && coopRes.data) {
          setCoopInfo({
            nameTh: coopRes.data.name_th || FALLBACK_COOP.nameTh,
            nameEn: coopRes.data.name_en || '',
            slogan: FALLBACK_COOP.slogan,
            phone: coopRes.data.phone || '',
            email: coopRes.data.email || '',
            address: coopRes.data.address || '',
          });
        }
      } catch (e) {
        console.warn('Failed to load homepage data:', e);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    setActiveNewsSlide((currentSlide) => Math.min(currentSlide, Math.max(newsList.length - 1, 0)));
  }, [newsList.length]);

  useEffect(() => {
    if (activeNewsTab !== 'news' || isNewsSlideshowPaused || newsList.length < 2) return undefined;

    const timer = window.setInterval(() => {
      setActiveNewsSlide((currentSlide) => (currentSlide + 1) % newsList.length);
    }, 5000);

    return () => window.clearInterval(timer);
  }, [activeNewsTab, isNewsSlideshowPaused, newsList.length]);

  const handleNewsSlidePointerDown = (event) => {
    if (newsList.length < 2 || (event.pointerType === 'mouse' && event.button !== 0)) return;

    newsSlideDragStartRef.current = { pointerId: event.pointerId, x: event.clientX };
    newsSlideWasDraggedRef.current = false;
    event.currentTarget.setPointerCapture?.(event.pointerId);
    setIsNewsSlideDragging(true);
    setIsNewsSlideshowPaused(true);
  };

  const handleNewsSlidePointerEnd = (event) => {
    const dragStart = newsSlideDragStartRef.current;
    if (!dragStart || dragStart.pointerId !== event.pointerId) return;

    const distance = event.clientX - dragStart.x;
    const swipeThreshold = 48;
    newsSlideDragStartRef.current = null;
    event.currentTarget.releasePointerCapture?.(event.pointerId);
    setIsNewsSlideDragging(false);
    setIsNewsSlideshowPaused(false);

    if (Math.abs(distance) < swipeThreshold) return;

    newsSlideWasDraggedRef.current = true;
    setActiveNewsSlide((currentSlide) => (
      distance < 0
        ? (currentSlide + 1) % newsList.length
        : (currentSlide - 1 + newsList.length) % newsList.length
    ));
  };

  const handleNewsSlidePointerCancel = () => {
    newsSlideDragStartRef.current = null;
    setIsNewsSlideDragging(false);
    setIsNewsSlideshowPaused(false);
  };

  const heroSettings = {
    title: coopInfo.nameTh,
    subtitle: `${coopInfo.slogan || FALLBACK_COOP.slogan} มอบความมั่นคงทางการเงิน ดอกเบี้ยเงินฝากคุ้มค่า สินเชื่ออัตราดอกเบี้ยเป็นธรรม พร้อมสวัสดิการดูแลตลอดทุกช่วงชีวิต`,
    badgeText: 'ยินดีต้อนรับสู่ระบบสหกรณ์ดิจิทัล',
    bgImageUrl: '/assets/img/hero_bg_coop.jpg'
  };

  return (
    <div>
      
      {/* =========================================================================
          HERO SECTION (High Impact Modern Banner)
          ========================================================================= */}
      <section style={{
        position: 'relative',
        backgroundImage: heroSettings.bgImageUrl ? `linear-gradient(135deg, rgba(15, 43, 92, 0.92) 0%, rgba(30, 64, 175, 0.88) 100%), url(${heroSettings.bgImageUrl})` : 'var(--gradient-hero)',
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        color: '#ffffff',
        padding: '4.5rem 0 4rem 0',
        overflow: 'hidden',
        borderBottom: '1px solid rgba(255,255,255,0.1)'
      }}>
        {/* Background glow & shapes */}
        <div style={{
          position: 'absolute',
          top: '-20%',
          right: '-10%',
          width: '500px',
          height: '500px',
          borderRadius: '50%',
          background: 'radial-gradient(circle, rgba(37, 99, 235, 0.4) 0%, rgba(37, 99, 235, 0) 70%)',
          filter: 'blur(50px)',
          pointerEvents: 'none'
        }} />
        <div style={{
          position: 'absolute',
          bottom: '-20%',
          left: '-5%',
          width: '400px',
          height: '400px',
          borderRadius: '50%',
          background: 'radial-gradient(circle, rgba(13, 148, 136, 0.3) 0%, rgba(13, 148, 136, 0) 70%)',
          filter: 'blur(50px)',
          pointerEvents: 'none'
        }} />

        <div className="container" style={{ position: 'relative', zIndex: 2 }}>
          <div className="home-hero-grid">
            
            {/* Left Hero Content */}
            <div className="animate-fade-in">
              <div style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.5rem',
                padding: '0.4rem 1rem',
                background: 'rgba(255, 255, 255, 0.12)',
                backdropFilter: 'blur(8px)',
                borderRadius: 'var(--radius-full)',
                fontSize: '0.85rem',
                fontWeight: 600,
                color: '#fbbf24',
                marginBottom: '1.25rem',
                border: '1px solid rgba(251, 191, 36, 0.3)'
              }}>
                <Sparkles size={16} />
                <span>{heroSettings.badgeText || 'ยินดีต้อนรับสู่ระบบสหกรณ์ดิจิทัล'}</span>
              </div>

              <h1 className="home-hero-title" style={{
                fontSize: 'clamp(1.8rem, 3vw, 3rem)',
                fontWeight: 800,
                lineHeight: 1.2,
                color: '#ffffff',
                marginBottom: '1rem',
                letterSpacing: '-0.02em',
                whiteSpace: 'nowrap'
              }}>
                {heroSettings.title || COOP_INFO.nameTh}
              </h1>

              <p style={{
                fontSize: '1.15rem',
                color: '#e2e8f0',
                lineHeight: 1.6,
                marginBottom: '2rem',
                maxWidth: '560px'
              }}>
                {heroSettings.subtitle || `${COOP_INFO.slogan} มอบความมั่นคงทางการเงิน ดอกเบี้ยเงินฝากคุ้มค่า สินเชื่ออัตราดอกเบี้ยเป็นธรรม พร้อมสวัสดิการดูแลตลอดทุกช่วงชีวิต`}
              </p>

              {/* Action Buttons */}
              <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
                <Link to="/service-unavailable" className="btn btn-gold btn-lg">
                  <span>ระบบสมาชิกออนไลน์อยู่ระหว่างดำเนินการ</span>
                  <ArrowRight size={18} />
                </Link>

                <Link to="/calculator" className="btn btn-outline btn-lg" style={{ color: '#ffffff', borderColor: 'rgba(255,255,255,0.4)', background: 'rgba(255,255,255,0.08)' }}>
                  <Calculator size={18} />
                  <span>คำนวณเงินกู้</span>
                </Link>
              </div>

              {/* Quick Trust Badges */}
              <div style={{ display: 'flex', gap: '1.5rem', marginTop: '2.5rem', paddingTop: '1.5rem', borderTop: '1px solid rgba(255,255,255,0.15)', flexWrap: 'wrap' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <ShieldCheck size={20} style={{ color: '#38bdf8' }} />
                  <span style={{ fontSize: '0.85rem', color: '#cbd5e1' }}>บริหารโปร่งใสตามหลักธรรมาภิบาล</span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <Award size={20} style={{ color: '#fbbf24' }} />
                  <span style={{ fontSize: '0.85rem', color: '#cbd5e1' }}>ปันผลปีล่าสุด {depositRates.length > 0 ? `${depositRates[0].rate}%` : '-'}</span>
                </div>
              </div>

            </div>

            <div className="hero-dashboard-row">
            {/* Right Hero Card: Quick Rates & Services Widget */}
            <div className="glass-card hero-rate-card" style={{
              background: 'rgba(15, 23, 42, 0.65)',
              border: '1px solid rgba(255,255,255,0.15)',
              padding: '2rem',
              borderRadius: 'var(--radius-xl)',
              color: '#ffffff'
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.25rem' }}>
                <h3 style={{ fontSize: '1.15rem', color: '#ffffff', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <TrendingUp style={{ color: '#fbbf24' }} size={20} />
                  <span>อัตราดอกเบี้ยเด่นวันนี้</span>
                </h3>
                <span style={{ fontSize: '0.75rem', color: '#94a3b8' }}>อัปเดตล่าสุด</span>
              </div>

              {/* Rate Items */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem', marginBottom: '1.5rem' }}>
                
                <div style={{ background: 'rgba(255,255,255,0.08)', padding: '0.85rem 1rem', borderRadius: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div>
                    <div style={{ fontSize: '0.9rem', fontWeight: 600 }}>เงินฝากออมทรัพย์พิเศษพลัส</div>
                    <div style={{ fontSize: '0.75rem', color: '#94a3b8' }}>จ่ายดอกเบี้ยรายเดือน</div>
                  </div>
                  <div style={{ fontSize: '1.35rem', fontWeight: 800, color: '#38bdf8', fontFamily: 'var(--font-display)' }}>
                    2.50% <span style={{ fontSize: '0.75rem', color: '#cbd5e1' }}>ต่อปี</span>
                  </div>
                </div>

                <div style={{ background: 'rgba(255,255,255,0.08)', padding: '0.85rem 1rem', borderRadius: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div>
                    <div style={{ fontSize: '0.9rem', fontWeight: 600 }}>เงินฝากประจำ 24 เดือน</div>
                    <div style={{ fontSize: '0.75rem', color: '#94a3b8' }}>เกษียณเกษม สบายใจ</div>
                  </div>
                  <div style={{ fontSize: '1.35rem', fontWeight: 800, color: '#fbbf24', fontFamily: 'var(--font-display)' }}>
                    3.10% <span style={{ fontSize: '0.75rem', color: '#cbd5e1' }}>ต่อปี</span>
                  </div>
                </div>

                <div style={{ background: 'rgba(255,255,255,0.08)', padding: '0.85rem 1rem', borderRadius: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div>
                    <div style={{ fontSize: '0.9rem', fontWeight: 600 }}>เงินกู้สามัญ</div>
                    <div style={{ fontSize: '0.75rem', color: '#94a3b8' }}>ผ่อนสูงสุด 180 งวด</div>
                  </div>
                  <div style={{ fontSize: '1.35rem', fontWeight: 800, color: '#4ade80', fontFamily: 'var(--font-display)' }}>
                    6.15% <span style={{ fontSize: '0.75rem', color: '#cbd5e1' }}>ต่อปี</span>
                  </div>
                </div>

              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                <Link to="/deposits" className="btn btn-sm" style={{ background: 'rgba(56, 189, 248, 0.2)', color: '#38bdf8', border: '1px solid rgba(56, 189, 248, 0.4)' }}>
                  <span>ดูดอกเบี้ยเงินฝาก</span>
                </Link>
                <Link to="/loans" className="btn btn-sm" style={{ background: 'rgba(74, 222, 128, 0.2)', color: '#4ade80', border: '1px solid rgba(74, 222, 128, 0.4)' }}>
                  <span>ดูดอกเบี้ยเงินกู้</span>
                </Link>
              </div>

            </div>

            <section className="glass-card hero-news-card" aria-labelledby="hero-news-heading">
              <div className="hero-news-header">
                <div>
                  <h3 id="hero-news-heading">
                    <FileText aria-hidden="true" size={20} />
                    <span>ข่าวสารและประกาศ</span>
                  </h3>
                  <span>อัปเดตล่าสุดจากสหกรณ์</span>
                </div>
                <Link to="/news" className="hero-news-all">
                  <span>ทั้งหมด</span>
                  <ChevronRight aria-hidden="true" size={16} />
                </Link>
              </div>

              <div className="hero-news-tabs" aria-label="ประเภทข่าวสาร">
                <button
                  type="button"
                  aria-pressed={activeNewsTab === 'news'}
                  className={activeNewsTab === 'news' ? 'is-active' : ''}
                  onClick={() => {
                    setActiveNewsTab('news');
                    setActiveNewsSlide(0);
                  }}
                >
                  ข่าวประชาสัมพันธ์
                </button>
                <button
                  type="button"
                  aria-pressed={activeNewsTab === 'announcements'}
                  className={activeNewsTab === 'announcements' ? 'is-active' : ''}
                  onClick={() => setActiveNewsTab('announcements')}
                >
                  ประกาศทางการ
                </button>
              </div>

              <div className="hero-news-list">
                {activeNewsTab === 'news' ? (
                  newsList.length > 0 && (
                    <div
                      className={`hero-news-slideshow${isNewsSlideDragging ? ' is-dragging' : ''}`}
                      onMouseEnter={() => setIsNewsSlideshowPaused(true)}
                      onMouseLeave={() => setIsNewsSlideshowPaused(false)}
                      onFocusCapture={() => setIsNewsSlideshowPaused(true)}
                      onBlurCapture={(event) => {
                        if (!event.currentTarget.contains(event.relatedTarget)) {
                          setIsNewsSlideshowPaused(false);
                        }
                      }}
                      onPointerDown={handleNewsSlidePointerDown}
                      onPointerUp={handleNewsSlidePointerEnd}
                      onPointerCancel={handleNewsSlidePointerCancel}
                    >
                      {(() => {
                        const item = newsList[activeNewsSlide];
                        return (
                          <article className="hero-news-slide" key={item.id}>
                            <img src={item.image} alt="" />
                            <div className="hero-news-slide-overlay">
                              <span className="hero-news-meta">{item.category || 'ข่าวสาร'} · {item.date}</span>
                              <h4>{item.title}</h4>
                              <Link
                                to="/news"
                                className="hero-news-read-more"
                                onClick={(event) => {
                                  if (newsSlideWasDraggedRef.current) {
                                    event.preventDefault();
                                    newsSlideWasDraggedRef.current = false;
                                  }
                                }}
                              >
                                อ่านข่าวฉบับเต็ม <ChevronRight aria-hidden="true" size={15} />
                              </Link>
                            </div>
                          </article>
                        );
                      })()}

                      {newsList.length > 1 && (
                        <>
                          <div className="hero-news-slide-actions">
                            <button type="button" onClick={() => setActiveNewsSlide((activeNewsSlide - 1 + newsList.length) % newsList.length)} aria-label="ข่าวก่อนหน้า">
                              <ChevronLeft aria-hidden="true" size={18} />
                            </button>
                            <button type="button" onClick={() => setActiveNewsSlide((activeNewsSlide + 1) % newsList.length)} aria-label="ข่าวถัดไป">
                              <ChevronRight aria-hidden="true" size={18} />
                            </button>
                          </div>
                          <div className="hero-news-slide-dots" aria-label="เลือกข่าวที่ต้องการดู">
                            {newsList.map((item, index) => (
                              <button
                                type="button"
                                key={item.id}
                                className={index === activeNewsSlide ? 'is-active' : ''}
                                aria-label={`แสดงข่าว ${index + 1}: ${item.title}`}
                                aria-pressed={index === activeNewsSlide}
                                onClick={() => setActiveNewsSlide(index)}
                              />
                            ))}
                          </div>
                        </>
                      )}
                    </div>
                  )
                ) : (
                  announcements.slice(0, 3).map((item) => (
                    <Link key={item.id} to="/news" className="hero-news-item">
                      <span className="hero-news-meta">{item.important ? 'ประกาศสำคัญ' : 'ประกาศ'} · {item.date}</span>
                      <strong>{item.title}</strong>
                      <ChevronRight aria-hidden="true" size={16} />
                    </Link>
                  ))
                )}
                {((activeNewsTab === 'news' && newsList.length === 0) || (activeNewsTab === 'announcements' && announcements.length === 0)) && (
                  <p className="hero-news-empty">ยังไม่มีรายการในขณะนี้</p>
                )}
              </div>
            </section>
            </div>

            <figure className="hero-portrait">
              <div className="hero-portrait-frame">
                <img
                  src={heroPortrait}
                  alt="นางสาวอุษา อิศรางกูร ณ อยุธยา ประธานกรรมการสหกรณ์"
                  width="294"
                  height="394"
                  fetchPriority="high"
                />
              </div>
              <figcaption className="hero-portrait-caption">
                <strong>นางสาวอุษา อิศรางกูร ณ อยุธยา</strong>
                <span>ประธานกรรมการสหกรณ์</span>
              </figcaption>
            </figure>

          </div>
        </div>
      </section>

      {/* =========================================================================
          QUICK SERVICES GRID (บริการดิจิทัลด่วน - Proper Spacing & Padding)
          ========================================================================= */}
      <section style={{ padding: '3.5rem 0 2.5rem 0', background: 'var(--bg-main)' }}>
        <div className="container">
          <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
            gap: '1.25rem'
          }}>
            
            <Link to="/calculator" className="surface-card" style={quickServiceCardStyle}>
              <div style={{ ...quickServiceIconWrap, background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                <Calculator size={24} />
              </div>
              <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.25rem' }}>คำนวณเงินกู้</h4>
              <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', lineHeight: 1.4 }}>จำลองค่างวดและดอกเบี้ย</p>
            </Link>

            <Link to="/dividend-estimator" className="surface-card" style={quickServiceCardStyle}>
              <div style={{ ...quickServiceIconWrap, background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)' }}>
                <TrendingUp size={24} />
              </div>
              <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.25rem' }}>ประมาณการปันผล</h4>
              <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', lineHeight: 1.4 }}>ปันผลหุ้น & เฉลี่ยคืน</p>
            </Link>

            <Link to="/verify-receipt" className="surface-card" style={quickServiceCardStyle}>
              <div style={{ ...quickServiceIconWrap, background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
                <FileText size={24} />
              </div>
              <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.25rem' }}>e-Receipt</h4>
              <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', lineHeight: 1.4 }}>ตรวจสอบใบเสร็จออนไลน์</p>
            </Link>

            <Link to="/loan-checklist" className="surface-card" style={quickServiceCardStyle}>
              <div style={{ ...quickServiceIconWrap, background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)' }}>
                <CheckCircle2 size={24} />
              </div>
              <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.25rem' }}>เช็คความพร้อมกู้</h4>
              <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', lineHeight: 1.4 }}>ตรวจเอกสารและสิทธิ</p>
            </Link>

            <Link to="/welfare" className="surface-card" style={quickServiceCardStyle}>
              <div style={{ ...quickServiceIconWrap, background: 'var(--accent-rose-light)', color: 'var(--accent-rose)' }}>
                <HeartHandshake size={24} />
              </div>
              <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.25rem' }}>สวัสดิการสมาชิก</h4>
              <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', lineHeight: 1.4 }}>ทุนการศึกษา & ช่วยเหลือ</p>
            </Link>

            <Link to="/service-unavailable" className="surface-card" style={quickServiceCardStyle}>
              <div style={{ ...quickServiceIconWrap, background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                <Coins size={24} />
              </div>
              <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.25rem' }}>ระบบสมาชิกออนไลน์อยู่ระหว่างดำเนินการ</h4>
              <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', lineHeight: 1.4 }}>ยังไม่เปิดให้บริการในระยะนี้</p>
            </Link>

          </div>
        </div>
      </section>

      {/* =========================================================================
          KEY COOPERATIVE STATS
          ========================================================================= */}
      <section className="section" style={{ background: 'var(--bg-surface)' }}>
        <div className="container">
          
          <div className="section-title-wrap">
            <span className="section-badge">ฐานะความมั่นคง</span>
            <h2 className="section-title">สถิติและผลการดำเนินงานที่โดดเด่น</h2>
            <p className="section-subtitle">ข้อมูลฐานะทางการเงินและความมั่นคงของสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด</p>
            <div className="section-line" />
          </div>

          <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
            gap: '1.5rem'
          }}>
            {keyStats.map((stat, idx) => (
              <div key={idx} className="surface-card" style={{ padding: '1.75rem 1.5rem', textAlign: 'center' }}>
                <div style={{ fontSize: '0.9rem', color: 'var(--text-muted)', marginBottom: '0.5rem', fontWeight: 600 }}>
                  {stat.label}
                </div>
                <div style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--primary-700)', fontFamily: 'var(--font-display)', lineHeight: 1.1 }}>
                  {stat.value}
                </div>
                <div style={{ fontSize: '0.95rem', fontWeight: 600, color: 'var(--text-main)', marginTop: '0.25rem' }}>
                  {stat.unit}
                </div>
                <div style={{ 
                  marginTop: '0.85rem', 
                  display: 'inline-block', 
                  fontSize: '0.75rem', 
                  padding: '0.2rem 0.6rem', 
                  borderRadius: 'var(--radius-full)', 
                  background: 'var(--accent-emerald-light)', 
                  color: 'var(--accent-emerald-dark)',
                  fontWeight: 600
                }}>
                  {stat.change}
                </div>
              </div>
            ))}
          </div>

        </div>
      </section>

      {/* =========================================================================
          FEATURED FINANCIAL PRODUCTS (เงินกู้และเงินฝากยอดนิยม)
          ========================================================================= */}
      <section className="section" style={{ background: 'var(--bg-main)' }}>
        <div className="container">
          
          <div className="section-title-wrap">
            <span className="section-badge">ผลิตภัณฑ์สินเชื่อ</span>
            <h2 className="section-title">สินเชื่อเพื่อบุคลากรสาธารณสุข</h2>
            <p className="section-subtitle">วงเงินกู้สูง ดอกเบี้ยต่ำ ผ่อนชำระสบาย ยื่นกู้ง่าย อนุมัติรวดเร็ว</p>
            <div className="section-line" />
          </div>

          <div className="grid-3">
            {loanProducts.map((prod) => (
              <div key={prod.id} className="surface-card" style={{ padding: '2rem', display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
                <div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
                    <span className={`badge badge-${prod.badgeColor}`}>{prod.badge}</span>
                    <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{prod.period}</span>
                  </div>

                  <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-800)', marginBottom: '0.4rem' }}>{prod.title}</h3>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginBottom: '1.25rem', lineHeight: 1.5 }}>{prod.subtitle}</p>

                  <div style={{ background: 'var(--bg-subtle)', padding: '1rem', borderRadius: '10px', marginBottom: '1.25rem' }}>
                    <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>วงเงินกู้</div>
                    <div style={{ fontSize: '1.2rem', fontWeight: 800, color: 'var(--primary-700)' }}>{prod.maxAmount}</div>
                    <div style={{ fontSize: '0.85rem', fontWeight: 700, color: 'var(--accent-gold-dark)', marginTop: '0.25rem' }}>
                      ดอกเบี้ย {prod.interestRate}
                    </div>
                  </div>

                  <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '0.5rem', marginBottom: '1.5rem', fontSize: '0.85rem' }}>
                    {prod.features.map((feat, fidx) => (
                      <li key={fidx} style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                        <CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0 }} />
                        <span>{feat}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                <div style={{ display: 'flex', gap: '0.5rem' }}>
                  <Link to="/calculator" className="btn btn-outline btn-sm" style={{ flex: 1 }}>
                    <span>คำนวณค่างวด</span>
                  </Link>
                  <Link to="/service-unavailable" className="btn btn-primary btn-sm" style={{ flex: 1 }}>
                    <span>บริการสมาชิกยังไม่เปิดใช้งาน</span>
                  </Link>
                </div>

              </div>
            ))}
          </div>

        </div>
      </section>

      {/* =========================================================================
          INTERACTIVE LOAN CALCULATOR SUITE EMBED
          ========================================================================= */}
      <section className="section" style={{ background: 'var(--bg-surface)' }}>
        <div className="container">
          <LoanCalculator />
        </div>
      </section>

      {/* =========================================================================
          MEMBER WELFARE HIGHLIGHTS
          ========================================================================= */}
      <section className="section" style={{ background: 'var(--bg-surface)' }}>
        <div className="container">
          
          <div className="section-title-wrap">
            <span className="section-badge">สวัสดิการสมาชิก</span>
            <h2 className="section-title">ดูแลสมาชิกและครอบครัวในทุกช่วงชีวิต</h2>
            <p className="section-subtitle">กองทุนสวัสดิการมอบความช่วยเหลือและสิทธิประโยชน์เพื่อสร้างความอุ่นใจแด่มวลสมาชิก</p>
            <div className="section-line" />
          </div>

          <div className="grid-3">
            {welfareItems.slice(0, 6).map((item, idx) => (
              <div key={idx} className="surface-card" style={{ padding: '1.75rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
                  <span className="badge badge-teal">{item.category}</span>
                </div>
                <h4 style={{ fontSize: '1.15rem', color: 'var(--primary-800)', marginBottom: '0.4rem' }}>{item.title}</h4>
                <div style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--accent-teal-dark)', marginBottom: '0.65rem' }}>
                  {item.amount}
                </div>
                <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', lineHeight: 1.5 }}>
                  {item.desc}
                </p>
              </div>
            ))}
          </div>

          <div style={{ textAlign: 'center', marginTop: '2.5rem' }}>
            <Link to="/welfare" className="btn btn-teal">
              <span>ดูระเบียบและสวัสดิการทั้งหมด</span>
              <ArrowRight size={16} />
            </Link>
          </div>

        </div>
      </section>

    </div>
  );
}

const quickServiceCardStyle = {
  padding: '1.6rem 1.25rem',
  textAlign: 'center',
  borderRadius: 'var(--radius-lg)',
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'center',
  textDecoration: 'none',
  color: 'var(--text-main)',
  boxShadow: 'var(--shadow-sm)',
  border: '1px solid var(--border-subtle)',
  transition: 'all 0.2s cubic-bezier(0.16, 1, 0.3, 1)'
};

const quickServiceIconWrap = {
  width: '56px',
  height: '56px',
  borderRadius: '16px',
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'center',
  marginBottom: '0.85rem',
  boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05)'
};
