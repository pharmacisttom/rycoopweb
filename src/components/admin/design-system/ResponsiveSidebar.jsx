import React, { useEffect, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import {
  Activity,
  ChevronDown,
  Database,
  FileSpreadsheet,
  FileText,
  FolderKanban,
  HardDriveDownload,
  KeyRound,
  LayoutDashboard,
  PanelLeftClose,
  PanelLeftOpen,
  Settings,
  ShieldAlert,
  Users,
  X,
  Layers,
  Sparkles
} from 'lucide-react';
import CoopBrandLogo from '../../branding/CoopBrandLogo';
import { useAuth } from '../../../context/AuthContext';

export const ADMIN_NAV_GROUPS = [
  {
    id: 'dashboard',
    label: 'แดชบอร์ด',
    icon: LayoutDashboard,
    items: [
      { label: 'ภาพรวมระบบ', to: '/admin/dashboard', permissions: ['dashboard.view'] },
      { label: 'สถิติผู้เข้าชมเว็บไซต์', to: '/admin/analytics/visitors', permissions: ['analytics.visitors.view', 'analytics.view'] },
      { label: 'ศูนย์การดำเนินงาน', to: '/admin/operations', permissions: ['operations.view', 'dashboard.view'] },
    ],
  },
  {
    id: 'cms',
    label: 'จัดการเนื้อหา & เว็บไซต์',
    icon: FileText,
    items: [
      { label: 'ข่าวสาร & ประกาศ', to: '/admin/content?tab=news', permissions: ['news.view'] },
      { label: 'แบนเนอร์ Hero Slider', to: '/admin/hero-slides', permissions: ['hero_slides.view', 'content.view'] },
      { label: 'ศูนย์เอกสาร & ดาวน์โหลด', to: '/admin/documents', permissions: ['documents.view'] },
      { label: 'คำถามที่พบบ่อย (FAQs)', to: '/admin/content?tab=faqs', permissions: ['faqs.view'] },
      { label: 'ข้อมูลสหกรณ์ & บุคลากร', to: '/admin/public-content', permissions: ['coop_info.view', 'people.view'] },
      { label: 'ลิงก์บริการ E-Services', to: '/admin/eservices', permissions: ['settings.view', 'content.view'] },
    ],
  },
  {
    id: 'finance',
    label: 'การเงิน ดอกเบี้ย & สวัสดิการ',
    icon: FileSpreadsheet,
    items: [
      { label: 'อัตราดอกเบี้ยเงินฝาก/กู้', to: '/admin/rates', permissions: ['rates.view'] },
      { label: 'ผลิตภัณฑ์สินเชื่อและเงินฝาก', to: '/admin/products', permissions: ['products.view'] },
      { label: 'สวัสดิการสมาชิก', to: '/admin/welfare', permissions: ['welfare.view', 'content.view'] },
      { label: 'รายงานการเงิน', to: '/admin/reports?category=finance', permissions: ['finance.view', 'rates.view'] },
    ],
  },
  {
    id: 'members',
    label: 'ระบบสมาชิก & ปันผล',
    icon: Users,
    items: [
      { label: 'ภาพรวมสมาชิก', to: '/admin/members/dashboard', permissions: ['members.view'] },
      { label: 'ค้นหาและจัดการสมาชิก', to: '/admin/members', permissions: ['members.view'] },
      { label: 'ข้อมูลปันผล & นำเข้า', to: '/admin/dividends/import', permissions: ['dividends.import'] },
      { label: 'สถานะตรวจสอบข้อมูล', to: '/admin/members/health', permissions: ['members.view'] },
      { label: 'ตรวจสอบบุคคลล้มละลาย (LED)', to: '/admin/led/dashboard', permissions: ['members.view', 'operations.view'] },
    ],
  },
  {
    id: 'access',
    label: 'ผู้ใช้ & สิทธิ์ RBAC',
    icon: KeyRound,
    items: [
      { label: 'ผู้ใช้งานระบบ', to: '/admin/users', permissions: ['users.view'] },
      { label: 'ทำเนียบเจ้าหน้าที่ (Directory)', to: '/admin/staff', permissions: ['users.view'] },
      { label: 'คำเชิญเจ้าหน้าที่ใหม่', to: '/admin/users?tab=invitations', permissions: ['users.create'] },
      { label: 'บทบาท (Roles)', to: '/admin/roles', permissions: ['roles.view'] },
      { label: 'สิทธิ์การใช้งาน (Permissions)', to: '/admin/permissions', permissions: ['roles.view'] },
      { label: 'หน่วยงานสังกัด (Departments)', to: '/admin/departments', permissions: ['departments.view'] },
      { label: 'จัดการสิทธิ์ขั้นสูง (Super Admin)', to: '/admin/access', roles: ['super_admin'] },
    ],
  },
  {
    id: 'security',
    label: 'ความปลอดภัย & ตรวจสอบ',
    icon: ShieldAlert,
    items: [
      { label: 'Security Dashboard', to: '/admin/security', permissions: ['security.view'] },
      { label: 'สถานะ 2FA / MFA', to: '/admin/security/mfa', permissions: ['security.mfa.view', 'security.view'] },
      { label: 'เซสชันที่ใช้งาน (Active Sessions)', to: '/admin/security/sessions', permissions: ['security.view'] },
      { label: 'Audit Trail & บันทึกกิจกรรม', to: '/admin/audit-logs', permissions: ['audit.view'] },
    ],
  },
  {
    id: 'system',
    label: 'ระบบ & การสำรองข้อมูล',
    icon: Database,
    items: [
      { label: 'Application Health', to: '/admin/health', permissions: ['health.view'] },
      { label: 'การสำรองข้อมูล (Backups)', to: '/admin/backups', permissions: ['backup.view'] },
      { label: 'ตั้งค่าระบบกลาง', to: '/admin/settings', permissions: ['settings.view'] },
    ],
  },
];

export default function ResponsiveSidebar({
  collapsed = false,
  onToggleCollapse,
  mobileOpen = false,
  onCloseMobile,
}) {
  const { user, can } = useAuth();
  const location = useLocation();
  const [openGroups, setOpenGroups] = useState({
    dashboard: true,
    cms: true,
    finance: true,
    members: true,
    access: true,
    security: true,
    system: true,
  });

  const sidebarRef = useRef(null);

  // Close mobile sidebar on route change
  useEffect(() => {
    if (mobileOpen && onCloseMobile) {
      onCloseMobile();
    }
  }, [location.pathname, location.search]);

  // Handle Escape key on mobile drawer
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && mobileOpen && onCloseMobile) {
        onCloseMobile();
      }
    };
    if (mobileOpen) {
      window.addEventListener('keydown', handleKeyDown);
      document.body.style.overflow = 'hidden';
    }
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [mobileOpen, onCloseMobile]);

  const toggleGroup = (groupId) => {
    setOpenGroups((prev) => ({
      ...prev,
      [groupId]: !prev[groupId],
    }));
  };

  const isItemVisible = (item) => {
    if (!user) return false;
    if (user.role === 'super_admin' || user.role_slug === 'super_admin') return true;
    if (item.roles && !item.roles.includes(user.role) && !item.roles.includes(user.role_slug)) return false;
    if (item.permissions && !item.permissions.some((p) => can(p))) return false;
    return true;
  };

  const isItemActive = (to) => {
    const currentPath = location.pathname + location.search;
    if (to === currentPath) return true;
    if (!to.includes('?') && location.pathname === to) return true;
    return false;
  };

  return (
    <>
      {/* Mobile Drawer Backdrop */}
      {mobileOpen && (
        <div
          className="admin-sidebar-backdrop"
          onClick={onCloseMobile}
          aria-hidden="true"
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(15, 23, 42, 0.65)',
            backdropFilter: 'blur(3px)',
            zIndex: 9998,
            transition: 'opacity 0.2s ease',
          }}
        />
      )}

      {/* Main Sidebar Element */}
      <aside
        ref={sidebarRef}
        className={`admin-responsive-sidebar ${mobileOpen ? 'mobile-open' : ''} ${collapsed ? 'collapsed' : ''}`}
        aria-label="เมนูหลักการจัดการระบบ"
        style={{
          width: collapsed ? '80px' : '280px',
          height: '100vh',
          position: 'sticky',
          top: 0,
          left: 0,
          backgroundColor: '#0f2d59', // Deep navy
          color: '#f8fafc',
          display: 'flex',
          flexDirection: 'column',
          zIndex: 9999,
          transition: 'width 0.25s cubic-bezier(0.4, 0, 0.2, 1), transform 0.25s ease',
          boxShadow: '4px 0 20px rgba(0, 0, 0, 0.15)',
          overflow: 'hidden',
          flexShrink: 0,
        }}
      >
        {/* Brand Header */}
        <div
          style={{
            padding: collapsed ? '16px 8px' : '20px 20px 16px 20px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: collapsed ? 'center' : 'space-between',
            borderBottom: '1px solid rgba(255, 255, 255, 0.1)',
            minHeight: '74px',
            boxSizing: 'border-box',
          }}
        >
          <Link
            to="/admin/dashboard"
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '12px',
              textDecoration: 'none',
              color: '#ffffff',
              overflow: 'hidden',
            }}
          >
            <div
              style={{
                width: '38px',
                height: '38px',
                borderRadius: '10px',
                backgroundColor: '#ffffff',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexShrink: 0,
                boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
              }}
            >
              <CoopBrandLogo size={28} />
            </div>
            {!collapsed && (
              <div style={{ display: 'flex', flexDirection: 'column' }}>
                <span style={{ fontSize: '1rem', fontWeight: 700, letterSpacing: '-0.01em', whiteSpace: 'nowrap' }}>
                  RYCOOP Admin
                </span>
                <span style={{ fontSize: '0.72rem', color: '#93c5fd', whiteSpace: 'nowrap' }}>
                  ระบบจัดการ 3.0
                </span>
              </div>
            )}
          </Link>

          {/* Mobile Close Button */}
          <button
            type="button"
            className="mobile-close-btn"
            onClick={onCloseMobile}
            style={{
              background: 'none',
              border: 'none',
              color: '#93c5fd',
              cursor: 'pointer',
              padding: '6px',
              display: 'none', // Handled via responsive CSS
            }}
            aria-label="ปิดเมนูนำทาง"
          >
            <X size={20} />
          </button>
        </div>

        {/* Scrollable Nav Items */}
        <nav
          style={{
            flex: '1 1 auto',
            overflowY: 'auto',
            overflowX: 'hidden',
            padding: collapsed ? '12px 6px' : '16px 12px',
            display: 'flex',
            flexDirection: 'column',
            gap: '8px',
          }}
        >
          {ADMIN_NAV_GROUPS.map((group) => {
            const visibleItems = group.items.filter(isItemVisible);
            if (visibleItems.length === 0) return null;

            const isOpen = openGroups[group.id] !== false;
            const GroupIcon = group.icon;

            return (
              <div key={group.id} style={{ marginBottom: '6px' }}>
                {!collapsed ? (
                  <button
                    type="button"
                    onClick={() => toggleGroup(group.id)}
                    style={{
                      width: '100%',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      padding: '8px 10px',
                      background: 'none',
                      border: 'none',
                      color: '#93c5fd',
                      fontSize: '0.78rem',
                      fontWeight: 700,
                      textTransform: 'uppercase',
                      letterSpacing: '0.04em',
                      cursor: 'pointer',
                      borderRadius: '6px',
                      textAlign: 'left',
                    }}
                    aria-expanded={isOpen}
                  >
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                      <GroupIcon size={15} style={{ opacity: 0.8 }} />
                      <span>{group.label}</span>
                    </span>
                    <ChevronDown
                      size={14}
                      style={{
                        transform: isOpen ? 'rotate(0deg)' : 'rotate(-90deg)',
                        transition: 'transform 0.2s ease',
                      }}
                    />
                  </button>
                ) : (
                  <div
                    style={{
                      display: 'flex',
                      justifyContent: 'center',
                      padding: '8px 0 4px 0',
                      color: '#93c5fd',
                    }}
                    title={group.label}
                  >
                    <GroupIcon size={18} />
                  </div>
                )}

                {/* Submenu Items */}
                {(isOpen || collapsed) && (
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '2px', marginTop: '2px' }}>
                    {visibleItems.map((item) => {
                      const active = isItemActive(item.to);

                      return (
                        <Link
                          key={item.to}
                          to={item.to}
                          title={collapsed ? item.label : undefined}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '10px',
                            padding: collapsed ? '10px 0' : '9px 12px 9px 28px',
                            justifyContent: collapsed ? 'center' : 'flex-start',
                            borderRadius: '8px',
                            textDecoration: 'none',
                            fontSize: '0.88rem',
                            fontWeight: active ? 600 : 400,
                            color: active ? '#ffffff' : '#cbd5e1',
                            backgroundColor: active ? 'rgba(2, 132, 199, 0.45)' : 'transparent',
                            borderLeft: active && !collapsed ? '3px solid #38bdf8' : '3px solid transparent',
                            transition: 'all 0.15s ease',
                          }}
                          className={`admin-nav-item ${active ? 'active' : ''}`}
                        >
                          <span
                            style={{
                              width: '6px',
                              height: '6px',
                              borderRadius: '50%',
                              backgroundColor: active ? '#38bdf8' : 'rgba(255, 255, 255, 0.3)',
                              flexShrink: 0,
                              display: collapsed ? 'none' : 'inline-block',
                            }}
                          />
                          {!collapsed && <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.label}</span>}
                        </Link>
                      );
                    })}
                  </div>
                )}
              </div>
            );
          })}
        </nav>

        {/* Footer Toggle */}
        <div
          style={{
            padding: '14px 16px',
            borderTop: '1px solid rgba(255, 255, 255, 0.1)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: collapsed ? 'center' : 'space-between',
            backgroundColor: 'rgba(0, 0, 0, 0.15)',
          }}
        >
          {!collapsed && (
            <div style={{ display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
              <span style={{ fontSize: '0.82rem', fontWeight: 600, color: '#f8fafc', whiteSpace: 'nowrap' }}>
                {user?.name || 'เจ้าหน้าที่'}
              </span>
              <span style={{ fontSize: '0.72rem', color: '#93c5fd' }}>
                {user?.role_name || user?.role || 'Staff'}
              </span>
            </div>
          )}

          {onToggleCollapse && (
            <button
              type="button"
              onClick={onToggleCollapse}
              style={{
                background: 'none',
                border: 'none',
                color: '#93c5fd',
                cursor: 'pointer',
                padding: '6px',
                borderRadius: '6px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
              aria-label={collapsed ? 'ขยายแถบเมนูข้าง' : 'ย่อแถบเมนูข้าง'}
              title={collapsed ? 'ขยายแถบเมนูข้าง' : 'ย่อแถบเมนูข้าง'}
            >
              {collapsed ? <PanelLeftOpen size={18} /> : <PanelLeftClose size={18} />}
            </button>
          )}
        </div>
      </aside>
    </>
  );
}
