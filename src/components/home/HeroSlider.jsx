import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Link } from 'react-router-dom';
import {
  ChevronLeft,
  ChevronRight,
  Play,
  Pause,
  ArrowRight,
  Sparkles,
  ExternalLink
} from 'lucide-react';
import './HeroSlider.css';

// 4 Initial Official Promotional Banners as required by System Owner & Legacy Migration
export const DEFAULT_HERO_SLIDES = [
  {
    id: 1,
    title: 'ยินดีต้อนรับสมาชิกสหกรณ์',
    badgeText: 'บริการสหกรณ์ดิจิทัล',
    subtitle: 'บริการออนไลน์ สะดวก รวดเร็ว เข้าถึงข้อมูลสมาชิกได้ง่าย ตรวจสอบสถานะและธุรกรรมได้ทุกที่ทุกเวลา',
    desktop_image: '/assets/img/hero_bg_coop.jpg',
    mobile_image: '/assets/img/hero_bg_coop.jpg',
    button_text: 'บริการสมาชิก',
    button_url: '/eservice',
    sec_button_text: 'คำนวณเงินกู้',
    sec_button_url: '/calculator',
    button_target: '_self',
    overlay_opacity: 0.85
  },
  {
    id: 2,
    title: 'เงินกู้ของสหกรณ์',
    badgeText: 'สินเชื่อและสวัสดิการทางการเงิน',
    subtitle: 'รู้จักผลิตภัณฑ์เงินกู้และสิทธิประโยชน์สำหรับสมาชิก ดอกเบี้ยเป็นธรรม ผ่อนสบาย เพื่อคุณภาพชีวิตที่ดีขึ้น',
    desktop_image: '/assets/img/hero_bg_health.jpg',
    mobile_image: '/assets/img/hero_bg_health.jpg',
    button_text: 'ดูผลิตภัณฑ์เงินกู้',
    button_url: '/loans',
    sec_button_text: 'กำหนดการยื่นกู้ 2569',
    sec_button_url: '/loan-schedule',
    button_target: '_self',
    overlay_opacity: 0.85
  },
  {
    id: 3,
    title: 'เงินปันผลและเงินเฉลี่ยคืน',
    badgeText: 'ผลตอบแทนและสิทธิประโยชน์',
    subtitle: 'ตรวจสอบรายการรายรับ รายหัก และยอดสุทธิของสมาชิก พร้อมข้อมูลสิทธิประโยชน์ประจำปีอย่างโปร่งใส',
    desktop_image: '/assets/img/hero_bg_default.jpg',
    mobile_image: '/assets/img/hero_bg_default.jpg',
    button_text: 'ประมาณการเงินปันผล',
    button_url: '/dividend-estimator',
    sec_button_text: 'รายงานประจำปี',
    sec_button_url: '/documents?category=annual_reports',
    button_target: '_self',
    overlay_opacity: 0.85
  },
  {
    id: 4,
    title: 'ข่าวสารและกิจกรรมสหกรณ์',
    badgeText: 'ข่าวประชาสัมพันธ์และประกาศ',
    subtitle: 'ติดตามประกาศ การประชุม ผลการดำเนินงาน และกิจกรรมประชาสัมพันธ์ของสหกรณ์อย่างต่อเนื่อง',
    desktop_image: '/assets/img/hero_bg_coop.jpg',
    mobile_image: '/assets/img/hero_bg_coop.jpg',
    button_text: 'อ่านข่าวประชาสัมพันธ์',
    button_url: '/news',
    sec_button_text: 'ศูนย์เอกสารดาวน์โหลด',
    sec_button_url: '/documents',
    button_target: '_self',
    overlay_opacity: 0.85
  }
];

export default function HeroSlider({ slides = [] }) {
  const effectiveSlides = Array.isArray(slides) && slides.length > 0 ? slides : DEFAULT_HERO_SLIDES;
  const totalSlides = effectiveSlides.length;

  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPlaying, setIsPlaying] = useState(true);
  const [isHovered, setIsHovered] = useState(false);
  const [prefersReducedMotion, setPrefersReducedMotion] = useState(false);

  // Swipe / Pointer Gesture State
  const pointerStartRef = useRef(null);
  const isDraggingRef = useRef(false);

  // Check prefers-reduced-motion
  useEffect(() => {
    const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
    setPrefersReducedMotion(mq.matches);

    const listener = (e) => setPrefersReducedMotion(e.matches);
    mq.addEventListener?.('change', listener);
    return () => mq.removeEventListener?.('change', listener);
  }, []);

  // Safe navigation handlers
  const goToNext = useCallback(() => {
    setCurrentIndex((prev) => (prev + 1) % totalSlides);
  }, [totalSlides]);

  const goToPrev = useCallback(() => {
    setCurrentIndex((prev) => (prev - 1 + totalSlides) % totalSlides);
  }, [totalSlides]);

  const goToSlide = useCallback((index) => {
    if (index >= 0 && index < totalSlides) {
      setCurrentIndex(index);
    }
  }, [totalSlides]);

  // Autoplay timer (6s)
  useEffect(() => {
    if (!isPlaying || isHovered || prefersReducedMotion || totalSlides <= 1) {
      return undefined;
    }

    const timer = setInterval(() => {
      goToNext();
    }, 6000);

    return () => clearInterval(timer);
  }, [isPlaying, isHovered, prefersReducedMotion, totalSlides, goToNext]);

  // Pointer swipe gestures
  const handlePointerDown = (e) => {
    if (e.target.closest('button, a, .hero-nav-btn, .hero-controls-bar')) return;
    if (totalSlides <= 1 || (e.pointerType === 'mouse' && e.button !== 0)) return;
    pointerStartRef.current = { x: e.clientX, y: e.clientY };
    isDraggingRef.current = true;
    try {
      e.currentTarget.setPointerCapture?.(e.pointerId);
    } catch {
      // Ignore if not supported
    }
  };

  const handlePointerUp = (e) => {
    if (!isDraggingRef.current || !pointerStartRef.current) return;
    const deltaX = e.clientX - pointerStartRef.current.x;
    const deltaY = e.clientY - pointerStartRef.current.y;
    pointerStartRef.current = null;
    isDraggingRef.current = false;

    try {
      e.currentTarget.releasePointerCapture?.(e.pointerId);
    } catch {
      // Ignore
    }

    // Only swipe if horizontal move > 48px and mostly horizontal
    if (Math.abs(deltaX) > 48 && Math.abs(deltaX) > Math.abs(deltaY)) {
      if (deltaX < 0) {
        goToNext();
      } else {
        goToPrev();
      }
    }
  };

  const handlePointerCancel = () => {
    pointerStartRef.current = null;
    isDraggingRef.current = false;
  };

  return (
    <section
      className="hero-slider-wrapper"
      role="region"
      aria-roledescription="carousel"
      aria-label="ภาพประชาสัมพันธ์สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด"
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      onFocus={() => setIsHovered(true)}
      onBlur={() => setIsHovered(false)}
    >
      <div
        className="hero-slider-container"
        onPointerDown={handlePointerDown}
        onPointerUp={handlePointerUp}
        onPointerCancel={handlePointerCancel}
      >
        {effectiveSlides.map((slide, idx) => {
          const isActive = idx === currentIndex;
          const bgImg = slide.desktop_image || '/assets/img/hero_bg_coop.jpg';
          const title = slide.title || '';
          const subtitle = slide.subtitle || slide.description || '';
          const badge = slide.badgeText || 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด';
          const primaryBtnText = slide.button_text || 'ดูรายละเอียด';
          const primaryBtnUrl = slide.button_url || '/loans';
          const isExternal = primaryBtnUrl.startsWith('http');
          const isButtonAction = !primaryBtnUrl;

          return (
            <div
              key={slide.id || idx}
              className={`hero-slide ${isActive ? 'active' : ''}`}
              role="group"
              aria-roledescription="slide"
              aria-label={`สไลด์ ${idx + 1} จาก ${totalSlides}: ${title}`}
              aria-hidden={!isActive}
            >
              <div
                className="hero-slide-bg"
                style={{
                  backgroundImage: `url(${bgImg})`
                }}
              />
              <div
                className="hero-slide-overlay"
                style={{
                  opacity: slide.overlay_opacity ?? 0.85
                }}
              />

              <div className="container hero-slide-content-wrap">
                <div className="hero-slide-card">
                  {badge && (
                    <div className="hero-slide-badge">
                      <Sparkles size={16} />
                      <span>{badge}</span>
                    </div>
                  )}

                  <h1 className="hero-slide-title">
                    {title}
                  </h1>

                  {subtitle && (
                    <p className="hero-slide-subtitle">
                      {subtitle}
                    </p>
                  )}

                  <div className="hero-slide-actions">
                    {isExternal ? (
                      <a
                        href={primaryBtnUrl}
                        target={slide.button_target || '_blank'}
                        rel="noopener noreferrer"
                        className="hero-slide-cta-btn"
                      >
                        <span>{primaryBtnText}</span>
                        <ExternalLink size={18} />
                      </a>
                    ) : isButtonAction ? (
                      <button type="button" className="hero-slide-cta-btn">
                        <span>{primaryBtnText}</span>
                        <ArrowRight size={18} />
                      </button>
                    ) : (
                      <Link
                        to={primaryBtnUrl}
                        className="hero-slide-cta-btn"
                      >
                        <span>{primaryBtnText}</span>
                        <ArrowRight size={18} />
                      </Link>
                    )}

                    {slide.sec_button_text && slide.sec_button_url && (
                      <Link
                        to={slide.sec_button_url}
                        className="hero-slide-sec-btn"
                      >
                        <span>{slide.sec_button_text}</span>
                      </Link>
                    )}
                  </div>
                </div>
              </div>
            </div>
          );
        })}

        {/* Desktop Navigation Arrows */}
        {totalSlides > 1 && (
          <>
            <button
              type="button"
              className="hero-nav-btn prev"
              onClick={goToPrev}
              aria-label="ย้อนกลับไปสไลด์ก่อนหน้า"
            >
              <ChevronLeft size={26} />
            </button>
            <button
              type="button"
              className="hero-nav-btn next"
              onClick={goToNext}
              aria-label="ไปยังสไลด์ถัดไป"
            >
              <ChevronRight size={26} />
            </button>
          </>
        )}

        {/* Bottom Controls Bar (Pagination Dots & Play/Pause Toggle) */}
        {totalSlides > 1 && (
          <div className="hero-controls-bar">
            <div className="hero-controls-inner">
              {effectiveSlides.map((_, dotIdx) => (
                <button
                  key={dotIdx}
                  type="button"
                  className={`hero-dot ${dotIdx === currentIndex ? 'active' : ''}`}
                  onClick={() => goToSlide(dotIdx)}
                  aria-label={`ไปยังสไลด์ที่ ${dotIdx + 1}`}
                  aria-current={dotIdx === currentIndex ? 'true' : 'false'}
                />
              ))}

              <button
                type="button"
                className="hero-toggle-play-btn"
                onClick={() => setIsPlaying((prev) => !prev)}
                aria-label={isPlaying ? 'หยุดการเลื่อนสไลด์อัตโนมัติ' : 'เริ่มการเลื่อนสไลด์อัตโนมัติ'}
                title={isPlaying ? 'หยุดการเลื่อนสไลด์' : 'เริ่มการเลื่อนสไลด์'}
              >
                {isPlaying ? <Pause size={14} /> : <Play size={14} />}
              </button>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}

