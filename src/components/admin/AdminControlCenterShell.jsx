import CoopBrandLogo from '../branding/CoopBrandLogo';
import React, { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';
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
import 'admin-lte/dist/css/adminlte.rycoop.css';
import './AdminControlCenter.css';
import './AdminUI.css';
import './AdminResponsiveFix.css';
import './AdminModernRefresh.css';
import './AdminLayoutPolishRC02.css';
import './AdminVisualConsistencyRC03.css';
import { AdminBreadcrumb } from './AdminUI';
const ShellContext=createContext(false);

export const ADMIN_SIDEBAR_GROUPS = [
  {
    id: 'help',
    label: 'User Manual',
    thaiLabel: 'คู่มือการใช้งาน',
    icon: FileText,
    items: [
      { label: 'คู่มือการใช้งานระบบ', to: '/admin/manual', roles: ['super_admin','admin','staff','editor','loan_officer','finance_officer','member_service','auditor','member_admin','it_admin'] },
    ],
  },
  {
    id: 'dashboard',
    label: 'Dashboard',
    thaiLabel: 'แดชบอร์ดผู้บริหาร',
    icon: LayoutDashboard,
    items: [
      { label: 'ภาพรวมระบบและสุขภาพระบบ', to: '/admin/control-center', permissions: ['dashboard.view'] },
      { label: 'Executive Analytics', to: '/admin/control-center?tab=analytics', permissions: ['analytics.view', 'dashboard.view'] },
      { label: 'สถิติการใช้งาน', to: '/admin/control-center?tab=usage', permissions: ['analytics.view', 'dashboard.view'] },
      { label: 'สถิติผู้เข้าชมเว็บไซต์ (Visitor Analytics)', to: '/admin/analytics/visitors', permissions: ['analytics.visitors.view', 'analytics.view'] },
      { label: 'ศูนย์จัดการเว็บไซต์ (CMS)', to: '/admin/cms', roles: ['super_admin', 'admin', 'staff', 'editor', 'loan_officer', 'finance_officer', 'member_service', 'auditor'] },
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
      { label: 'ค้นหาและจัดการสมาชิก', to: '/admin/members', permissions: ['members.view'] },
      { label: 'ข้อมูลปันผล & นำเข้าข้อมูล', to: '/admin/dividends/import', permissions: ['dividends.import'] },
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
      { label: 'ข้อมูลเงินฝาก & อัตราดอกเบี้ย', to: '/admin/public-content', permissions: ['rates.view'] },
      { label: 'ข้อมูลสินเชื่อ', to: '/admin/public-content?section=products', permissions: ['products.view'] },
      { label: 'รายงานที่ได้รับอนุญาต', to: '/admin/reports', permissions: ['reports.view', 'finance.view', 'audit.view'] },
    ],
  },
  {
    id: 'content',
    label: 'Content Management',
    thaiLabel: 'จัดการเนื้อหาและข่าวสาร',
    icon: FileText,
    items: [
      { label: 'ข่าวสาร & ประกาศ', to: '/admin/content?tab=news', permissions: ['news.view'] },
      { label: 'ประกาศสำคัญ', to: '/admin/workspace', permissions: ['announcements.view'] },
      { label: 'คำถามที่พบบ่อย (FAQ)', to: '/admin/content?tab=faqs', permissions: ['faqs.view'] },
      { label: 'ข้อมูลสหกรณ์ & บุคลากร', to: '/admin/public-content', permissions: ['coop_info.view', 'people.view', 'rates.view', 'products.view'] },
      { label: 'คลังภาพกิจกรรม', to: '/admin/activities', permissions: ['news.view'] },
      { label: 'จัดการ Layout หน้าแรก', to: '/admin/layout', roles: ['super_admin'] },
      { label: 'แก้ไขข้อความเมนูเว็บไซต์', to: '/admin/navigation-copy', roles: ['super_admin'] },
      { label: 'จัดการ Mega Menu', to: '/admin/mega-menu', roles: ['super_admin'] },
      { label: 'จัดการชุดกรรมการและอนุกรรมการ', to: '/admin/public-content?section=boards', permissions: ['people.view'] },
      { label: 'จัดการคณะอนุกรรมการ', to: '/admin/public-content?section=groups', permissions: ['people.view'] },
      { label: 'ศูนย์เอกสาร', to: '/admin/documents', permissions: ['documents.view'] },
      { label: 'คลังรูปภาพ (Media Library)', to: '/admin/media', permissions: ['news.view'] },
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
      { label: 'ข้อมูลเว็บไซต์ที่อนุญาตให้แก้ไข', to: '/admin/settings/content', permissions: ['settings.view'] },
      { label: 'Notification & Security Policy', to: '/admin/settings?tab=security', permissions: ['settings.view', 'security.view', 'dashboard.view'] },
      { label: 'Profile & Change Password', to: '/admin/settings?tab=profile', permissions: ['dashboard.view'] },
    ],
  },
  {id:'compliance',label:'Compliance & Dual-Control',thaiLabel:'การอนุมัติและแยกหน้าที่',icon:ShieldCheck,items:[{label:'คำขอ Maker / Checker',to:'/admin/approvals',permissions:['backup.view','backup.restore.request','backup.restore.approve','security.view','audit.view','operations.view','dashboard.view']}]},
  {id:'reports',label:'Reports',thaiLabel:'รายงาน',icon:FileText,items:[{label:'รายงานที่ได้รับอนุญาต',to:'/admin/reports',permissions:['reports.view','finance.view','audit.view']}]},
  {id:'tools',label:'Administrative Tools',thaiLabel:'เครื่องมือเจ้าหน้าที่',icon:FolderKanban,items:[{label:'พื้นที่ทำงานตามสิทธิ์',to:'/admin/workspace',permissions:['coop_info.view','people.view','rates.view','products.view','members.view','dividends.import','news.view','faqs.view','dashboard.view']} ]},

];

export function hasControlCenterAccess(user, canFn, requiredPermissions = [], requiredRoles = []) {
  if (!user || user.role === 'member') return false;
  if (user.role === 'super_admin') return true;
  if (requiredRoles.length && !requiredRoles.includes(user.role)) return false;
  if (!requiredPermissions.length) return requiredRoles.length > 0;
  if (typeof canFn === 'function') return requiredPermissions.some(permission => canFn(permission));
  const effective = new Set([...(user.permissions || []), ...(user.effective_permissions || [])]);
  return effective.has('*') || requiredPermissions.some(permission => effective.has(permission));
}

function FullAdminControlCenterShell({
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
      help: true,
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

  const mobileDialog=useRef(null);
  const [isMobile,setIsMobile]=useState(false);
  useEffect(()=>{const media=window.matchMedia("(max-width:920px)");const update=()=>{setIsMobile(media.matches);if(!media.matches)setMobileOpen(false);};update();media.addEventListener("change",update);return()=>media.removeEventListener("change",update);},[]);
  const [navSearch, setNavSearch] = useState('');
  const [systemFooterOpen, setSystemFooterOpen] = useState(false);
  const [accountOpen, setAccountOpen] = useState(false);
  const accountMenuRef = useRef(null);
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

  useEffect(() => { setSystemFooterOpen(false); }, [location.pathname, location.search]);
  useEffect(() => { setAccountOpen(false); }, [location.pathname, location.search]);
  useEffect(() => {
    if (!accountOpen) return undefined;
    const closeOnOutside = event => { if (!accountMenuRef.current?.contains(event.target)) setAccountOpen(false); };
    const closeOnEscape = event => { if (event.key === 'Escape') { setAccountOpen(false); accountMenuRef.current?.querySelector('button')?.focus(); } };
    document.addEventListener('pointerdown', closeOnOutside);
    document.addEventListener('keydown', closeOnEscape);
    return () => {
      document.removeEventListener('pointerdown', closeOnOutside);
      document.removeEventListener('keydown', closeOnEscape);
    };
  }, [accountOpen]);


  useEffect(() => {
    if(!mobileOpen)return undefined;
    const panel=mobileDialog.current,previous=document.activeElement;
    const focusable=()=>[...panel.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled)')].filter(node=>node.offsetParent!==null);
    focusable()[0]?.focus();
    const key=event=>{
      if(event.key==='Escape'){event.preventDefault();setMobileOpen(false);}
      if(event.key==='Tab'){const nodes=focusable();const first=nodes[0],last=nodes[nodes.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}
    };
    const overflow=document.body.style.overflow;document.body.style.overflow='hidden';document.addEventListener('keydown',key);
    return()=>{document.body.style.overflow=overflow;document.removeEventListener('keydown',key);previous?.focus();};
  },[mobileOpen]);
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

  // Keep everyday tasks in the sidebar; move operational/system utilities into the footer.
  // Search continues to reveal all permitted menu groups.
  const footerGroupIds = new Set(['security', 'monitoring', 'audit', 'backups', 'settings', 'compliance', 'reports', 'tools']);
  const primaryGroups = visibleGroups.filter(group => !footerGroupIds.has(group.id) || navSearch.trim())
    .sort((a, b) => a.id === 'content' ? -1 : b.id === 'content' ? 1 : 0);
  const footerGroups = visibleGroups.filter(group => footerGroupIds.has(group.id));

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
    <div className={`acc-shell app-wrapper ${collapsed ? 'is-collapsed' : ''}`}>
      <div
        className={`acc-backdrop ${mobileOpen ? 'is-open' : ''}`}
        onClick={() => setMobileOpen(false)}
        aria-hidden="true"
      />

      <aside
        inert={isMobile&&!mobileOpen?"":undefined} aria-hidden={isMobile&&!mobileOpen?true:undefined}
        ref={mobileDialog} role={mobileOpen?"dialog":undefined} aria-modal={mobileOpen?true:undefined}
        className={`acc-sidebar app-sidebar ${mobileOpen ? 'is-mobile-open' : ''}`}
        aria-label="เมนูศูนย์ควบคุมผู้ดูแลระบบ"
      >
        <div className="acc-sidebar-header">
          {mobileOpen&&<button type="button" className="acc-icon-btn" onClick={()=>setMobileOpen(false)} aria-label="ปิดเมนูผู้ดูแลระบบ"><X size={18}/></button>}
          <Link to={user?.role === 'super_admin' ? '/admin/cms' : '/admin/workspace'} className="acc-brand" title="ศูนย์ควบคุมผู้ดูแลระบบ สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด">
            <CoopBrandLogo variant={collapsed ? 'emblem' : 'sidebar'} tone="dark" showEstablished size={collapsed ? 38 : 44} />
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
          {primaryGroups.map((group) => {
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

                {!collapsed && group.items.length > 0 && (
                  <Link
                    to={group.items[0].to}
                    className="acc-nav-link"
                    style={{fontSize:'0.75rem',padding:'4px 14px',margin:'0 8px 4px'}}
                    aria-label={`เปิดหน้าหลักหมวด${group.thaiLabel}`}
                  >
                    เปิดหน้าหลักหมวด
                  </Link>
                )}

                {!collapsed && isOpen && (
                  <div className="acc-nav-items">
                    {group.items.map((item) => {
                      const active = isItemActive(item.to);
                      return (
                        <Link
                          key={item.to + item.label}
                          to={item.to}
                          aria-current={active?'page':undefined}
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

      </aside>

      <div className="acc-main-wrapper app-main" inert={mobileOpen?"":undefined}>
        <header className="acc-topbar app-header">
          <div className="acc-topbar-left">
            <CoopBrandLogo variant="emblem" size={32} />
            <button
              type="button"
              className="acc-mobile-toggle"
              onClick={() => {setCollapsed(false);setMobileOpen(true);}}
              aria-label="เปิดเมนูผู้ดูแลระบบ"
            >
              <Menu size={20} />
            </button>

            <nav className="acc-breadcrumbs" aria-label="Breadcrumb">
              <Link to={user?.role === 'super_admin' ? '/admin/cms' : '/admin/workspace'}>Admin Control Center</Link>
              {breadcrumbs.map((crumb, idx) => (
                <React.Fragment key={crumb.label + idx}>
                  <span aria-hidden="true">/</span>
                  {crumb.to ? <Link to={crumb.to}>{crumb.label}</Link> : <span>{crumb.label}</span>}
                </React.Fragment>
              ))}
            </nav>
          </div>

          <div className="acc-topbar-right">
            <span className="acc-badge-pill success" title="คำสั่งสำคัญตรวจสิทธิ์และ MFA ที่เซิร์ฟเวอร์">
              <ShieldCheck size={14} />
              <span>พื้นที่ผู้ดูแลระบบ</span>
            </span>
            <div className="acc-account-menu" ref={accountMenuRef}>
              <button type="button" className="acc-account-trigger"
                aria-haspopup="true" aria-expanded={accountOpen}
                aria-controls="acc-account-dropdown"
                onClick={() => setAccountOpen(open => !open)}>
                <span className="acc-account-trigger-avatar" aria-hidden="true">{userInitial}</span>
                <span className="acc-account-trigger-meta">
                  <strong>{user?.name || 'ผู้ดูแลระบบ'}</strong>
                  <small>{user?.roleName || user?.role || 'เจ้าหน้าที่สหกรณ์'}</small>
                </span>
                <ChevronDown size={16} aria-hidden="true" />
              </button>
              {accountOpen && (
                <div id="acc-account-dropdown" className="acc-account-dropdown" role="group" aria-label="เมนูบัญชีผู้ดูแล">
                  <button type="button" onClick={() => {toggleTheme(); setAccountOpen(false);}}>
                    {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
                    <span>{theme === 'light' ? 'โหมดมืด' : 'โหมดสว่าง'}</span>
                  </button>
                  <button type="button" onClick={() => {setAccountOpen(false);setPasswordModalOpen(true);}}>
                    <Lock size={16} /><span>รหัสผ่าน</span>
                  </button>
                  <Link to="/" onClick={() => setAccountOpen(false)}>
                    <ArrowLeft size={16} /><span>หน้าเว็บ</span>
                  </Link>
                  <button type="button" className="acc-account-signout" onClick={() => {setAccountOpen(false);handleSignOut();}} disabled={loggingOut}>
                    <LogOut size={16} /><span>{loggingOut ? 'ออก...' : 'ออกระบบ'}</span>
                  </button>
                </div>
              )}
            </div>
          </div>
        </header>

        <div className="acc-content" role="region" aria-label={title||"พื้นที่ทำงานผู้ดูแลระบบ"}>
          {title&&<div className="acc-page-header">
            <div>
              <div className="acc-page-eyebrow">{eyebrow}</div>
              <h1 className="acc-page-title">{title}</h1>
              {subtitle && <p className="acc-page-desc">{subtitle}</p>}
            </div>
            {actions && <div className="acc-page-actions">{actions}</div>}
          </div>}

          {children}
        </div>
        <footer className="acc-system-footer app-footer" aria-label="ระบบและเครื่องมือผู้ดูแล">
          {footerGroups.length > 0 && (
          <>
            <div className="acc-system-footer-bar">
              <div className="acc-system-footer-identity">
                <Settings size={17} aria-hidden="true" />
                <div><strong>ศูนย์ระบบและเครื่องมือ</strong><small>เมนูจัดการตามสิทธิ์ของคุณ</small></div>
              </div>
              <div className="acc-system-footer-shortcuts">
                {footerGroups.slice(0, 3).map((group) => {
                  const first = group.items[0];
                  return first ? <Link key={group.id} to={first.to}
                    className="acc-system-footer-shortcut">{group.thaiLabel}</Link> : null;
                })}
              </div>
              <button type="button" className="acc-system-footer-toggle"
                aria-expanded={systemFooterOpen}
                aria-controls="acc-system-footer-menu"
                onClick={() => setSystemFooterOpen(open => !open)}>
                <span>{systemFooterOpen ? 'ปิดเมนูระบบ' : 'ระบบทั้งหมด'}</span>
                <ChevronDown size={17} aria-hidden="true" />
              </button>
            </div>
            {systemFooterOpen && (
              <nav id="acc-system-footer-menu" className="acc-system-footer-menu" aria-label="เมนูเครื่องมือระบบ">
                {footerGroups.map((group) => (
                  <section className="acc-system-footer-group" key={group.id}>
                    <h2>{group.thaiLabel}</h2>
                    {group.items.map(item => (
                      <Link key={item.to + item.label} to={item.to}
                        aria-current={isItemActive(item.to) ? 'page' : undefined}
                        className={`acc-system-footer-link ${isItemActive(item.to) ? 'is-active' : ''}`}>
                        {item.label}
                      </Link>
                    ))}
                  </section>
                ))}
              </nav>
            )}
          </>
          )}
        </footer>
      </div>

      <ChangePasswordModal isOpen={passwordModalOpen} onClose={() => setPasswordModalOpen(false)} />
    </div>
  );
}


// Every protected route uses one shell; module-specific shells contribute their header only.
export default function AdminControlCenterShell(props) {
  const nested=useContext(ShellContext);
  if(nested)return <section className="admin-module">{props.breadcrumbs?.length>0&&<AdminBreadcrumb items={props.breadcrumbs}/>}<header className="acc-page-header"><div><h1 className="acc-page-title">{props.title}</h1>{props.subtitle&&<p className="acc-page-desc">{props.subtitle}</p>}</div>{props.actions&&<div className="acc-page-actions">{props.actions}</div>}</header>{props.children}</section>;
  return <ShellContext.Provider value={true}><FullAdminControlCenterShell {...props}/></ShellContext.Provider>;
}
