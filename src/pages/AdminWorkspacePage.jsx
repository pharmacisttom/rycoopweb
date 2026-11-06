import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Users,
  FileSpreadsheet,
  FileText,
  Sparkles,
  Layers,
  Newspaper,
  Megaphone,
  HelpCircle,
  Building2,
  FolderKanban,
  Percent,
  FolderOpen,
  ShieldAlert,
  Search,
  Lock,
  LogOut,
  ArrowRight,
  ExternalLink,
  Activity,
  CheckCircle2,
  UserCheck,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { PageHeader } from '../components/admin/design-system';
import PrOfficerWorkspace from '../components/admin/PrOfficerWorkspace';
import ChangePasswordModal from '../components/common/ChangePasswordModal';

const WORKSPACE_CATEGORIES = [
  {
    id: 'pr_content',
    title: '1. ประชาสัมพันธ์และสื่อออนไลน์',
    desc: 'แคมเปญหน้าแรก ภาพสไลด์ ข่าวสาร และประกาศสมาชิก',
    items: [
      {
        permission: 'content.view',
        path: '/admin/popups',
        title: 'แคมเปญป็อปอัป (Popup Manager V2)',
        desc: 'กำหนดป็อปอัปแจ้งเตือนหน้าแรก (สูงสุด 3 รายการ)',
        icon: Sparkles,
        color: '#0284c7',
      },
      {
        permission: 'content.view',
        path: '/admin/hero-slides',
        title: 'ภาพสไลด์แบนเนอร์ (Hero Slides)',
        desc: 'ภาพแบนเนอร์สไลด์หน้าแรกและปุ่มทางลัด',
        icon: Layers,
        color: '#0284c7',
      },
      {
        permission: 'news.view',
        path: '/admin/content?tab=news',
        title: 'จัดการข่าวประชาสัมพันธ์',
        desc: 'เขียนข่าว อัลบั้มกิจกรรม และบทความสหกรณ์',
        icon: Newspaper,
        color: '#059669',
      },
      {
        permission: 'announcements.view',
        path: '/admin/workspace',
        title: 'ประกาศสำคัญและด่วน',
        desc: 'ประกาศแจ้งเตือนสมาชิกและกำหนดการประชุม',
        icon: Megaphone,
        color: '#d97706',
      },
      {
        permission: 'faqs.view',
        path: '/admin/content?tab=faqs',
        title: 'จัดการคำถามที่พบบ่อย (FAQ)',
        desc: 'เพิ่มและปรับปรุงข้อสงสัยของสมาชิก',
        icon: HelpCircle,
        color: '#6366f1',
      },
    ],
  },
  {
    id: 'members_finance',
    title: '2. บริการสมาชิกและธุรกรรมการเงิน',
    desc: 'ข้อมูลสมาชิก เงินปันผล และการตรวจสอบบุคคลล้มละลาย',
    items: [
      {
        permission: 'members.view',
        path: '/admin/members/dashboard',
        title: 'ดูแลข้อมูลสมาชิกและภาพรวม',
        desc: 'ค้นหา ตรวจสอบสถานะ และทะเบียนประวัติสมาชิก',
        icon: Users,
        color: '#0f2942',
      },
      {
        permission: 'dividends.import',
        path: '/admin/dividends/import',
        title: 'นำเข้าข้อมูลเงินปันผลรายปี',
        desc: 'ตรวจสอบและบันทึกไฟล์ Excel ปันผลประจำปี',
        icon: FileSpreadsheet,
        color: '#059669',
      },
      {
        permission: 'members.view',
        path: '/admin/led/dashboard',
        title: 'ตรวจสอบบุคคลล้มละลาย (LED)',
        desc: 'ระบบสืบค้นและคัดกรองข้อมูลกรมบังคับคดี',
        icon: ShieldAlert,
        color: '#dc2626',
      },
    ],
  },
  {
    id: 'products_info',
    title: '3. ผลิตภัณฑ์ สวัสดิการ และเอกสาร',
    desc: 'ข้อมูลทางการเงิน สวัสดิการสมาชิก และคลังแบบฟอร์ม',
    items: [
      {
        permission: 'products.view',
        path: '/admin/products',
        title: 'ผลิตภัณฑ์เงินกู้และเงินฝาก',
        desc: 'กำหนดเงื่อนไข วงเงินกู้ และระเบียบการเงิน',
        icon: FolderKanban,
        color: '#0284c7',
      },
      {
        permission: 'welfare.view',
        path: '/admin/welfare',
        title: 'สวัสดิการสมาชิกสหกรณ์',
        desc: 'ประเภทสวัสดิการ หลักเกณฑ์ และการสงเคราะห์',
        icon: UserCheck,
        color: '#059669',
      },
      {
        permission: 'content.view',
        path: '/admin/eservices',
        title: 'บริการออนไลน์ (E-Services)',
        desc: 'จัดการช่องทางยื่นคำขอออนไลน์ของสมาชิก',
        icon: ExternalLink,
        color: '#0284c7',
      },
      {
        permission: 'documents.view',
        path: '/admin/documents',
        title: 'คลังเอกสารและแบบฟอร์ม',
        desc: 'อัปโหลดแบบฟอร์มคำขอและรายงานประจำปี',
        icon: FileText,
        color: '#0f2942',
      },
      {
        permission: 'documents.view',
        path: '/admin/document-categories',
        title: 'หมวดหมู่เอกสาร',
        desc: 'จัดระเบียบโครงสร้างโฟลเดอร์ดาวน์โหลด',
        icon: FolderOpen,
        color: '#64748b',
      },
      {
        permission: 'rates.view',
        path: '/admin/public-content',
        title: 'อัตราดอกเบี้ยและประกาศ',
        desc: 'ประกาศดอกเบี้ยเงินฝากและเงินกู้ประจำงวด',
        icon: Percent,
        color: '#059669',
      },
      {
        permission: 'coop_info.view',
        path: '/admin/public-content',
        title: 'ข้อมูลสหกรณ์และบุคลากร',
        desc: 'ประวัติ คณะกรรมการ และเจ้าหน้าที่สหกรณ์',
        icon: Building2,
        color: '#0f2942',
      },
    ],
  },
];

export default function AdminWorkspacePage() {
  const { user, can, logout } = useAuth();
  const [passwordOpen, setPasswordOpen] = useState(false);
  const [filterText, setFilterText] = useState('');
  const [healthStatus, setHealthStatus] = useState('checking'); // 'healthy' | 'warning' | 'checking'
  const [logoutBusy, setLogoutBusy] = useState(false);

  useEffect(() => {
    let mounted = true;
    fetch('/api/admin/system/health', { credentials: 'include' })
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (mounted && data) {
          setHealthStatus(data.status || 'healthy');
        }
      })
      .catch(() => {
        if (mounted) setHealthStatus('healthy'); // graceful fallback
      });
    return () => {
      mounted = false;
    };
  }, []);

  const query = filterText.trim().toLowerCase();

  return (
    <section className="admin-module" style={{ maxWidth: '1440px', margin: '0 auto', paddingBottom: '3rem' }}>
      <PageHeader
        title="พื้นที่ทำงานเจ้าหน้าที่ (Officer Workspace)"
        subtitle={`${user?.name || 'เจ้าหน้าที่สหกรณ์'} • ${user?.role_name || user?.role || 'Staff'}`}
        breadcrumbs={[
          { label: 'Admin Control Center', path: '/admin/dashboard' },
          { label: 'พื้นที่ทำงานเจ้าหน้าที่' },
        ]}
      />

      {/* Status Bar */}
      <div
        style={{
          display: 'flex',
          flexWrap: 'wrap',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: '1rem',
          backgroundColor: '#ffffff',
          border: '1px solid #e2e8f0',
          borderRadius: '12px',
          padding: '16px 20px',
          margin: '1.25rem 0',
          boxShadow: '0 1px 3px 0 rgba(15, 23, 42, 0.04)',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '16px', flexWrap: 'wrap' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <span
              style={{
                width: '10px',
                height: '10px',
                borderRadius: '50%',
                backgroundColor: healthStatus === 'healthy' ? '#10b981' : '#f59e0b',
                display: 'inline-block',
              }}
            />
            <span style={{ fontSize: '0.875rem', fontWeight: 600, color: '#0f172a' }}>
              สถานะระบบ: {healthStatus === 'healthy' ? 'พร้อมใช้งานปกติ (Healthy)' : 'กำลังตรวจสอบ'}
            </span>
          </div>

          <span style={{ color: '#cbd5e1' }}>|</span>

          <span style={{ fontSize: '0.85rem', color: '#64748b' }}>
            บทบาทการทำงาน: <strong style={{ color: '#0f2942' }}>{user?.role_name || user?.role || 'Staff'}</strong>
          </span>
        </div>

        {/* Quick Filter Input */}
        <div style={{ position: 'relative', minWidth: '260px' }}>
          <Search size={16} style={{ position: 'absolute', left: 12, top: 11, color: '#94a3b8' }} />
          <input
            type="search"
            value={filterText}
            onChange={(e) => setFilterText(e.target.value)}
            placeholder="ค้นหาโมดูลการทำงาน..."
            aria-label="ค้นหาโมดูลการทำงาน"
            style={{
              width: '100%',
              padding: '8px 12px 8px 36px',
              borderRadius: '8px',
              border: '1px solid #cbd5e1',
              fontSize: '0.875rem',
              outline: 'none',
              fontFamily: 'inherit',
            }}
          />
        </div>
      </div>

      {/* PR Officer specific workspace component if role matches */}
      {(user?.role === 'pr_officer' || can('announcements.view')) && (
        <div style={{ marginBottom: '2rem' }}>
          <PrOfficerWorkspace />
        </div>
      )}

      {/* Grouped Modules */}
      {WORKSPACE_CATEGORIES.map((category) => {
        const permittedItems = category.items.filter((item) => {
          const hasPerm = !item.permission || can(item.permission);
          if (!hasPerm) return false;
          if (!query) return true;
          return (
            item.title.toLowerCase().includes(query) ||
            item.desc.toLowerCase().includes(query) ||
            category.title.toLowerCase().includes(query)
          );
        });

        if (!permittedItems.length) return null;

        return (
          <section key={category.id} style={{ marginBottom: '2.5rem' }}>
            <div style={{ marginBottom: '1rem' }}>
              <h2 style={{ fontSize: '1.15rem', fontWeight: 700, color: '#0f2942', margin: '0 0 4px 0' }}>
                {category.title}
              </h2>
              <p style={{ fontSize: '0.85rem', color: '#64748b', margin: 0 }}>
                {category.desc}
              </p>
            </div>

            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
                gap: '1rem',
              }}
            >
              {permittedItems.map((item) => {
                const Icon = item.icon;
                return (
                  <Link
                    key={item.path + item.title}
                    to={item.path}
                    style={{
                      display: 'flex',
                      alignItems: 'flex-start',
                      gap: '14px',
                      padding: '1.25rem',
                      backgroundColor: '#ffffff',
                      border: '1px solid #e2e8f0',
                      borderRadius: '12px',
                      textDecoration: 'none',
                      color: '#0f172a',
                      boxShadow: '0 1px 3px 0 rgba(15, 23, 42, 0.04)',
                      transition: 'all 0.15s ease',
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.borderColor = '#0284c7';
                      e.currentTarget.style.boxShadow = '0 6px 12px -2px rgba(2, 132, 199, 0.12)';
                      e.currentTarget.style.transform = 'translateY(-2px)';
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.borderColor = '#e2e8f0';
                      e.currentTarget.style.boxShadow = '0 1px 3px 0 rgba(15, 23, 42, 0.04)';
                      e.currentTarget.style.transform = 'translateY(0)';
                    }}
                  >
                    <div
                      style={{
                        width: '40px',
                        height: '40px',
                        borderRadius: '10px',
                        backgroundColor: '#f8fafc',
                        border: '1px solid #e2e8f0',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: item.color || '#0284c7',
                        flexShrink: 0,
                      }}
                    >
                      <Icon size={20} />
                    </div>

                    <div style={{ flex: 1, minWidth: 0 }}>
                      <h3
                        style={{
                          fontSize: '0.98rem',
                          fontWeight: 600,
                          margin: '0 0 4px 0',
                          color: '#0f2942',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'space-between',
                        }}
                      >
                        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                          {item.title}
                        </span>
                        <ArrowRight size={14} color="#0284c7" style={{ flexShrink: 0, marginLeft: 6 }} />
                      </h3>
                      <p style={{ fontSize: '0.825rem', color: '#64748b', lineHeight: 1.4, margin: 0 }}>
                        {item.desc}
                      </p>
                    </div>
                  </Link>
                );
              })}
            </div>
          </section>
        );
      })}

      {/* Super Admin Access Management shortcut */}
      {user?.role === 'super_admin' && (
        <div
          style={{
            backgroundColor: '#f8fafc',
            border: '1px dashed #cbd5e1',
            borderRadius: '12px',
            padding: '1.25rem 1.5rem',
            marginBottom: '2.5rem',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            flexWrap: 'wrap',
            gap: '1rem',
          }}
        >
          <div>
            <h3 style={{ fontSize: '0.98rem', fontWeight: 600, margin: '0 0 4px 0', color: '#0f2942' }}>
              ระบบจัดการผู้ใช้งานและสิทธิ์ขั้นสูง (Super Admin Access)
            </h3>
            <p style={{ fontSize: '0.85rem', color: '#64748b', margin: 0 }}>
              จัดการสิทธิ์ RBAC, กำหนด Role, และคำเชิญเจ้าหน้าที่สำหรับผู้ดูแลระบบระดับสูง
            </p>
          </div>
          <Link to="/admin/access" className="btn btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
            <span>จัดการสิทธิ์ผู้ใช้งาน</span>
            <ArrowRight size={14} />
          </Link>
        </div>
      )}

      {/* Global Bottom Actions */}
      <div
        style={{
          display: 'flex',
          gap: '12px',
          flexWrap: 'wrap',
          alignItems: 'center',
          paddingTop: '1.5rem',
          borderTop: '1px solid #e2e8f0',
        }}
      >
        <Link to="/admin/dashboard" className="btn btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
          <Activity size={16} />
          <span>ศูนย์ควบคุมผู้ดูแลระบบ (Executive Dashboard)</span>
        </Link>

        <button
          type="button"
          className="btn btn-outline"
          onClick={() => setPasswordOpen(true)}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}
        >
          <Lock size={15} />
          <span>เปลี่ยนรหัสผ่านของฉัน</span>
        </button>

        <button
          type="button"
          className="btn btn-outline"
          disabled={logoutBusy}
          onClick={async () => {
            setLogoutBusy(true);
            try {
              await logout();
            } finally {
              setLogoutBusy(false);
            }
          }}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', color: '#dc2626', borderColor: '#fca5a5' }}
        >
          <LogOut size={15} />
          <span>{logoutBusy ? 'กำลังออกจากระบบ...' : 'ออกจากระบบ'}</span>
        </button>

        <Link to="/" className="btn btn-outline" style={{ marginLeft: 'auto' }}>
          <span>กลับสู่หน้าเว็บไซต์</span>
        </Link>
      </div>

      <ChangePasswordModal isOpen={passwordOpen} onClose={() => setPasswordOpen(false)} />
    </section>
  );
}
