import { maskName, maskPhone } from '../utils/pdpa';
import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  User, Coins, Landmark, ShieldCheck, TrendingUp,
  FileText, Download, LogOut, CreditCard, Clock,
  CheckCircle2, ShieldAlert, Users, Settings, Database,
  Activity, Check, X, Search, FileCheck, Eye, MessageSquare,
  AlertCircle, ExternalLink, Trash2, Send, PlusCircle, Edit3, Phone, Camera,
  FileSpreadsheet, ArrowUpRight, Calculator, HelpCircle, Shield, Scale, Layers, CheckSquare, LayoutDashboard
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { COOP_INFO, MEMBER_COMPLAINTS } from '../data/mockData';
import { fetchMemberDashboard } from '../services/api';
import StaffReviewModal from '../components/staff/StaffReviewModal';
import EditProfileModal from '../components/member/EditProfileModal';
import ChangePasswordModal from '../components/common/ChangePasswordModal';
import NewLoanRequestModal from '../components/member/NewLoanRequestModal';

// Reusable Dashboard UI Components
import DashboardHeader from '../components/dashboard/DashboardHeader';
import KpiCard from '../components/dashboard/KpiCard';
import SectionHeader from '../components/dashboard/SectionHeader';
import StatusBadge from '../components/dashboard/StatusBadge';
import EmptyState from '../components/dashboard/EmptyState';

export default function MemberDashboardPage() {
  const { user, isLoggedIn, logout, setShowAuthModal } = useAuth();
  const { toast, confirmDialog } = useToast();
  const [activeTab, setActiveTab] = useState('overview');
  const [staffFilter, setStaffFilter] = useState('all');
  const [staffSearch, setStaffSearch] = useState('');
  const [reviewModalOpen, setReviewModalOpen] = useState(false);
  const [selectedReviewRequest, setSelectedReviewRequest] = useState(null);
  const [editProfileModalOpen, setEditProfileModalOpen] = useState(false);
  const [changePasswordModalOpen, setChangePasswordModalOpen] = useState(false);

  // Sync activeTab based on pathname and search parameters
  useEffect(() => {
    const path = window.location.pathname.toLowerCase();
    const searchParams = new URLSearchParams(window.location.search);
    const tabParam = searchParams.get('tab');

    if (tabParam) {
      setActiveTab(tabParam);
    } else if (path.includes('/loans') || path.includes('/loan-requests') || path.includes('/tracking') || path.includes('/e-tracking')) {
      setActiveTab('loans');
    } else if (path.includes('/receipts')) {
      setActiveTab('receipts');
    } else if (path.includes('/complaints')) {
      setActiveTab('complaints');
    } else if (path.includes('/shares') || path.includes('/deposits')) {
      setActiveTab('overview');
    }
  }, []);

  // Member Loan Request State
  const [memberLoanModalOpen, setMemberLoanModalOpen] = useState(false);
  const [memberLoanFilter, setMemberLoanFilter] = useState('all');

  // Complaints & Feedback State
  const [complaintsList, setComplaintsList] = useState(() => {
    try {
      const saved = localStorage.getItem('coop_member_complaints');
      if (saved) return JSON.parse(saved);
    } catch (e) {}
    return MEMBER_COMPLAINTS;
  });
  const [complaintFilter, setComplaintFilter] = useState('all');
  const [selectedAdminComplaint, setSelectedAdminComplaint] = useState(null);
  const [adminReplyText, setAdminReplyText] = useState('');

  // Member Quick Complaint Modal & Filter State
  const [memberComplaintFilter, setMemberComplaintFilter] = useState('all');
  const [memberComplaintModalOpen, setMemberComplaintModalOpen] = useState(false);
  const [newMemberComplaint, setNewMemberComplaint] = useState({
    topic: 'ข้อเสนอแนะการให้บริการ',
    message: ''
  });

  // Auto-sync complaints and loan requests from localStorage whenever updated
  useEffect(() => {
    const syncData = () => {
      try {
        const savedComplaints = localStorage.getItem('coop_member_complaints');
        if (savedComplaints) {
          setComplaintsList(JSON.parse(savedComplaints));
        }
        const savedLoans = localStorage.getItem('coop_service_requests');
        if (savedLoans) {
          setLoanQueue(JSON.parse(savedLoans));
        }
      } catch (e) {}
    };

    syncData();
    window.addEventListener('storage', syncData);
    window.addEventListener('focus', syncData);
    return () => {
      window.removeEventListener('storage', syncData);
      window.removeEventListener('focus', syncData);
    };
  }, []);

  // Loan/Service requests queue
  const [loanQueue, setLoanQueue] = useState(() => {
    try {
      const saved = localStorage.getItem('coop_service_requests');
      if (saved) {
        return JSON.parse(saved);
      }
    } catch (e) {}
    return [
      { id: 'LN-6703-01', memberName: 'นายสมชาย มีสุข', memberId: '04892', department: 'โรงพยาบาลระยอง', phone: '081-234-5678', type: 'คำขอกู้เงินฉุกเฉินออนไลน์', amount: '50,000 บาท', date: '11 มี.ค. 2567', status: 'รอดำเนินการ', currentStep: 2, note: 'รอการตรวจสอบเอกสารและอนุมัติจากเจ้าหน้าที่สินเชื่อ' },
      { id: 'LN-6703-02', memberName: 'นางสาววิมลรัตน์ จันทร์เพ็ญ', memberId: '05120', department: 'สสจ.ระยอง', phone: '089-987-6543', type: 'เงินกู้สามัญเพื่อสวัสดิการ', amount: '400,000 บาท', date: '10 มี.ค. 2567', status: 'รอตรวจเอกสารผู้ค้ำ', currentStep: 2, note: 'ตรวจสอบเอกสารผู้ค้ำประกัน 2 ท่าน' },
      { id: 'REQ-6703-09', memberName: 'นายเอกชัย บุญรอด', memberId: '03411', department: 'รพ.สต.บ้านแลง', phone: '086-555-4321', type: 'ขอเปลี่ยนแปลงค่าหุ้นรายเดือน', amount: '4,000 บ./ด.', date: '10 มี.ค. 2567', status: 'รอดำเนินการ', currentStep: 2, note: 'ขอปรับเพิ่มค่าหุ้นรายเดือนเป็น 4,000 บาท' },
      { id: 'WF-6703-05', memberName: 'นางสาวจารุณี รัตนโชติ', memberId: '06214', department: 'รพ.แกลง', phone: '082-111-2233', type: 'ขอรับสวัสดิการคลอดบุตร', amount: '3,000 บาท', date: '09 มี.ค. 2567', status: 'อนุมัติเรียบร้อยแล้ว', currentStep: 4, note: 'โอนเงินสวัสดิการเข้าบัญชีเรียบร้อย' }
    ];
  });

  if (!isLoggedIn || !user) {
    return (
      <div className="section" style={{ minHeight: '65vh', display: 'flex', alignItems: 'center', background: 'var(--bg-main)' }}>
        <div className="container" style={{ textAlign: 'center', maxWidth: '460px' }}>
          <div className="surface-card shadow-lg" style={{ padding: '3rem 2rem', borderRadius: 'var(--radius-xl)' }}>
            <div style={{ width: '56px', height: '56px', margin: '0 auto 1.25rem auto', borderRadius: '50%', background: 'var(--primary-50)', color: 'var(--primary-600)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <User size={30} />
            </div>
            <h2 style={{ fontSize: '1.3rem', fontWeight: 800, color: 'var(--primary-900)', marginBottom: '0.5rem' }}>
              กรุณาเข้าสู่ระบบ
            </h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', marginBottom: '1.75rem', lineHeight: 1.5 }}>
              เพื่อเข้าถึงหน้าแดชบอร์ดและข้อมูลตามสิทธิ์การใช้งานของท่าน
            </p>
            <button onClick={() => setShowAuthModal(true)} className="btn btn-primary" style={{ width: '100%', padding: '0.8rem' }}>
              <span>เข้าสู่ระบบ</span>
            </button>
          </div>
        </div>
      </div>
    );
  }

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  const handleApproveLoan = (id, remarks) => {
    const updated = loanQueue.map(item => item.id === id ? {
      ...item,
      status: 'อนุมัติเรียบร้อยแล้ว',
      currentStep: 3,
      note: remarks || 'ผ่านการตรวจสอบและอนุมัติจากเจ้าหน้าที่สินเชื่อเรียบร้อยแล้ว',
      lastUpdated: new Date().toLocaleDateString('th-TH')
    } : item);
    setLoanQueue(updated);
    try {
      localStorage.setItem('coop_service_requests', JSON.stringify(updated));
    } catch (e) {}
    toast.success(`อนุมัติคำขอ ${id} เรียบร้อยแล้ว`);
  };

  const handleRejectLoan = (id, remarks) => {
    const updated = loanQueue.map(item => item.id === id ? {
      ...item,
      status: 'ส่งกลับแก้ไข',
      currentStep: 2,
      note: remarks || 'เอกสารไม่สมบูรณ์ กรุณาแนบหลักฐานเพิ่มเติม',
      lastUpdated: new Date().toLocaleDateString('th-TH')
    } : item);
    setLoanQueue(updated);
    try {
      localStorage.setItem('coop_service_requests', JSON.stringify(updated));
    } catch (e) {}
    toast.info(`ส่งกลับคำขอ ${id} ให้สมาชิกแก้ไขเรียบร้อยแล้ว`);
  };

  const handleAddNote = (id, remarks) => {
    const updated = loanQueue.map(item => item.id === id ? {
      ...item,
      note: remarks,
      lastUpdated: new Date().toLocaleDateString('th-TH')
    } : item);
    setLoanQueue(updated);
    try {
      localStorage.setItem('coop_service_requests', JSON.stringify(updated));
    } catch (e) {}
    toast.success(`บันทึกหมายเหตุสำหรับคำขอ ${id} เรียบร้อยแล้ว`);
  };

  const handleUpdateComplaintStatus = (id, newStatus) => {
    const updated = complaintsList.map(c => c.id === id ? { ...c, status: newStatus } : c);
    setComplaintsList(updated);
    try {
      localStorage.setItem('coop_member_complaints', JSON.stringify(updated));
    } catch (e) {}
    if (selectedAdminComplaint && selectedAdminComplaint.id === id) {
      setSelectedAdminComplaint({ ...selectedAdminComplaint, status: newStatus });
    }
  };

  const handleSaveAdminComplaintReply = (id, replyText) => {
    const now = new Date().toLocaleString('th-TH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' น.';
    const updated = complaintsList.map(c => c.id === id ? {
      ...c,
      adminReply: replyText,
      status: 'ตอบกลับแล้ว',
      replyDate: now
    } : c);
    setComplaintsList(updated);
    try {
      localStorage.setItem('coop_member_complaints', JSON.stringify(updated));
    } catch (e) {}
    if (selectedAdminComplaint && selectedAdminComplaint.id === id) {
      setSelectedAdminComplaint({
        ...selectedAdminComplaint,
        adminReply: replyText,
        status: 'ตอบกลับแล้ว',
        replyDate: now
      });
    }
    toast.success(`บันทึกข้อความตอบกลับสำหรับรหัส ${id} เรียบร้อยแล้ว`);
  };

  const handleDeleteComplaint = async (id) => {
    const confirmed = await confirmDialog({
      title: 'ยืนยันการลบรายการ',
      message: `ยืนยันการลบรายการ ${id} หรือไม่? รายการที่ถูกลบจะไม่สามารถกู้คืนได้`,
      confirmText: 'ลบรายการ',
      cancelText: 'ยกเลิก',
      type: 'danger'
    });

    if (confirmed) {
      const updated = complaintsList.filter(c => c.id !== id);
      setComplaintsList(updated);
      try {
        localStorage.setItem('coop_member_complaints', JSON.stringify(updated));
        window.dispatchEvent(new Event('storage'));
      } catch (e) {}
      if (selectedAdminComplaint && selectedAdminComplaint.id === id) {
        setSelectedAdminComplaint(null);
      }
      toast.success(`ลบรายการ ${id} เรียบร้อยแล้ว`);
    }
  };

  const handleMemberSubmitComplaint = (e) => {
    e.preventDefault();
    if (!newMemberComplaint.message.trim()) {
      toast.error('กรุณากรอกรายละเอียดเรื่องร้องเรียน / ข้อเสนอแนะ');
      return;
    }

    const randomSuffix = Math.floor(10 + Math.random() * 90);
    const newId = `TKT-6703${randomSuffix}`;
    const dateStr = new Date().toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });

    const newEntry = {
      id: newId,
      name: user.name || 'สมาชิกสหกรณ์',
      phone: user.phone || '081-234-5678',
      email: user.email || '',
      department: user.department || 'โรงพยาบาลระยอง',
      memberId: user.memberId || user.username || '04892',
      topic: newMemberComplaint.topic || 'ข้อเสนอแนะการให้บริการ',
      message: newMemberComplaint.message.trim(),
      date: dateStr,
      status: 'รอดำเนินการ',
      adminReply: '',
      replyDate: ''
    };

    const updated = [newEntry, ...complaintsList];
    setComplaintsList(updated);
    try {
      localStorage.setItem('coop_member_complaints', JSON.stringify(updated));
      window.dispatchEvent(new Event('storage'));
    } catch (e) {}

    setNewMemberComplaint({ topic: 'ข้อเสนอแนะการให้บริการ', message: '' });
    setMemberComplaintModalOpen(false);
    setActiveTab('complaints');
    toast.success(`ส่งเรื่องร้องเรียน / ข้อเสนอแนะเรียบร้อยแล้ว (รหัสติดตามเรื่อง: ${newId})`, 'ส่งเรื่องสำเร็จ');
  };

  const handleMemberSubmitLoan = (newLoanItem) => {
    const updated = [newLoanItem, ...loanQueue];
    setLoanQueue(updated);
    try {
      localStorage.setItem('coop_service_requests', JSON.stringify(updated));
      window.dispatchEvent(new Event('storage'));
    } catch (e) {}
    setActiveTab('loans');
    toast.success(`ยื่นคำขอกู้เงินออนไลน์เรียบร้อยแล้ว! รหัสคำขอ: ${newLoanItem.id} วงเงิน: ${newLoanItem.amount}`, 'ยื่นกู้เงินสำเร็จ');
  };

  const userRole = user.role || 'member';

  // Filter complaints strictly belonging to this logged-in member
  const myComplaints = complaintsList.filter(item => {
    if (user?.memberId && item?.memberId) {
      return String(item.memberId).trim() === String(user.memberId).trim();
    }
    if (user?.name && item?.name) {
      return item.name.trim() === user.name.trim();
    }
    return false;
  });

  const [apiSummary, setApiSummary] = useState(null);

  useEffect(() => {
    if (isLoggedIn && userRole === 'member') {
      fetchMemberDashboard().then(res => {
        if (res?.success && res?.data?.summary) {
          setApiSummary(res.data.summary);
        }
      }).catch(() => {});
    }
  }, [isLoggedIn, userRole]);

  // Use live API summary data when available, with fallbacks to user object / default state
  const memberShares = apiSummary?.shares?.total_amount ?? user.shares ?? 0;
  const memberMonthlyShare = apiSummary?.shares?.monthly_share ?? user.monthlyShare ?? 0;
  const memberSavings = apiSummary?.deposits?.total_balance ?? user.savings ?? 0;
  const memberLoanBalance = apiSummary?.loans?.total_balance ?? user.loanBalance ?? 0;
  const memberDividendEst = user.dividendEstimated ?? 0;
  const memberLoanRefundEst = user.loanRefundEstimated ?? 0;

  const memberAccounts = apiSummary?.deposits?.accounts && apiSummary.deposits.accounts.length > 0
    ? apiSummary.deposits.accounts.map(acc => ({
        accNo: acc.account_no,
        type: acc.account_type || 'บัญชีเงินฝาก',
        balance: parseFloat(acc.balance || 0),
        status: acc.status === 'active' ? 'ปกติ' : acc.status
      }))
    : (user.accounts && user.accounts.length > 0 ? user.accounts : [
        { accNo: '101-2-04892-1', type: 'ออมทรัพย์สุขใจ', balance: 45300.50, status: 'ปกติ' },
        { accNo: '201-4-04892-8', type: 'ออมทรัพย์พิเศษพลัส', balance: 200000.00, status: 'ปกติ' },
      ]);

  const memberLoans = apiSummary?.loans?.active_loans && apiSummary.loans.active_loans.length > 0
    ? apiSummary.loans.active_loans.map(loan => ({
        contractNo: loan.contract_no,
        type: loan.loan_type || 'เงินกู้สามัญ',
        principal: parseFloat(loan.principal_amount || 0),
        balance: parseFloat(loan.balance || 0),
        monthlyPay: parseFloat(loan.monthly_installment || 0),
        termRemaining: `${loan.remaining_months || 0} งวด`
      }))
    : (user.loans && user.loans.length > 0 ? user.loans : [
        { contractNo: 'ส.66/0129', type: 'เงินกู้สามัญ', principal: 1000000, balance: 820000, monthlyPay: 12500, termRemaining: '96 งวด' },
      ]);

  const memberReceipts = user.recentReceipts && user.recentReceipts.length > 0 ? user.recentReceipts : [
    { receiptNo: 'RC-67020084', period: 'กุมภาพันธ์ 2567', date: '28 ก.พ. 2567', totalAmount: 15500, status: 'ชำระแล้ว' },
    { receiptNo: 'RC-67010079', period: 'มกราคม 2567', date: '31 ม.ค. 2567', totalAmount: 15500, status: 'ชำระแล้ว' },
    { receiptNo: 'RC-66120092', period: 'ธันวาคม 2566', date: '29 ธ.ค. 2566', totalAmount: 15500, status: 'ชำระแล้ว' },
  ];

  return (
    <div className="section" style={{ background: 'var(--bg-main)', minHeight: '85vh', paddingBottom: '3.5rem' }}>
      <div className="container">

        {/* Standardized Dashboard Hero Header */}
        <DashboardHeader
          user={user}
          onEditProfile={() => setEditProfileModalOpen(true)}
          onChangePassword={() => setChangePasswordModalOpen(true)}
          onLogout={handleLogout}
          quickActions={
            userRole === 'member' ? (
              <button
                onClick={() => setMemberComplaintModalOpen(true)}
                className="btn btn-primary btn-sm"
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
              >
                <PlusCircle size={15} />
                <span>ส่งคำร้อง / ข้อเสนอแนะ</span>
              </button>
            ) : userRole === 'super_admin' ? (
              <button
                onClick={() => navigate('/admin/dashboard')}
                className="btn btn-primary btn-sm"
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
              >
                <Settings size={15} />
                <span>เปิดแผงควบคุม CMS</span>
              </button>
            ) : null
          }
        />

        {/* =========================================================================
            ROLE 1: SUPER ADMIN DASHBOARD
            ========================================================================= */}
        {userRole === 'super_admin' && (
          <div className="animate-fade-in">
            {/* KPI Cards Grid */}
            <div className="dashboard-kpi-grid">
              <KpiCard
                title="ผู้ใช้งานทั้งหมดในระบบ"
                value="4,850"
                unit="คน"
                subtitle="Admin 3 / Staff 12 / Member 4,835"
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
                value={complaintsList.filter(c => c.status === 'รอดำเนินการ').length}
                unit="เรื่อง"
                subtitle={`จากทั้งหมด ${complaintsList.length} เรื่อง`}
                icon={MessageSquare}
                variant="primary"
              />
            </div>

            {/* Quick Access to Full CMS */}
            <div
              className="surface-card"
              style={{
                border: '1px solid rgba(30, 58, 138, 0.2)',
                borderRadius: 'var(--radius-xl)',
                padding: '1.5rem 1.75rem',
                marginBottom: '2rem',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                flexWrap: 'wrap',
                gap: '1.25rem',
                background: 'linear-gradient(135deg, rgba(30, 58, 138, 0.05), rgba(244, 63, 94, 0.05))'
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                <div style={{ padding: '0.85rem', borderRadius: '12px', background: 'var(--primary-600)', color: '#ffffff' }}>
                  <Settings size={22} />
                </div>
                <div>
                  <h4 style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--primary-900)', margin: '0 0 0.2rem 0' }}>
                    แผงควบคุมระบบเว็บไซต์แบบเต็มรูปแบบ (Super Admin Full CMS)
                  </h4>
                  <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: 0 }}>
                    จัดการประกาศ, ข่าวสาร, Hero Section, Pop-up แคมเปญ, และคำถาม FAQs 6 โมดูลครบวงจร
                  </p>
                </div>
              </div>
              <button
                onClick={() => navigate('/admin/dashboard')}
                className="btn btn-primary"
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
              >
                <span>เปิดแผงควบคุม CMS เต็มรูปแบบ</span>
                <ExternalLink size={15} />
              </button>
            </div>

            {/* Complaints & Feedback Management */}
            <div className="surface-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '2rem' }}>
              <SectionHeader
                title="กล่องข้อเสนอแนะและเรื่องร้องเรียนจากสมาชิก"
                subtitle="ติดตามเรื่องร้องเรียน ตรวจสอบข้อเสนอแนะ และเขียนตอบกลับให้สมาชิกทราบแบบเรียลไทม์"
                icon={MessageSquare}
                iconColor="var(--accent-rose)"
                filters={
                  <div style={{ display: 'flex', gap: '0.35rem', flexWrap: 'wrap' }}>
                    {[
                      { key: 'all', label: `ทั้งหมด (${complaintsList.length})` },
                      { key: 'complaint', label: `เรื่องร้องเรียน (${complaintsList.filter(c => c.topic.includes('ร้องเรียน')).length})` },
                      { key: 'feedback', label: `ข้อเสนอแนะ (${complaintsList.filter(c => c.topic.includes('ข้อเสนอแนะ')).length})` },
                      { key: 'pending', label: `รอดำเนินการ (${complaintsList.filter(c => c.status === 'รอดำเนินการ').length})` },
                      { key: 'replied', label: `ตอบกลับแล้ว (${complaintsList.filter(c => c.status === 'ตอบกลับแล้ว').length})` }
                    ].map(tab => (
                      <button
                        key={tab.key}
                        onClick={() => setComplaintFilter(tab.key)}
                        className={`btn btn-sm ${complaintFilter === tab.key ? 'btn-primary' : 'btn-subtle'}`}
                        style={{ fontSize: '0.78rem' }}
                      >
                        {tab.label}
                      </button>
                    ))}
                  </div>
                }
              />

              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                {complaintsList
                  .filter(item => {
                    if (complaintFilter === 'complaint') return item.topic.includes('ร้องเรียน');
                    if (complaintFilter === 'feedback') return item.topic.includes('ข้อเสนอแนะ');
                    if (complaintFilter === 'pending') return item.status === 'รอดำเนินการ';
                    if (complaintFilter === 'replied') return item.status === 'ตอบกลับแล้ว';
                    return true;
                  })
                  .map((item) => (
                    <div
                      key={item.id}
                      style={{
                        background: 'var(--bg-subtle)',
                        padding: '1.25rem',
                        borderRadius: '12px',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: '1rem',
                        border: item.topic.includes('ร้องเรียน') ? '1px solid rgba(244, 63, 94, 0.3)' : '1px solid var(--border-subtle)'
                      }}
                    >
                      <div style={{ flex: 1, minWidth: '260px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.35rem', flexWrap: 'wrap' }}>
                          <span style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--text-main)' }}>{item.name}</span>
                          <span className={`badge ${item.topic.includes('ร้องเรียน') ? 'badge-rose' : 'badge-primary'}`}>{item.topic}</span>
                          <span className="badge badge-subtle">{item.id}</span>
                        </div>

                        <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)', display: 'flex', gap: '1.5rem', flexWrap: 'wrap', marginBottom: '0.35rem' }}>
                          <span>โทร: <strong>{item.phone}</strong></span>
                          <span>สังกัด: <strong>{item.department || 'สมาชิกสหกรณ์'}</strong></span>
                          <span>วันที่: <strong>{item.date}</strong></span>
                        </div>

                        <div style={{ fontSize: '0.88rem', color: 'var(--text-main)', background: 'var(--bg-surface)', padding: '0.75rem', borderRadius: '8px', border: '1px solid var(--border-subtle)', marginBottom: '0.35rem' }}>
                          {item.message}
                        </div>

                        {item.adminReply && (
                          <div style={{ fontSize: '0.8rem', color: 'var(--accent-emerald-dark)', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                            <CheckCircle2 size={14} />
                            <span>ข้อความตอบกลับ: {item.adminReply}</span>
                          </div>
                        )}

                        <div style={{ marginTop: '0.35rem' }}>
                          <StatusBadge status={item.status} size="sm" />
                        </div>
                      </div>

                      <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                        <button
                          onClick={() => {
                            setSelectedAdminComplaint(item);
                            setAdminReplyText(item.adminReply || '');
                          }}
                          className="btn btn-sm btn-primary"
                          style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}
                        >
                          <Eye size={14} />
                          <span>เปิดอ่าน & ตอบกลับ</span>
                        </button>
                        <button
                          onClick={() => handleDeleteComplaint(item.id)}
                          className="btn btn-outline btn-sm"
                          style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)', padding: '0.4rem 0.6rem' }}
                          title="ลบรายการ"
                        >
                          <Trash2 size={14} />
                        </button>
                      </div>
                    </div>
                  ))}

                {complaintsList.length === 0 && (
                  <EmptyState
                    title="ไม่มีรายการเรื่องร้องเรียนหรือข้อเสนอแนะ"
                    description="เมื่อสมาชิกส่งเรื่องร้องเรียนหรือข้อเสนอแนะเข้ามา รายการจะแสดงผลที่นี่ทันที"
                    icon={MessageSquare}
                  />
                )}
              </div>
            </div>

            {/* RBAC Overview & System Logs */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1.5rem' }}>
              <div className="surface-card" style={{ padding: '1.75rem' }}>
                <SectionHeader
                  title="ภาพรวมบทบาทและสิทธิ์ผู้ใช้งาน (RBAC)"
                  icon={Settings}
                />
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                  <div style={{ background: 'var(--bg-subtle)', padding: '0.85rem 1rem', borderRadius: '8px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                      <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>1. Super Admin (ผู้ดูแลระบบสูงสุด)</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>สิทธิ์เต็ม 100% ทุกโมดูล</div>
                    </div>
                    <span className="badge badge-rose">Full Control</span>
                  </div>
                  <div style={{ background: 'var(--bg-subtle)', padding: '0.85rem 1rem', borderRadius: '8px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                      <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>2. Loan & Finance Staff (เจ้าหน้าที่)</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>จัดการสมาชิก เงินกู้ เงินฝาก และเอกสาร</div>
                    </div>
                    <span className="badge badge-primary">Operational</span>
                  </div>
                  <div style={{ background: 'var(--bg-subtle)', padding: '0.85rem 1rem', borderRadius: '8px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                      <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>3. Auditor (ผู้ตรวจสอบกิจการ)</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>ตรวจสอบบัญชี Audit Logs และรายงาน</div>
                    </div>
                    <span className="badge badge-gold">Audit Access</span>
                  </div>
                  <div style={{ background: 'var(--bg-subtle)', padding: '0.85rem 1rem', borderRadius: '8px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                      <div style={{ fontWeight: 700, fontSize: '0.88rem' }}>4. Member (สมาชิกสหกรณ์)</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>ดูข้อมูลตนเอง หุ้น เงินฝาก หนี้สิน e-Receipt</div>
                    </div>
                    <span className="badge badge-emerald">Self Service</span>
                  </div>
                </div>
              </div>

              <div className="surface-card" style={{ padding: '1.75rem' }}>
                <SectionHeader
                  title="บันทึกความปลอดภัยล่าสุด (System Audit Trail)"
                  icon={ShieldCheck}
                />
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem', fontSize: '0.82rem' }}>
                  <div style={{ borderBottom: '1px solid var(--border-subtle)', paddingBottom: '0.5rem' }}>
                    <span style={{ color: 'var(--text-muted)' }}>วันนี้ 13:45 น.</span> • เจ้าหน้าที่อนุมัติคำขอยื่นกู้ฉุกเฉินออนไลน์ LN-6703-01
                  </div>
                  <div style={{ borderBottom: '1px solid var(--border-subtle)', paddingBottom: '0.5rem' }}>
                    <span style={{ color: 'var(--text-muted)' }}>วันนี้ 12:30 น.</span> • ผู้ดูแลระบบอัปเดตการตั้งค่าประกาศทางการ
                  </div>
                  <div style={{ borderBottom: '1px solid var(--border-subtle)', paddingBottom: '0.5rem' }}>
                    <span style={{ color: 'var(--text-muted)' }}>วันนี้ 04:00 น.</span> • สำรองฐานข้อมูลอัตโนมัติประจำวันเสร็จสมบูรณ์
                  </div>
                </div>
              </div>
            </div>

          </div>
        )}

        {/* =========================================================================
            ROLE 2: STAFF (LOAN & FINANCE OFFICER WORKFLOW)
            ========================================================================= */}
        {userRole === 'staff' && (
          <div className="animate-fade-in">
            {/* KPI Summary Cards */}
            <div className="dashboard-kpi-grid">
              <KpiCard
                title="คำขอรอดำเนินการ (Pending)"
                value={loanQueue.filter(i => !i.status.includes('เรียบร้อย') && !i.status.includes('Approved')).length}
                unit="รายการ"
                subtitle="คำขอกู้ฉุกเฉินและบริการสมาชิก"
                icon={Clock}
                variant="gold"
              />
              <KpiCard
                title="วงเงินรอเบิกจ่ายรวม"
                value="450,000"
                unit="บาท"
                subtitle="พร้อมโอนเข้าบัญชีสมาชิก"
                icon={Coins}
                variant="primary"
              />
              <KpiCard
                title="อนุมัติแล้ววันนี้ (Completed)"
                value={loanQueue.filter(i => i.status.includes('เรียบร้อย') || i.status.includes('Approved')).length}
                unit="รายการ"
                subtitle="ดำเนินการเสร็จสมบูรณ์"
                icon={CheckCircle2}
                variant="emerald"
              />
              <KpiCard
                title="ส่งกลับแก้ไข (Revisions)"
                value={loanQueue.filter(i => i.status.includes('แก้ไข')).length}
                unit="รายการ"
                subtitle="รอสมาชิกส่งเอกสารเพิ่มเติม"
                icon={AlertCircle}
                variant="rose"
              />
            </div>

            {/* RYCOOP LED Member Check Integration Card for Staff */}
            <div className="rounded-3xl p-6 lg:p-7 mb-8 border transition-all duration-300 shadow-sm
              bg-gradient-to-br from-slate-50 via-white to-sky-50/40 border-slate-200/80
              dark:from-[#0b1329] dark:via-[#0f1d3d] dark:to-[#091124] dark:border-blue-900/50 dark:shadow-[0_8px_32px_rgba(7,59,116,0.25)]">
              <div className="flex flex-col gap-5">
                <div className="flex justify-between items-start flex-wrap gap-4">
                  <div className="flex items-center gap-3.5">
                    <div className="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 shadow-md
                      bg-gradient-to-br from-[#073B74] to-teal-700 text-white
                      dark:from-blue-600 dark:to-teal-500 dark:shadow-[0_0_20px_rgba(59,130,246,0.35)]">
                      <Scale size={24} />
                    </div>
                    <div>
                      <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="text-lg lg:text-xl font-extrabold text-slate-900 dark:text-white m-0">
                          RYCOOP LED Member Check
                        </h3>
                        <span className="px-2.5 py-0.5 rounded-full text-xs font-bold
                          bg-emerald-100 text-emerald-800 border border-emerald-300
                          dark:bg-emerald-950/70 dark:text-emerald-300 dark:border-emerald-600/50">
                          สิทธิ์ตัดสินใจเทียบเท่า Admin
                        </span>
                      </div>
                      <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                        ระบบตรวจสอบสมาชิกสหกรณ์กับฐานข้อมูลคดีล้มละลาย/บังคับคดี กรมบังคับคดี (LED Open Data)
                      </p>
                    </div>
                  </div>
                  <Link
                    to="/admin/led/dashboard"
                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold text-white shadow-md transition-all
                      bg-gradient-to-r from-[#073B74] to-[#0B5ED7] hover:brightness-110
                      dark:from-blue-600 dark:to-indigo-600 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]"
                  >
                    <LayoutDashboard size={16} />
                    <span>เข้าสู่ระบบ LED Check</span>
                  </Link>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-4 border-t border-slate-200/80 dark:border-slate-800">
                  <Link
                    to="/admin/led/review"
                    className="group flex items-center gap-3 p-3.5 rounded-2xl transition-all duration-200 border
                      bg-white/90 hover:bg-amber-50/60 border-slate-200/80 hover:border-amber-300 shadow-sm
                      dark:bg-slate-800/80 dark:hover:bg-slate-800 dark:border-slate-700/80 dark:hover:border-amber-500/50 dark:shadow-[0_4px_16px_rgba(0,0,0,0.3)]"
                  >
                    <div className="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105
                      bg-amber-100 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 dark:border dark:border-amber-500/40">
                      <CheckSquare size={20} />
                    </div>
                    <div className="min-w-0">
                      <div className="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 group-hover:text-amber-700 dark:group-hover:text-amber-300 transition-colors truncate">
                        คิวรอตรวจสอบ (Review)
                      </div>
                      <div className="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 truncate">
                        พิจารณาและบันทึกผลตัดสินใจ
                      </div>
                    </div>
                  </Link>

                  <Link
                    to="/admin/led/search"
                    className="group flex items-center gap-3 p-3.5 rounded-2xl transition-all duration-200 border
                      bg-white/90 hover:bg-blue-50/60 border-slate-200/80 hover:border-blue-300 shadow-sm
                      dark:bg-slate-800/80 dark:hover:bg-slate-800 dark:border-slate-700/80 dark:hover:border-blue-500/50 dark:shadow-[0_4px_16px_rgba(0,0,0,0.3)]"
                  >
                    <div className="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105
                      bg-blue-100 text-[#073B74] dark:bg-blue-950/70 dark:text-blue-300 dark:border dark:border-blue-500/40">
                      <Search size={20} />
                    </div>
                    <div className="min-w-0">
                      <div className="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 group-hover:text-blue-700 dark:group-hover:text-blue-300 transition-colors truncate">
                        ค้นหาและตรวจรายคน
                      </div>
                      <div className="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 truncate">
                        ตรวจสอบสมาชิกเดี่ยว Real-time
                      </div>
                    </div>
                  </Link>

                  <Link
                    to="/admin/led/batch"
                    className="group flex items-center gap-3 p-3.5 rounded-2xl transition-all duration-200 border
                      bg-white/90 hover:bg-emerald-50/60 border-slate-200/80 hover:border-emerald-300 shadow-sm
                      dark:bg-slate-800/80 dark:hover:bg-slate-800 dark:border-slate-700/80 dark:hover:border-emerald-500/50 dark:shadow-[0_4px_16px_rgba(0,0,0,0.3)]"
                  >
                    <div className="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105
                      bg-emerald-100 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 dark:border dark:border-emerald-500/40">
                      <Layers size={20} />
                    </div>
                    <div className="min-w-0">
                      <div className="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition-colors truncate">
                        ตรวจสอบแบบกลุ่ม (Batch)
                      </div>
                      <div className="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 truncate">
                        เริ่มรอบตรวจตามสังกัด/ช่วงเลข
                      </div>
                    </div>
                  </Link>
                </div>
              </div>
            </div>

            {/* Worklist Section */}
            <div className="surface-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '2rem' }}>
              <SectionHeader
                title="ระบบจัดการและพิจารณาคำขอสมาชิก (Staff Workflow)"
                subtitle="ตรวจสอบคุณสมบัติ อนุมัติวงเงิน หรือส่งกลับแก้ไขแบบเรียลไทม์"
                icon={FileCheck}
                filters={
                  <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap', alignItems: 'center' }}>
                    <div style={{ position: 'relative', width: '200px' }}>
                      <Search size={14} style={{ position: 'absolute', left: '10px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                      <input
                        type="text"
                        placeholder="ค้นหาชื่อ / เลขคำขอ..."
                        className="form-control"
                        style={{ paddingLeft: '2rem', fontSize: '0.8rem', height: '32px' }}
                        value={staffSearch}
                        onChange={(e) => setStaffSearch(e.target.value)}
                      />
                    </div>
                    <button
                      onClick={() => setStaffFilter('all')}
                      className={`btn btn-sm ${staffFilter === 'all' ? 'btn-primary' : 'btn-subtle'}`}
                      style={{ fontSize: '0.78rem' }}
                    >
                      ทั้งหมด ({loanQueue.length})
                    </button>
                    <button
                      onClick={() => setStaffFilter('pending')}
                      className={`btn btn-sm ${staffFilter === 'pending' ? 'btn-primary' : 'btn-subtle'}`}
                      style={{ fontSize: '0.78rem' }}
                    >
                      รอดำเนินการ ({loanQueue.filter(i => !i.status.includes('เรียบร้อย') && !i.status.includes('Approved')).length})
                    </button>
                    <button
                      onClick={() => setStaffFilter('approved')}
                      className={`btn btn-sm ${staffFilter === 'approved' ? 'btn-primary' : 'btn-subtle'}`}
                      style={{ fontSize: '0.78rem' }}
                    >
                      อนุมัติแล้ว ({loanQueue.filter(i => i.status.includes('เรียบร้อย') || i.status.includes('Approved')).length})
                    </button>
                  </div>
                }
              />

              {/* Worklist Items */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                {loanQueue
                  .filter(item => {
                    if (staffFilter === 'pending') return !item.status.includes('เรียบร้อย') && !item.status.includes('Approved');
                    if (staffFilter === 'approved') return item.status.includes('เรียบร้อย') || item.status.includes('Approved');
                    return true;
                  })
                  .filter(item => {
                    if (!staffSearch.trim()) return true;
                    const q = staffSearch.toLowerCase();
                    return item.memberName.toLowerCase().includes(q) || item.id.toLowerCase().includes(q);
                  })
                  .map((item) => {
                    const isApproved = item.status.includes('เรียบร้อย') || item.status.includes('Approved');

                    return (
                      <div
                        key={item.id}
                        style={{
                          background: 'var(--bg-subtle)',
                          padding: '1.25rem',
                          borderRadius: '12px',
                          display: 'flex',
                          justifyContent: 'space-between',
                          alignItems: 'center',
                          flexWrap: 'wrap',
                          gap: '1rem',
                          border: isApproved ? '1px solid rgba(16, 185, 129, 0.3)' : '1px solid var(--border-subtle)'
                        }}
                      >
                        <div style={{ flex: 1, minWidth: '260px' }}>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.35rem', flexWrap: 'wrap' }}>
                            <span style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--text-main)' }}>{maskName(item.memberName)}</span>
                            <span className="badge badge-primary">{item.type}</span>
                            <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>({item.id})</span>
                          </div>

                          <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)', display: 'flex', gap: '1.5rem', flexWrap: 'wrap' }}>
                            <span>วงเงินที่ขอ: <strong style={{ color: 'var(--primary-700)' }}>{item.amount}</strong></span>
                            <span>วันที่ยื่น: <strong>{item.date || item.submitDate}</strong></span>
                            <span>สังกัด: <strong>{item.department || 'โรงพยาบาลระยอง'}</strong></span>
                          </div>

                          {item.note && (
                            <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '0.35rem', fontStyle: 'italic' }}>
                              📝 หมายเหตุ: {item.note}
                            </div>
                          )}

                          <div style={{ marginTop: '0.35rem' }}>
                            <StatusBadge status={item.status} size="sm" />
                          </div>
                        </div>

                        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                          <button
                            onClick={() => {
                              setSelectedReviewRequest(item);
                              setReviewModalOpen(true);
                            }}
                            className={`btn btn-sm ${isApproved ? 'btn-outline' : 'btn-teal'}`}
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}
                          >
                            <ShieldCheck size={15} />
                            <span>{isApproved ? 'ดูผลการอนุมัติ' : 'ตรวจสอบ & พิจารณา'}</span>
                          </button>
                        </div>
                      </div>
                    );
                  })}

                {loanQueue.length === 0 && (
                  <EmptyState
                    title="ไม่มีรายการคำขอในคิว"
                    description="ไม่มีคำขอใหม่ที่ต้องดำเนินการในขณะนี้"
                    icon={FileCheck}
                  />
                )}
              </div>

            </div>
          </div>
        )}

        {/* =========================================================================
            ROLE 3: AUDITOR / MANAGER (READ-ONLY OVERSIGHT)
            ========================================================================= */}
        {userRole === 'auditor' && (
          <div className="animate-fade-in">
            {/* KPI Summary Cards */}
            <div className="dashboard-kpi-grid">
              <KpiCard
                title="คะแนนความถูกต้องทางบัญชี"
                value="99.8%"
                subtitle="ผ่านเกณฑ์มาตรฐานกรมส่งเสริมสหกรณ์"
                icon={ShieldCheck}
                variant="gold"
              />
              <KpiCard
                title="สินทรัพย์ที่ตรวจสอบแล้ว"
                value="3,210.80"
                unit="ล้านบาท"
                subtitle="กระทบยอดตรงกับสมุดบัญชีแยกประเภท"
                icon={Landmark}
                variant="teal"
              />
              <KpiCard
                title="รายการที่รอตรวจสอบ"
                value="0"
                unit="รายการ"
                subtitle="ตรวจสอบครบถ้วนทุกรอบเดือน"
                icon={CheckCircle2}
                variant="emerald"
              />
              <KpiCard
                title="อัตราผลตอบแทนต่อสินทรัพย์ (ROA)"
                value="4.85%"
                subtitle="ประสิทธิภาพการบริหารจัดการเงินทุน"
                icon={TrendingUp}
                variant="primary"
              />
            </div>

            <div className="surface-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '2rem' }}>
              <SectionHeader
                title="รายงานการตรวจสอบงบการเงินและเงินปันผลประจำงวด (Auditor Verification)"
                subtitle="รายงานการสุ่มตรวจยอดเงินกู้ เงินฝาก และสูตรคำนวณเงินปันผลเฉลี่ยคืนตามระเบียบข้อบังคับ"
                icon={FileSpreadsheet}
                iconColor="var(--accent-gold-dark)"
                actionButton={
                  <button onClick={() => toast.info('จำลองการดาวน์โหลดรายงานผลการตรวจสอบกิจการฉบับเต็ม (PDF)')} className="btn btn-gold btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}>
                    <Download size={15} />
                    <span>ดาวน์โหลดรายงานผลการตรวจสอบ (PDF)</span>
                  </button>
                }
              />

              <div style={{ background: 'var(--bg-subtle)', padding: '1.25rem', borderRadius: '12px', border: '1px solid var(--border-subtle)', lineHeight: 1.6, fontSize: '0.9rem', color: 'var(--text-main)', marginBottom: '1.5rem' }}>
                <div style={{ fontWeight: 700, color: 'var(--primary-900)', marginBottom: '0.5rem' }}>
                  สรุปความเห็นของผู้ตรวจสอบกิจการ:
                </div>
                <p style={{ margin: 0 }}>
                  ผู้ตรวจสอบกิจการได้ทำการสุ่มตรวจยอดเงินกู้ เงินฝาก และสูตรคำนวณเงินปันผลหุ้น 5.25% และเงินเฉลี่ยคืน 12.50% พบว่าถูกต้องตามระเบียบข้อบังคับ และการบันทึกบัญชีเป็นไปตามมาตรฐานการบัญชีของสหกรณ์ออมทรัพย์
                </p>
              </div>

              {/* Read-only Audit Trail */}
              <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: '1.25rem' }}>
                <h4 style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--primary-900)', marginBottom: '0.75rem' }}>
                  ประวัติการตรวจสอบล่าสุด (Audit Trail - Read Only):
                </h4>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.82rem' }}>
                  <div style={{ background: 'var(--bg-surface)', padding: '0.75rem 1rem', borderRadius: '8px', border: '1px solid var(--border-subtle)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span>ตรวจสอบการกระทบยอดบัญชีเงินฝากธนาคารพาณิชย์</span>
                    <span className="badge badge-emerald">ตรงกับยอดบัญชี</span>
                  </div>
                  <div style={{ background: 'var(--bg-surface)', padding: '0.75rem 1rem', borderRadius: '8px', border: '1px solid var(--border-subtle)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span>สุ่มตรวจสัญญาสินเชื่อเงินกู้สามัญ ประจำงวด ก.พ. 2567</span>
                    <span className="badge badge-emerald">เอกสารครบถ้วน</span>
                  </div>
                  <div style={{ background: 'var(--bg-surface)', padding: '0.75rem 1rem', borderRadius: '8px', border: '1px solid var(--border-subtle)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span>ตรวจสอบการคำนวณเงินปันผลและเงินเฉลี่ยคืน</span>
                    <span className="badge badge-emerald">สูตรคำนวณถูกต้อง</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* =========================================================================
            ROLE 4: REGULAR COOPERATIVE MEMBER DASHBOARD
            ========================================================================= */}
        {userRole === 'member' && (
          <div className="animate-fade-in">
            {/* 4 KPI Balances Cards */}
            <div className="dashboard-kpi-grid">
              <KpiCard
                title="ทุนเรือนหุ้นสะสม"
                value={memberShares.toLocaleString()}
                unit="บาท"
                subtitle={`ส่งเพิ่มเดือนละ ${memberMonthlyShare.toLocaleString()} บ.`}
                icon={Coins}
                variant="primary"
              />
              <KpiCard
                title="เงินฝากรวมทุกบัญชี"
                value={memberSavings.toLocaleString()}
                unit="บาท"
                subtitle={`รวม ${memberAccounts.length} บัญชีเงินฝาก`}
                icon={Landmark}
                variant="teal"
              />
              <KpiCard
                title="หนี้สินเงินกู้คงเหลือ"
                value={memberLoanBalance.toLocaleString()}
                unit="บาท"
                subtitle="สัญญา ส.66/0129 (เหลือ 96 งวด)"
                icon={CreditCard}
                variant="rose"
              />
              <KpiCard
                title="ปันผล + เฉลี่ยคืน (ประมาณการ)"
                value={(memberDividendEst + memberLoanRefundEst).toLocaleString()}
                unit="บาท"
                subtitle={`ปันผล ${memberDividendEst.toLocaleString()} + คืน ${memberLoanRefundEst.toLocaleString()} บ.`}
                icon={TrendingUp}
                variant="gold"
              />
            </div>

            {/* Tab Navigation (4-column Grid Layout) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">

              {/* 1. Accounts & Loans Overview */}
              <button
                onClick={() => setActiveTab('overview')}
                className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
                  activeTab === 'overview'
                    ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                    : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
                }`}
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                    activeTab === 'overview'
                      ? 'bg-white/20 text-white'
                      : 'bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-400 dark:border dark:border-teal-900/50'
                  }`}>
                    <Landmark size={18} />
                  </div>
                  <div className="min-w-0">
                    <div className="text-xs sm:text-sm font-bold truncate">1. เงินฝาก & หนี้สิน</div>
                    <div className={`text-[11px] truncate ${activeTab === 'overview' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                      บัญชีเงินฝากและสัญญาเงินกู้
                    </div>
                  </div>
                </div>
                <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
                  activeTab === 'overview'
                    ? 'bg-white/25 text-white'
                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                }`}>
                  ภาพรวม
                </span>
              </button>

              {/* 2. Receipts */}
              <button
                onClick={() => setActiveTab('receipts')}
                className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
                  activeTab === 'receipts'
                    ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                    : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
                }`}
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                    activeTab === 'receipts'
                      ? 'bg-white/20 text-white'
                      : 'bg-blue-50 text-[#073B74] dark:bg-blue-950/60 dark:text-blue-400 dark:border dark:border-blue-900/50'
                  }`}>
                    <FileText size={18} />
                  </div>
                  <div className="min-w-0">
                    <div className="text-xs sm:text-sm font-bold truncate">2. ใบเสร็จ e-Receipt</div>
                    <div className={`text-[11px] truncate ${activeTab === 'receipts' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                      ประวัติและพิมพ์ใบเสร็จ
                    </div>
                  </div>
                </div>
                <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
                  activeTab === 'receipts'
                    ? 'bg-white/25 text-white'
                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                }`}>
                  e-Doc
                </span>
              </button>

              {/* 3. Feedback & Complaints */}
              <button
                onClick={() => setActiveTab('complaints')}
                className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
                  activeTab === 'complaints'
                    ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                    : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
                }`}
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                    activeTab === 'complaints'
                      ? 'bg-white/20 text-white'
                      : 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-400 dark:border dark:border-cyan-900/50'
                  }`}>
                    <MessageSquare size={18} />
                  </div>
                  <div className="min-w-0">
                    <div className="text-xs sm:text-sm font-bold truncate">3. ข้อเสนอแนะ/ร้องเรียน</div>
                    <div className={`text-[11px] truncate ${activeTab === 'complaints' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                      ติดตามสถานะเรื่องร้องเรียน
                    </div>
                  </div>
                </div>
                <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
                  activeTab === 'complaints'
                    ? 'bg-white/25 text-white'
                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                }`}>
                  {myComplaints.length}
                </span>
              </button>

              {/* 4. Quick Services & Calculators */}
              <button
                onClick={() => setActiveTab('services')}
                className={`group flex items-center justify-between p-3.5 rounded-2xl border text-left transition-all duration-200 shadow-sm ${
                  activeTab === 'services'
                    ? 'bg-gradient-to-r from-[#073B74] to-[#0B5ED7] text-white border-[#073B74] shadow-md dark:from-blue-600 dark:to-indigo-600 dark:border-blue-500/60 dark:shadow-[0_0_18px_rgba(37,99,235,0.4)]'
                    : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800/90 dark:text-slate-300 dark:hover:text-white dark:border-slate-800'
                }`}
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105 ${
                    activeTab === 'services'
                      ? 'bg-white/20 text-white'
                      : 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 dark:border dark:border-amber-900/50'
                  }`}>
                    <Calculator size={18} />
                  </div>
                  <div className="min-w-0">
                    <div className="text-xs sm:text-sm font-bold truncate">4. บริการ & เครื่องคำนวณ</div>
                    <div className={`text-[11px] truncate ${activeTab === 'services' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
                      คำนวณเงินกู้และเงินปันผล
                    </div>
                  </div>
                </div>
                <span className={`px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0 ${
                  activeTab === 'services'
                    ? 'bg-white/25 text-white'
                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                }`}>
                  เครื่องมือ
                </span>
              </button>

            </div>

            {/* TAB 1: ACCOUNTS & LOANS OVERVIEW */}
            {activeTab === 'overview' && (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1.5rem' }}>
                {/* Deposit Accounts */}
                <div className="surface-card" style={{ padding: '1.75rem' }}>
                  <SectionHeader
                    title="บัญชีเงินฝากของสมาชิก"
                    icon={Landmark}
                    iconColor="var(--accent-teal)"
                  />
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                    {memberAccounts.map((acc, idx) => (
                      <div key={idx} style={{ background: 'var(--bg-subtle)', padding: '1rem', borderRadius: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', border: '1px solid var(--border-subtle)' }}>
                        <div>
                          <div style={{ fontWeight: 700, fontSize: '0.95rem', color: 'var(--text-main)' }}>{acc.type}</div>
                          <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>เลขที่บัญชี: {acc.accNo}</div>
                        </div>
                        <div style={{ textAlign: 'right' }}>
                          <div style={{ fontWeight: 800, fontSize: '1.1rem', color: 'var(--accent-teal-dark)' }}>
                            {acc.balance.toLocaleString()} ฿
                          </div>
                          <StatusBadge status={acc.status} size="sm" />
                        </div>
                      </div>
                    ))}
                    {memberAccounts.length === 0 && (
                      <EmptyState
                        title="ไม่มีบัญชีเงินฝาก"
                        description="ท่านยังไม่มีบัญชีเงินฝากที่เปิดไว้กับสหกรณ์"
                        icon={Landmark}
                      />
                    )}
                  </div>
                </div>

                {/* Loans Overview */}
                <div className="surface-card" style={{ padding: '1.75rem' }}>
                  <SectionHeader
                    title="สัญญาเงินกู้ที่ผูกพัน"
                    icon={CreditCard}
                    iconColor="var(--accent-rose)"
                  />
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                    {memberLoans.map((loan, idx) => (
                      <div key={idx} style={{ background: 'var(--bg-subtle)', padding: '1rem', borderRadius: '10px', border: '1px solid var(--border-subtle)' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                          <div style={{ fontWeight: 700, color: 'var(--text-main)' }}>{loan.type} ({loan.contractNo})</div>
                          <span className="badge badge-emerald">ผ่อนชำระปกติ</span>
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem', fontSize: '0.85rem' }}>
                          <div><span style={{ color: 'var(--text-muted)' }}>วงเงินตามสัญญา:</span> <strong>{loan.principal.toLocaleString()} ฿</strong></div>
                          <div><span style={{ color: 'var(--text-muted)' }}>ยอดคงเหลือ:</span> <strong style={{ color: 'var(--accent-rose)' }}>{loan.balance.toLocaleString()} ฿</strong></div>
                          <div><span style={{ color: 'var(--text-muted)' }}>ผ่อนเดือนละ:</span> <strong>{loan.monthlyPay.toLocaleString()} ฿</strong></div>
                          <div><span style={{ color: 'var(--text-muted)' }}>งวดคงเหลือ:</span> <strong>{loan.termRemaining}</strong></div>
                        </div>
                      </div>
                    ))}
                    {memberLoans.length === 0 && (
                      <EmptyState
                        title="ไม่มีสัญญาเงินกู้ค้างชำระ"
                        description="ท่านไม่มีภาระหนี้สินเงินกู้กับสหกรณ์ในขณะนี้"
                        icon={CreditCard}
                      />
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* TAB 2: E-RECEIPTS */}
            {activeTab === 'receipts' && (
              <div className="surface-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>
                <SectionHeader
                  title="ประวัติใบเสร็จรับเงินประจำเดือน (e-Receipt)"
                  subtitle="ตรวจสอบและดาวน์โหลดใบเสร็จรับเงินประจำงวดของสมาชิก"
                  icon={FileText}
                  actionButton={
                    <button onClick={() => navigate('/verify-receipt')} className="btn btn-outline btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}>
                      <CheckCircle2 size={14} />
                      <span>ระบบตรวจสอบลายมือชื่อดิจิทัล (Verify Receipt)</span>
                    </button>
                  }
                />

                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                  {memberReceipts.map((rc, idx) => (
                    <div key={idx} style={{ background: 'var(--bg-subtle)', padding: '1.25rem', borderRadius: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', border: '1px solid var(--border-subtle)' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                        <div style={{ padding: '0.65rem', borderRadius: '10px', background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)' }}>
                          <FileText size={22} />
                        </div>
                        <div>
                          <div style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--text-main)' }}>ใบเสร็จประจำเดือน {rc.period}</div>
                          <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>เลขที่: {rc.receiptNo} • วันที่: {rc.date}</div>
                        </div>
                      </div>

                      <div style={{ display: 'flex', alignItems: 'center', gap: '1.5rem' }}>
                        <div style={{ textAlign: 'right' }}>
                          <div style={{ fontSize: '1.15rem', fontWeight: 800, color: 'var(--primary-700)' }}>
                            {rc.totalAmount.toLocaleString()} บาท
                          </div>
                          <StatusBadge status={rc.status} size="sm" />
                        </div>

                        <button onClick={() => navigate('/verify-receipt')} className="btn btn-outline btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
                          <Download size={14} />
                          <span>ดู/พิมพ์</span>
                        </button>
                      </div>
                    </div>
                  ))}

                  {memberReceipts.length === 0 && (
                    <EmptyState
                      title="ยังไม่มีใบเสร็จรับเงิน"
                      description="เมื่อมีการชำระเงินรายเดือน ระบบจะสร้างใบเสร็จรับเงินอิเล็กทรอนิกส์ให้ท่านทันที"
                      icon={FileText}
                    />
                  )}
                </div>
              </div>
            )}

            {/* TAB 3: MY COMPLAINTS & FEEDBACK */}
            {activeTab === 'complaints' && (
              <div className="surface-card animate-fade-in" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>
                <SectionHeader
                  title="เรื่องร้องเรียน & ข้อเสนอแนะของฉัน (My Feedback)"
                  subtitle="ติดตามสถานะคำร้องและตรวจสอบข้อความตอบกลับจากสหกรณ์ (แสดงเฉพาะรายการของท่าน)"
                  icon={MessageSquare}
                  actionButton={
                    <button
                      onClick={() => setMemberComplaintModalOpen(true)}
                      className="btn btn-primary btn-sm"
                      style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
                    >
                      <PlusCircle size={15} />
                      <span>ส่งเรื่องใหม่</span>
                    </button>
                  }
                  filters={
                    <div style={{ display: 'flex', gap: '0.35rem', flexWrap: 'wrap' }}>
                      <button
                        onClick={() => setMemberComplaintFilter('all')}
                        className={`btn btn-sm ${memberComplaintFilter === 'all' ? 'btn-primary' : 'btn-subtle'}`}
                        style={{ fontSize: '0.78rem' }}
                      >
                        ทั้งหมด ({myComplaints.length})
                      </button>
                      <button
                        onClick={() => setMemberComplaintFilter('pending')}
                        className={`btn btn-sm ${memberComplaintFilter === 'pending' ? 'btn-primary' : 'btn-subtle'}`}
                        style={{ fontSize: '0.78rem' }}
                      >
                        รอดำเนินการ ({myComplaints.filter(c => c.status === 'รอดำเนินการ').length})
                      </button>
                      <button
                        onClick={() => setMemberComplaintFilter('replied')}
                        className={`btn btn-sm ${memberComplaintFilter === 'replied' ? 'btn-primary' : 'btn-subtle'}`}
                        style={{ fontSize: '0.78rem' }}
                      >
                        ตอบกลับแล้ว ({myComplaints.filter(c => c.status === 'ตอบกลับแล้ว').length})
                      </button>
                    </div>
                  }
                />

                <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                  {myComplaints
                    .filter(item => {
                      if (memberComplaintFilter === 'pending') return item.status === 'รอดำเนินการ';
                      if (memberComplaintFilter === 'replied') return item.status === 'ตอบกลับแล้ว';
                      return true;
                    })
                    .map(item => {
                      const isReplied = item.status === 'ตอบกลับแล้ว';

                      return (
                        <div
                          key={item.id}
                          style={{
                            background: 'var(--bg-subtle)',
                            padding: '1.25rem 1.5rem',
                            borderRadius: '12px',
                            border: isReplied ? '1px solid rgba(16, 185, 129, 0.35)' : '1px solid var(--border-subtle)',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: '0.75rem'
                          }}
                        >
                          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.75rem' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
                              <span className="badge badge-primary">{item.id}</span>
                              <span style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--text-main)' }}>{item.topic}</span>
                              <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>• วันที่: {item.date}</span>
                            </div>
                            <StatusBadge status={item.status} size="sm" />
                          </div>

                          <div style={{ background: 'var(--bg-surface)', padding: '0.85rem 1rem', borderRadius: '8px', border: '1px solid var(--border-subtle)', fontSize: '0.9rem', color: 'var(--text-main)', lineHeight: 1.5 }}>
                            <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', fontWeight: 600, marginBottom: '0.35rem' }}>
                              💬 ข้อความที่ส่ง:
                            </div>
                            {item.message}
                          </div>

                          {item.adminReply ? (
                            <div style={{
                              background: 'rgba(16, 185, 129, 0.08)',
                              borderLeft: '4px solid var(--accent-emerald)',
                              padding: '1rem 1.25rem',
                              borderRadius: '0 8px 8px 0',
                              fontSize: '0.88rem'
                            }}>
                              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.4rem', flexWrap: 'wrap', gap: '0.5rem' }}>
                                <span style={{ fontWeight: 700, color: 'var(--accent-emerald-dark)', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                                  <CheckCircle2 size={16} />
                                  <span>ข้อความชี้แจงจากสหกรณ์:</span>
                                </span>
                                {item.replyDate && (
                                  <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>
                                    {item.replyDate}
                                  </span>
                                )}
                              </div>
                              <p style={{ margin: 0, color: 'var(--text-main)', lineHeight: 1.55 }}>
                                {item.adminReply}
                              </p>
                            </div>
                          ) : (
                            <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: '0.35rem', fontStyle: 'italic' }}>
                              <Clock size={14} style={{ color: 'var(--accent-gold)' }} />
                              <span>อยู่ระหว่างการตรวจสอบและพิจารณาโดยเจ้าหน้าที่</span>
                            </div>
                          )}
                        </div>
                      );
                    })}

                  {myComplaints.length === 0 && (
                    <EmptyState
                      title="ยังไม่มีประวัติเรื่องร้องเรียนหรือข้อเสนอแนะ"
                      description="ท่านสามารถส่งข้อเสนอแนะหรือร้องเรียนการให้บริการเพื่อการพัฒนาปรับปรุงได้ตลอดเวลา"
                      icon={MessageSquare}
                      actionButton={
                        <button
                          onClick={() => setMemberComplaintModalOpen(true)}
                          className="btn btn-primary btn-sm"
                        >
                          ส่งข้อเสนอแนะข้อแรก
                        </button>
                      }
                    />
                  )}
                </div>
              </div>
            )}

            {/* TAB 4: QUICK SERVICES */}
            {activeTab === 'services' && (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1.25rem' }}>
                <div className="surface-card" style={{ padding: '1.5rem', textAlign: 'center' }}>
                  <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--primary-50)', color: 'var(--primary-600)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem auto' }}>
                    <Calculator size={24} />
                  </div>
                  <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.35rem' }}>โปรแกรมคำนวณเงินกู้</h4>
                  <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', marginBottom: '1.25rem' }}>จำลองค่างวดและอัตราดอกเบี้ยผ่อนชำระ</p>
                  <button onClick={() => navigate('/calculator')} className="btn btn-outline btn-sm" style={{ width: '100%' }}>
                    เปิดโปรแกรมคำนวณ
                  </button>
                </div>

                <div className="surface-card" style={{ padding: '1.5rem', textAlign: 'center' }}>
                  <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem auto' }}>
                    <TrendingUp size={24} />
                  </div>
                  <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.35rem' }}>ประมาณการเงินปันผล</h4>
                  <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', marginBottom: '1.25rem' }}>คำนวณเงินปันผลหุ้นและเงินเฉลี่ยคืน</p>
                  <button onClick={() => navigate('/dividend-estimator')} className="btn btn-outline btn-sm" style={{ width: '100%' }}>
                    คำนวณเงินปันผล
                  </button>
                </div>

                <div className="surface-card" style={{ padding: '1.5rem', textAlign: 'center' }}>
                  <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem auto' }}>
                    <FileCheck size={24} />
                  </div>
                  <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.35rem' }}>ตรวจสอบความพร้อมกู้</h4>
                  <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', marginBottom: '1.25rem' }}>Checklist เอกสารและคุณสมบัติผู้กู้/ผู้ค้ำ</p>
                  <button onClick={() => navigate('/loan-checklist')} className="btn btn-outline btn-sm" style={{ width: '100%' }}>
                    ตรวจสอบคุณสมบัติ
                  </button>
                </div>
              </div>
            )}

          </div>
        )}

        {/* Staff Review Modal */}
        <StaffReviewModal
          isOpen={reviewModalOpen}
          onClose={() => setReviewModalOpen(false)}
          request={selectedReviewRequest}
          onApprove={handleApproveLoan}
          onReject={handleRejectLoan}
          onAddNote={handleAddNote}
        />

        {/* Super Admin Complaint Details & Reply Modal */}
        {selectedAdminComplaint && (
          <div style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: 2500,
            background: 'rgba(15, 23, 42, 0.75)',
            backdropFilter: 'blur(8px)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '1rem',
            overflowY: 'auto'
          }}>
            <div className="surface-card animate-fade-in" style={{
              width: '100%',
              maxWidth: '620px',
              borderRadius: 'var(--radius-xl)',
              padding: '2rem',
              position: 'relative',
              maxHeight: '92vh',
              overflowY: 'auto',
              border: '1px solid var(--border-subtle)'
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', borderBottom: '1px solid var(--border-subtle)', paddingBottom: '1rem', marginBottom: '1.25rem' }}>
                <div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.35rem' }}>
                    <span className="badge badge-primary">{selectedAdminComplaint.id}</span>
                    <StatusBadge status={selectedAdminComplaint.status} size="sm" />
                  </div>
                  <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-900)', fontWeight: 800, margin: 0 }}>
                    {selectedAdminComplaint.topic}
                  </h3>
                  <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '0.2rem' }}>
                    วันที่: <strong>{selectedAdminComplaint.date}</strong> • ผู้ส่ง: <strong>{selectedAdminComplaint.name}</strong>
                  </div>
                </div>
                <button onClick={() => setSelectedAdminComplaint(null)} style={{ background: 'transparent', border: 'none', fontSize: '1.25rem', cursor: 'pointer', color: 'var(--text-muted)' }}>✕</button>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '1.15rem', fontSize: '0.9rem', marginBottom: '1.5rem' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem', background: 'var(--bg-subtle)', padding: '0.85rem 1rem', borderRadius: '8px', fontSize: '0.82rem' }}>
                  <div>📞 <strong>เบอร์โทร:</strong> {selectedAdminComplaint.phone}</div>
                  <div>✉️ <strong>อีเมล:</strong> {selectedAdminComplaint.email || '-'}</div>
                  <div style={{ gridColumn: 'span 2' }}>🏢 <strong>สังกัด/หน่วยงาน:</strong> {selectedAdminComplaint.department || 'สมาชิกทั่วไป'}</div>
                </div>

                <div style={{ background: 'var(--bg-subtle)', padding: '1rem', borderRadius: '10px', border: '1px solid var(--border-subtle)' }}>
                  <div style={{ fontWeight: 700, color: 'var(--text-main)', marginBottom: '0.4rem' }}>
                    ข้อความเรื่องร้องเรียน / ข้อเสนอแนะ:
                  </div>
                  <p style={{ color: 'var(--text-main)', lineHeight: 1.6, margin: 0 }}>
                    {selectedAdminComplaint.message}
                  </p>
                </div>

                <div>
                  <label className="form-label" style={{ fontWeight: 700, marginBottom: '0.5rem', display: 'block' }}>
                    ปรับเปลี่ยนสถานะ:
                  </label>
                  <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                    {['รอดำเนินการ', 'กำลังตรวจสอบ', 'ตอบกลับแล้ว'].map((st) => (
                      <button
                        key={st}
                        type="button"
                        onClick={() => handleUpdateComplaintStatus(selectedAdminComplaint.id, st)}
                        className={`btn btn-sm ${selectedAdminComplaint.status === st ? 'btn-primary' : 'btn-subtle'}`}
                        style={{ fontSize: '0.8rem' }}
                      >
                        {st}
                      </button>
                    ))}
                  </div>
                </div>

                <div style={{ background: 'rgba(14, 165, 233, 0.05)', padding: '1.25rem', borderRadius: '10px', border: '1px solid rgba(14, 165, 233, 0.25)' }}>
                  <label className="form-label" style={{ fontWeight: 700, color: 'var(--primary-800)', display: 'flex', alignItems: 'center', gap: '0.35rem', marginBottom: '0.5rem' }}>
                    <CheckCircle2 size={16} style={{ color: 'var(--accent-emerald-dark)' }} />
                    <span>พิมพ์ข้อความตอบกลับจากสหกรณ์:</span>
                  </label>
                  <textarea
                    className="form-control"
                    rows={3}
                    value={adminReplyText}
                    onChange={(e) => setAdminReplyText(e.target.value)}
                    placeholder="ระบุคำชี้แจง มาตรการแก้ไข หรือผลการตรวจสอบ..."
                    style={{ fontSize: '0.85rem', marginBottom: '0.75rem' }}
                  />
                  <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <button
                      type="button"
                      onClick={() => handleSaveAdminComplaintReply(selectedAdminComplaint.id, adminReplyText)}
                      className="btn btn-primary btn-sm"
                      style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}
                    >
                      <Send size={14} />
                      <span>บันทึกและส่งข้อความตอบกลับ</span>
                    </button>
                  </div>
                </div>
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid var(--border-subtle)', paddingTop: '1rem' }}>
                <button
                  type="button"
                  onClick={() => handleDeleteComplaint(selectedAdminComplaint.id)}
                  className="btn btn-outline btn-sm"
                  style={{ color: 'var(--accent-rose)', borderColor: 'var(--accent-rose)', display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}
                >
                  <Trash2 size={14} />
                  <span>ลบรายการนี้</span>
                </button>
                <button onClick={() => setSelectedAdminComplaint(null)} className="btn btn-subtle btn-sm">
                  <span>ปิดหน้าต่าง</span>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Member Quick Complaint Modal */}
        {memberComplaintModalOpen && (
          <div style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: 2500,
            background: 'rgba(15, 23, 42, 0.75)',
            backdropFilter: 'blur(8px)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '1rem',
            overflowY: 'auto'
          }}>
            <div className="surface-card animate-fade-in" style={{
              width: '100%',
              maxWidth: '560px',
              borderRadius: 'var(--radius-xl)',
              padding: '2rem',
              position: 'relative',
              border: '1px solid var(--border-subtle)'
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid var(--border-subtle)', paddingBottom: '1rem', marginBottom: '1.25rem' }}>
                <h3 style={{ fontSize: '1.2rem', color: 'var(--primary-900)', fontWeight: 800, margin: 0, display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <MessageSquare size={20} style={{ color: 'var(--primary-600)' }} />
                  <span>ส่งเรื่องร้องเรียน / ข้อเสนอแนะ</span>
                </h3>
                <button onClick={() => setMemberComplaintModalOpen(false)} style={{ background: 'transparent', border: 'none', fontSize: '1.25rem', cursor: 'pointer', color: 'var(--text-muted)' }}>✕</button>
              </div>

              <form onSubmit={handleMemberSubmitComplaint} style={{ display: 'flex', flexDirection: 'column', gap: '1.15rem' }}>
                <div style={{ background: 'var(--bg-subtle)', padding: '0.85rem 1rem', borderRadius: '8px', fontSize: '0.85rem', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
                  <div>👤 <strong>ผู้ยื่น:</strong> {user.name || 'สมาชิกสหกรณ์'}</div>
                  <div>🆔 <strong>เลขสมาชิก:</strong> {user.memberId || user.username || '-'}</div>
                  <div style={{ gridColumn: 'span 2' }}>🏢 <strong>สังกัด:</strong> {user.department || 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง'}</div>
                </div>

                <div>
                  <label className="form-label" style={{ fontWeight: 600, fontSize: '0.9rem', marginBottom: '0.4rem', display: 'block' }}>
                    หัวข้อเรื่อง / ประเภทคำร้อง <span style={{ color: 'var(--accent-rose)' }}>*</span>
                  </label>
                  <select
                    className="form-control"
                    value={newMemberComplaint.topic}
                    onChange={(e) => setNewMemberComplaint({ ...newMemberComplaint, topic: e.target.value })}
                    style={{ fontSize: '0.9rem' }}
                  >
                    <option value="ข้อเสนอแนะการให้บริการ">💡 ข้อเสนอแนะการให้บริการ</option>
                    <option value="ร้องเรียนการบริการ">⚠️ ร้องเรียนการบริการ / พฤติกรรมบริการ</option>
                    <option value="แจ้งปัญหาการใช้งานระบบออนไลน์">💻 แจ้งปัญหาการใช้งานระบบออนไลน์</option>
                    <option value="สอบถามข้อมูล/สิทธิประโยชน์สมาชิก">❓ สอบถามข้อมูลทั่วไปและสิทธิประโยชน์</option>
                    <option value="เรื่องอื่นๆ">📝 เรื่องอื่นๆ</option>
                  </select>
                </div>

                <div>
                  <label className="form-label" style={{ fontWeight: 600, fontSize: '0.9rem', marginBottom: '0.4rem', display: 'block' }}>
                    รายละเอียดข้อความ <span style={{ color: 'var(--accent-rose)' }}>*</span>
                  </label>
                  <textarea
                    className="form-control"
                    rows={4}
                    required
                    value={newMemberComplaint.message}
                    onChange={(e) => setNewMemberComplaint({ ...newMemberComplaint, message: e.target.value })}
                    placeholder="โปรดระบุรายละเอียดเรื่องร้องเรียนหรือข้อเสนอแนะของท่านให้ชัดเจน..."
                    style={{ fontSize: '0.88rem' }}
                  />
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '0.5rem' }}>
                  <button
                    type="button"
                    onClick={() => setMemberComplaintModalOpen(false)}
                    className="btn btn-subtle"
                  >
                    ยกเลิก
                  </button>
                  <button
                    type="submit"
                    className="btn btn-primary"
                    style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
                  >
                    <Send size={15} />
                    <span>ส่งเรื่องร้องเรียน</span>
                  </button>
                </div>
              </form>
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

        {/* New Online Loan Request Modal */}
        <NewLoanRequestModal
          isOpen={memberLoanModalOpen}
          onClose={() => setMemberLoanModalOpen(false)}
          onSubmitLoan={handleMemberSubmitLoan}
        />

      </div>
    </div>
  );
}
