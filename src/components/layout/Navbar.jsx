import React, { useState, useEffect, useRef } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import {
  Menu, X, ChevronDown, Landmark, ShieldCheck, HeartHandshake,
  FileText, Bell, PhoneCall, LayoutDashboard, LogIn, Award,
  PiggyBank, TrendingUp, Zap, Banknote, Home, CheckSquare,
  Calculator, Sparkles, GraduationCap, Stethoscope, LifeBuoy,
  Medal, Shield, Users, Newspaper, Megaphone, Calendar,
  Download, Globe, QrCode, HelpCircle, Coins, CreditCard,
  Receipt, FilePlus2, Briefcase, FileSpreadsheet, Lock,
  ArrowRight, CheckCircle2, ChevronRight, User, Power, Gauge, Wallet
} from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { COOP_INFO } from '../../data/mockData';
import UserStatusBadge from '../common/UserStatusBadge';

export default function Navbar() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [activeMegaMenu, setActiveMegaMenu] = useState(null);
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);
  const [mobileAccordion, setMobileAccordion] = useState(null);
  const [isScrolled, setIsScrolled] = useState(false);

  const navRef = useRef(null);
  const userMenuRef = useRef(null);
  const location = useLocation();
  const navigate = useNavigate();
  const { user, isLoggedIn, logout, setShowAuthModal } = useAuth();

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Close menus on route change
  useEffect(() => {
    setMobileMenuOpen(false);
    setActiveMegaMenu(null);
    setUserDropdownOpen(false);
    setMobileAccordion(null);
  }, [location.pathname]);

  // Click outside to close menus
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (navRef.current && !navRef.current.contains(event.target)) {
        setActiveMegaMenu(null);
        setMobileMenuOpen(false);
      }
      if (userMenuRef.current && !userMenuRef.current.contains(event.target)) {
        setUserDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('touchstart', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('touchstart', handleClickOutside);
    };
  }, []);

  const toggleMegaMenu = (menuName) => {
    setUserDropdownOpen(false);
    setActiveMegaMenu(activeMegaMenu === menuName ? null : menuName);
  };

  const toggleMobileAccordion = (menuName) => {
    setMobileAccordion(mobileAccordion === menuName ? null : menuName);
  };

  const handleLogout = () => {
    logout();
    setUserDropdownOpen(false);
    navigate('/');
  };

  const isActive = (path) => location.pathname === path;
  const isCategoryActive = (paths) => paths.some(p => location.pathname.startsWith(p));

  // Determine avatar initial letter
  const getAvatarInitial = () => {
    if (!user || !user.name) return 'U';
    const cleanName = user.name.replace(/^(นาย|นางสาว|นาง|ดร\.|นพ\.|พญ\.)/, '').trim();
    return cleanName.charAt(0) || user.name.charAt(0) || 'U';
  };

  // Determine role English label
  const getRoleLabel = () => {
    if (!user) return 'Member';
    switch (user.role) {
      case 'super_admin': return 'Super Admin';
      case 'auditor': return 'Auditor';
      case 'staff': return 'Staff';
      default: return 'Member';
    }
  };

  return (
    <header
      ref={navRef}
      style={{
        position: 'sticky',
        top: 0,
        zIndex: 1000,
        background: isScrolled ? 'var(--glass-bg)' : 'var(--bg-surface)',
        backdropFilter: isScrolled ? 'var(--glass-blur)' : 'none',
        borderBottom: '1px solid var(--border-subtle)',
        boxShadow: isScrolled ? 'var(--shadow-md)' : 'none',
        transition: 'all 0.3s ease'
      }}
    >
      <div className="navbar-container">

        {/* Logo & Brand */}
        <Link to="/" className="navbar-brand-link">
          <img
            src="/assets/img/logo.webp"
            alt="Logo"
            className="navbar-brand-logo"
            onError={(e) => { e.target.src = '/img/logo.webp'; }}
          />
          <div className="navbar-brand-text">
            <div className="navbar-brand-title">
              {COOP_INFO.nameTh}
            </div>
            <div className="navbar-brand-subtitle">
              {COOP_INFO.nameEn}
            </div>
          </div>
        </Link>

        {/* Desktop Navigation - 5 MAIN CATEGORIES */}
        <nav className="hide-mobile navbar-desktop-nav" aria-label="เมนูหลัก">

          {/* 1. หน้าแรก */}
          <Link
            to="/"
            className={`nav-link ${isActive('/') ? 'active' : ''}`}
            style={navLinkStyle(isActive('/'))}
            onMouseEnter={() => setActiveMegaMenu(null)}
          >
            หน้าแรก
          </Link>

          {/* 2. เกี่ยวกับสหกรณ์ (Mega Menu) */}
          <div style={{ position: 'static' }}>
            <button
              onClick={() => toggleMegaMenu('about')}
              onMouseEnter={() => setActiveMegaMenu('about')}
              style={{
                ...navLinkStyle(isCategoryActive(['/about', '/board', '/statistics']) || activeMegaMenu === 'about'),
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.3rem'
              }}
            >
              <span>เกี่ยวกับสหกรณ์</span>
              <ChevronDown size={14} style={{ transform: activeMegaMenu === 'about' ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }} />
            </button>
          </div>

          {/* 3. บริการทางการเงิน (Mega Menu) */}
          <div style={{ position: 'static' }}>
            <button
              onClick={() => toggleMegaMenu('finance')}
              onMouseEnter={() => setActiveMegaMenu('finance')}
              style={{
                ...navLinkStyle(isCategoryActive(['/deposits', '/loans', '/calculator', '/loan-checklist', '/dividend-estimator']) || activeMegaMenu === 'finance'),
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.3rem'
              }}
            >
              <span>บริการทางการเงิน</span>
              <ChevronDown size={14} style={{ transform: activeMegaMenu === 'finance' ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }} />
            </button>
          </div>

          {/* 4. สวัสดิการ (Mega Menu) */}
          <div style={{ position: 'static' }}>
            <button
              onClick={() => toggleMegaMenu('welfare')}
              onMouseEnter={() => setActiveMegaMenu('welfare')}
              style={{
                ...navLinkStyle(isCategoryActive(['/welfare']) || activeMegaMenu === 'welfare'),
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.3rem'
              }}
            >
              <span>สวัสดิการ</span>
              <ChevronDown size={14} style={{ transform: activeMegaMenu === 'welfare' ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }} />
            </button>
          </div>

          {/* 5. ข่าวสารและเอกสาร (Mega Menu) */}
          <div style={{ position: 'static' }}>
            <button
              onClick={() => toggleMegaMenu('news_docs')}
              onMouseEnter={() => setActiveMegaMenu('news_docs')}
              style={{
                ...navLinkStyle(isCategoryActive(['/news', '/documents', '/eservice', '/verify-receipt']) || activeMegaMenu === 'news_docs'),
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.3rem'
              }}
            >
              <span>ข่าวสารและเอกสาร</span>
              <ChevronDown size={14} style={{ transform: activeMegaMenu === 'news_docs' ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }} />
            </button>
          </div>

        </nav>

        {/* Right Action: User Profile Pill / Login */}
        <div className="navbar-actions">
          <Link to="/member/dividends" className="btn btn-outline navbar-dividend-link" aria-label="ตรวจสอบปันผล"><Coins size={18} aria-hidden="true" /><span>ตรวจสอบปันผล</span></Link>

          {isLoggedIn ? (
            /* Logged-In User Pill with Dropdown matching user screenshot */
            <div ref={userMenuRef} style={{ position: 'relative' }}>
              <button
                onClick={() => {
                  setActiveMegaMenu(null);
                  setUserDropdownOpen(!userDropdownOpen);
                }}
                className="user-pill-btn user-avatar-only"
                aria-label="User Account Menu"
              >
                <div className="user-avatar-status-wrap">
                  <div className="user-pill-avatar" style={{ overflow: 'hidden', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    {user.avatar ? (
                      <img src={user.avatar} alt="Profile" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    ) : (
                      getAvatarInitial()
                    )}
                  </div>
                  <UserStatusBadge compact />
                </div>
              </button>

              {/* User Account Dropdown Menu (Exact match to screenshot) */}
              {userDropdownOpen && (
                <div className="user-dropdown-panel animate-fade-in">

                  {/* Header */}
                  <div className="user-dropdown-header">
                    <div className="user-dropdown-header-name">
                      {user.name} {user.roleBadge ? `(${user.roleBadge})` : ''}
                    </div>
                    <div className="user-dropdown-header-role">
                      บทบาท: <strong>{getRoleLabel()}</strong>
                    </div>
                    <UserStatusBadge />
                  </div>

                  {/* Menu Items */}
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.15rem' }}>

                    <Link
                      to="/admin/dashboard"
                      className="user-dropdown-item"
                      onClick={() => setUserDropdownOpen(false)}
                    >
                      <Gauge size={18} style={{ color: '#3b82f6', flexShrink: 0 }} />
                      <span>{user.role === 'super_admin' ? 'ไปที่ แผงควบคุมระบบ (Admin)' : 'ไปที่ พอร์ทัลสมาชิก'}</span>
                    </Link>

                    <Link
                      to="/admin/dashboard"
                      className="user-dropdown-item"
                      onClick={() => setUserDropdownOpen(false)}
                    >
                      <CreditCard size={18} style={{ color: '#0ea5e9', flexShrink: 0 }} />
                      <span>คำขอกู้เงิน & ติดตามสถานะ</span>
                    </Link>

                    <Link
                      to="/admin/dashboard"
                      className="user-dropdown-item"
                      onClick={() => setUserDropdownOpen(false)}
                    >
                      <Wallet size={18} style={{ color: '#10b981', flexShrink: 0 }} />
                      <span>บัญชีเงินฝาก & หุ้น</span>
                    </Link>

                    <Link
                      to="/admin/dashboard"
                      className="user-dropdown-item"
                      onClick={() => setUserDropdownOpen(false)}
                    >
                      <User size={18} style={{ color: '#8b5cf6', flexShrink: 0 }} />
                      <span>ข้อมูลส่วนตัว & เปลี่ยนรหัสผ่าน</span>
                    </Link>

                    <Link
                      to="/admin/dashboard"
                      className="user-dropdown-item"
                      onClick={() => setUserDropdownOpen(false)}
                    >
                      <FileText size={18} style={{ color: '#06b6d4', flexShrink: 0 }} />
                      <span>ใบเสร็จรับเงิน (e-Receipt)</span>
                    </Link>

                    {user.role === 'super_admin' && (
                      <Link
                        to="/admin/dashboard"
                        className="user-dropdown-item"
                        onClick={() => setUserDropdownOpen(false)}
                      >
                        <ShieldCheck size={18} style={{ color: '#dc2626', flexShrink: 0 }} />
                        <span>จัดการระบบ (CMS 6 โมดูล)</span>
                      </Link>
                    )}

                    <div style={{ height: '1px', background: 'var(--border-subtle)', margin: '0.35rem 0' }} />

                    <button
                      onClick={handleLogout}
                      className="user-dropdown-item logout"
                    >
                      <Power size={18} style={{ color: '#ef4444', flexShrink: 0 }} />
                      <span style={{ fontWeight: 600 }}>ออกจากระบบ (Logout)</span>
                    </button>

                  </div>

                </div>
              )}
            </div>
          ) : null}

          {/* Mobile Menu Toggle Button */}
          <button
            className="show-mobile-btn"
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            aria-label="Toggle navigation menu"
          >
            {mobileMenuOpen ? <X size={22} /> : <Menu size={22} />}
          </button>

        </div>

      </div>

      {/* ========================================================================= */}
      {/* DESKTOP MEGA MENUS CONTAINER                                            */}
      {/* ========================================================================= */}
      {activeMegaMenu && (
        <div
          className="mega-menu-wrapper"
          onMouseLeave={() => setActiveMegaMenu(null)}
        >

          {/* MEGA MENU 1: เกี่ยวกับสหกรณ์ */}
          {activeMegaMenu === 'about' && (
            <div className="mega-menu-grid">
              <div className="mega-menu-col-main">
                <div>
                  <div className="mega-menu-category-title">
                    <Landmark size={14} style={{ color: 'var(--primary-600)' }} />
                    <span>ข้อมูลและโครงสร้างองค์กร</span>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                    <Link to="/about" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
                        <Landmark size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ประวัติและวิสัยทัศน์</div>
                        <div className="mega-menu-item-desc">ความเป็นมา พันธกิจ และค่านิยมหลักของสหกรณ์</div>
                      </div>
                    </Link>

                    <Link to="/board_committee" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                        <Award size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">คณะกรรมการดำเนินการ</div>
                        <div className="mega-menu-item-desc">ทำเนียบคณะกรรมการชุดที่ 32 และผู้ทรงคุณวุฒิ</div>
                      </div>
                    </Link>

                    <Link to="/board_officer" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)' }}>
                        <Users size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ฝ่ายจัดการและเจ้าหน้าที่</div>
                        <div className="mega-menu-item-desc">โครงสร้างบริหารและการดำเนินงานสาธารณสุขระยอง</div>
                      </div>
                    </Link>
                  </div>
                </div>

                <div>
                  <div className="mega-menu-category-title">
                    <ShieldCheck size={14} style={{ color: 'var(--accent-emerald-dark)' }} />
                    <span>ความมั่นคงและธรรมาภิบาล</span>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                    <Link to="/statistics" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)' }}>
                        <ShieldCheck size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">
                          <span>ฐานะและสถิติทางการเงิน</span>
                          <span className="badge badge-emerald" style={{ fontSize: '0.65rem', padding: '0.1rem 0.4rem' }}>มั่นคงสูง</span>
                        </div>
                        <div className="mega-menu-item-desc">สินทรัพย์ ทุนเรือนหุ้น และผลการดำเนินงานย้อนหลัง 5 ปี</div>
                      </div>
                    </Link>

                    <Link to="/statistics" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                        <Coins size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">สถิติเงินปันผล-เฉลี่ยคืน</div>
                        <div className="mega-menu-item-desc">ประวัติอัตราจ่ายเงินปันผล 5.25% - 5.50% ทุกปี</div>
                      </div>
                    </Link>

                    <Link to="/documents" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
                        <FileText size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ข้อบังคับและระเบียบสหกรณ์</div>
                        <div className="mega-menu-item-desc">กฎเกณฑ์ ความโปร่งใส และนโยบายคุ้มครองข้อมูล</div>
                      </div>
                    </Link>
                  </div>
                </div>
              </div>

              {/* Side Highlight Card */}
              <div className="mega-menu-col-side">
                <div className="mega-menu-feature-banner">
                  <div>
                    <span className="badge badge-gold" style={{ background: 'rgba(245, 158, 11, 0.25)', color: '#fef08a', marginBottom: '0.75rem' }}>
                      ความมั่นคงขององค์กร
                    </span>
                    <h4 style={{ fontSize: '1.1rem', fontWeight: 800, color: '#ffffff', marginBottom: '0.5rem' }}>
                      สหกรณ์ออมทรัพย์สาธารณสุขระยอง
                    </h4>
                    <p style={{ fontSize: '0.8rem', color: '#cbd5e1', lineHeight: 1.45, marginBottom: '1rem' }}>
                      สินทรัพย์รวมกว่า 4,850 ล้านบาท มุ่งมั่นดูแลคุณภาพชีวิตบุคลากรสาธารณสุขจังหวัดระยองอย่างยั่งยืน
                    </p>
                  </div>
                  <Link
                    to="/statistics"
                    className="btn btn-gold btn-sm"
                    style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '0.4rem', width: '100%' }}
                  >
                    <span>ดูรายงานฐานะการเงิน</span>
                    <ArrowRight size={14} />
                  </Link>
                </div>
              </div>
            </div>
          )}

          {/* MEGA MENU 2: บริการทางการเงิน */}
          {activeMegaMenu === 'finance' && (
            <div className="mega-menu-grid">
              <div className="mega-menu-col-main">
                <div>
                  <div className="mega-menu-category-title">
                    <PiggyBank size={14} style={{ color: 'var(--accent-teal)' }} />
                    <span>เงินฝากและผลตอบแทน</span>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                    <Link to="/deposits" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)' }}>
                        <PiggyBank size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">
                          <span>เงินฝากออมทรัพย์ & ประจำ</span>
                          <span className="badge badge-teal" style={{ fontSize: '0.65rem', padding: '0.1rem 0.4rem' }}>ปลอดภาษี</span>
                        </div>
                        <div className="mega-menu-item-desc">ดอกเบี้ยสูง รับดอกเบี้ยรายเดือนและรายปี</div>
                      </div>
                    </Link>

                    <Link to="/deposits" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
                        <TrendingUp size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ตารางอัตราดอกเบี้ยเงินฝาก</div>
                        <div className="mega-menu-item-desc">อัปเดตอัตราดอกเบี้ยเงินฝากประจำและออมทรัพย์ล่าสุด</div>
                      </div>
                    </Link>

                    <Link to="/dividend-estimator" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                        <Sparkles size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">
                          <span>ประมาณการเงินปันผล-เฉลี่ยคืน</span>
                          <span className="badge badge-gold" style={{ fontSize: '0.65rem', padding: '0.1rem 0.4rem' }}>ยอดนิยม</span>
                        </div>
                        <div className="mega-menu-item-desc">คำนวณผลตอบแทนหุ้นและเงินเฉลี่ยคืนสิ้นปีล่วงหน้า</div>
                      </div>
                    </Link>
                  </div>
                </div>

                <div>
                  <div className="mega-menu-category-title">
                    <Banknote size={14} style={{ color: 'var(--primary-600)' }} />
                    <span>ผลิตภัณฑ์สินเชื่อ & เงินกู้</span>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                    <Link to="/loans#emergency" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
                        <Zap size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">
                          <span>เงินกู้เพื่อเหตุฉุกเฉิน</span>
                          <span className="badge badge-rose" style={{ fontSize: '0.65rem', padding: '0.1rem 0.4rem' }}>อนุมัติไว</span>
                        </div>
                        <div className="mega-menu-item-desc">วงเงินตามความจำเป็น ไม่ต้องมีผู้ค้ำประกัน</div>
                      </div>
                    </Link>

                    <Link to="/loans#ordinary" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                        <Banknote size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">เงินกู้สามัญ & สามัญศึกษา</div>
                        <div className="mega-menu-item-desc">เพื่อการลงทุน สวัสดิการ และการศึกษาต่อ</div>
                      </div>
                    </Link>

                    <Link to="/loans#housing" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)' }}>
                        <Home size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">เงินกู้พิเศษเพื่อเคหะ</div>
                        <div className="mega-menu-item-desc">ซื้อที่อยู่อาศัย ปลูกสร้าง หรือไถ่ถอนจำนอง</div>
                      </div>
                    </Link>

                    <Link to="/loans" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)' }}>
                        <CreditCard size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">
                          <span>ยื่นคำขอกู้เงินออนไลน์ & ติดตามสถานะ</span>
                          <span className="badge badge-teal" style={{ fontSize: '0.65rem', padding: '0.1rem 0.4rem' }}>ออนไลน์ 24 ชม.</span>
                        </div>
                        <div className="mega-menu-item-desc">ยื่นกู้ฉุกเฉิน กู้สามัญ และติดตามผลการอนุมัติแบบเรียลไทม์</div>
                      </div>
                    </Link>

                    <Link to="/loan-checklist" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--bg-subtle)', color: 'var(--text-main)' }}>
                        <CheckSquare size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">เช็คความพร้อมและเอกสารขอกู้</div>
                        <div className="mega-menu-item-desc">ตรวจสอบคุณสมบัติและรายการเอกสารที่ต้องใช้</div>
                      </div>
                    </Link>
                  </div>
                </div>
              </div>

              {/* Side Quick Calculator Card */}
              <div className="mega-menu-col-side">
                <div className="mega-menu-feature-banner" style={{ background: 'linear-gradient(135deg, #064e3b, #047857)' }}>
                  <div>
                    <span className="badge badge-emerald" style={{ background: 'rgba(52, 211, 153, 0.25)', color: '#a7f3d0', marginBottom: '0.75rem' }}>
                      เครื่องมือคำนวณเงินกู้
                    </span>
                    <h4 style={{ fontSize: '1.1rem', fontWeight: 800, color: '#ffffff', marginBottom: '0.5rem' }}>
                      วางแผนการกู้ยืมอย่างมั่นใจ
                    </h4>
                    <p style={{ fontSize: '0.8rem', color: '#d1fae5', lineHeight: 1.45, marginBottom: '1rem' }}>
                      คำนวณค่างวดรายเดือน ดอกเบี้ยลดต้นลดดอก และตารางการผ่อนชำระแบบเรียลไทม์
                    </p>
                  </div>
                  <Link
                    to="/calculator"
                    className="btn btn-gold btn-sm"
                    style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '0.4rem', width: '100%' }}
                  >
                    <Calculator size={15} />
                    <span>เปิดโปรแกรมคำนวณ</span>
                  </Link>
                </div>
              </div>
            </div>
          )}

          {/* MEGA MENU 3: สวัสดิการ */}
          {activeMegaMenu === 'welfare' && (
            <div className="mega-menu-grid">
              <div className="mega-menu-col-main">
                <div>
                  <div className="mega-menu-category-title">
                    <HeartHandshake size={14} style={{ color: 'var(--accent-rose)' }} />
                    <span>กองทุนสวัสดิการสมาชิก</span>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                    <Link to="/welfare" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-rose-light)', color: 'var(--accent-rose)' }}>
                        <HeartHandshake size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">
                          <span>สวัสดิการสงเคราะห์ 6 ประเภท</span>
                          <span className="badge badge-rose" style={{ fontSize: '0.65rem', padding: '0.1rem 0.4rem' }}>ครอบคลุม</span>
                        </div>
                        <div className="mega-menu-item-desc">ดูแลสมาชิกตั้งแต่แรกเข้า ตลอดจนถึงวัยเกษียณ</div>
                      </div>
                    </Link>

                    <Link to="/welfare" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                        <GraduationCap size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ทุนการศึกษาบุตรสมาชิก</div>
                        <div className="mega-menu-item-desc">สนับสนุนการศึกษาตั้งแต่ระดับประถมจนถึงปริญญาตรี</div>
                      </div>
                    </Link>

                    <Link to="/welfare" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)' }}>
                        <Stethoscope size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">สวัสดิการรักษาพยาบาล/เจ็บป่วย</div>
                        <div className="mega-menu-item-desc">เงินช่วยเหลือเมื่อเข้ารับการรักษาตัวในโรงพยาบาล</div>
                      </div>
                    </Link>
                  </div>
                </div>

                <div>
                  <div className="mega-menu-category-title">
                    <Shield size={14} style={{ color: 'var(--primary-600)' }} />
                    <span>สวัสดิการคุ้มครองและเกียรติยศ</span>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                    <Link to="/welfare" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)' }}>
                        <Medal size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">สวัสดิการสมาชิกผู้สูงอายุ</div>
                        <div className="mega-menu-item-desc">บำเหน็จเกียรติยศและเงินขวัญถุงแด่สมาชิกอาวุโส</div>
                      </div>
                    </Link>

                    <Link to="/welfare" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
                        <Shield size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">คุ้มครองหนี้ & ฌาปนกิจ (สสธท.)</div>
                        <div className="mega-menu-item-desc">ปกป้องครอบครัวและทายาท วงเงินสงเคราะห์สูงสุด</div>
                      </div>
                    </Link>

                    <Link to="/documents" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--bg-subtle)', color: 'var(--text-main)' }}>
                        <FileText size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ดาวน์โหลดฟอร์มขอรับสวัสดิการ</div>
                        <div className="mega-menu-item-desc">แบบคำขอและเอกสารแนบเพื่อยื่นขอสวัสดิการ</div>
                      </div>
                    </Link>
                  </div>
                </div>
              </div>

              {/* Side Welfare Highlight */}
              <div className="mega-menu-col-side">
                <div className="mega-menu-feature-banner" style={{ background: 'linear-gradient(135deg, #881337, #be123c)' }}>
                  <div>
                    <span className="badge badge-rose" style={{ background: 'rgba(255, 255, 255, 0.25)', color: '#ffffff', marginBottom: '0.75rem' }}>
                      ดูแลด้วยใจ
                    </span>
                    <h4 style={{ fontSize: '1.1rem', fontWeight: 800, color: '#ffffff', marginBottom: '0.5rem' }}>
                      กองทุนสวัสดิการสมาชิก
                    </h4>
                    <p style={{ fontSize: '0.8rem', color: '#ffe4e6', lineHeight: 1.45, marginBottom: '1rem' }}>
                      จัดสรรงบประมาณสวัสดิการกว่า 15 ล้านบาท/ปี เพื่อคุ้มครองและเสริมสร้างความสุขแก่สมาชิกทุกคน
                    </p>
                  </div>
                  <Link
                    to="/welfare"
                    className="btn btn-light btn-sm"
                    style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '0.4rem', width: '100%', background: '#ffffff', color: '#881337', fontWeight: 700 }}
                  >
                    <span>ดูสวัสดิการทั้งหมด</span>
                    <ArrowRight size={14} />
                  </Link>
                </div>
              </div>
            </div>
          )}

          {/* MEGA MENU 4: ข่าวสารและเอกสาร */}
          {activeMegaMenu === 'news_docs' && (
            <div className="mega-menu-grid">
              <div className="mega-menu-col-main">
                <div>
                  <div className="mega-menu-category-title">
                    <Newspaper size={14} style={{ color: 'var(--primary-600)' }} />
                    <span>ข่าวสารและกิจกรรมสหกรณ์</span>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                    <Link to="/news" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
                        <Newspaper size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">
                          <span>ข่าวประชาสัมพันธ์ & กิจกรรม</span>
                          <span className="badge badge-primary" style={{ fontSize: '0.65rem', padding: '0.1rem 0.4rem' }}>อัปเดต</span>
                        </div>
                        <div className="mega-menu-item-desc">ข่าวสารโครงการ สัมมนา และภาพกิจกรรมสหกรณ์</div>
                      </div>
                    </Link>

                    <Link to="/news" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                        <Megaphone size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ประกาศทางการสหกรณ์</div>
                        <div className="mega-menu-item-desc">ประกาศอัตราดอกเบี้ย วันหยุด และมติที่ประชุม</div>
                      </div>
                    </Link>

                    <Link to="/news" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)' }}>
                        <Calendar size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ปฏิทินกิจกรรมและการประชุม</div>
                        <div className="mega-menu-item-desc">กำหนดการประชุมใหญ่สามัญและวันจ่ายเงินปันผล</div>
                      </div>
                    </Link>
                  </div>
                </div>

                <div>
                  <div className="mega-menu-category-title">
                    <FileText size={14} style={{ color: 'var(--accent-emerald-dark)' }} />
                    <span>เอกสารและศูนย์บริการดิจิทัล</span>
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                    <Link to="/documents" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)' }}>
                        <Download size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">
                          <span>ดาวน์โหลดแบบฟอร์ม & รายงาน</span>
                          <span className="badge badge-emerald" style={{ fontSize: '0.65rem', padding: '0.1rem 0.4rem' }}>PDF</span>
                        </div>
                        <div className="mega-menu-item-desc">คำขอกู้เงิน สมัครสมาชิก และรายงานประจำปี</div>
                      </div>
                    </Link>

                    <Link to="/eservice" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
                        <Globe size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ศูนย์บริการออนไลน์ e-Services</div>
                        <div className="mega-menu-item-desc">ยื่นคำขอออนไลน์ ปรับค่าหุ้น ขอหนังสือรับรองภาษี</div>
                      </div>
                    </Link>

                    <Link to="/verify-receipt" className="mega-menu-card-item">
                      <div className="mega-menu-icon-wrap" style={{ background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
                        <QrCode size={18} />
                      </div>
                      <div>
                        <div className="mega-menu-item-title">ตรวจสอบใบเสร็จ e-Receipt</div>
                        <div className="mega-menu-item-desc">ตรวจสอบความถูกต้องและลายมือชื่อดิจิทัล</div>
                      </div>
                    </Link>
                  </div>
                </div>
              </div>

              {/* Side Document Card */}
              <div className="mega-menu-col-side">
                <div className="mega-menu-feature-banner" style={{ background: 'linear-gradient(135deg, #1e3a8a, #3b82f6)' }}>
                  <div>
                    <span className="badge badge-primary" style={{ background: 'rgba(255, 255, 255, 0.25)', color: '#ffffff', marginBottom: '0.75rem' }}>
                      ดาวน์โหลดด่วน
                    </span>
                    <h4 style={{ fontSize: '1.1rem', fontWeight: 800, color: '#ffffff', marginBottom: '0.5rem' }}>
                      คลังเอกสาร & ฟอร์มออนไลน์
                    </h4>
                    <p style={{ fontSize: '0.8rem', color: '#dbeafe', lineHeight: 1.45, marginBottom: '1rem' }}>
                      ดาวน์โหลดแบบฟอร์มสัญญากู้เงิน แบบฟอร์มสวัสดิการ และรายงานประจำปี ครบถ้วนในที่เดียว
                    </p>
                  </div>
                  <Link
                    to="/documents"
                    className="btn btn-gold btn-sm"
                    style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '0.4rem', width: '100%' }}
                  >
                    <Download size={14} />
                    <span>ไปที่คลังเอกสาร</span>
                  </Link>
                </div>
              </div>
            </div>
          )}

        </div>
      )}

      {/* ========================================================================= */}
      {/* MOBILE DRAWER NAVIGATION                                                 */}
      {/* ========================================================================= */}
      {mobileMenuOpen && (
        <div className="animate-fade-in" style={{
          background: 'var(--bg-surface)',
          borderTop: '1px solid var(--border-subtle)',
          padding: '1.25rem 1.5rem',
          display: 'flex',
          flexDirection: 'column',
          gap: '0.4rem',
          boxShadow: 'var(--shadow-xl)',
          maxHeight: '80vh',
          overflowY: 'auto'
        }}>
          {/* If NOT Logged In, Show Quick Login CTA Card in Mobile Drawer */}
          {false && !isLoggedIn && (
            <div className="mobile-login-card">
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <div style={{ width: '36px', height: '36px', borderRadius: '50%', background: 'var(--primary-100)', color: 'var(--primary-700)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <User size={18} />
                </div>
                <div>
                  <div style={{ fontSize: '0.88rem', fontWeight: 700, color: 'var(--text-main)' }}>สำหรับสมาชิกสหกรณ์</div>
                  <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)' }}>เข้าสู่ระบบเพื่อดูหุ้น เงินฝาก และยื่นกู้</div>
                </div>
              </div>
              <button
                onClick={() => {
                  setMobileMenuOpen(false);
                  setShowAuthModal(true);
                }}
                className="btn btn-primary btn-sm"
                style={{ width: '100%', marginTop: '0.75rem', display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '0.4rem' }}
              >
                <LogIn size={15} />
                <span>เข้าสู่ระบบสมาชิก</span>
              </button>
            </div>
          )}
          {/* If Logged In, Show User Card in Mobile Drawer */}
          {isLoggedIn && (
            <div style={{
              background: 'var(--bg-subtle)',
              padding: '0.85rem',
              borderRadius: '12px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              marginBottom: '0.5rem',
              border: '1px solid var(--border-subtle)'
            }}>
              <Link
                to="/profile"
                onClick={() => setMobileMenuOpen(false)}
                style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', textDecoration: 'none', color: 'inherit' }}
              >
                <div className="user-avatar-status-wrap">
                  <div className="user-pill-avatar" style={{ width: '36px', height: '36px', fontSize: '0.85rem', overflow: 'hidden', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    {user.avatar ? (
                      <img src={user.avatar} alt="Profile" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    ) : (
                      getAvatarInitial()
                    )}
                  </div>
                  <UserStatusBadge compact />
                </div>
                <div>
                  <div style={{ fontSize: '0.85rem', fontWeight: 700, color: 'var(--text-main)' }}>{user.name}</div>
                  <UserStatusBadge />
                </div>
              </Link>
              <button
                onClick={handleLogout}
                style={{ background: 'none', border: 'none', color: '#ef4444', display: 'flex', alignItems: 'center', gap: '0.25rem', fontSize: '0.8rem', fontWeight: 600, cursor: 'pointer' }}
              >
                <Power size={14} />
                <span>ออก</span>
              </button>
            </div>
          )}

          {/* 1. หน้าแรก */}
          <Link to="/" style={mobileItemStyle} onClick={() => setMobileMenuOpen(false)}>
            <span>หน้าแรก</span>
          </Link>

          {/* 2. เกี่ยวกับสหกรณ์ Accordion */}
          <div>
            <button
              onClick={() => toggleMobileAccordion('about')}
              style={{ ...mobileItemStyle, width: '100%', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'none', border: 'none', cursor: 'pointer', textAlign: 'left' }}
            >
              <span>เกี่ยวกับสหกรณ์</span>
              <ChevronDown size={16} style={{ transform: mobileAccordion === 'about' ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }} />
            </button>
            {mobileAccordion === 'about' && (
              <div style={mobileSubMenuStyle}>
                <Link to="/about" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ประวัติและวิสัยทัศน์</Link>
                <Link to="/board_committee" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• คณะกรรมการดำเนินการ</Link>
                <Link to="/board_officer" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ฝ่ายจัดการและเจ้าหน้าที่</Link>
                <Link to="/statistics" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ฐานะและสถิติทางการเงิน</Link>
                <Link to="/documents" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ข้อบังคับและระเบียบสหกรณ์</Link>
              </div>
            )}
          </div>

          {/* 3. บริการทางการเงิน Accordion */}
          <div>
            <button
              onClick={() => toggleMobileAccordion('finance')}
              style={{ ...mobileItemStyle, width: '100%', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'none', border: 'none', cursor: 'pointer', textAlign: 'left' }}
            >
              <span>บริการทางการเงิน</span>
              <ChevronDown size={16} style={{ transform: mobileAccordion === 'finance' ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }} />
            </button>
            {mobileAccordion === 'finance' && (
              <div style={mobileSubMenuStyle}>
                <Link to="/deposits" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• เงินฝากออมทรัพย์ & ประจำ</Link>
                <Link to="/deposits" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ตารางอัตราดอกเบี้ยเงินฝาก</Link>
                <Link to="/loans" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ผลิตภัณฑ์สินเชื่อทุกประเภท</Link>
                <Link to="/loans" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ข้อมูลผลิตภัณฑ์สินเชื่อ</Link>
                <Link to="/calculator" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• โปรแกรมคำนวณเงินกู้</Link>
                <Link to="/loan-checklist" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• เช็คความพร้อมการกู้</Link>
                <Link to="/dividend-estimator" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ประมาณการเงินปันผล-เฉลี่ยคืน</Link>
              </div>
            )}
          </div>

          {/* 4. สวัสดิการ Accordion */}
          <div>
            <button
              onClick={() => toggleMobileAccordion('welfare')}
              style={{ ...mobileItemStyle, width: '100%', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'none', border: 'none', cursor: 'pointer', textAlign: 'left' }}
            >
              <span>สวัสดิการ</span>
              <ChevronDown size={16} style={{ transform: mobileAccordion === 'welfare' ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }} />
            </button>
            {mobileAccordion === 'welfare' && (
              <div style={mobileSubMenuStyle}>
                <Link to="/welfare" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• สวัสดิการสงเคราะห์ 6 ประเภท</Link>
                <Link to="/welfare" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ทุนการศึกษาบุตรสมาชิก</Link>
                <Link to="/welfare" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• สวัสดิการค่ารักษาพยาบาล/เจ็บป่วย</Link>
                <Link to="/welfare" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• สวัสดิการสมาชิกผู้สูงอายุ</Link>
                <Link to="/welfare" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• คุ้มครองหนี้ & ฌาปนกิจ (สสธท.)</Link>
              </div>
            )}
          </div>

          {/* 5. ข่าวสารและเอกสาร Accordion */}
          <div>
            <button
              onClick={() => toggleMobileAccordion('news_docs')}
              style={{ ...mobileItemStyle, width: '100%', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'none', border: 'none', cursor: 'pointer', textAlign: 'left' }}
            >
              <span>ข่าวสารและเอกสาร</span>
              <ChevronDown size={16} style={{ transform: mobileAccordion === 'news_docs' ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }} />
            </button>
            {mobileAccordion === 'news_docs' && (
              <div style={mobileSubMenuStyle}>
                <Link to="/news" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ข่าวประชาสัมพันธ์ & กิจกรรม</Link>
                <Link to="/news" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ประกาศทางการสหกรณ์</Link>
                <Link to="/documents" style={mobileSubItemStyle} onClick={() => setMobileMenuOpen(false)}>• ดาวน์โหลดแบบฟอร์ม & รายงาน</Link>
                <span style={mobileSubItemStyle}>• ระบบสมาชิกออนไลน์อยู่ระหว่างดำเนินการ</span>
              </div>
            )}
          </div>

        </div>
      )}
    </header>
  );
}

const navLinkStyle = (active) => ({
  padding: '0.45rem 0.75rem',
  fontSize: '0.9rem',
  fontFamily: 'var(--font-heading)',
  fontWeight: active ? '700' : '600',
  color: active ? 'var(--primary-600)' : 'var(--text-main)',
  borderRadius: '8px',
  background: active ? 'var(--primary-50)' : 'transparent',
  transition: 'all 0.2s ease',
  whiteSpace: 'nowrap',
  cursor: 'pointer',
  display: 'inline-flex',
  alignItems: 'center',
  border: 'none',
  outline: 'none',
  flexShrink: 0
});

const mobileItemStyle = {
  padding: '0.75rem 0',
  fontSize: '0.98rem',
  fontFamily: 'var(--font-heading)',
  fontWeight: 700,
  color: 'var(--text-main)',
  borderBottom: '1px solid var(--border-subtle)',
  textDecoration: 'none'
};

const mobileSubMenuStyle = {
  display: 'flex',
  flexDirection: 'column',
  padding: '0.35rem 0 0.5rem 1rem',
  gap: '0.4rem',
  background: 'var(--bg-subtle)',
  borderRadius: '8px',
  marginTop: '0.35rem',
  marginBottom: '0.5rem'
};

const mobileSubItemStyle = {
  fontSize: '0.88rem',
  color: 'var(--text-muted)',
  padding: '0.3rem 0',
  textDecoration: 'none',
  display: 'block'
};
