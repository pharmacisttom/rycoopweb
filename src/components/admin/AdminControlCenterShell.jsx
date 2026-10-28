import React, { useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import {
  Activity,
  ArrowLeft,
  ChevronDown,
  ChevronRight,
  Database,
  FileSpreadsheet,
  FileText,
  FolderKanban,
  HardDriveDownload,
  KeyRound,
  LayoutDashboard,
  Lock,
  LogOut,
  Menu,
  Moon,
  PanelLeftClose,
  PanelLeftOpen,
  Search,
  Settings,
  ShieldAlert,
  ShieldCheck,
  Sun,
  Users,
  X
} from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { useTheme } from '../../context/ThemeContext';
import ChangePasswordModal from '../common/ChangePasswordModal';
import './AdminControlCenter.css';

export const ADMIN_SIDEBAR_GROUPS = [
  {
    id: 'dashboard',
    label: 'Dashboard',
    thaiLabel: 'แดชบอร์ดผู้บริหาร',
    icon: LayoutDashboard,
    items: [
      { label: 'ภาพรวมระบบ', to: '/admin/dashboard', permissions: ['dashboard.view'] },
      { label: 'Executive Analytics', to: '/admin/dashboard?tab=analytics', permissions: ['analytics.view', 'dashboard.view'] },
      { label: 'สถิติการใช้งาน', to: '/admin/dashboard?tab=usage', permissions: ['analytics.view', 'dashboard.view'] },
      { label: 'ระบบจัดการเว็บไซต์เดิม (CMS)', to: '/admin/cms', roles: ['super_admin', 'admin', 'staff', 'editor', 'loan_officer', 'finance_officer', 'member_service', 'auditor'] },
    ],
  },
  {
    id: 'operations',
    label: 'Operations',
    thaiLabel: 'การดำเนินงาน',
    icon: FolderKanban,
    items: [
      { label: 'การดำเนินงาน', to: '/admin/operations', permissions: ['operations.view', 'dashboard.view'] },
      { label: 'งานรอดำเนินการ', to: '/admin/operations?tab=pending', permissions: ['operations.view', 'dashboard.view'] },
      { label: 'รายงานการปฏิบัติงาน', to: '/admin/operations?tab=summary', permissions: ['operations.view', 'reports.view', 'dashboard.view'] },
      { label: 'พื้นที่ทำงานเจ้าหน้าที่', to: '/admin/workspace', permissions: ['dashboard.view'] },
    ],
  },
  {
    id: 'members',
    label: 'Member Management',
    thaiLabel: 'ระบบสมาชิกและปันผล',
    icon: Users,
    items: [
      { label: 'ภาพรวมสมาชิก', to: '/admin/members/dashboard', permissions: ['members.view'] },
      { label: 'ค้นหาและจัดการสมาชิก', to: '/admin/members', permissions: ['members.view', 'members.update'] },
      { label: 'ข้อมูลปันผล & นำเข้าข้อมูล', to: '/admin/dividends/import', permissions: ['dividends.import', 'members.import'] },
      { label: 'สถานะการตรวจสอบข้อมูล', to: '/admin/members/health', permissions: ['members.view'] },
      { label: 'ตรวจสอบบุคคลล้มละลาย (LED)', to: '/admin/led/dashboard', permissions: ['members.view', 'operations.view'] },
    ],
  },
  {
    id: 'finance',
    label: 'Financial Administration',
    thaiLabel: 'การเงินและสินเชื่อ',
    icon: FileSpreadsheet,
    items: [
      { label: 'รายงานการเงิน', to: '/admin/reports?category=finance', permissions: ['finance.view', 'rates.view', 'products.view', 'dividends.import'] },
      { label: 'ข้อมูลเงินฝาก & อัตราดอกเบี้ย', to: '/admin/public-content', permissions: ['rates.view', 'finance.view'] },
      { label: 'ข้อมูลสินเชื่อ', to: '/admin/public-content?section=products', permissions: ['products.view', 'finance.view'] },
      { label: 'รายงานที่ได้รับอนุญาต', to: '/admin/reports', permissions: ['reports.view', 'finance.view', 'audit.view'] },
    ],
  },
  {
    id: 'content',
    label: 'Content Management',
    thaiLabel: 'จัดการเนื้อหาและข่าวสาร',
    icon: FileText,
    items: [
      { label: 'ข่าวสาร & ประกาศ', to: '/admin/content?tab=news', permissions: ['news.view', 'content.create', 'content.publish'] },
      { label: 'คำถามที่พบบ่อย (FAQ)', to: '/admin/content?tab=faqs', permissions: ['faqs.view', 'content.create'] },
      { label: 'ข้อมูลสหกรณ์ & บุคลากร', to: '/admin/public-content', permissions: ['coop_info.view', 'people.view', 'rates.view', 'products.view'] },
      { label: 'เอกสารและการเผยแพร่เนื้อหา', to: '/admin/cms', roles: ['super_admin', 'admin', 'editor', 'pr_officer', 'document_officer'] },
    ],
  },
  {
    id: 'access',
    label: 'User & Access Management',
    thaiLabel: 'ผู้ใช้งานและสิทธิ์ RBAC',
    icon: KeyRound,
    items: [
      { label: 'ผู้ใช้งานระบบ', to: '/admin/users', permissions: ['users.view', 'users.create', 'users.update'] },
      { label: 'บทบาท (Roles)', to: '/admin/roles', permissions: ['roles.view', 'roles.manage'] },
      { label: 'สิทธิ์การใช้งาน (Permissions)', to: '/admin/permissions', permissions: ['roles.view', 'permissions.manage'] },
      { label: 'หน่วยงาน (Departments)', to: '/admin/departments', permissions: ['departments.view', 'users.view'] },
      { label: 'คำเชิญเจ้าหน้าที่ & การอนุมัติสิทธิ์', to: '/admin/users?tab=invitations', permissions: ['users.create', 'users.view'] },
      { label: 'จัดการผู้ใช้งานขั้นสูง (RBAC CRUD)', to: '/admin/access', roles: ['super_admin'] },
    ],
  },
  {
    id: 'security',
    label: 'Security Center',
    thaiLabel: 'ศูนย์ความมั่นคงปลอดภัย',
    icon: ShieldAlert,
    items: [
      { label: 'Security Dashboard', to: '/admin/security', permissions: ['security.view'] },
      { label: 'MFA Status', to: '/admin/security/mfa', permissions: ['security.mfa.view', 'security.view'] },
      { label: 'Login History', to: '/admin/audit-logs?category=authentication', permissions: ['security.view', 'audit.view'] },
      { label: 'Active Sessions', to: '/admin/security/sessions', permissions: ['security.view', 'security.sessions.revoke'] },
      { label: 'Security Events & Suspicious Logins', to: '/admin/security?tab=events', permissions: ['security.view'] },
    ],
  },
  {
    id: 'monitoring',
    label: 'System Monitoring',
    thaiLabel: 'ตรวจสอบสถานะระบบ',
    icon: Activity,
    items: [
      { label: 'Application Health', to: '/admin/health', permissions: ['health.view', 'settings.view'] },
      { label: 'Server & Database Monitoring', to: '/admin/health?tab=database', permissions: ['health.view'] },
      { label: 'Scheduled Jobs & Service Status', to: '/admin/health?tab=jobs', permissions: ['health.view'] },
    ],
  },
  {
    id: 'audit',
    label: 'Audit & Logs',
    thaiLabel: 'บันทึกการตรวจสอบ (Audit)',
    icon: Database,
    items: [
      { label: 'Audit Trail & Administrative Activities', to: '/admin/audit-logs', permissions: ['audit.view'] },
      { label: 'Authentication Logs', to: '/admin/audit-logs?category=authentication', permissions: ['audit.view', 'security.view'] },
      { label: 'Security & Error Events', to: '/admin/audit-logs?category=security', permissions: ['audit.view', 'security.view'] },
      { label: 'Export History', to: '/admin/audit-logs?category=export', permissions: ['audit.view', 'audit.export'] },
    ],
  },
  {
    id: 'backups',
    label: 'Backup & Recovery',
    thaiLabel: 'ระบบสำรองและกู้คืนข้อมูล',
    icon: HardDriveDownload,
    items: [
      { label: 'Backup Overview', to: '/admin/backups', permissions: ['backup.view'] },
      { label: 'Backup History & Verification', to: '/admin/backups/history', permissions: ['backup.view', 'backup.verify'] },
      { label: 'Backup Failures & Restore Governance', to: '/admin/backups?tab=governance', permissions: ['backup.view'] },
    ],
  },
  {
    id: 'settings',
    label: 'Settings',
    thaiLabel: 'ตั้งค่าระบบและบัญชี',
    icon: Settings,
    items: [
      { label: 'General & System Configuration', to: '/admin/settings', permissions: ['settings.view', 'dashboard.view'] },
      { label: 'Notification & Security Policy', to: '/admin/settings?tab=security', permissions: ['settings.view', 'security.view', 'dashboard.view'] },
      { label: 'Profile & Change Password', to: '/admin/settings?tab=profile', permissions: ['dashboard.view'] },
    ],
  },
];

const ROLE_DEFAULT_PERMS = {
  super_admin: ['*'],
  admin: [
    'dashboard.view', 'analytics.view', 'operations.view', 'users.view', 'roles.view', 'departments.view',
    'members.view', 'members.update', 'finance.view', 'news.view', 'faqs.view', 'coop_info.view', 'people.view',
    'rates.view', 'products.view', 'audit.view', 'security.view', 'security.mfa.view', 'backup.view',
    'backup.verify', 'health.view', 'reports.view', 'settings.view'
  ],
  it_admin: ['dashboard.view', 'analytics.view', 'operations.view', 'security.view', 'security.mfa.view', 'audit.view', 'backup.view', 'backup.verify', 'health.view', 'settings.view'],
  auditor: ['dashboard.view', 'analytics.view', 'operations.view', 'users.view', 'roles.view', 'departments.view', 'members.view', 'finance.view', 'audit.view', 'audit.export', 'security.view', 'security.mfa.view', 'backup.view', 'health.view', 'reports.view'],
  executive: ['dashboard.view', 'analytics.view', 'operations.view', 'finance.view', 'audit.view', 'health.view', 'reports.view'],
  manager: ['dashboard.view', 'analytics.view', 'operations.view', 'members.view', 'finance.view', 'audit.view', 'reports.view'],
  editor: ['dashboard.view', 'operations.view', 'news.view', 'faqs.view', 'coop_info.view', 'people.view'],
  pr_officer: ['dashboard.view', 'operations.view', 'news.view', 'faqs.view', 'coop_info.view'],
  loan_officer: ['dashboard.view', 'operations.view', 'members.view', 'finance.view', 'rates.view', 'products.view', 'reports.view'],
  finance_officer: ['dashboard.view', 'operations.view', 'members.view', 'finance.view', 'dividends.import', 'rates.view', 'products.view', 'reports.view'],
  finance: ['dashboard.view', 'operations.view', 'members.view', 'finance.view', 'dividends.import', 'rates.view', 'products.view', 'reports.view'],
  member_service: ['dashboard.view', 'operations.view', 'members.view', 'members.update', 'reports.view'],
  member_admin: ['dashboard.view', 'operations.view', 'members.view', 'members.update', 'dividends.import'],
  staff: ['dashboard.view', 'operations.view', 'members.view', 'news.view', 'faqs.view'],
};

export function hasControlCenterAccess(user, canFn, requiredPermissions = [], requiredRoles = []) {
  if (!user || user.role === 'member') return false;
  if (user.role === 'super_admin') return true;
  if (requiredRoles.length > 0 && requiredRoles.includes(user.role)) return true;

  const userPerms = new Set([
    ...(user.permissions || []),
    ...(user.effective_permissions || []),
    ...(ROLE_DEFAULT_PERMS[user.role] || []),
  ]);
  if (userPerms.size > 0) {
    userPerms.add('dashboard.view');
  }
  if (userPerms.has('*')) return true;
  if (requiredPermissions.length === 0 && requiredRoles.length === 0) return true;

  return requiredPermissions.some((perm) => userPerms.has(perm) || (typeof canFn === 'function' && canFn(perm)));
}

export default function AdminControlCenterShell({
  title,
  subtitle,
  eyebrow = 'RYCOOPWEB 3.0 • Enterprise Admin Control Center',
  breadcrumbs = [],
  actions = null,
  children,
}) {
  const { user, logout, can } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const location = useLocation();
  const navigate = useNavigate();

  const [collapsed, setCollapsed] = useState(() => {
    if (typeof window === 'undefined') return false;
    try {
      return window.localStorage.getItem('rycoop_admin_sidebar_collapsed') === '1';
    } catch {
      return false;
    }
  });

  const [openGroups, setOpenGroups] = useState(() => {
    const defaults = {
      dashboard: true,
      operations: true,
      members: true,
      access: true,
      security: true,
      monitoring: true,
      audit: true,
      backups: true,
    };
    if (typeof window === 'undefined') return defaults;
    try {
      const saved = window.localStorage.getItem('rycoop_admin_sidebar_groups');
      return saved ? { ...defaults, ...JSON.parse(saved) } : defaults;
    } catch {
      return defaults;
    }
  });

  const [navSearch, setNavSearch] = useState('');
  const [mobileOpen, setMobileOpen] = useState(false);
  const [passwordModalOpen, setPasswordModalOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    try {
      window.localStorage.setItem('rycoop_admin_sidebar_collapsed', collapsed ? '1' : '0');
    } catch {}
  }, [collapsed]);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    try {
      window.localStorage.setItem('rycoop_admin_sidebar_groups', JSON.stringify(openGroups));
    } catch {}
  }, [openGroups]);

  useEffect(() => {
    setMobileOpen(false);
  }, [location.pathname, location.search]);

  const currentFullUrl = `${location.pathname}${location.search || ''}`;

  const visibleGroups = useMemo(() => {
    const q = navSearch.trim().toLowerCase();
    return ADMIN_SIDEBAR_GROUPS.map((group) => {
      const items = group.items.filter((item) => {
        const permitted = hasControlCenterAccess(user, can, item.permissions || [], item.roles || []);
        if (!permitted) return false;
        if (!q) return true;
        return (
          item.label.toLowerCase().includes(q) ||
          group.label.toLowerCase().includes(q) ||
          group.thaiLabel.toLowerCase().includes(q)
        );
      });
      return { ...group, items };
    }).filter((group) => group.items.length > 0);
  }, [user, can, navSearch]);

  const isItemActive = (to) => {
    if (to.includes('?')) {
      return currentFullUrl === to;
    }
    return location.pathname === to && !location.search;
  };

  const toggleGroup = (groupId) => {
    setOpenGroups((prev) => ({ ...prev, [groupId]: !prev[groupId] }));
  };

  const handleSignOut = async () => {
    setLoggingOut(true);
    try {
      await logout();
      navigate('/admin/login', { replace: true });
    } finally {
      setLoggingOut(false);
    }
  };

  const userInitial = (user?.name || user?.username || 'A').trim().charAt(0).toUpperCase();

  return (
    <div className={`acc-shell ${collapsed ? 'is-collapsed' : ''}`}>
      <div
        className={`acc-backdrop ${mobileOpen ? 'is-open' : ''}`}
        onClick={() => setMobileOpen(false)}
        aria-hidden="true"
      />

      <aside
        className={`acc-sidebar ${mobileOpen ? 'is-mobile-open' : ''}`}
        aria-label="เมนูศูนย์ควบคุมผู้ดูแลระบบ"
      >
        <div className="acc-sidebar-header">
          <Link to="/admin/dashboard" className="acc-brand" title="ศูนย์ควบคุมผู้ดูแลระบบ สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด">
            <img
              src="/assets/images/logo.png"
              alt="ตราสัญลักษณ์สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด"
              className="acc-brand-logo"
              onError={(e) => {
                e.currentTarget.style.display = 'none';
              }}
            />
            {!collapsed && (
              <div className="acc-brand-text">
                <span className="acc-brand-title">RYCOOP ADMIN 3.0</span>
                <span className="acc-brand-subtitle">สหกรณ์ออมทรัพย์สาธารณสุขระยอง</span>
              </div>
            )}
          </Link>

          <button
            type="button"
            className="acc-icon-btn"
            onClick={() => setCollapsed((prev) => !prev)}
            aria-label={collapsed ? 'ขยายแถบเมนูด้านข้าง' : 'ย่อแถบเมนูด้านข้าง'}
            title={collapsed ? 'ขยายเมนู' : 'ย่อเมนู'}
          >
            {collapsed ? <PanelLeftOpen size={16} /> : <PanelLeftClose size={16} />}
          </button>
        </div>

        {!collapsed && (
          <div className="acc-sidebar-search">
            <div className="acc-search-box">
              <Search size={15} />
              <input
                type="search"
                className="acc-search-input"
                placeholder="ค้นหาเมนูควบคุมระบบ..."
                aria-label="ค้นหาเมนูควบคุมระบบ"
                value={navSearch}
                onChange={(e) => setNavSearch(e.target.value)}
              />
              {navSearch && (
                <button
                  type="button"
                  className="acc-search-clear"
                  onClick={() => setNavSearch('')}
                  aria-label="ล้างคำค้นหา"
                >
                  <X size={13} />
                </button>
              )}
            </div>
          </div>
        )}

        <nav className="acc-sidebar-nav" aria-label="หมวดหมู่การจัดการระบบ">
          {visibleGroups.map((group) => {
            const Icon = group.icon;
            const groupActive = group.items.some((item) => isItemActive(item.to));
            const isOpen = collapsed ? false : Boolean(navSearch) || openGroups[group.id] !== false || groupActive;

            return (
              <div key={group.id} className="acc-nav-group">
                <button
                  type="button"
                  className={`acc-nav-group-btn ${groupActive ? 'has-active' : ''}`}
                  onClick={() => {
                    if (collapsed) {
                      setCollapsed(false);
                      setOpenGroups((prev) => ({ ...prev, [group.id]: true }));
                    } else {
                      toggleGroup(group.id);
                    }
                  }}
                  aria-expanded={isOpen}
                  title={`${group.thaiLabel} (${group.label})`}
                >
                  <span className="acc-nav-group-label">
                    <Icon size={18} />
                    {!collapsed && <span>{group.thaiLabel}</span>}
                  </span>
                  {!collapsed && (isOpen ? <ChevronDown size={15} /> : <ChevronRight size={15} />)}
                </button>

                {!collapsed && isOpen && (
                  <div className="acc-nav-items">
                    {group.items.map((item) => {
                      const active = isItemActive(item.to);
                      return (
                        <Link
                          key={item.to + item.label}
                          to={item.to}
                          className={`acc-nav-link ${active ? 'is-active' : ''}`}
                        >
                          <span>{item.label}</span>
                        </Link>
                      );
                    })}
                  </div>
                )}
              </div>
            );
          })}
        </nav>

        <div className="acc-sidebar-footer">
          {!collapsed && (
            <div className="acc-user-card">
              <div className="acc-user-avatar" aria-hidden="true">
                {userInitial}
              </div>
              <div className="acc-user-meta">
                <strong>{user?.name || 'ผู้ดูแลระบบ'}</strong>
                <span>{user?.roleName || user?.role || 'เจ้าหน้าที่สหกรณ์'}</span>
              </div>
            </div>
          )}

          <div className="acc-footer-actions">
            <button
              type="button"
              className="acc-footer-btn"
              onClick={toggleTheme}
              title={theme === 'light' ? 'สลับเป็นโหมดมืด' : 'สลับเป็นโหมดสว่าง'}
            >
              {theme === 'light' ? <Moon size={14} /> : <Sun size={14} />}
              {!collapsed && <span>{theme === 'light' ? 'โหมดมืด' : 'โหมดสว่าง'}</span>}
            </button>

            <button
              type="button"
              className="acc-footer-btn"
              onClick={() => setPasswordModalOpen(true)}
              title="เปลี่ยนรหัสผ่านของฉัน"
            >
              <Lock size={14} />
              {!collapsed && <span>รหัสผ่าน</span>}
            </button>

            <Link to="/" className="acc-footer-btn" title="กลับสู่หน้าเว็บไซต์สหกรณ์">
              <ArrowLeft size={14} />
              {!collapsed && <span>หน้าเว็บ</span>}
            </Link>

            <button
              type="button"
              className="acc-footer-btn danger"
              onClick={handleSignOut}
              disabled={loggingOut}
              title="ออกจากระบบ"
            >
              <LogOut size={14} />
              {!collapsed && <span>{loggingOut ? 'ออก...' : 'ออกระบบ'}</span>}
            </button>
          </div>
        </div>
      </aside>

      <div className="acc-main-wrapper">
        <header className="acc-topbar">
          <div className="acc-topbar-left">
            <button
              type="button"
              className="acc-mobile-toggle"
              onClick={() => setMobileOpen(true)}
              aria-label="เปิดเมนูผู้ดูแลระบบ"
            >
              <Menu size={20} />
            </button>

            <nav className="acc-breadcrumbs" aria-label="Breadcrumb">
              <Link to="/admin/dashboard">Admin Control Center</Link>
              {breadcrumbs.map((crumb, idx) => (
                <React.Fragment key={crumb.label + idx}>
                  <span aria-hidden="true">/</span>
                  {crumb.to ? <Link to={crumb.to}>{crumb.label}</Link> : <span>{crumb.label}</span>}
                </React.Fragment>
              ))}
            </nav>
          </div>

          <div className="acc-topbar-right">
            <span className="acc-badge-pill success" title="การป้องกัน CSRF, RBAC และ Mandatory MFA ทำงานปกติ">
              <ShieldCheck size={14} />
              <span>RBAC + MFA Enforced</span>
            </span>
            <span className="acc-badge-pill">
              <span>บทบาท: {user?.roleName || user?.role || 'Staff'}</span>
            </span>
          </div>
        </header>

        <div className="acc-content" role="region" aria-label={title}>
          <div className="acc-page-header">
            <div>
              <div className="acc-page-eyebrow">{eyebrow}</div>
              <h1 className="acc-page-title">{title}</h1>
              {subtitle && <p className="acc-page-desc">{subtitle}</p>}
            </div>
            {actions && <div className="acc-page-actions">{actions}</div>}
          </div>

          {children}
        </div>
      </div>

      <ChangePasswordModal isOpen={passwordModalOpen} onClose={() => setPasswordModalOpen(false)} />
    </div>
  );
}

