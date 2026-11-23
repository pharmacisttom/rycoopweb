import React from 'react';
import {
  Activity, Archive, Bell, BookOpen, BriefcaseBusiness, CalendarDays,
  ChartColumnIncreasing, ChartNoAxesCombined, CircleHelp, ClipboardCheck,
  ClipboardList, Cog, Database, Download, FileClock, FilePenLine,
  FileSpreadsheet, FileText, FolderOpen, GalleryHorizontalEnd,
  HeartHandshake, Home, IdCard, Image, KeyRound, Landmark, LayoutDashboard,
  LayoutTemplate, ListFilter, ListTree, LockKeyhole, MailPlus, Menu,
  Megaphone, Newspaper, PanelTop, ReceiptText, RotateCcw, Search,
  Settings, ShieldCheck, ShieldAlert, SlidersHorizontal, SquareChartGantt,
  Users, UserRoundCog, UserRoundSearch, Wallet, Workflow
} from 'lucide-react';

// Icons are derived only from internal navigation paths. No permissions,
// routes, sessions, or server data are changed by this presentation mapping.
const exact = {
  '/admin/manual': BookOpen,
  '/admin/cms': LayoutDashboard,
  '/admin/dashboard': LayoutDashboard,
  '/admin/control-center': ChartNoAxesCombined,
  '/admin/analytics/visitors': ChartColumnIncreasing,
  '/admin/operations': Workflow,
  '/admin/workspace': BriefcaseBusiness,
  '/admin/members/dashboard': Users,
  '/admin/members': UserRoundSearch,
  '/admin/members/health': ClipboardCheck,
  '/admin/dividends/import': FileSpreadsheet,
  '/admin/led/dashboard': ShieldCheck,
  '/admin/reports': FileSpreadsheet,
  '/admin/public-content': Landmark,
  '/admin/content': Newspaper,
  '/admin/activities': GalleryHorizontalEnd,
  '/admin/layout': LayoutTemplate,
  '/admin/navigation-copy': FilePenLine,
  '/admin/mega-menu': ListTree,
  '/admin/documents': FolderOpen,
  '/admin/media': Image,
  '/admin/users': UserRoundCog,
  '/admin/roles': IdCard,
  '/admin/permissions': KeyRound,
  '/admin/departments': Users,
  '/admin/access': LockKeyhole,
  '/admin/security': ShieldAlert,
  '/admin/security/mfa': ShieldCheck,
  '/admin/security/sessions': LockKeyhole,
  '/admin/audit-logs': ClipboardList,
  '/admin/health': Activity,
  '/admin/backups': Archive,
  '/admin/backups/history': FileClock,
  '/admin/settings/content': SlidersHorizontal,
  '/admin/settings': Settings,
  '/admin/approvals': ClipboardCheck,
};
const queryIcons = {
  '/admin/content?tab=faqs': CircleHelp,
  '/admin/content?tab=news': Newspaper,
  '/admin/public-content?section=boards': Users,
  '/admin/public-content?section=groups': UserRoundCog,
  '/admin/public-content?section=products': Wallet,
  '/admin/control-center?tab=usage': Activity,
  '/admin/control-center?tab=analytics': ChartNoAxesCombined,
  '/admin/operations?tab=pending': ClipboardList,
  '/admin/operations?tab=summary': FileSpreadsheet,
  '/admin/reports?category=finance': Wallet,
  '/admin/audit-logs?category=authentication': KeyRound,
  '/admin/audit-logs?category=security': ShieldAlert,
  '/admin/audit-logs?category=export': Download,
  '/admin/users?tab=invitations': MailPlus,
  '/admin/settings?tab=profile': UserRoundCog,
  '/admin/settings?tab=security': ShieldCheck,
  '/admin/security?tab=events': ShieldAlert,
  '/admin/health?tab=database': Database,
  '/admin/health?tab=jobs': CalendarDays,
  '/admin/backups?tab=governance': ShieldCheck,
};

export function iconForAdminPath(path = '') {
  const candidate = String(path || '');
  if (queryIcons[candidate]) return queryIcons[candidate];
  return exact[candidate.split('?')[0]] || FileText;
}

export function AdminSidebarItemIcon({path, size = 16}) {
  const Icon = iconForAdminPath(path);
  return <Icon size={size} strokeWidth={1.8} aria-hidden="true" focusable="false" className="acc-nav-item-icon"/>;
}
