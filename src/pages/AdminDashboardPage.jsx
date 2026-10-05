import React, { useState, useEffect, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  ShieldCheck, Users, Settings, Database, Activity,
  UserPlus, KeyRound, RefreshCw, Download, Search,
  Trash2, Edit, CheckCircle2, AlertTriangle, Lock,
  Sliders, Shield, HardDrive, Cpu, Terminal, Sparkles,
  LogOut, ArrowRight, Eye, Bell, Newspaper, Image,
  Megaphone, MessageSquare, HelpCircle, Plus, Check, X, ExternalLink,
  UploadCloud, FileImage, ImagePlus, Edit3, Phone, Camera, Percent, Scale
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { COOP_INFO, KEY_STATS, INTEREST_RATES, ANNOUNCEMENTS, NEWS_LIST, FAQS, MEMBER_COMPLAINTS, LOAN_PRODUCTS } from '../data/mockData';
import { fetchAdminDashboard } from '../services/api';
import EditProfileModal from '../components/member/EditProfileModal';
import ChangePasswordModal from '../components/common/ChangePasswordModal';
import DashboardHeader from '../components/dashboard/DashboardHeader';
import KpiCard from '../components/dashboard/KpiCard';
import SectionHeader from '../components/dashboard/SectionHeader';
import StatusBadge from '../components/dashboard/StatusBadge';
import EmptyState from '../components/dashboard/EmptyState';

export default function AdminDashboardPage() {
  const { user, isLoggedIn, logout, setShowAuthModal } = useAuth();
  const { toast, confirmDialog } = useToast();
  const [activeTab, setActiveTab] = useState('announcements');
  const [editProfileModalOpen, setEditProfileModalOpen] = useState(false);
  const [changePasswordModalOpen, setChangePasswordModalOpen] = useState(false);
  const navigate = useNavigate();

  // 1. Announcements State
  const [announcements, setAnnouncements] = useState(ANNOUNCEMENTS);
  const [annModal, setAnnModal] = useState(false);
  const [newAnn, setNewAnn] = useState({ title: '', date: '11 มี.ค. 2567', fileSize: '850 KB', important: true });

  // 2. News State
  const [news, setNews] = useState(NEWS_LIST);
  const [newsModal, setNewsModal] = useState(false);
  const [newNews, setNewNews] = useState({
    title: '',
    category: 'ข่าวประชาสัมพันธ์',
    date: '11 มี.ค. 2567',
    excerpt: '',
    image: '/assets/img/news_placeholder.jpg'
  });

  // 3. Hero Section Settings State
  const [heroSettings, setHeroSettings] = useState(() => {
    try {
      const saved = localStorage.getItem('coop_hero_settings');
      return saved ? JSON.parse(saved) : {
        title: COOP_INFO.nameTh,
        subtitle: `${COOP_INFO.slogan} มอบความมั่นคงทางการเงิน ดอกเบี้ยเงินฝากคุ้มค่า สินเชื่ออัตราดอกเบี้ยเป็นธรรม พร้อมสวัสดิการดูแลตลอดทุกช่วงชีวิต`,
        badgeText: 'ยินดีต้อนรับสู่ระบบสหกรณ์ดิจิทัล',
        bgImageUrl: '/assets/img/hero_bg_coop.jpg'
      };
    } catch (e) {
      return {
        title: COOP_INFO.nameTh,
        subtitle: `${COOP_INFO.slogan} มอบความมั่นคงทางการเงิน ดอกเบี้ยเงินฝากคุ้มค่า สินเชื่ออัตราดอกเบี้ยเป็นธรรม พร้อมสวัสดิการดูแลตลอดทุกช่วงชีวิต`,
        badgeText: 'ยินดีต้อนรับสู่ระบบสหกรณ์ดิจิทัล',
        bgImageUrl: '/assets/img/hero_bg_coop.jpg'
      };
    }
  });

  // 4. Pop-up Campaign State
  const [popupCampaign, setPopupCampaign] = useState(() => {
    try {
      const saved = localStorage.getItem('coop_popup_campaign');
      return saved ? JSON.parse(saved) : {
        enabled: true,
        title: 'โครงการประมาณการเงินปันผลและเฉลี่ยคืน ประจำปี 2567',
        subtitle: 'สมาชิกสามารถคำนวณและตรวจสอบสิทธิประโยชน์ได้แล้ววันนี้ผ่านระบบดิจิทัล',
        imageUrl: '/assets/img/popup_dividend.jpg',
        buttonText: 'ประมาณการเงินปันผลทันที',
        linkUrl: '/dividend-estimator'
      };
    } catch (e) {
      return {
        enabled: true,
        title: 'โครงการประมาณการเงินปันผลและเฉลี่ยคืน ประจำปี 2567',
        subtitle: 'สมาชิกสามารถคำนวณและตรวจสอบสิทธิประโยชน์ได้แล้ววันนี้ผ่านระบบดิจิทัล',
        imageUrl: '/assets/img/popup_dividend.jpg',
        buttonText: 'ประมาณการเงินปันผลทันที',
        linkUrl: '/dividend-estimator'
      };
    }
  });

  // Pop-up Drag & Drop File State
  const popupFileRef = useRef(null);
  const [isPopupDragging, setIsPopupDragging] = useState(false);
  const [popupFileInfo, setPopupFileInfo] = useState(null);

  const handlePopupFileUpload = (file) => {
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      toast.error('กรุณาเลือกไฟล์รูปภาพเท่านั้น (เช่น PNG, JPG, JPEG, WEBP, SVG)');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error('ไฟล์รูปภาพมีขนาดใหญ่เกิน 5MB กรุณาเลือกไฟล์ที่มีขนาดเล็กลง');
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      const base64 = event.target.result;
      setPopupCampaign(prev => ({ ...prev, imageUrl: base64 }));
      setPopupFileInfo({
        name: file.name,
        size: (file.size / 1024).toFixed(1) + ' KB'
      });
    };
    reader.readAsDataURL(file);
  };

  const handlePopupDrop = (e) => {
    e.preventDefault();
    setIsPopupDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handlePopupFileUpload(e.dataTransfer.files[0]);
    }
  };

  const handlePopupDragOver = (e) => {
    e.preventDefault();
    setIsPopupDragging(true);
  };

  const handlePopupDragLeave = () => {
    setIsPopupDragging(false);
  };

  // 5. Member Feedback & Complaints State
  const [feedbacks, setFeedbacks] = useState(() => {
    try {
      const saved = localStorage.getItem('coop_member_complaints');
      if (saved) return JSON.parse(saved);
    } catch (e) {}
    return MEMBER_COMPLAINTS;
  });
  const [feedbackFilter, setFeedbackFilter] = useState('all');
  const [feedbackSearch, setFeedbackSearch] = useState('');
  const [selectedFeedback, setSelectedFeedback] = useState(null);
  const [replyInput, setReplyInput] = useState('');

  // Auto-sync complaints from localStorage and check URL tab
  useEffect(() => {
    const syncData = () => {
      try {
        const saved = localStorage.getItem('coop_member_complaints');
        if (saved) {
          setFeedbacks(JSON.parse(saved));
        }
      } catch (e) {}
    };

    syncData();
    window.addEventListener('storage', syncData);
    window.addEventListener('focus', syncData);

    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (tabParam) {
      setActiveTab(tabParam);
    }

    return () => {
      window.removeEventListener('storage', syncData);
      window.removeEventListener('focus', syncData);
    };
  }, []);

  // 6. FAQs State
  const [faqsList, setFaqsList] = useState(FAQS);
  const [faqModal, setFaqModal] = useState(false);
  const [newFaq, setNewFaq] = useState({ q: '', a: '' });

  // 7. Loan Products & Interest Rates State (Master Data 10 Items)
  const [loanProductsList, setLoanProductsList] = useState(LOAN_PRODUCTS);

  // Users Management State
  const [usersList, setUsersList] = useState([]);
  const [apiAdminData, setApiAdminData] = useState(null);

  const isSuperAdmin = user && user.role === 'super_admin';

  useEffect(() => {
    if (isLoggedIn && isSuperAdmin) {
      fetchAdminDashboard().then(res => {
        if (res?.success && res?.data) {
          setApiAdminData(res.data);
        }
      }).catch(() => {});
    }
  }, [isLoggedIn, isSuperAdmin]);

  if (!isLoggedIn) {
    return (
      <div className="section" style={{ minHeight: '60vh', display: 'flex', alignItems: 'center' }}>
        <div className="container" style={{ textAlign: 'center' }}>
          <div className="glass-card" style={{ maxWidth: '480px', margin: '0 auto', padding: '3rem 2rem', borderRadius: 'var(--radius-xl)' }}>
            <Lock size={48} style={{ color: 'var(--accent-rose)', margin: '0 auto 1rem auto' }} />
            <h2 style={{ fontSize: '1.4rem', marginBottom: '0.5rem' }}>เฉพาะผู้ดูแลระบบ Super Admin</h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '1.5rem' }}>
              กรุณาเข้าสู่ระบบด้วยบัญชี Super Admin เพื่อเข้าถึงแผงควบคุมระบบ
            </p>
            <button onClick={() => setShowAuthModal(true)} className="btn btn-primary" style={{ width: '100%' }}>
              <span>เข้าสู่ระบบ Super Admin</span>
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Handlers
  const handleSaveHero = (e) => {
    e.preventDefault();
    localStorage.setItem('coop_hero_settings', JSON.stringify(heroSettings));
    toast.success('บันทึกการตั้งค่า Hero Section เรียบร้อยแล้ว! (แสดงผลที่หน้าแรกทันที)');
  };

  const handleSavePopup = (e) => {
    e.preventDefault();
    localStorage.setItem('coop_popup_campaign', JSON.stringify(popupCampaign));
    sessionStorage.removeItem('coop_campaign_shown'); // reset so it shows on next homepage visit
    toast.success('บันทึกการตั้งค่า Pop-up Campaign เรียบร้อยแล้ว! (เปิดหน้าเว็บเพื่อดูพรีวิวได้ทันที)');
  };

  const handleAddAnnouncement = (e) => {
    e.preventDefault();
    const item = {
      id: `ann-${Date.now()}`,
      title: newAnn.title,
      date: newAnn.date,
      fileSize: newAnn.fileSize,
      important: newAnn.important
    };
    setAnnouncements([item, ...announcements]);
    setAnnModal(false);
    setNewAnn({ title: '', date: '11 มี.ค. 2567', fileSize: '850 KB', important: true });
    toast.success('เพิ่มประกาศใหม่เรียบร้อยแล้ว');
  };

  const handleDeleteAnnouncement = async (id) => {
    const confirmed = await confirmDialog({
      title: 'ยืนยันการลบประกาศ',
      message: 'ท่านแน่ใจหรือไม่ว่าต้องการลบประกาศนี้?',
      type: 'danger'
    });
    if (confirmed) {
      setAnnouncements(announcements.filter(a => a.id !== id));
      toast.success('ลบประกาศเรียบร้อยแล้ว');
    }
  };

  const handleAddNews = (e) => {
    e.preventDefault();
    const item = {
      id: `news-${Date.now()}`,
      title: newNews.title,
      category: newNews.category,
      date: newNews.date,
      views: 0,
      excerpt: newNews.excerpt,
      image: newNews.image || '/assets/img/news_placeholder.jpg'
    };
    setNews([item, ...news]);
    setNewsModal(false);
    setNewNews({ title: '', category: 'ข่าวประชาสัมพันธ์', date: '11 มี.ค. 2567', excerpt: '', image: '/assets/img/news_placeholder.jpg' });
    toast.success('เพิ่มข่าวประชาสัมพันธ์เรียบร้อยแล้ว');
  };

  const handleDeleteNews = async (id) => {
    const confirmed = await confirmDialog({
      title: 'ยืนยันการลบข่าวสาร',
      message: 'ท่านแน่ใจหรือไม่ว่าต้องการลบข่าวสารนี้?',
      type: 'danger'
    });
    if (confirmed) {
      setNews(news.filter(n => n.id !== id));
      toast.success('ลบข่าวสารเรียบร้อยแล้ว');
    }
  };

  const handleAddFaq = (e) => {
    e.preventDefault();
    setFaqsList([...faqsList, newFaq]);
    setFaqModal(false);
    setNewFaq({ q: '', a: '' });
    toast.success('เพิ่มคำถาม-คำตอบที่พบบ่อยเรียบร้อยแล้ว');
  };

  const handleDeleteFaq = async (index) => {
    const confirmed = await confirmDialog({
      title: 'ยืนยันการลบคำถาม',
      message: 'ท่านแน่ใจหรือไม่ว่าต้องการลบคำถามนี้?',
      type: 'danger'
    });
    if (confirmed) {
      setFaqsList(faqsList.filter((_, idx) => idx !== index));
      toast.success('ลบคำถามเรียบร้อยแล้ว');
    }
  };

  const handleUpdateFeedbackStatus = (id, newStatus) => {
    const updated = feedbacks.map(f => f.id === id ? { ...f, status: newStatus } : f);
    setFeedbacks(updated);
    try {
      localStorage.setItem('coop_member_complaints', JSON.stringify(updated));
    } catch (e) {}
    if (selectedFeedback && selectedFeedback.id === id) {
      setSelectedFeedback({ ...selectedFeedback, status: newStatus });
    }
  };

  const handleSaveAdminReply = (id, replyText) => {
    const now = new Date().toLocaleString('th-TH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' น.';
    const updated = feedbacks.map(f => f.id === id ? {
      ...f,
      adminReply: replyText,
      status: 'ตอบกลับแล้ว',
      replyDate: now
    } : f);
    setFeedbacks(updated);
    try {
      localStorage.setItem('coop_member_complaints', JSON.stringify(updated));
    } catch (e) {}
    if (selectedFeedback && selectedFeedback.id === id) {
      setSelectedFeedback({
        ...selectedFeedback,
        adminReply: replyText,
        status: 'ตอบกลับแล้ว',
        replyDate: now
      });
    }
    toast.success(`บันทึกข้อความตอบกลับสำหรับรหัส ${id} เรียบร้อยแล้ว (สมาชิกสามารถตรวจสอบผลได้ทันที)`);
  };

  const handleDeleteFeedback = async (id) => {
    const confirmed = await confirmDialog({
      title: 'ยืนยันการลบเรื่องร้องเรียน',
      message: `ยืนยันการลบรายการเรื่องร้องเรียน ${id}?`,
      type: 'danger'
    });
    if (confirmed) {
      const updated = feedbacks.filter(f => f.id !== id);
      setFeedbacks(updated);
      try {
        localStorage.setItem('coop_member_complaints', JSON.stringify(updated));
      } catch (e) {}
      if (selectedFeedback && selectedFeedback.id === id) {
        setSelectedFeedback(null);
      }
      toast.success(`ลบรายการเรื่องร้องเรียน ${id} เรียบร้อยแล้ว`);
    }
  };

  return (
    <div className="section" style={{ background: 'var(--bg-main)', minHeight: '90vh' }}>
      <div className="container">

        {/* Standardized Dashboard Hero Header */}
        <DashboardHeader
          user={user}
          onEditProfile={() => setEditProfileModalOpen(true)}
          onChangePassword={() => setChangePasswordModalOpen(true)}
          onLogout={() => {
            logout();
            navigate('/');
          }}
          quickActions={
            <Link
              to="/"
              target="_blank"
              className="btn btn-subtle btn-sm"
              style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem', border: '1px solid var(--border-subtle)' }}
            >
              <ExternalLink size={14} />
              <span>ดูหน้าเว็บหลัก</span>
            </Link>
          }
        />

        {/* 4 Super Admin KPI Overview Cards */}
        {isSuperAdmin && <p style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}><Link to="/admin/members/dashboard" className="btn btn-primary">ดูแลระบบสมาชิก</Link><Link to="/admin/dividends/import" className="btn btn-outline">นำเข้าเงินปันผลรายปี</Link></p>}
        <div className="dashboard-kpi-grid">
          <KpiCard
            title="ผู้ใช้งานทั้งหมดในระบบ"
            value={apiAdminData?.totalUsers == null ? '—' : Number(apiAdminData.totalUsers).toLocaleString('th-TH')}
            unit="คน"
            subtitle="จำนวนบัญชีจากฐานข้อมูลจริง"
            icon={Users}
            variant="rose"
          />
          <KpiCard
            title="สถานะความปลอดภัยเซิร์ฟเวอร์"
            value="100%"
            subtitle="TLS 1.3 / Firewall Active"
            icon={Activity}
            variant="emerald"
            badgeText="ปกติ"
            badgeVariant="emerald"
          />
          <KpiCard
            title="ฐานข้อมูลและการสำรอง"
            value="Auto Backup"
            subtitle="สำรองข้อมูลล่าสุด: วันนี้ 04:00 น."
            icon={Database}
            variant="gold"
          />
          <KpiCard
            title="เรื่องร้องเรียนรอดำเนินการ"
            value={feedbacks.filter(f => f.status === 'รอดำเนินการ').length}
            unit="เรื่อง"
            subtitle={`จากทั้งหมด ${feedbacks.length} เรื่อง`}
            icon={MessageSquare}
            variant="primary"
          />
        </div>

        {/* Navigation Tabs for All 8 Modules (4-column Grid Layout) */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">

          {/* 1. Announcements */}
          <button
            onClick={() => setActiveTab('announcements')}
            className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
              activeTab === 'announcements'
                ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
            }`}
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                activeTab === 'announcements'
                  ? 'bg-white/20 text-white'
                  : 'bg-blue-50 text-[#073B74] dark:bg-blue-950/60 dark:text-blue-400 dark:border dark:border-blue-900/50'
              }`}>
                <Bell size={18} />
              </div>
              <div className="min-w-0">
                <div className="text-xs sm:text-sm font-bold truncate">1. ประกาศสหกรณ์</div>
                <div className={`text-[11px] truncate ${activeTab === 'announcements' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                  แจ้งเตือนทางการ
                </div>
              </div>
            </div>
            <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
              activeTab === 'announcements'
                ? 'bg-white/25 text-white'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
            }`}>
              {announcements.length}
            </span>
          </button>

          {/* 2. News & Events */}
          <button
            onClick={() => setActiveTab('news')}
            className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
              activeTab === 'news'
                ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
            }`}
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                activeTab === 'news'
                  ? 'bg-white/20 text-white'
                  : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border dark:border-emerald-900/50'
              }`}>
                <Newspaper size={18} />
              </div>
              <div className="min-w-0">
                <div className="text-xs sm:text-sm font-bold truncate">2. ข่าวสาร & กิจกรรม</div>
                <div className={`text-[11px] truncate ${activeTab === 'news' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                  ประชาสัมพันธ์
                </div>
              </div>
            </div>
            <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
              activeTab === 'news'
                ? 'bg-white/25 text-white'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
            }`}>
              {news.length}
            </span>
          </button>

          {/* 3. Hero Section */}
          <button
            onClick={() => setActiveTab('hero')}
            className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
              activeTab === 'hero'
                ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
            }`}
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                activeTab === 'hero'
                  ? 'bg-white/20 text-white'
                  : 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400 dark:border dark:border-purple-900/50'
              }`}>
                <Image size={18} />
              </div>
              <div className="min-w-0">
                <div className="text-xs sm:text-sm font-bold truncate">3. ปรับแต่ง Hero</div>
                <div className={`text-[11px] truncate ${activeTab === 'hero' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                  แบนเนอร์หน้าแรก
                </div>
              </div>
            </div>
            <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
              activeTab === 'hero'
                ? 'bg-white/25 text-white'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
            }`}>
              ตั้งค่า
            </span>
          </button>

          {/* 4. Pop-up Campaign */}
          <button
            onClick={() => setActiveTab('popup')}
            className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
              activeTab === 'popup'
                ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
            }`}
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                activeTab === 'popup'
                  ? 'bg-white/20 text-white'
                  : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 dark:border dark:border-rose-900/50'
              }`}>
                <Megaphone size={18} />
              </div>
              <div className="min-w-0">
                <div className="text-xs sm:text-sm font-bold truncate">4. Pop-up Campaign</div>
                <div className={`text-[11px] truncate ${activeTab === 'popup' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                  แคมเปญป๊อปอัป
                </div>
              </div>
            </div>
            <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
              activeTab === 'popup'
                ? 'bg-white/25 text-white'
                : popupCampaign.enabled
                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
            }`}>
              {popupCampaign.enabled ? 'เปิด' : 'ปิด'}
            </span>
          </button>

          {/* 5. Feedback Box */}
          <button
            onClick={() => setActiveTab('feedback')}
            className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
              activeTab === 'feedback'
                ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
            }`}
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                activeTab === 'feedback'
                  ? 'bg-white/20 text-white'
                  : 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-400 dark:border dark:border-cyan-900/50'
              }`}>
                <MessageSquare size={18} />
              </div>
              <div className="min-w-0">
                <div className="text-xs sm:text-sm font-bold truncate">5. ข้อเสนอแนะ</div>
                <div className={`text-[11px] truncate ${activeTab === 'feedback' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                  ความคิดเห็นสมาชิก
                </div>
              </div>
            </div>
            <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
              activeTab === 'feedback'
                ? 'bg-white/25 text-white'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
            }`}>
              {feedbacks.length}
            </span>
          </button>

          {/* 6. FAQs */}
          <button
            onClick={() => setActiveTab('faqs')}
            className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
              activeTab === 'faqs'
                ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
            }`}
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                activeTab === 'faqs'
                  ? 'bg-white/20 text-white'
                  : 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 dark:border dark:border-amber-900/50'
              }`}>
                <HelpCircle size={18} />
              </div>
              <div className="min-w-0">
                <div className="text-xs sm:text-sm font-bold truncate">6. คำถาม FAQs</div>
                <div className={`text-[11px] truncate ${activeTab === 'faqs' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                  ตอบข้อสงสัย
                </div>
              </div>
            </div>
            <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
              activeTab === 'faqs'
                ? 'bg-white/25 text-white'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
            }`}>
              {faqsList.length}
            </span>
          </button>

          {/* 7. Interest & Loans */}
          <button
            onClick={() => setActiveTab('loans')}
            className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
              activeTab === 'loans'
                ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
            }`}
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                activeTab === 'loans'
                  ? 'bg-white/20 text-white'
                  : 'bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-400 dark:border dark:border-teal-900/50'
              }`}>
                <Percent size={18} />
              </div>
              <div className="min-w-0">
                <div className="text-xs sm:text-sm font-bold truncate">7. ดอกเบี้ย & สินเชื่อ</div>
                <div className={`text-[11px] truncate ${activeTab === 'loans' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                  อัตรากู้ยืม
                </div>
              </div>
            </div>
            <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
              activeTab === 'loans'
                ? 'bg-white/25 text-white'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
            }`}>
              {loanProductsList.length}
            </span>
          </button>

          {/* 8. LED Member Check */}
          <Link
            to="/admin/led/dashboard"
            className="group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm
              bg-gradient-to-r from-amber-50 to-amber-100/70 hover:from-amber-100 hover:to-amber-200 text-amber-950 border-amber-300/80
              dark:from-[#0d1c3a] dark:to-[#132852] dark:hover:from-[#11244a] dark:hover:to-[#183266] dark:text-amber-300 dark:border-amber-500/50 dark:shadow-[0_0_15px_rgba(245,158,11,0.2)]"
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105
                bg-amber-500 text-white shadow-sm dark:bg-amber-500/20 dark:text-amber-400 dark:border dark:border-amber-500/30">
                <Scale size={18} />
              </div>
              <div className="min-w-0">
                <div className="text-xs sm:text-sm font-bold truncate">8. ตรวจกรมบังคับคดี</div>
                <div className="text-[11px] text-amber-700/80 dark:text-amber-400/80 truncate">
                  คดีล้มละลาย/บังคับคดี
                </div>
              </div>
            </div>
            <span className="px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0
              bg-amber-200/90 text-amber-950 dark:bg-amber-950/80 dark:text-amber-300 dark:border dark:border-amber-500/40">
              LED
            </span>
          </Link>

        </div>

        {/* =========================================================================
            MODULE 1: ANNOUNCEMENTS MANAGEMENT (จัดการประกาศ)
            ========================================================================= */}
        {activeTab === 'announcements' && (
          <div className="surface-card animate-fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', margin: 0 }}>ประกาศทางการของสหกรณ์ (Announcements)</h3>
                <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>จัดการประกาศผลการคัดเลือก ระเบียบ และคำสั่งทางการ</p>
              </div>

              <button onClick={() => setAnnModal(true)} className="btn btn-primary btn-sm">
                <Plus size={15} />
                <span>เพิ่มประกาศใหม่</span>
              </button>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
              {announcements.map((ann) => (
                <div key={ann.id} style={{ background: 'var(--bg-subtle)', padding: '1.25rem', borderRadius: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                    <div style={{ padding: '0.65rem', borderRadius: '10px', background: ann.important ? 'var(--accent-rose-light)' : 'var(--primary-100)', color: ann.important ? 'var(--accent-rose)' : 'var(--primary-600)' }}>
                      <Bell size={20} />
                    </div>
                    <div>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.2rem' }}>
                        {ann.important && <span className="badge badge-rose">สำคัญ</span>}
                        <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{ann.date} • ขนาดไฟล์ {ann.fileSize}</span>
                      </div>
                      <h4 style={{ fontSize: '1rem', fontWeight: 700 }}>{ann.title}</h4>
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '0.5rem' }}>
                    <button onClick={() => handleDeleteAnnouncement(ann.id)} className="btn btn-outline btn-sm" style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)' }}>
                      <Trash2 size={14} />
                      <span>ลบ</span>
                    </button>
                  </div>
                </div>
              ))}
            </div>

          </div>
        )}

        {/* =========================================================================
            MODULE 2: NEWS & ACTIVITIES (จัดการข่าวสารและกิจกรรม)
            ========================================================================= */}
        {activeTab === 'news' && (
          <div className="surface-card animate-fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', margin: 0 }}>ข่าวสารประชาสัมพันธ์และกิจกรรม</h3>
                <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>เพิ่ม/แก้ไขข่าวสาร พร้อมภาพประกอบและสรุปย่อ</p>
              </div>

              <button onClick={() => setNewsModal(true)} className="btn btn-primary btn-sm">
                <Plus size={15} />
                <span>เพิ่มข่าวสารใหม่</span>
              </button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '1.25rem' }}>
              {news.map((item) => (
                <div key={item.id} style={{ background: 'var(--bg-subtle)', borderRadius: '12px', overflow: 'hidden', display: 'flex', flexDirection: 'column', border: '1px solid var(--border-subtle)' }}>
                  <div style={{ height: '140px', position: 'relative', background: 'var(--primary-100)' }}>
                    <img src={item.image} alt={item.title} style={{ width: '100%', height: '100%', objectFit: 'cover' }} onError={(e) => { e.target.style.display = 'none'; }} />
                    <span style={{ position: 'absolute', top: '8px', left: '8px', background: 'rgba(15,23,42,0.8)', color: '#fff', padding: '0.2rem 0.5rem', borderRadius: '4px', fontSize: '0.72rem', fontWeight: 600 }}>
                      {item.category}
                    </span>
                  </div>
                  <div style={{ padding: '1.25rem', flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
                    <div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginBottom: '0.35rem' }}>{item.date}</div>
                      <h4 style={{ fontSize: '0.98rem', fontWeight: 700, marginBottom: '0.5rem', lineHeight: 1.35 }}>{item.title}</h4>
                      <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', lineHeight: 1.5, marginBottom: '1rem' }}>{item.excerpt?.slice(0, 80)}...</p>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem' }}>
                      <button onClick={() => handleDeleteNews(item.id)} className="btn btn-outline btn-sm" style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)' }}>
                        <Trash2 size={13} />
                        <span>ลบข่าว</span>
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>

          </div>
        )}

        {/* =========================================================================
            MODULE 3: HERO SECTION SETTINGS (ปรับแต่งแบนเนอร์หลัก)
            ========================================================================= */}
        {activeTab === 'hero' && (
          <div className="surface-card animate-fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>

            <div style={{ marginBottom: '1.5rem' }}>
              <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', margin: 0 }}>ปรับแต่งแบนเนอร์หลักหน้าแรก (Hero Section Settings)</h3>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>แก้ไขข้อความพาดหัว, คำโปรย, และเลือก/เปลี่ยนรูปภาพพื้นหลัง</p>
            </div>

            <form onSubmit={handleSaveHero}>

              <div className="form-group">
                <label className="form-label">ข้อความ Badge เล็กด้านบน</label>
                <input type="text" className="form-control" value={heroSettings.badgeText} onChange={(e) => setHeroSettings({ ...heroSettings, badgeText: e.target.value })} />
              </div>

              <div className="form-group">
                <label className="form-label">หัวข้อหลัก (Main Heading H1)</label>
                <input type="text" className="form-control" value={heroSettings.title} onChange={(e) => setHeroSettings({ ...heroSettings, title: e.target.value })} required />
              </div>

              <div className="form-group">
                <label className="form-label">คำโปรยและสโลแกน (Subtitle)</label>
                <textarea className="form-control" rows={3} value={heroSettings.subtitle} onChange={(e) => setHeroSettings({ ...heroSettings, subtitle: e.target.value })} required />
              </div>

              {/* Background Image Selection & URL */}
              <div className="form-group">
                <label className="form-label">รูปภาพพื้นหลัง Hero Banner (URL หรือเลือกภาพที่มีในระบบ)</label>
                <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '0.75rem' }}>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="เช่น /assets/img/hero_bg_coop.jpg หรือ https://..."
                    value={heroSettings.bgImageUrl}
                    onChange={(e) => setHeroSettings({ ...heroSettings, bgImageUrl: e.target.value })}
                  />
                </div>

                {/* Preset Image Options */}
                <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
                  {[
                    { label: 'ธีมสหกรณ์มาตรฐาน', url: '/assets/img/hero_bg_coop.jpg' },
                    { label: 'ธีมสาธารณสุขและการแพทย์', url: '/assets/img/hero_bg_health.jpg' },
                    { label: 'ธีมธรรมชาติเมืองระยอง', url: '/assets/img/hero_bg_default.jpg' }
                  ].map((preset, idx) => (
                    <button
                      key={idx}
                      type="button"
                      onClick={() => setHeroSettings({ ...heroSettings, bgImageUrl: preset.url })}
                      className="btn btn-subtle btn-sm"
                      style={{
                        border: heroSettings.bgImageUrl === preset.url ? '2px solid var(--primary-600)' : '1px solid var(--border-subtle)',
                        fontWeight: heroSettings.bgImageUrl === preset.url ? 700 : 500
                      }}
                    >
                      <span>{preset.label}</span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Live Preview Box */}
              <div style={{ marginBottom: '1.5rem', background: 'var(--bg-subtle)', padding: '1.25rem', borderRadius: '12px' }}>
                <div style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-muted)', marginBottom: '0.5rem' }}>
                  พรีวิวภาพพื้นหลัง (Live Background Preview):
                </div>
                <div style={{
                  height: '160px',
                  borderRadius: '10px',
                  backgroundImage: heroSettings.bgImageUrl ? `linear-gradient(135deg, rgba(15, 43, 92, 0.85), rgba(30, 64, 175, 0.8)), url(${heroSettings.bgImageUrl})` : 'var(--gradient-hero)',
                  backgroundSize: 'cover',
                  backgroundPosition: 'center',
                  color: '#fff',
                  padding: '1.5rem',
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'center'
                }}>
                  <div style={{ fontSize: '0.75rem', color: '#fbbf24', fontWeight: 600 }}>{heroSettings.badgeText}</div>
                  <h3 style={{ fontSize: '1.2rem', color: '#fff', margin: '0.25rem 0' }}>{heroSettings.title}</h3>
                  <p style={{ fontSize: '0.78rem', color: '#cbd5e1', maxWidth: '450px' }}>{heroSettings.subtitle}</p>
                </div>
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                <button type="submit" className="btn btn-primary">
                  <CheckCircle2 size={16} />
                  <span>บันทึกการตั้งค่า Hero Section</span>
                </button>
              </div>

            </form>

          </div>
        )}

        {/* =========================================================================
            MODULE 4: POP-UP CAMPAIGN (จัดการป็อปอัปแคมเปญ)
            ========================================================================= */}
        {activeTab === 'popup' && (
          <div className="surface-card animate-fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', margin: 0 }}>ป็อปอัปแคมเปญแจ้งเตือนหน้าแรก (Pop-up Campaign Modal)</h3>
                <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>เปิด/ปิด หรือปรับเปลี่ยนภาพและโปรโมชั่นเงินปันผล/เงินฝาก</p>
              </div>

              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <span style={{ fontSize: '0.88rem', fontWeight: 600 }}>สถานะการแสดงผล:</span>
                <button
                  onClick={() => setPopupCampaign({ ...popupCampaign, enabled: !popupCampaign.enabled })}
                  className={`btn btn-sm ${popupCampaign.enabled ? 'btn-primary' : 'btn-subtle'}`}
                >
                  {popupCampaign.enabled ? 'เปิดใช้งานอยู่ (Active)' : 'ปิดการแสดงผล (Disabled)'}
                </button>
              </div>
            </div>

            <form onSubmit={handleSavePopup}>

              <div className="form-group">
                <label className="form-label">หัวข้อป็อปอัป (Campaign Title)</label>
                <input type="text" className="form-control" value={popupCampaign.title} onChange={(e) => setPopupCampaign({ ...popupCampaign, title: e.target.value })} required />
              </div>

              <div className="form-group">
                <label className="form-label">ข้อความรายละเอียด (Campaign Subtitle)</label>
                <textarea className="form-control" rows={3} value={popupCampaign.subtitle} onChange={(e) => setPopupCampaign({ ...popupCampaign, subtitle: e.target.value })} required />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                <div className="form-group">
                  <label className="form-label">ข้อความบนปุ่มกด (Button Text)</label>
                  <input type="text" className="form-control" value={popupCampaign.buttonText} onChange={(e) => setPopupCampaign({ ...popupCampaign, buttonText: e.target.value })} required />
                </div>
                <div className="form-group">
                  <label className="form-label">ลิงก์ปลายทาง (Link URL)</label>
                  <input type="text" className="form-control" value={popupCampaign.linkUrl} onChange={(e) => setPopupCampaign({ ...popupCampaign, linkUrl: e.target.value })} required />
                </div>
              </div>

              {/* Drop File Image Upload Zone */}
              <div className="form-group" style={{ marginBottom: '1.5rem' }}>
                <label className="form-label" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <span>รูปภาพแคมเปญ (Drop / Upload Image File)</span>
                  {popupCampaign.imageUrl && (
                    <span style={{ fontSize: '0.78rem', color: 'var(--accent-emerald-dark)', fontWeight: 600 }}>
                      ✓ มีรูปภาพพร้อมแสดงผล
                    </span>
                  )}
                </label>

                {/* Hidden Native File Input */}
                <input
                  type="file"
                  ref={popupFileRef}
                  accept="image/*"
                  style={{ display: 'none' }}
                  onChange={(e) => {
                    if (e.target.files && e.target.files[0]) {
                      handlePopupFileUpload(e.target.files[0]);
                    }
                  }}
                />

                {/* Dropzone Box */}
                <div
                  onDrop={handlePopupDrop}
                  onDragOver={handlePopupDragOver}
                  onDragLeave={handlePopupDragLeave}
                  onClick={() => popupFileRef.current && popupFileRef.current.click()}
                  style={{
                    border: isPopupDragging ? '2px dashed var(--primary-600)' : '2px dashed var(--border-subtle)',
                    background: isPopupDragging ? 'rgba(2, 132, 199, 0.08)' : 'var(--bg-subtle)',
                    borderRadius: '14px',
                    padding: '2rem 1.5rem',
                    textAlign: 'center',
                    cursor: 'pointer',
                    transition: 'all 0.2s ease',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '0.75rem'
                  }}
                >
                  <div style={{
                    width: '52px',
                    height: '52px',
                    borderRadius: '50%',
                    background: isPopupDragging ? 'var(--primary-600)' : 'rgba(2, 132, 199, 0.1)',
                    color: isPopupDragging ? '#ffffff' : 'var(--primary-600)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    transition: 'all 0.2s ease'
                  }}>
                    <UploadCloud size={28} />
                  </div>

                  <div>
                    <div style={{ fontWeight: 700, fontSize: '0.95rem', color: 'var(--text-main)', marginBottom: '0.25rem' }}>
                      {isPopupDragging ? 'ปล่อยไฟล์เพื่ออัปโหลดทันที' : 'ลากไฟล์รูปภาพมาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์'}
                    </div>
                    <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                      รองรับไฟล์ PNG, JPG, JPEG, WEBP (ขนาดไม่เกิน 5MB) • อัตราส่วนแนะนำ 2:1
                    </div>
                  </div>

                  {popupFileInfo && (
                    <div style={{
                      background: 'var(--bg-surface)',
                      border: '1px solid var(--border-subtle)',
                      padding: '0.35rem 0.75rem',
                      borderRadius: '20px',
                      fontSize: '0.78rem',
                      color: 'var(--primary-700)',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '0.35rem'
                    }}>
                      <FileImage size={14} />
                      <span>{popupFileInfo.name} ({popupFileInfo.size})</span>
                    </div>
                  )}
                </div>

                {/* Quick Preset / Action Buttons */}
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '0.75rem', flexWrap: 'wrap', gap: '0.5rem' }}>
                  <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap' }}>
                    <button
                      type="button"
                      onClick={() => {
                        setPopupCampaign({ ...popupCampaign, imageUrl: '/assets/img/popup_dividend.jpg' });
                        setPopupFileInfo(null);
                      }}
                      className="btn btn-sm btn-subtle"
                      style={{ fontSize: '0.75rem', padding: '0.25rem 0.55rem' }}
                    >
                      ✨ ใช้ภาพปันผลเริ่มต้น
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setPopupCampaign({ ...popupCampaign, imageUrl: '/assets/img/hero_bg_coop.jpg' });
                        setPopupFileInfo(null);
                      }}
                      className="btn btn-sm btn-subtle"
                      style={{ fontSize: '0.75rem', padding: '0.25rem 0.55rem' }}
                    >
                      🏛️ ภาพอาคารสหกรณ์
                    </button>
                  </div>

                  {popupCampaign.imageUrl && (
                    <button
                      type="button"
                      onClick={() => {
                        setPopupCampaign({ ...popupCampaign, imageUrl: '' });
                        setPopupFileInfo(null);
                      }}
                      className="btn btn-sm btn-outline"
                      style={{ fontSize: '0.75rem', color: 'var(--accent-rose)', borderColor: 'var(--border-subtle)', padding: '0.25rem 0.55rem' }}
                    >
                      <Trash2 size={13} style={{ marginRight: '0.25rem' }} />
                      <span>ลบรูปภาพ</span>
                    </button>
                  )}
                </div>
              </div>

              {/* Preview */}
              <div style={{ background: 'var(--bg-subtle)', padding: '1.25rem', borderRadius: '12px', marginBottom: '1.5rem' }}>
                <div style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-muted)', marginBottom: '0.75rem' }}>
                  พรีวิวป็อปอัป (Popup Preview):
                </div>
                <div style={{ maxWidth: '420px', margin: '0 auto', background: 'var(--bg-surface)', border: '1px solid var(--border-subtle)', borderRadius: '16px', overflow: 'hidden', boxShadow: 'var(--shadow-lg)' }}>
                  <div style={{ height: '140px', background: 'var(--gradient-hero)' }}>
                    <img src={popupCampaign.imageUrl || '/assets/img/popup_dividend.jpg'} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} onError={(e) => { e.target.style.display = 'none'; }} />
                  </div>
                  <div style={{ padding: '1rem' }}>
                    <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.3rem' }}>{popupCampaign.title}</h4>
                    <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.85rem' }}>{popupCampaign.subtitle}</p>
                    <div className="btn btn-primary btn-sm" style={{ width: '100%' }}>{popupCampaign.buttonText}</div>
                  </div>
                </div>
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                <button type="submit" className="btn btn-primary">
                  <CheckCircle2 size={16} />
                  <span>บันทึกการตั้งค่า Pop-up</span>
                </button>
              </div>

            </form>

          </div>
        )}

        {/* =========================================================================
            MODULE 5: MEMBER FEEDBACK & COMPLAINTS INBOX
            ========================================================================= */}
        {activeTab === 'feedback' && (
          <div className="surface-card animate-fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', margin: 0, display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <MessageSquare size={22} style={{ color: 'var(--accent-rose)' }} />
                  <span>กล่องข้อเสนอแนะและเรื่องร้องเรียน (Feedback & Complaints Inbox)</span>
                </h3>
                <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>ตรวจสอบข้อความ เรื่องร้องเรียน และข้อเสนอแนะที่สมาชิกส่งเข้ามา พร้อมเขียนตอบกลับแบบเรียลไทม์</p>
              </div>
              <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                <span className="badge badge-rose">ร้องเรียน {feedbacks.filter(f => f.topic.includes('ร้องเรียน')).length}</span>
                <span className="badge badge-gold">รอดำเนินการ {feedbacks.filter(f => f.status === 'รอดำเนินการ').length}</span>
                <span className="badge badge-emerald">ตอบกลับแล้ว {feedbacks.filter(f => f.status === 'ตอบกลับแล้ว').length}</span>
              </div>
            </div>

            {/* Filter and Search Bar */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '1rem', flexWrap: 'wrap', marginBottom: '1.25rem' }}>
              <div style={{ display: 'flex', gap: '0.35rem', flexWrap: 'wrap' }}>
                {[
                  { key: 'all', label: `ทั้งหมด (${feedbacks.length})` },
                  { key: 'complaint', label: `เรื่องร้องเรียน (${feedbacks.filter(f => f.topic.includes('ร้องเรียน')).length})` },
                  { key: 'feedback', label: `ข้อเสนอแนะ (${feedbacks.filter(f => f.topic.includes('ข้อเสนอแนะ')).length})` },
                  { key: 'pending', label: `รอดำเนินการ (${feedbacks.filter(f => f.status === 'รอดำเนินการ').length})` },
                  { key: 'replied', label: `ตอบกลับแล้ว (${feedbacks.filter(f => f.status === 'ตอบกลับแล้ว').length})` }
                ].map(tab => (
                  <button
                    key={tab.key}
                    onClick={() => setFeedbackFilter(tab.key)}
                    className={`btn btn-sm ${feedbackFilter === tab.key ? 'btn-primary' : 'btn-subtle'}`}
                    style={{ fontSize: '0.78rem' }}
                  >
                    {tab.label}
                  </button>
                ))}
              </div>

              <div style={{ position: 'relative', width: '260px' }}>
                <Search size={15} style={{ position: 'absolute', left: '10px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                <input
                  type="text"
                  placeholder="ค้นหาชื่อ, รหัส, ข้อความ..."
                  value={feedbackSearch}
                  onChange={(e) => setFeedbackSearch(e.target.value)}
                  className="form-control"
                  style={{ paddingLeft: '2rem', fontSize: '0.82rem' }}
                />
              </div>
            </div>

            <div className="table-scroll-hint">👈 เลื่อนซ้าย-ขวาเพื่อดูข้อมูลเพิ่มเติม 👉</div>
            <div className="table-scroll-container">
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.88rem' }}>
                <thead>
                  <tr style={{ background: 'var(--bg-subtle)', borderBottom: '2px solid var(--border-subtle)', textAlign: 'left' }}>
                    <th style={{ padding: '0.85rem' }}>รหัส / วันที่</th>
                    <th style={{ padding: '0.85rem' }}>ผู้ส่ง / สังกัด</th>
                    <th style={{ padding: '0.85rem' }}>หัวข้อเรื่อง</th>
                    <th style={{ padding: '0.85rem' }}>ข้อความสรุป</th>
                    <th style={{ padding: '0.85rem', textAlign: 'center' }}>สถานะ</th>
                    <th style={{ padding: '0.85rem', textAlign: 'right' }}>การจัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  {feedbacks
                    .filter(f => {
                      if (feedbackFilter === 'complaint') return f.topic.includes('ร้องเรียน');
                      if (feedbackFilter === 'feedback') return f.topic.includes('ข้อเสนอแนะ');
                      if (feedbackFilter === 'pending') return f.status === 'รอดำเนินการ';
                      if (feedbackFilter === 'replied') return f.status === 'ตอบกลับแล้ว';
                      return true;
                    })
                    .filter(f => {
                      if (!feedbackSearch) return true;
                      const q = feedbackSearch.toLowerCase();
                      return f.id.toLowerCase().includes(q) || f.name.toLowerCase().includes(q) || f.topic.toLowerCase().includes(q) || f.message.toLowerCase().includes(q);
                    })
                    .map((f) => {
                      const isComplaint = f.topic.includes('ร้องเรียน');
                      return (
                        <tr key={f.id} style={{ borderBottom: '1px solid var(--border-subtle)', background: isComplaint ? 'rgba(244, 63, 94, 0.02)' : 'transparent' }}>
                          <td style={{ padding: '0.85rem' }}>
                            <strong style={{ color: 'var(--primary-700)' }}>{f.id}</strong>
                            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{f.date}</div>
                          </td>
                          <td style={{ padding: '0.85rem' }}>
                            <div style={{ fontWeight: 600 }}>{f.name}</div>
                            <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>{f.department || f.phone}</div>
                          </td>
                          <td style={{ padding: '0.85rem' }}>
                            <span className={`badge ${isComplaint ? 'badge-rose' : 'badge-primary'}`}>{f.topic}</span>
                          </td>
                          <td style={{ padding: '0.85rem', color: 'var(--text-muted)', maxWidth: '280px' }}>
                            <div style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                              {f.message}
                            </div>
                            {f.adminReply && (
                              <div style={{ fontSize: '0.75rem', color: 'var(--accent-emerald-dark)', marginTop: '0.2rem' }}>
                                💬 ตอบแล้ว: {f.adminReply.slice(0, 30)}...
                              </div>
                            )}
                          </td>
                          <td style={{ padding: '0.85rem', textAlign: 'center' }}>
                            <span className={`badge badge-${f.status === 'ตอบกลับแล้ว' ? 'emerald' : f.status === 'กำลังตรวจสอบ' ? 'gold' : 'rose'}`}>
                              {f.status}
                            </span>
                          </td>
                          <td style={{ padding: '0.85rem', textAlign: 'right' }}>
                            <div style={{ display: 'inline-flex', gap: '0.35rem' }}>
                              <button
                                onClick={() => {
                                  setSelectedFeedback(f);
                                  setReplyInput(f.adminReply || '');
                                }}
                                className="btn btn-outline btn-sm"
                                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}
                              >
                                <Eye size={13} />
                                <span>เปิดดู / ตอบกลับ</span>
                              </button>
                              <button
                                onClick={() => handleDeleteFeedback(f.id)}
                                className="btn btn-outline btn-sm"
                                style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)', padding: '0.35rem 0.5rem' }}
                                title="ลบรายการนี้"
                              >
                                <Trash2 size={13} />
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                </tbody>
              </table>
            </div>

          </div>
        )}

        {/* =========================================================================
            MODULE 6: FAQS MANAGEMENT (เพิ่มและลบ คำถาม FAQs)
            ========================================================================= */}
        {activeTab === 'faqs' && (
          <div className="surface-card animate-fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', margin: 0 }}>จัดการคำถามที่พบบ่อย (FAQs Management)</h3>
                <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>เพิ่ม ลบ และอัปเดตคำถาม-คำตอบที่แสดงในหน้าติดต่อเรา</p>
              </div>

              <button onClick={() => setFaqModal(true)} className="btn btn-primary btn-sm">
                <Plus size={15} />
                <span>เพิ่มคำถามใหม่</span>
              </button>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
              {faqsList.map((faq, idx) => (
                <div key={idx} style={{ background: 'var(--bg-subtle)', padding: '1.25rem', borderRadius: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem' }}>
                  <div style={{ flex: 1 }}>
                    <h4 style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--text-main)', marginBottom: '0.4rem' }}>
                      ถาม: {faq.q}
                    </h4>
                    <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', lineHeight: 1.6 }}>
                      ตอบ: {faq.a}
                    </p>
                  </div>

                  <button onClick={() => handleDeleteFaq(idx)} className="btn btn-outline btn-sm" style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)', flexShrink: 0 }}>
                    <Trash2 size={13} />
                    <span>ลบ</span>
                  </button>
                </div>
              ))}
            </div>

          </div>
        )}

        {/* =========================================================================
            MODULE 7: LOAN PRODUCTS & INTEREST RATES (10 MASTER DATA ITEMS)
            ========================================================================= */}
        {activeTab === 'loans' && (
          <div className="surface-card animate-fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>
            
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
              <div>
                <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', margin: 0, display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <Percent className="text-primary" size={20} />
                  <span>ประเภทเงินกู้และอัตราดอกเบี้ย (Master Data 10 รายการ)</span>
                </h3>
                <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', margin: '0.25rem 0 0 0' }}>
                  Source of Truth ตามประกาศสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด (คิดดอกเบี้ยแบบลดต้นลดดอก)
                </p>
              </div>

              <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                <span className="badge badge-primary" style={{ padding: '0.4rem 0.75rem', fontSize: '0.85rem' }}>
                  ครบ 10 ประเภท
                </span>
              </div>
            </div>

            {/* Table of 10 items */}
            <div className="table-scroll-hint">👈 เลื่อนซ้าย-ขวาเพื่อดูข้อมูลเพิ่มเติม 👉</div>
            <div className="table-scroll-container" style={{ border: '1px solid var(--border-subtle)' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.92rem' }}>
                <thead>
                  <tr style={{ background: 'var(--bg-subtle)', borderBottom: '2px solid var(--border-subtle)', textAlign: 'left' }}>
                    <th style={{ padding: '0.85rem', width: '50px' }}>#</th>
                    <th style={{ padding: '0.85rem' }}>ประเภทเงินกู้</th>
                    <th style={{ padding: '0.85rem', textAlign: 'center' }}>อัตราดอกเบี้ย</th>
                    <th style={{ padding: '0.85rem' }}>วงเงินกู้สูงสุด</th>
                    <th style={{ padding: '0.85rem' }}>ผ่อนสูงสุด</th>
                    <th style={{ padding: '0.85rem' }}>หลักประกัน / เงื่อนไข</th>
                  </tr>
                </thead>
                <tbody>
                  {loanProductsList.map((p, idx) => (
                    <tr key={p.id} style={{ borderBottom: '1px solid var(--border-subtle)', background: idx % 2 === 0 ? 'transparent' : 'rgba(0,0,0,0.015)' }}>
                      <td style={{ padding: '0.85rem', color: 'var(--text-muted)', fontWeight: 600 }}>{idx + 1}</td>
                      <td style={{ padding: '0.85rem', fontWeight: 700, color: 'var(--primary-900)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <span>{p.title}</span>
                          <span className={`badge badge-${p.badgeColor}`} style={{ fontSize: '0.7rem' }}>{p.badge}</span>
                        </div>
                      </td>
                      <td style={{ padding: '0.85rem', textAlign: 'center', fontWeight: 800, color: 'var(--accent-gold-dark)', fontSize: '1.15rem', fontFamily: 'var(--font-display)' }}>
                        {p.rateValue.toFixed(2)}%
                      </td>
                      <td style={{ padding: '0.85rem', fontWeight: 600, color: 'var(--primary-700)' }}>{p.maxAmount}</td>
                      <td style={{ padding: '0.85rem' }}>{p.period}</td>
                      <td style={{ padding: '0.85rem', color: 'var(--text-muted)', fontSize: '0.85rem' }}>{p.guarantee}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

          </div>
        )}

        {/* =========================================================================
            MODALS
            ========================================================================= */}

        {/* Modal: Add Announcement */}
        {annModal && (
          <div style={modalBackdropStyle}>
            <div className="glass-card animate-fade-in" style={modalBoxStyle}>
              <div style={modalHeaderStyle}>
                <h3 style={{ fontSize: '1.2rem', color: 'var(--primary-800)' }}>เพิ่มประกาศทางการสหกรณ์</h3>
                <button onClick={() => setAnnModal(false)}>✕</button>
              </div>
              <form onSubmit={handleAddAnnouncement}>
                <div className="form-group">
                  <label className="form-label">หัวข้อประกาศ</label>
                  <input type="text" className="form-control" required value={newAnn.title} onChange={(e) => setNewAnn({ ...newAnn, title: e.target.value })} placeholder="เช่น ประกาศกำหนดการประชุมใหญ่..." />
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                  <div className="form-group">
                    <label className="form-label">วันที่</label>
                    <input type="text" className="form-control" value={newAnn.date} onChange={(e) => setNewAnn({ ...newAnn, date: e.target.value })} />
                  </div>
                  <div className="form-group">
                    <label className="form-label">ขนาดไฟล์</label>
                    <input type="text" className="form-control" value={newAnn.fileSize} onChange={(e) => setNewAnn({ ...newAnn, fileSize: e.target.value })} />
                  </div>
                </div>
                <div className="form-group" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <input type="checkbox" id="impCheck" checked={newAnn.important} onChange={(e) => setNewAnn({ ...newAnn, important: e.target.checked })} />
                  <label htmlFor="impCheck" style={{ fontSize: '0.9rem', cursor: 'pointer' }}>กำหนดเป็นประกาศด่วน/สำคัญ (Highlight)</label>
                </div>
                <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.5rem' }}>
                  <button type="button" onClick={() => setAnnModal(false)} className="btn btn-subtle" style={{ flex: 1 }}>ยกเลิก</button>
                  <button type="submit" className="btn btn-primary" style={{ flex: 1 }}>เผยแพร่ประกาศ</button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal: Add News */}
        {newsModal && (
          <div style={modalBackdropStyle}>
            <div className="glass-card animate-fade-in" style={modalBoxStyle}>
              <div style={modalHeaderStyle}>
                <h3 style={{ fontSize: '1.2rem', color: 'var(--primary-800)' }}>เพิ่มข่าวสาร / กิจกรรมใหม่</h3>
                <button onClick={() => setNewsModal(false)}>✕</button>
              </div>
              <form onSubmit={handleAddNews}>
                <div className="form-group">
                  <label className="form-label">หัวข้อข่าวสาร</label>
                  <input type="text" className="form-control" required value={newNews.title} onChange={(e) => setNewNews({ ...newNews, title: e.target.value })} placeholder="เช่น สหกรณ์มอบทุนการศึกษา..." />
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                  <div className="form-group">
                    <label className="form-label">หมวดหมู่</label>
                    <select className="form-control" value={newNews.category} onChange={(e) => setNewNews({ ...newNews, category: e.target.value })}>
                      <option value="ข่าวประชาสัมพันธ์">ข่าวประชาสัมพันธ์</option>
                      <option value="กิจกรรม">กิจกรรม</option>
                      <option value="บริการดิจิทัล">บริการดิจิทัล</option>
                      <option value="รายงานประจำปี">รายงานประจำปี</option>
                    </select>
                  </div>
                  <div className="form-group">
                    <label className="form-label">วันที่</label>
                    <input type="text" className="form-control" value={newNews.date} onChange={(e) => setNewNews({ ...newNews, date: e.target.value })} />
                  </div>
                </div>
                <div className="form-group">
                  <label className="form-label">เนื้อหาข่าว / สรุปย่อ</label>
                  <textarea className="form-control" rows={3} required value={newNews.excerpt} onChange={(e) => setNewNews({ ...newNews, excerpt: e.target.value })} />
                </div>
                <div className="form-group">
                  <label className="form-label">รูปภาพประกอบ (URL)</label>
                  <input type="text" className="form-control" value={newNews.image} onChange={(e) => setNewNews({ ...newNews, image: e.target.value })} placeholder="/assets/img/news_placeholder.jpg" />
                </div>
                <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.5rem' }}>
                  <button type="button" onClick={() => setNewsModal(false)} className="btn btn-subtle" style={{ flex: 1 }}>ยกเลิก</button>
                  <button type="submit" className="btn btn-primary" style={{ flex: 1 }}>โพสต์ข่าวสาร</button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal: Add FAQ */}
        {faqModal && (
          <div style={modalBackdropStyle}>
            <div className="glass-card animate-fade-in" style={modalBoxStyle}>
              <div style={modalHeaderStyle}>
                <h3 style={{ fontSize: '1.2rem', color: 'var(--primary-800)' }}>เพิ่มคำถามที่พบบ่อย (FAQ)</h3>
                <button onClick={() => setFaqModal(false)}>✕</button>
              </div>
              <form onSubmit={handleAddFaq}>
                <div className="form-group">
                  <label className="form-label">คำถาม (Question)</label>
                  <input type="text" className="form-control" required value={newFaq.q} onChange={(e) => setNewFaq({ ...newFaq, q: e.target.value })} placeholder="เช่น สมาชิกสามารถ..." />
                </div>
                <div className="form-group">
                  <label className="form-label">คำตอบ (Answer)</label>
                  <textarea className="form-control" rows={4} required value={newFaq.a} onChange={(e) => setNewFaq({ ...newFaq, a: e.target.value })} placeholder="พิมพ์คำตอบอย่างละเอียด..." />
                </div>
                <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.5rem' }}>
                  <button type="button" onClick={() => setFaqModal(false)} className="btn btn-subtle" style={{ flex: 1 }}>ยกเลิก</button>
                  <button type="submit" className="btn btn-primary" style={{ flex: 1 }}>บันทึก FAQ</button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal: View Feedback Details & Reply */}
        {selectedFeedback && (
          <div style={modalBackdropStyle}>
            <div className="glass-card animate-fade-in" style={{ ...modalBoxStyle, maxWidth: '600px' }}>
              <div style={modalHeaderStyle}>
                <div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                    <span className="badge badge-primary">{selectedFeedback.id}</span>
                    <span className={`badge badge-${selectedFeedback.status === 'ตอบกลับแล้ว' ? 'emerald' : selectedFeedback.status === 'กำลังตรวจสอบ' ? 'gold' : 'rose'}`}>
                      {selectedFeedback.status}
                    </span>
                  </div>
                  <h3 style={{ fontSize: '1.2rem', color: 'var(--primary-800)', margin: 0 }}>
                    {selectedFeedback.topic}
                  </h3>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.2rem' }}>
                    วันที่ส่ง: {selectedFeedback.date} • ผู้ส่ง: <strong>{selectedFeedback.name}</strong> ({selectedFeedback.phone})
                  </div>
                </div>
                <button onClick={() => setSelectedFeedback(null)} style={{ background: 'transparent', border: 'none', fontSize: '1.2rem', cursor: 'pointer' }}>✕</button>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '1.15rem', fontSize: '0.9rem', marginBottom: '1.5rem' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem', background: 'var(--bg-subtle)', padding: '0.85rem', borderRadius: '8px', fontSize: '0.82rem' }}>
                  <div><strong>อีเมล:</strong> {selectedFeedback.email || '-'}</div>
                  <div><strong>สังกัด:</strong> {selectedFeedback.department || 'สมาชิกทั่วไป'}</div>
                </div>

                <div style={{ background: 'var(--bg-subtle)', padding: '1rem', borderRadius: '8px', lineHeight: 1.6, border: '1px solid var(--border-subtle)' }}>
                  <div style={{ fontWeight: 700, color: 'var(--text-main)', marginBottom: '0.4rem', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                    <MessageSquare size={16} style={{ color: 'var(--primary-600)' }} />
                    <span>ข้อความจากสมาชิก / เรื่องร้องเรียน:</span>
                  </div>
                  <p style={{ color: 'var(--text-main)', margin: 0 }}>
                    {selectedFeedback.message}
                  </p>
                </div>

                {/* Status Switcher */}
                <div>
                  <label className="form-label" style={{ fontWeight: 700 }}>ปรับเปลี่ยนสถานะการดำเนินการ:</label>
                  <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                    {['รอดำเนินการ', 'กำลังตรวจสอบ', 'ตอบกลับแล้ว'].map((st) => (
                      <button
                        key={st}
                        type="button"
                        onClick={() => handleUpdateFeedbackStatus(selectedFeedback.id, st)}
                        className={`btn btn-sm ${selectedFeedback.status === st ? 'btn-primary' : 'btn-subtle'}`}
                        style={{ fontSize: '0.8rem' }}
                      >
                        {st}
                      </button>
                    ))}
                  </div>
                </div>

                {/* Admin Reply Form */}
                <div style={{ background: 'rgba(14, 165, 233, 0.05)', padding: '1.25rem', borderRadius: '10px', border: '1px solid rgba(14, 165, 233, 0.2)' }}>
                  <label className="form-label" style={{ fontWeight: 700, color: 'var(--primary-800)', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                    <CheckCircle2 size={16} style={{ color: 'var(--accent-emerald-dark)' }} />
                    <span>ข้อความตอบกลับจากผู้บริหาร / เจ้าหน้าที่ (Official Reply):</span>
                  </label>
                  <textarea
                    className="form-control"
                    rows={3}
                    value={replyInput}
                    onChange={(e) => setReplyInput(e.target.value)}
                    placeholder="พิมพ์คำชี้แจง แนวทางแก้ไข หรือผลการตรวจสอบเพื่อแจ้งให้สมาชิกทราบ..."
                    style={{ fontSize: '0.85rem', marginBottom: '0.75rem' }}
                  />
                  <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem' }}>
                    <button
                      type="button"
                      onClick={() => handleSaveAdminReply(selectedFeedback.id, replyInput)}
                      className="btn btn-primary btn-sm"
                    >
                      <Check size={14} />
                      <span>บันทึกข้อความตอบกลับ</span>
                    </button>
                  </div>
                </div>

              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid var(--border-subtle)', paddingTop: '1rem' }}>
                <button
                  type="button"
                  onClick={() => handleDeleteFeedback(selectedFeedback.id)}
                  className="btn btn-outline btn-sm"
                  style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)' }}
                >
                  <Trash2 size={14} />
                  <span>ลบรายการนี้</span>
                </button>
                <button onClick={() => setSelectedFeedback(null)} className="btn btn-subtle btn-sm">
                  <span>ปิดหน้าต่าง</span>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Edit Profile Modal (Avatar & Phone) */}
        <EditProfileModal
          isOpen={editProfileModalOpen}
          onClose={() => setEditProfileModalOpen(false)}
        />

        {/* Change Password Modal */}
        <ChangePasswordModal
          isOpen={changePasswordModalOpen}
          onClose={() => setChangePasswordModalOpen(false)}
        />

      </div>
    </div>
  );
}

const modalBackdropStyle = {
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
  padding: '1rem'
};

const modalBoxStyle = {
  width: '100%',
  maxWidth: '520px',
  background: 'var(--bg-surface)',
  borderRadius: 'var(--radius-xl)',
  padding: '2rem',
  position: 'relative',
  maxHeight: '90vh',
  overflowY: 'auto'
};

const modalHeaderStyle = {
  display: 'flex',
  justifyContent: 'space-between',
  alignItems: 'center',
  marginBottom: '1.25rem',
  borderBottom: '1px solid var(--border-subtle)',
  paddingBottom: '0.75rem'
};
