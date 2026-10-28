import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import {
  Activity,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Database,
  Download,
  ExternalLink,
  FileSpreadsheet,
  FolderKanban,
  HardDriveDownload,
  KeyRound,
  Layers,
  Lock,
  RefreshCw,
  Server,
  ShieldAlert,
  ShieldCheck,
  UserCheck,
  Users,
  XCircle
} from 'lucide-react';
import AdminControlCenterShell, { hasControlCenterAccess } from '../components/admin/AdminControlCenterShell';
import ChangePasswordModal from '../components/common/ChangePasswordModal';
import { useAuth } from '../context/AuthContext';
import { memberAdminApi } from '../services/memberAdminApi';

function formatNumber(val) {
  if (val === null || val === undefined || val === '') return 'ยังไม่มีข้อมูล';
  if (typeof val === 'number') return val.toLocaleString('th-TH');
  return String(val);
}

function downloadCsv(filename, headers, rows) {
  if (typeof window === 'undefined') return;
  const escapeCell = (cell) => {
    const str = cell === null || cell === undefined ? '' : String(cell);
    return `"${str.replace(/"/g, '""')}"`;
  };
  const csvContent = [
    '\uFEFF' + headers.map(escapeCell).join(','),
    ...rows.map((row) => row.map(escapeCell).join(',')),
  ].join('\r\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', filename);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function SvgTrendChart({ data = [], xKey = 'date', series = [], emptyLabel = 'ยังไม่มีข้อมูลในช่วงเวลาที่เลือก' }) {
  if (!Array.isArray(data) || data.length === 0) {
    return <div className="acc-empty-state">{emptyLabel}</div>;
  }

  const width = 640;
  const height = 190;
  const padLeft = 38;
  const padRight = 16;
  const padTop = 16;
  const padBottom = 30;
  const plotW = width - padLeft - padRight;
  const plotH = height - padTop - padBottom;

  const maxVal = Math.max(
    1,
    ...data.flatMap((row) => series.map((s) => Number(row[s.key] || 0)))
  );

  return (
    <div className="acc-chart-box">
      <svg
        viewBox={`0 0 ${width} ${height}`}
        style={{ width: '100%', height: '190px', overflow: 'visible' }}
        role="img"
        aria-label="กราฟแนวโน้มข้อมูล"
      >
        {[0, 0.5, 1].map((ratio) => {
          const y = padTop + plotH * (1 - ratio);
          const label = Math.round(maxVal * ratio);
          return (
            <g key={ratio}>
              <line x1={padLeft} y1={y} x2={width - padRight} y2={y} stroke="var(--acc-border)" strokeDasharray="3 3" />
              <text x={padLeft - 6} y={y + 4} textAnchor="end" fontSize="10" fill="var(--acc-text-secondary)">
                {label}
              </text>
            </g>
          );
        })}

        {series.map((s) => {
          const points = data.map((row, idx) => {
            const x = padLeft + (data.length === 1 ? plotW / 2 : (idx / (data.length - 1)) * plotW);
            const val = Number(row[s.key] || 0);
            const y = padTop + plotH - (val / maxVal) * plotH;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
          });
          return (
            <g key={s.key}>
              <polyline
                fill="none"
                stroke={s.color}
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                points={points.join(' ')}
              />
              {data.map((row, idx) => {
                const x = padLeft + (data.length === 1 ? plotW / 2 : (idx / (data.length - 1)) * plotW);
                const val = Number(row[s.key] || 0);
                const y = padTop + plotH - (val / maxVal) * plotH;
                return (
                  <circle key={`${s.key}-${idx}`} cx={x} cy={y} r="3.2" fill={s.color}>
                    <title>{`${row[xKey]} • ${s.label}: ${val}`}</title>
                  </circle>
                );
              })}
            </g>
          );
        })}

        {data.map((row, idx) => {
          if (data.length > 10 && idx % Math.ceil(data.length / 6) !== 0 && idx !== data.length - 1) {
            return null;
          }
          const x = padLeft + (data.length === 1 ? plotW / 2 : (idx / (data.length - 1)) * plotW);
          return (
            <text
              key={`lbl-${idx}`}
              x={x}
              y={height - 8}
              textAnchor="middle"
              fontSize="10"
              fill="var(--acc-text-secondary)"
            >
              {String(row[xKey] || '').slice(-5)}
            </text>
          );
        })}
      </svg>

      <div style={{ display: 'flex', gap: '14px', flexWrap: 'wrap', fontSize: '0.78rem' }}>
        {series.map((s) => (
          <span key={s.key} style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: 10, height: 10, borderRadius: '50%', background: s.color, display: 'inline-block' }} />
            <span>{s.label}</span>
          </span>
        ))}
      </div>
    </div>
  );
}

function HorizontalBarChart({ items = [], labelKey = 'label', valueKey = 'value', suffix = '', emptyLabel = 'ยังไม่มีข้อมูล' }) {
  if (!Array.isArray(items) || items.length === 0) {
    return <div className="acc-empty-state">{emptyLabel}</div>;
  }
  const max = Math.max(1, ...items.map((it) => Number(it[valueKey] || 0)));
  return (
    <div className="acc-bar-list">
      {items.map((it, idx) => {
        const val = Number(it[valueKey] || 0);
        const pct = Math.min(100, Math.round((val / max) * 100));
        return (
          <div key={`${it[labelKey]}-${idx}`} className="acc-bar-row">
            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={String(it[labelKey])}>
              {it[labelKey]}
            </span>
            <div className="acc-bar-track">
              <div className="acc-bar-fill" style={{ width: `${pct}%` }} />
            </div>
            <strong style={{ textAlign: 'right', fontVariantNumeric: 'tabular-nums' }}>
              {val.toLocaleString('th-TH')}{suffix}
            </strong>
          </div>
        );
      })}
    </div>
  );
}

const SECTION_META = {
  dashboard: {
    title: 'ศูนย์ควบคุมผู้บริหาร (Executive Admin Control Center)',
    subtitle: 'ติดตามตัวชี้วัดหลัก 12 รายการ แนวโน้มความมั่นคงปลอดภัย สถานะระบบ และภาพรวมการดำเนินงานแบบเรียลไทม์',
    breadcrumbs: [{ label: 'Executive Dashboard' }],
    permissions: ['dashboard.view', 'analytics.view'],
  },
  operations: {
    title: 'ศูนย์ติดตามการดำเนินงาน (Operations Center)',
    subtitle: 'ตรวจสอบงานรอดำเนินการ คำร้องแก้ไขข้อมูลสมาชิก เรื่องร้องเรียน ข่าวสารรออนุมัติ และคิวตรวจสอบ LED',
    breadcrumbs: [{ label: 'Operations' }],
    permissions: ['operations.view', 'dashboard.view'],
  },
  users: {
    title: 'จัดการผู้ใช้งานและบัญชีเจ้าหน้าที่ (User & Staff Administration)',
    subtitle: 'ตรวจสอบสถานะบัญชีเจ้าหน้าที่ การผูกบัญชี Google OIDC สถานะ MFA และคำเชิญเจ้าหน้าที่',
    breadcrumbs: [{ label: 'User & Access' }, { label: 'Users' }],
    permissions: ['users.view', 'users.create', 'users.update'],
  },
  roles: {
    title: 'บริหารจัดการบทบาท (Role-Based Access Control)',
    subtitle: 'โครงสร้างบทบาทมาตรฐาน 10 บทบาท และการผูกสิทธิ์เข้ากับบทบาทในฐานข้อมูลโดยไม่กระทบสิทธิ์เดิม',
    breadcrumbs: [{ label: 'User & Access' }, { label: 'Roles' }],
    permissions: ['roles.view', 'roles.manage'],
  },
  permissions: {
    title: 'ตารางสิทธิ์การใช้งานระบบ (Permission Matrix)',
    subtitle: 'รายการสิทธิ์แบบ Resource-Action และการบังคับใช้หลักการ Least Privilege & Default Deny',
    breadcrumbs: [{ label: 'User & Access' }, { label: 'Permissions' }],
    permissions: ['roles.view', 'permissions.manage'],
  },
  departments: {
    title: 'ข้อมูลหน่วยงานและสังกัด (Departments Overview)',
    subtitle: 'สรุปจำนวนสมาชิกและเจ้าหน้าที่แยกตามหน่วยงานสังกัดสาธารณสุขจังหวัดระยอง',
    breadcrumbs: [{ label: 'User & Access' }, { label: 'Departments' }],
    permissions: ['departments.view', 'users.view'],
  },
  'audit-logs': {
    title: 'บันทึกการตรวจสอบและกิจกรรมผู้ดูแลระบบ (Audit & Activity Logs)',
    subtitle: 'ตรวจสอบประวัติการเข้าสู่ระบบ การแก้ไขข้อมูลสำคัญ เหตุการณ์ความปลอดภัย และส่งออกรายงาน CSV (ปกปิดข้อมูลลับอัตโนมัติ)',
    breadcrumbs: [{ label: 'Audit & Logs' }],
    permissions: ['audit.view', 'security.view'],
  },
  security: {
    title: 'ศูนย์ความมั่นคงปลอดภัย (Security Center)',
    subtitle: 'คะแนนสุขภาพความปลอดภัยแบบโปร่งใส การตรวจจับการเข้าสู่ระบบผิดปกติ และการป้องกันบัญชีผู้ดูแลระบบ',
    breadcrumbs: [{ label: 'Security Center' }],
    permissions: ['security.view'],
  },
  sessions: {
    title: 'จัดการเซสชันที่กำลังใช้งาน (Active Sessions)',
    subtitle: 'ตรวจสอบเซสชันของผู้ดูแลระบบและเจ้าหน้าที่ พร้อมสั่งเพิกถอนเซสชัน (Force Logout) เมื่อพบความเสี่ยง',
    breadcrumbs: [{ label: 'Security Center', to: '/admin/security' }, { label: 'Active Sessions' }],
    permissions: ['security.view', 'security.sessions.revoke'],
  },
  mfa: {
    title: 'สถานะการยืนยันตัวตนสองขั้นตอน (Mandatory MFA Administration)',
    subtitle: 'ติดตามความครอบคลุมการลงทะเบียน TOTP 2FA และจำนวนรหัสกู้คืนคงเหลือ (ไม่เปิดเผย Secret หรือ Recovery Code)',
    breadcrumbs: [{ label: 'Security Center', to: '/admin/security' }, { label: 'MFA Status' }],
    permissions: ['security.mfa.view', 'security.view'],
  },
  backups: {
    title: 'ศูนย์ติดตามการสำรองข้อมูล (Backup & Recovery Monitoring)',
    subtitle: 'Phase 1 Read-Only Monitoring: ตรวจสอบสถานะไฟล์สำรองข้อมูล ขนาดไฟล์ เวลาล่าสุด และผลตรวจวัด SHA-256 Checksum',
    breadcrumbs: [{ label: 'Backup & Recovery' }],
    permissions: ['backup.view', 'backup.verify'],
  },
  'backups-history': {
    title: 'ประวัติการสำรองข้อมูลและการตรวจสอบความสมบูรณ์ (Backup History & Verification)',
    subtitle: 'รายการไฟล์สำรองข้อมูลย้อนหลัง ผลการตรวจสอบ SHA-256 และข้อกำหนดธรรมาภิบาลสำหรับการกู้คืนข้อมูล',
    breadcrumbs: [{ label: 'Backup & Recovery', to: '/admin/backups' }, { label: 'Backup History' }],
    permissions: ['backup.view', 'backup.verify'],
  },
  health: {
    title: 'ตรวจสอบสถานะระบบและโครงสร้างพื้นฐาน (System Health & Monitoring)',
    subtitle: 'ติดตามสถานะ PHP-FPM, MySQL Latency, พื้นที่จัดเก็บข้อมูล, ความพร้อมของตารางฐานข้อมูล และ Scheduled Jobs',
    breadcrumbs: [{ label: 'System Monitoring' }],
    permissions: ['health.view', 'settings.view'],
  },
  reports: {
    title: 'ศูนย์รายงานผู้บริหารและการปฏิบัติตามเกณฑ์ (Compliance & Operational Reports)',
    subtitle: 'รายงานสรุปสำหรับผู้บริหาร ผู้ตรวจสอบกิจการ ฝ่ายการเงิน และฝ่ายบริหารสมาชิก พร้อมส่งออก CSV',
    breadcrumbs: [{ label: 'Reports' }],
    permissions: ['reports.view', 'finance.view', 'audit.view', 'dashboard.view'],
  },
  settings: {
    title: 'ตั้งค่าระบบและนโยบายความปลอดภัย (System Configuration & Profile)',
    subtitle: 'ตรวจสอบค่าพารามิเตอร์ความปลอดภัยของระบบ สถานะฟีเจอร์ และจัดการข้อมูลบัญชีผู้ใช้งานของคุณ',
    breadcrumbs: [{ label: 'Settings' }],
    permissions: ['settings.view', 'dashboard.view'],
  },
};

const KPI_ICONS = {
  total_users: Users,
  active_staff: UserCheck,
  active_members: Users,
  successful_logins: CheckCircle2,
  failed_logins: AlertTriangle,
  mfa_coverage: ShieldCheck,
  active_sessions: Activity,
  system_health: Server,
  latest_backup: HardDriveDownload,
  failed_jobs: Clock,
  pending_tasks: FolderKanban,
  security_alerts: ShieldAlert,
};

export default function AdminControlCenterPage({ section: propSection }) {
  const location = useLocation();
  const [searchParams, setSearchParams] = useSearchParams();
  const { user, can } = useAuth();

  const section = useMemo(() => {
    if (propSection) return propSection;
    const path = location.pathname.replace(/^\/admin\/?/, '') || 'dashboard';
    if (path === 'security/sessions') return 'sessions';
    if (path === 'security/mfa') return 'mfa';
    if (path === 'backups/history') return 'backups-history';
    return SECTION_META[path] ? path : 'dashboard';
  }, [propSection, location.pathname]);

  const meta = SECTION_META[section] || SECTION_META.dashboard;
  const authorized = hasControlCenterAccess(user, can, meta.permissions);

  const [days, setDays] = useState(30);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [refreshTick, setRefreshTick] = useState(0);
  const [passwordModalOpen, setPasswordModalOpen] = useState(false);

  // Section states
  const [summaryData, setSummaryData] = useState(null);
  const [trendsData, setTrendsData] = useState(null);
  const [opsData, setOpsData] = useState(null);
  const [securityData, setSecurityData] = useState(null);
  const [sessionsData, setSessionsData] = useState(null);
  const [mfaData, setMfaData] = useState(null);
  const [auditData, setAuditData] = useState(null);
  const [backupData, setBackupData] = useState(null);
  const [healthData, setHealthData] = useState(null);
  const [permMatrixData, setPermMatrixData] = useState(null);
  const [deptData, setDeptData] = useState(null);
  const [reportsData, setReportsData] = useState(null);
  const [staffDirData, setStaffDirData] = useState(null);

  // Audit filter states
  const [auditCategory, setAuditCategory] = useState(searchParams.get('category') || '');
  const [auditSeverity, setAuditSeverity] = useState('');
  const [auditQuery, setAuditQuery] = useState('');
  const [auditPage, setAuditPage] = useState(1);

  useEffect(() => {
    const cat = searchParams.get('category') || '';
    if (section === 'audit-logs' && cat !== auditCategory) {
      setAuditCategory(cat);
      setAuditPage(1);
    }
  }, [searchParams, section, auditCategory]);

  const loadSectionData = useCallback(async (signal) => {
    if (!authorized) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError('');
    try {
      if (section === 'dashboard') {
        const [sum, trn] = await Promise.all([
          memberAdminApi('/api/admin/dashboard/summary', { signal }),
          memberAdminApi(`/api/admin/dashboard/trends?days=${days}`, { signal }),
        ]);
        setSummaryData(sum);
        setTrendsData(trn);
      } else if (section === 'operations') {
        const ops = await memberAdminApi('/api/admin/operations/overview', { signal });
        setOpsData(ops);
      } else if (section === 'users' || section === 'roles' || section === 'permissions') {
        const requests = [memberAdminApi('/api/admin/access/permissions', { signal })];
        if (user?.role === 'super_admin') {
          requests.push(memberAdminApi('/api/admin/staff', { signal }).catch(() => null));
        }
        const [matrix, staffRes] = await Promise.all(requests);
        setPermMatrixData(matrix);
        if (staffRes) setStaffDirData(staffRes);
      } else if (section === 'departments') {
        const dept = await memberAdminApi('/api/admin/departments', { signal });
        setDeptData(dept);
      } else if (section === 'audit-logs') {
        const params = new URLSearchParams({
          page: String(auditPage),
          per_page: '25',
        });
        if (auditCategory) params.set('category', auditCategory);
        if (auditSeverity) params.set('severity', auditSeverity);
        if (auditQuery.trim()) params.set('q', auditQuery.trim());
        const logs = await memberAdminApi(`/api/admin/audit-logs/center?${params.toString()}`, { signal });
        setAuditData(logs);
      } else if (section === 'security') {
        const sec = await memberAdminApi('/api/admin/security/overview', { signal });
        setSecurityData(sec);
      } else if (section === 'sessions') {
        const ses = await memberAdminApi('/api/admin/security/sessions', { signal });
        setSessionsData(ses);
      } else if (section === 'mfa') {
        const mfa = await memberAdminApi('/api/admin/security/mfa', { signal });
        setMfaData(mfa);
      } else if (section === 'backups' || section === 'backups-history') {
        const bkp = await memberAdminApi('/api/admin/backups', { signal });
        setBackupData(bkp);
      } else if (section === 'health' || section === 'settings') {
        const hlt = await memberAdminApi('/api/admin/system/health', { signal }).catch(() => null);
        setHealthData(hlt);
      } else if (section === 'reports') {
        const [rep, sum] = await Promise.all([
          memberAdminApi('/api/admin/reports/catalog', { signal }),
          memberAdminApi('/api/admin/dashboard/summary', { signal }).catch(() => null),
        ]);
        setReportsData(rep);
        if (sum) setSummaryData(sum);
      }
    } catch (err) {
      if (err?.name !== 'AbortError') {
        setError(err?.message || 'ไม่สามารถโหลดข้อมูลได้');
      }
    } finally {
      if (!signal?.aborted) {
        setLoading(false);
      }
    }
  }, [authorized, section, days, auditPage, auditCategory, auditSeverity, auditQuery, user?.role]);

  useEffect(() => {
    const controller = new AbortController();
    loadSectionData(controller.signal);
    return () => controller.abort();
  }, [loadSectionData, refreshTick]);

  const handleRevokeStaffSessions = async (targetUserId, targetName) => {
    if (typeof window !== 'undefined' && !window.confirm(`ยืนยันการเพิกถอนเซสชันทั้งหมดของ ${targetName}?`)) {
      return;
    }
    setNotice('');
    setError('');
    try {
      await memberAdminApi(`/api/admin/staff/${targetUserId}/revoke-sessions`, { data: {} });
      setNotice(`เพิกถอนเซสชันของ ${targetName} และบันทึก Audit Log เรียบร้อยแล้ว`);
      setRefreshTick((t) => t + 1);
    } catch (err) {
      setError(err?.message || 'ไม่สามารถเพิกถอนเซสชันได้');
    }
  };

  if (!authorized) {
    return (
      <AdminControlCenterShell
        title="ไม่มีสิทธิ์เข้าถึงส่วนนี้ (Access Denied)"
        subtitle="ระบบบังคับใช้นโยบาย Least Privilege และ Default Deny กรุณาติดต่อผู้ดูแลระบบสูงสุดหากท่านจำเป็นต้องขออนุมัติสิทธิ์"
        breadcrumbs={meta.breadcrumbs}
      >
        <div className="acc-card" role="alert">
          <h2 className="acc-card-title">
            <ShieldAlert size={20} /> คุณไม่มีสิทธิ์เข้าถึงส่วนนี้
          </h2>
          <p className="acc-card-subtitle" style={{ marginTop: 8 }}>
            บัญชีของคุณ ({user?.roleName || user?.role}) ไม่มีสิทธิ์ที่จำเป็นสำหรับโมดูลนี้ ({meta.permissions.join(', ')})
          </p>
          <div style={{ marginTop: 16, display: 'flex', gap: 10 }}>
            <Link to="/admin/workspace" className="acc-btn primary">กลับพื้นที่ทำงานเจ้าหน้าที่</Link>
            <Link to="/admin/dashboard" className="acc-btn">กลับหน้าภาพรวม</Link>
          </div>
        </div>
      </AdminControlCenterShell>
    );
  }

  const headerActions = (
    <>
      {section === 'dashboard' && (
        <select
          className="acc-select"
          aria-label="เลือกช่วงเวลาของกราฟ"
          value={days}
          onChange={(e) => setDays(Number(e.target.value))}
        >
          <option value={7}>ย้อนหลัง 7 วัน</option>
          <option value={30}>ย้อนหลัง 30 วัน</option>
          <option value={90}>ย้อนหลัง 90 วัน</option>
        </select>
      )}
      <button
        type="button"
        className="acc-btn"
        onClick={() => setRefreshTick((t) => t + 1)}
        disabled={loading}
      >
        <RefreshCw size={15} />
        <span>{loading ? 'กำลังโหลด...' : 'รีเฟรชข้อมูล'}</span>
      </button>
      {user?.role === 'super_admin' && (
        <Link to="/admin/cms" className="acc-btn primary">
          <Layers size={15} />
          <span>เปิดระบบจัดการเว็บไซต์ (CMS 8 โมดูล)</span>
        </Link>
      )}
    </>
  );

  const variantToTone = (variant) => {
    if (variant === 'emerald' || variant === 'teal') return 'success';
    if (variant === 'gold') return 'warning';
    if (variant === 'rose') return 'danger';
    return 'info';
  };

  return (
    <AdminControlCenterShell
      title={meta.title}
      subtitle={meta.subtitle}
      breadcrumbs={meta.breadcrumbs}
      actions={headerActions}
    >
      {error && (
        <div className="acc-card" role="alert" style={{ borderColor: 'var(--acc-danger)', background: 'var(--acc-danger-bg)' }}>
          <strong style={{ color: 'var(--acc-danger)' }}>แจ้งเตือนข้อผิดพลาด: </strong>
          <span>{error}</span>
        </div>
      )}

      {notice && (
        <div className="acc-card" role="status" style={{ borderColor: 'var(--acc-success)', background: 'var(--acc-success-bg)' }}>
          <strong style={{ color: 'var(--acc-success)' }}>สำเร็จ: </strong>
          <span>{notice}</span>
        </div>
      )}

      {loading && (
        <div className="acc-kpi-grid" aria-busy="true" aria-label="กำลังโหลดข้อมูล">
          {[1, 2, 3, 4].map((n) => (
            <div key={n} className="acc-skeleton" />
          ))}
        </div>
      )}

      {/* ===================================================================
          1. EXECUTIVE DASHBOARD (/admin/dashboard)
         =================================================================== */}
      {!loading && section === 'dashboard' && summaryData && (
        <>
          <div className="acc-kpi-grid">
            {(Array.isArray(summaryData.kpis) ? summaryData.kpis : []).map((card, idx) => {
              const Icon = KPI_ICONS[card.key] || Activity;
              const tone = variantToTone(card.variant);
              return (
                <article key={card.key || idx} className="acc-kpi-card">
                  <div className="acc-kpi-top">
                    <span className="acc-kpi-label">{`${idx + 1}. ${card.title}`}</span>
                    <span className={`acc-kpi-icon ${tone}`}>
                      <Icon size={19} />
                    </span>
                  </div>
                  <div className="acc-kpi-value">
                    {card.display ?? formatNumber(card.value)}
                    {card.unit ? <small style={{ fontSize: '0.85rem', fontWeight: 500, marginLeft: 6 }}>{card.unit}</small> : null}
                  </div>
                  <div className="acc-kpi-sub">
                    <span>{card.subtitle}</span>
                  </div>
                </article>
              );
            })}
          </div>

          {trendsData && (
            <>
              <div className="acc-grid-2">
                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">1. สถิติการเข้าสู่ระบบรายวัน (Daily Logins - {days} วัน)</h2>
                      <p className="acc-card-subtitle">เปรียบเทียบการเข้าสู่ระบบสำเร็จและล้มเหลวจากฐานข้อมูลจริง</p>
                    </div>
                    <button
                      type="button"
                      className="acc-btn"
                      onClick={() =>
                        downloadCsv(
                          `daily-logins-${days}d.csv`,
                          ['Date', 'Success', 'Failed', 'Total'],
                          (trendsData.charts?.daily_logins || []).map((r) => [r.date, r.success, r.failed, r.total])
                        )
                      }
                    >
                      <Download size={14} /> CSV
                    </button>
                  </div>
                  <SvgTrendChart
                    data={trendsData.charts?.daily_logins || []}
                    xKey="date"
                    series={[
                      { key: 'success', label: 'เข้าสู่ระบบสำเร็จ', color: '#0284c7' },
                      { key: 'failed', label: 'เข้าสู่ระบบไม่สำเร็จ', color: '#dc2626' },
                    ]}
                  />
                </section>

                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">2. สัดส่วนผลการเข้าสู่ระบบ (Successful vs Failed Logins)</h2>
                      <p className="acc-card-subtitle">วิเคราะห์แนวโน้มความพยายามพิสูจน์ตัวตนย้อนหลัง {days} วัน</p>
                    </div>
                  </div>
                  <HorizontalBarChart
                    items={trendsData.charts?.login_outcomes || []}
                    labelKey="label"
                    valueKey="count"
                    suffix=" ครั้ง"
                  />
                </section>
              </div>

              <div className="acc-grid-2">
                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">3. กิจกรรมผู้ดูแลระบบแยกตามโมดูล (Activity by Module)</h2>
                      <p className="acc-card-subtitle">วัดจากจำนวนรายการใน Audit Trail ย้อนหลัง {days} วัน</p>
                    </div>
                    <button
                      type="button"
                      className="acc-btn"
                      onClick={() =>
                        downloadCsv(
                          `module-activity-${days}d.csv`,
                          ['Module', 'Count'],
                          (trendsData.charts?.activity_by_module || []).map((r) => [r.module, r.count])
                        )
                      }
                    >
                      <Download size={14} /> CSV
                    </button>
                  </div>
                  <HorizontalBarChart
                    items={trendsData.charts?.activity_by_module || []}
                    labelKey="module"
                    valueKey="count"
                    suffix=" รายการ"
                  />
                </section>

                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">4. ความครอบคลุม MFA แยกตามบทบาท (MFA Coverage by Role)</h2>
                      <p className="acc-card-subtitle">ร้อยละของเจ้าหน้าที่แต่ละบทบาทที่เปิดใช้งาน TOTP 2FA แล้ว</p>
                    </div>
                  </div>
                  <HorizontalBarChart
                    items={(trendsData.charts?.mfa_by_role || []).map((r) => ({
                      label: `${r.role_name} (${r.mfa_enrolled}/${r.total_users})`,
                      value: r.coverage_percent,
                    }))}
                    labelKey="label"
                    valueKey="value"
                    suffix="%"
                  />
                </section>
              </div>

              <div className="acc-grid-2">
                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">5. แนวโน้มการเติบโตของบัญชีผู้ใช้งาน (User Growth Trend)</h2>
                      <p className="acc-card-subtitle">จำนวนบัญชีที่สร้างใหม่รายเดือน</p>
                    </div>
                  </div>
                  <SvgTrendChart
                    data={trendsData.charts?.user_growth || []}
                    xKey="period"
                    series={[{ key: 'users_created', label: 'บัญชีผู้ใช้งานใหม่', color: '#16a34a' }]}
                  />
                </section>

                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">6. แนวโน้มเหตุการณ์ผิดปกติของระบบ (System Errors Over Time)</h2>
                      <p className="acc-card-subtitle">รวบรวมจาก System Logs ระดับ Warning / Error / Critical</p>
                    </div>
                  </div>
                  <SvgTrendChart
                    data={trendsData.charts?.system_errors || []}
                    xKey="date"
                    series={[{ key: 'count', label: 'จำนวนเหตุการณ์ผิดปกติ', color: '#d97706' }]}
                  />
                </section>
              </div>

              <div className="acc-grid-2">
                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">7. การกระจายตัวของ HTTP / Security Rejections</h2>
                      <p className="acc-card-subtitle">{trendsData.charts?.http_error_distribution?.status_label}</p>
                    </div>
                  </div>
                  <HorizontalBarChart
                    items={trendsData.charts?.http_error_distribution?.application_rejections || []}
                    labelKey="code"
                    valueKey="count"
                    suffix=" ครั้ง"
                  />
                </section>

                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">8. ประวัติขนาดไฟล์สำรองข้อมูลล่าสุด (Backup Archive Trend)</h2>
                      <p className="acc-card-subtitle">ตรวจสอบจากไฟล์สำรองข้อมูลจริงใน storage/backups (KB)</p>
                    </div>
                  </div>
                  <HorizontalBarChart
                    items={trendsData.charts?.backup_trend || []}
                    labelKey="filename"
                    valueKey="size_kb"
                    suffix=" KB"
                    emptyLabel="ยังไม่มีไฟล์สำรองข้อมูลใน storage/backups"
                  />
                </section>
              </div>

              <div className="acc-grid-2">
                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">9. การใช้พื้นที่จัดเก็บข้อมูล (Storage Usage Breakdown)</h2>
                      <p className="acc-card-subtitle">ขนาดไฟล์จริงในโฟลเดอร์ Uploads, Logs และ Backups (MB)</p>
                    </div>
                  </div>
                  <HorizontalBarChart
                    items={trendsData.charts?.storage_usage?.categories || []}
                    labelKey="label"
                    valueKey="size_mb"
                    suffix=" MB"
                  />
                </section>

                <section className="acc-card">
                  <div className="acc-card-header">
                    <div>
                      <h2 className="acc-card-title">10. งานรอดำเนินการแยกตามหน่วยงาน (Pending Work by Department)</h2>
                      <p className="acc-card-subtitle">คำร้องสมาชิกและรายการรอตรวจสอบแยกตามสังกัด</p>
                    </div>
                  </div>
                  <HorizontalBarChart
                    items={trendsData.charts?.pending_by_department || []}
                    labelKey="department"
                    valueKey="pending_count"
                    suffix=" รายการ"
                    emptyLabel="ยังไม่มีงานค้างรอดำเนินการในขณะนี้"
                  />
                </section>
              </div>
            </>
          )}
        </>
      )}

      {/* ===================================================================
          2. OPERATIONS CENTER (/admin/operations)
         =================================================================== */}
      {!loading && section === 'operations' && opsData && (
        <>
          <div className="acc-kpi-grid">
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">คำร้องแก้ไขข้อมูลสมาชิก (Pending)</span>
                <span className="acc-kpi-icon warning"><Users size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(opsData.summary?.member_requests ?? 0)}</div>
              <div className="acc-kpi-sub"><Link to="/admin/members/dashboard">ไปที่ระบบจัดการสมาชิก →</Link></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">เรื่องร้องเรียนรอดำเนินการ</span>
                <span className="acc-kpi-icon danger"><AlertTriangle size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(opsData.summary?.complaints ?? 0)}</div>
              <div className="acc-kpi-sub"><Link to="/admin/cms">จัดการข้อร้องเรียน →</Link></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">ข่าวสารและเนื้อหารออนุมัติเผยแพร่</span>
                <span className="acc-kpi-icon info"><FolderKanban size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(opsData.summary?.content_drafts ?? 0)}</div>
              <div className="acc-kpi-sub"><Link to="/admin/content?tab=news">ไปที่จัดการข่าวสาร →</Link></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">คิวตรวจสอบบุคคลล้มละลาย (LED)</span>
                <span className="acc-kpi-icon info"><ShieldCheck size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(opsData.summary?.led_reviews ?? 0)}</div>
              <div className="acc-kpi-sub"><Link to="/admin/led/review">เปิดคิวตรวจสอบ LED →</Link></div>
            </article>
          </div>

          <div className="acc-grid-2">
            <section className="acc-card">
              <div className="acc-card-header">
                <div>
                  <h2 className="acc-card-title">ข่าวสารฉบับร่าง / รอตรวจสอบ (News Drafts)</h2>
                  <p className="acc-card-subtitle">รายการข่าวที่ยังไม่ได้เผยแพร่สู่หน้าเว็บไซต์สาธารณะ</p>
                </div>
                <Link to="/admin/content?tab=news" className="acc-btn">จัดการข่าวสาร <ExternalLink size={13} /></Link>
              </div>
              {(opsData.queues?.news_drafts || []).length === 0 ? (
                <div className="acc-empty-state">ไม่มีข่าวสารฉบับร่างที่รอดำเนินการ</div>
              ) : (
                <div className="acc-table-wrap">
                  <table className="acc-table">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>หัวข้อข่าว</th>
                        <th>สถานะ</th>
                        <th>อัปเดตล่าสุด</th>
                      </tr>
                    </thead>
                    <tbody>
                      {opsData.queues.news_drafts.map((n) => (
                        <tr key={n.id}>
                          <td>#{n.id}</td>
                          <td><strong>{n.title}</strong></td>
                          <td><span className="acc-badge-pill warning">{n.workflow_status}</span></td>
                          <td>{n.updated_at}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>

            <section className="acc-card">
              <div className="acc-card-header">
                <div>
                  <h2 className="acc-card-title">คำร้องแก้ไขข้อมูลสมาชิก (Pending Member Requests)</h2>
                  <p className="acc-card-subtitle">คำร้องขอแก้ไขเบอร์โทรศัพท์ อีเมล หรือที่อยู่จากสมาชิก</p>
                </div>
                <Link to="/admin/members/dashboard" className="acc-btn">เปิดระบบสมาชิก <ExternalLink size={13} /></Link>
              </div>
              {(opsData.queues?.member_profile_requests || []).length === 0 ? (
                <div className="acc-empty-state">ไม่มีคำร้องแก้ไขข้อมูลสมาชิกที่ค้างอยู่</div>
              ) : (
                <div className="acc-table-wrap">
                  <table className="acc-table">
                    <thead>
                      <tr>
                        <th>เลขสมาชิก</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>สถานะ</th>
                        <th>วันที่ยื่น</th>
                      </tr>
                    </thead>
                    <tbody>
                      {opsData.queues.member_profile_requests.map((req) => (
                        <tr key={req.id}>
                          <td><code>{req.member_no}</code></td>
                          <td>{req.first_name} {req.last_name}</td>
                          <td><span className="acc-badge-pill warning">{req.status}</span></td>
                          <td>{req.created_at}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          </div>
        </>
      )}

      {/* ===================================================================
          3. USERS, ROLES & PERMISSIONS (/admin/users, /admin/roles, /admin/permissions)
         =================================================================== */}
      {!loading && (section === 'users' || section === 'roles' || section === 'permissions') && permMatrixData && (
        <>
          <div className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">การจัดการสิทธิ์และบทบาทตามมาตรฐาน Enterprise RBAC (10 บทบาทมาตรฐาน)</h2>
                <p className="acc-card-subtitle">
                  เชื่อมโยงบทบาทมาตรฐานเข้ากับรหัสบทบาทในฐานข้อมูลจริง โดยไม่เขียนทับหรือลบสิทธิ์เดิม
                </p>
              </div>
              {user?.role === 'super_admin' && (
                <Link to="/admin/access" className="acc-btn primary">
                  <KeyRound size={15} /> เปิดหน้าแก้ไขบทบาทและสร้างบัญชีเจ้าหน้าที่ (RBAC CRUD)
                </Link>
              )}
            </div>

            <div className="acc-table-wrap">
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>ลำดับ</th>
                    <th>บทบาทมาตรฐาน (Enterprise Role)</th>
                    <th>รหัสในฐานข้อมูล (Canonical Slug)</th>
                    <th>ชื่อภาษาไทย</th>
                    <th>ระดับการแยกหน้าที่ (Tier)</th>
                    <th>สิทธิ์เริ่มต้น (Default Permissions)</th>
                  </tr>
                </thead>
                <tbody>
                  {(permMatrixData.enterprise_role_map || []).map((r) => (
                    <tr key={r.order}>
                      <td>#{r.order}</td>
                      <td><strong>{r.proposed_name}</strong></td>
                      <td><code>{r.canonical_slug}</code></td>
                      <td>{r.thai_name}</td>
                      <td><span className="acc-badge-pill">{r.separation_tier}</span></td>
                      <td>{(r.default_permissions || []).join(', ')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <section className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">บทบาทที่มีอยู่ในฐานข้อมูลปัจจุบัน (Active Database Roles)</h2>
                <p className="acc-card-subtitle">ตรวจสอบจำนวนผู้ใช้งานและสถานะการป้องกันของแต่ละบทบาท</p>
              </div>
            </div>
            <div className="acc-table-wrap">
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>ชื่อบทบาท</th>
                    <th>รหัส (Slug)</th>
                    <th>ประเภท</th>
                    <th>จำนวนผู้ใช้งาน</th>
                    <th>คำอธิบาย</th>
                  </tr>
                </thead>
                <tbody>
                  {(permMatrixData.db_roles || []).map((r) => (
                    <tr key={r.id}>
                      <td><strong>{r.name}</strong></td>
                      <td><code>{r.slug}</code></td>
                      <td>
                        <span className={`acc-badge-pill ${r.protected ? 'warning' : 'success'}`}>
                          {r.protected ? 'System Protected' : r.builtin ? 'Built-in Role' : 'Custom Role'}
                        </span>
                      </td>
                      <td>{formatNumber(r.users)} บัญชี</td>
                      <td>{r.description || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {section === 'users' && staffDirData?.data?.staff && (
            <section className="acc-card">
              <div className="acc-card-header">
                <div>
                  <h2 className="acc-card-title">รายชื่อบัญชีเจ้าหน้าที่และสถานะความปลอดภัย (Staff Directory)</h2>
                  <p className="acc-card-subtitle">ข้อมูลจากระบบบริหารจัดการเจ้าหน้าที่ (/api/admin/staff)</p>
                </div>
              </div>
              <div className="acc-table-wrap">
                <table className="acc-table">
                  <thead>
                    <tr>
                      <th>ชื่อ-นามสกุล</th>
                      <th>ชื่อผู้ใช้ / อีเมล</th>
                      <th>บทบาท</th>
                      <th>สถานะบัญชี</th>
                      <th>Google OIDC</th>
                      <th>Mandatory MFA</th>
                      <th>การจัดการเซสชัน</th>
                    </tr>
                  </thead>
                  <tbody>
                    {staffDirData.data.staff.map((st) => (
                      <tr key={st.id}>
                        <td><strong>{st.name}</strong></td>
                        <td>{st.username}<br /><small>{st.email}</small></td>
                        <td>{st.role_name || st.role_slug}</td>
                        <td>
                          <span className={`acc-badge-pill ${st.status === 'active' ? 'success' : 'danger'}`}>
                            {st.status}
                          </span>
                        </td>
                        <td>{st.google_email ? `ผูกแล้ว (${st.google_email})` : 'ยังไม่ผูก'}</td>
                        <td>
                          <span className={`acc-badge-pill ${Number(st.two_factor_enabled) === 1 ? 'success' : 'warning'}`}>
                            {Number(st.two_factor_enabled) === 1 ? 'เปิดใช้งาน MFA แล้ว' : 'รอลงทะเบียน MFA'}
                          </span>
                        </td>
                        <td>
                          <button
                            type="button"
                            className="acc-btn danger"
                            onClick={() => handleRevokeStaffSessions(st.id, st.name)}
                          >
                            เพิกถอนเซสชัน
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}

          <section className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">รายการสิทธิ์แบบละเอียด (Resource-Action Permission Catalog)</h2>
                <p className="acc-card-subtitle">สิทธิ์ทั้งหมดที่ควบคุมด้วย PHP Middleware และตรวจสอบซ้ำในระดับ API</p>
              </div>
            </div>
            <div className="acc-table-wrap">
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>Permission Key</th>
                    <th>โมดูล (Module)</th>
                    <th>การกระทำ (Action)</th>
                    <th>คำอธิบายสิทธิ์ (ภาษาไทย)</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(permMatrixData.permission_catalog || {}).map(([permKey, info]) => (
                    <tr key={permKey}>
                      <td><code>{permKey}</code></td>
                      <td>{info?.module || permKey.split('.')[0]}</td>
                      <td><code>{info?.action || permKey.split('.')[1]}</code></td>
                      <td>{typeof info === 'object' ? info.label : String(info)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}

      {/* ===================================================================
          4. DEPARTMENTS (/admin/departments)
         =================================================================== */}
      {!loading && section === 'departments' && deptData && (
        <section className="acc-card">
          <div className="acc-card-header">
            <div>
              <h2 className="acc-card-title">หน่วยงานและแผนกของเจ้าหน้าที่ (Staff Departments Summary)</h2>
              <p className="acc-card-subtitle">สรุปจำนวนบัญชีเจ้าหน้าที่และสถานะการปฏิบัติงานแยกตามแผนก</p>
            </div>
          </div>
          {(deptData.staff_departments || []).length === 0 ? (
            <div className="acc-empty-state">ยังไม่มีข้อมูลแผนกของเจ้าหน้าที่ในฐานข้อมูล</div>
          ) : (
            <div className="acc-table-wrap">
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>ชื่อหน่วยงาน / แผนก</th>
                    <th>เจ้าหน้าที่ทั้งหมด</th>
                    <th>สถานะปฏิบัติงาน (Active)</th>
                  </tr>
                </thead>
                <tbody>
                  {deptData.staff_departments.map((d, idx) => (
                    <tr key={`${d.department}-${idx}`}>
                      <td><strong>{d.department}</strong></td>
                      <td>{formatNumber(d.staff_count)} บัญชี</td>
                      <td>{formatNumber(d.active_count)} บัญชี</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {/* ===================================================================
          5. AUDIT & ACTIVITY LOGS (/admin/audit-logs)
         =================================================================== */}
      {!loading && section === 'audit-logs' && auditData && (
        <>
          <div className="acc-filter-bar">
            <select
              className="acc-select"
              aria-label="หมวดหมู่บันทึก"
              value={auditCategory}
              onChange={(e) => {
                const val = e.target.value;
                setAuditCategory(val);
                setAuditPage(1);
                if (val) setSearchParams({ category: val });
                else setSearchParams({});
              }}
            >
              <option value="">ทุกหมวดหมู่ (All Categories)</option>
              <option value="authentication">Authentication Logs (เข้าสู่ระบบ/MFA)</option>
              <option value="security">Security Events (ความปลอดภัย)</option>
              <option value="access">User & Role Changes (สิทธิ์และบทบาท)</option>
              <option value="members">Member Operations (ข้อมูลสมาชิก)</option>
              <option value="finance">Financial & Dividends (การเงินและปันผล)</option>
              <option value="content">Content Management (ข่าวสารและเนื้อหา)</option>
              <option value="export">Export History (ประวัติการส่งออกข้อมูล)</option>
            </select>

            <select
              className="acc-select"
              aria-label="ระดับความสำคัญ"
              value={auditSeverity}
              onChange={(e) => {
                setAuditSeverity(e.target.value);
                setAuditPage(1);
              }}
            >
              <option value="">ทุกระดับความรุนแรง (All Severities)</option>
              <option value="info">Info (ปกติ)</option>
              <option value="warning">Warning (เฝ้าระวัง)</option>
              <option value="critical">Critical (วิกฤต)</option>
            </select>

            <input
              type="search"
              className="acc-input"
              placeholder="ค้นหาผู้ใช้, โมดูล, กิจกรรม หรือ Target ID..."
              aria-label="ค้นหาบันทึกการตรวจสอบ"
              value={auditQuery}
              onChange={(e) => {
                setAuditQuery(e.target.value);
                setAuditPage(1);
              }}
              style={{ minWidth: 240, flex: 1 }}
            />

            <button
              type="button"
              className="acc-btn primary"
              onClick={() =>
                downloadCsv(
                  `audit-logs-page-${auditPage}.csv`,
                  ['Timestamp', 'Actor', 'Role', 'Category', 'Module', 'Action', 'Target', 'IP', 'Severity', 'CorrelationID'],
                  (auditData.records || []).map((it) => [
                    it.created_at,
                    it.actor_name,
                    it.actor_role,
                    it.category,
                    it.module,
                    it.action,
                    it.target_resource,
                    it.ip_address,
                    it.severity,
                    it.correlation_id,
                  ])
                )
              }
            >
              <Download size={15} /> ส่งออก CSV
            </button>
          </div>

          <section className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">รายการบันทึกการตรวจสอบ (Audit Trail & Security Events)</h2>
                <p className="acc-card-subtitle">
                  แสดงหน้า {auditData.page || 1} (ทั้งหมด {formatNumber(auditData.total)} รายการ) • ระบบปกปิดรหัสผ่านและคีย์ลับอัตโนมัติ ([REDACTED])
                </p>
              </div>
            </div>

            {(auditData.records || []).length === 0 ? (
              <div className="acc-empty-state">ไม่พบบันทึกกิจกรรมที่ตรงกับเงื่อนไขการค้นหา</div>
            ) : (
              <div className="acc-table-wrap">
                <table className="acc-table">
                  <thead>
                    <tr>
                      <th>วัน-เวลา</th>
                      <th>ผู้ดำเนินการ (Actor)</th>
                      <th>หมวดหมู่</th>
                      <th>โมดูล / การกระทำ</th>
                      <th>เป้าหมาย (Target)</th>
                      <th>IP Address</th>
                      <th>ระดับ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {auditData.records.map((item) => (
                      <tr key={item.id}>
                        <td style={{ whiteSpace: 'nowrap' }}>
                          {item.created_at}
                          <br />
                          <small><code>{item.correlation_id}</code></small>
                        </td>
                        <td>
                          <strong>{item.actor_name}</strong>
                          <br />
                          <small>{item.actor_role_name || item.actor_role}</small>
                        </td>
                        <td><span className="acc-badge-pill">{item.category}</span></td>
                        <td>
                          <strong>{item.module}</strong> : <code>{item.action}</code>
                        </td>
                        <td>{item.target_resource}</td>
                        <td><code>{item.ip_address || '-'}</code></td>
                        <td>
                          <span
                            className={`acc-badge-pill ${
                              item.severity === 'critical'
                                ? 'danger'
                                : item.severity === 'warning'
                                ? 'warning'
                                : 'success'
                            }`}
                          >
                            {item.severity}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 16 }}>
              <button
                type="button"
                className="acc-btn"
                disabled={auditPage <= 1}
                onClick={() => setAuditPage((p) => Math.max(1, p - 1))}
              >
                ← หน้าก่อนหน้า
              </button>
              <span>
                หน้า {auditData.page || 1} / {auditData.pagination?.total_pages || 1}
              </span>
              <button
                type="button"
                className="acc-btn"
                disabled={auditPage >= (auditData.pagination?.total_pages || 1)}
                onClick={() => setAuditPage((p) => p + 1)}
              >
                หน้าถัดไป →
              </button>
            </div>
          </section>
        </>
      )}

      {/* ===================================================================
          6. SECURITY CENTER (/admin/security)
         =================================================================== */}
      {!loading && section === 'security' && securityData && (
        <>
          <div className="acc-kpi-grid">
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">คะแนนสุขภาพความปลอดภัย (Rule-Based Score)</span>
                <span className="acc-kpi-icon success"><ShieldCheck size={18} /></span>
              </div>
              <div className="acc-kpi-value">{securityData.health_score?.score ?? 0} / {securityData.health_score?.max_score ?? 100}</div>
              <div className="acc-kpi-sub"><span>ประเมินจากกฎความปลอดภัย 5 ข้อ</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">เข้าสู่ระบบล้มเหลว (24 ชั่วโมง)</span>
                <span className="acc-kpi-icon warning"><AlertTriangle size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(securityData.metrics?.failed_logins_24h)}</div>
              <div className="acc-kpi-sub"><span>7 วันล่าสุด: {formatNumber(securityData.metrics?.failed_logins_7d)} ครั้ง</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">ยืนยันตัวตน MFA ไม่สำเร็จ (7 วัน)</span>
                <span className="acc-kpi-icon warning"><Lock size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(securityData.metrics?.mfa_failures_7d)}</div>
              <div className="acc-kpi-sub"><Link to="/admin/security/mfa">ดูสถานะ MFA รายบุคคล →</Link></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">การเพิกถอนเซสชัน (30 วัน)</span>
                <span className="acc-kpi-icon danger"><XCircle size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(securityData.metrics?.session_revocations_30d)}</div>
              <div className="acc-kpi-sub"><Link to="/admin/security/sessions">จัดการเซสชันที่ออนไลน์ →</Link></div>
            </article>
          </div>

          <div className="acc-grid-2">
            <section className="acc-card">
              <div className="acc-card-header">
                <div>
                  <h2 className="acc-card-title">วิธีคำนวณคะแนนความปลอดภัยแบบโปร่งใส (Rule-Based Score Breakdown)</h2>
                  <p className="acc-card-subtitle">{securityData.disclaimer}</p>
                </div>
              </div>
              <div className="acc-table-wrap">
                <table className="acc-table">
                  <thead>
                    <tr>
                      <th>เกณฑ์การประเมิน (Security Rule)</th>
                      <th>คะแนนที่ได้</th>
                      <th>รายละเอียด</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(securityData.health_score?.rules || []).map((r) => (
                      <tr key={r.rule}>
                        <td><strong>{r.rule}</strong></td>
                        <td>
                          <span className={`acc-badge-pill ${r.status === 'pass' ? 'success' : 'warning'}`}>
                            {r.earned} / {r.weight}
                          </span>
                        </td>
                        <td>{r.detail}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            <section className="acc-card">
              <div className="acc-card-header">
                <div>
                  <h2 className="acc-card-title">เหตุการณ์การเข้าสู่ระบบล่าสุด (Recent Login & Security Events)</h2>
                  <p className="acc-card-subtitle">แสดงรายการจากตาราง login_logs พร้อมปกปิด IP บางส่วนตามนโยบาย PDPA</p>
                </div>
              </div>
              {(securityData.recent_events || []).length === 0 ? (
                <div className="acc-empty-state">ยังไม่มีบันทึกเหตุการณ์ในตาราง login_logs</div>
              ) : (
                <div className="acc-table-wrap">
                  <table className="acc-table">
                    <thead>
                      <tr>
                        <th>วัน-เวลา</th>
                        <th>ผู้ใช้งาน / อีเมล</th>
                        <th>สถานะ</th>
                        <th>IP Address</th>
                      </tr>
                    </thead>
                    <tbody>
                      {securityData.recent_events.map((ev) => (
                        <tr key={ev.id}>
                          <td>{ev.created_at}</td>
                          <td>{ev.user_name}<br /><small>{ev.email}</small></td>
                          <td>
                            <span className={`acc-badge-pill ${ev.status === 'success' ? 'success' : 'danger'}`}>
                              {ev.status}
                            </span>
                          </td>
                          <td><code>{ev.ip_address}</code></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          </div>
        </>
      )}

      {/* ===================================================================
          7. ACTIVE SESSIONS (/admin/security/sessions)
         =================================================================== */}
      {!loading && section === 'sessions' && sessionsData && (
        <section className="acc-card">
          <div className="acc-card-header">
            <div>
              <h2 className="acc-card-title">เซสชันผู้ดูแลระบบและเจ้าหน้าที่ (Sanitized Staff Sessions)</h2>
              <p className="acc-card-subtitle">
                แสดงเฉพาะข้อมูลระบุตัวตนและเวอร์ชันเซสชันโดยไม่เปิดเผย Session ID หรือ Cookie Value • เพิกถอนเซสชันได้ทันที
              </p>
            </div>
          </div>

          {(sessionsData.staff_sessions || []).length === 0 ? (
            <div className="acc-empty-state">ไม่พบรายการเซสชันของเจ้าหน้าที่</div>
          ) : (
            <div className="acc-table-wrap">
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>ผู้ใช้งาน</th>
                    <th>บทบาท / แผนก</th>
                    <th>Session Version</th>
                    <th>IP ล่าสุด</th>
                    <th>เข้าสู่ระบบล่าสุด</th>
                    <th>สถานะเซสชัน</th>
                    <th>การจัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  {sessionsData.staff_sessions.map((s) => (
                    <tr key={s.user_id}>
                      <td>
                        <strong>{s.name}</strong> {s.is_current_user && <span className="acc-badge-pill">(คุณ)</span>}
                        <br />
                        <small>{s.username} • {s.email}</small>
                      </td>
                      <td>{s.role_name}<br /><small>{s.department}</small></td>
                      <td><code>v{s.session_version}</code></td>
                      <td><code>{s.last_login_ip || '-'}</code></td>
                      <td>{s.last_login_at || 'ยังไม่เคยเข้าสู่ระบบ'}</td>
                      <td>
                        <span className={`acc-badge-pill ${s.estimated_active ? 'success' : 'warning'}`}>
                          {s.estimated_active ? 'กำลังออนไลน์ (Active)' : 'ไม่ได้ออนไลน์'}
                        </span>
                      </td>
                      <td>
                        {user?.role === 'super_admin' && !s.is_current_user ? (
                          <button
                            type="button"
                            className="acc-btn danger"
                            onClick={() => handleRevokeStaffSessions(s.user_id, s.name)}
                          >
                            บังคับออกจากระบบ
                          </button>
                        ) : (
                          <span>-</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {/* ===================================================================
          8. MFA ADMINISTRATION (/admin/security/mfa)
         =================================================================== */}
      {!loading && section === 'mfa' && mfaData && (
        <>
          <div className="acc-kpi-grid">
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">อัตราการเปิดใช้งาน MFA ของเจ้าหน้าที่</span>
                <span className="acc-kpi-icon success"><ShieldCheck size={18} /></span>
              </div>
              <div className="acc-kpi-value">{mfaData.summary?.coverage_percent ?? 0}%</div>
              <div className="acc-kpi-sub"><span>บังคับใช้ Mandatory MFA สำหรับบทบาทผู้ดูแล</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">ลงทะเบียน MFA สำเร็จแล้ว</span>
                <span className="acc-kpi-icon success"><CheckCircle2 size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(mfaData.summary?.enrolled_staff)} บัญชี</div>
              <div className="acc-kpi-sub"><span>จากทั้งหมด {formatNumber(mfaData.summary?.total_staff)} บัญชี</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">รอดำเนินการลงทะเบียน MFA</span>
                <span className="acc-kpi-icon warning"><Clock size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(mfaData.summary?.pending_staff)} บัญชี</div>
              <div className="acc-kpi-sub"><span>ระบบจะบังคับลงทะเบียนทันทีเมื่อล็อกอิน</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">การปกป้องข้อมูลลับ TOTP</span>
                <span className="acc-kpi-icon info"><Lock size={18} /></span>
              </div>
              <div className="acc-kpi-value">AES-256</div>
              <div className="acc-kpi-sub"><span>ไม่แสดง TOTP Secret หรือ Recovery Code ใน API</span></div>
            </article>
          </div>

          <section className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">สถานะการลงทะเบียน MFA รายบุคคล (Staff MFA Enrollment Status)</h2>
                <p className="acc-card-subtitle">{mfaData.policy_note}</p>
              </div>
            </div>
            <div className="acc-table-wrap">
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ชื่อผู้ใช้ / อีเมล</th>
                    <th>บทบาท</th>
                    <th>สถานะ MFA</th>
                    <th>วันที่ยืนยันตัวตน (Confirmed At)</th>
                    <th>รหัสกู้คืนคงเหลือ (Recovery Codes)</th>
                  </tr>
                </thead>
                <tbody>
                  {(mfaData.accounts || []).map((st) => (
                    <tr key={st.user_id}>
                      <td><strong>{st.name}</strong></td>
                      <td>{st.username}<br /><small>{st.email}</small></td>
                      <td>{st.role_name}</td>
                      <td>
                        <span className={`acc-badge-pill ${st.mfa_enabled ? 'success' : 'warning'}`}>
                          {st.mfa_enabled ? 'ลงทะเบียนแล้ว (Enrolled)' : 'ยังไม่ลงทะเบียน (Pending)'}
                        </span>
                      </td>
                      <td>{st.enrolled_at || 'ยังไม่มีข้อมูล'}</td>
                      <td>{st.mfa_enabled ? `${st.recovery_codes_remaining} รหัส` : '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}

      {/* ===================================================================
          9. BACKUP & RECOVERY CENTER (/admin/backups & /admin/backups/history)
         =================================================================== */}
      {!loading && (section === 'backups' || section === 'backups-history') && backupData && (
        <>
          <div className="acc-kpi-grid">
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">สถานะความสดใหม่ของข้อมูลสำรอง</span>
                <span className="acc-kpi-icon info"><HardDriveDownload size={18} /></span>
              </div>
              <div className="acc-kpi-value">{backupData.freshness_status === 'healthy' ? 'ปกติ (Healthy)' : 'ยังไม่มีข้อมูล'}</div>
              <div className="acc-kpi-sub"><span>{backupData.phase_mode}</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">จำนวนไฟล์สำรองข้อมูลที่ตรวจพบ</span>
                <span className="acc-kpi-icon success"><Database size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(backupData.total_backups)} ไฟล์</div>
              <div className="acc-kpi-sub"><span>ขนาดรวม: {backupData.total_size_mb ?? 0} MB</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">เวลาสำรองข้อมูลสำเร็จล่าสุด</span>
                <span className="acc-kpi-icon info"><Clock size={18} /></span>
              </div>
              <div className="acc-kpi-value" style={{ fontSize: '1.15rem' }}>
                {backupData.latest_backup_at || 'ยังไม่มีข้อมูล'}
              </div>
              <div className="acc-kpi-sub">
                <span>อายุข้อมูล: {backupData.freshness_hours !== null ? `${backupData.freshness_hours} ชม.` : 'ยังไม่มีข้อมูล'}</span>
              </div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">ผ่านการตรวจสอบ SHA-256</span>
                <span className="acc-kpi-icon success"><ShieldCheck size={18} /></span>
              </div>
              <div className="acc-kpi-value">{formatNumber(backupData.verified_backups)} ไฟล์</div>
              <div className="acc-kpi-sub"><span>ไม่อนุญาตให้สั่ง Restore อัตโนมัติผ่านหน้าเว็บ</span></div>
            </article>
          </div>

          <section className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">หมวดหมู่ข้อมูลสำรองตามแผนความต่อเนื่องทางธุรกิจ (Backup Scope & Retention)</h2>
                <p className="acc-card-subtitle">{backupData.retention_policy}</p>
              </div>
            </div>
            <div className="acc-table-wrap">
              <table className="acc-table">
                <thead>
                  <tr>
                    <th>หมวดหมู่ (Category)</th>
                    <th>สถานะล่าสุด</th>
                  </tr>
                </thead>
                <tbody>
                  {(backupData.categories_status || []).map((cat) => (
                    <tr key={cat.category}>
                      <td><strong>{cat.category}</strong></td>
                      <td><span className="acc-badge-pill">{cat.status}</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">ประวัติไฟล์สำรองข้อมูลและผลตรวจสอบ Checksum (Backup Archive Inventory)</h2>
                <p className="acc-card-subtitle">ตรวจสอบไฟล์จริงในโฟลเดอร์ storage/backups พร้อมคำนวณ SHA-256</p>
              </div>
            </div>
            {(backupData.recent_history || []).length === 0 ? (
              <div className="acc-empty-state">
                ยังไม่มีไฟล์สำรองข้อมูลใน storage/backups (ระบบแสดงผลตามข้อมูลจริงโดยไม่สร้างข้อมูลจำลอง)
              </div>
            ) : (
              <div className="acc-table-wrap">
                <table className="acc-table">
                  <thead>
                    <tr>
                      <th>ชื่อไฟล์สำรองข้อมูล</th>
                      <th>หมวดหมู่</th>
                      <th>ขนาดไฟล์</th>
                      <th>วันที่สร้าง</th>
                      <th>SHA-256 Checksum</th>
                      <th>สถานะการตรวจสอบ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {backupData.recent_history.map((item) => (
                      <tr key={item.filename}>
                        <td><strong>{item.filename}</strong></td>
                        <td>{item.category_label}</td>
                        <td>{item.size_kb} KB</td>
                        <td>{item.created_at}</td>
                        <td><code>{item.sha256_prefix || 'ไม่มีไฟล์ .sha256'}</code></td>
                        <td>
                          <span className={`acc-badge-pill ${item.integrity_verified ? 'success' : 'warning'}`}>
                            {item.integrity_verified ? 'Verified (SHA-256 ตรงกัน)' : 'Unverified'}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}

      {/* ===================================================================
          10. SYSTEM HEALTH (/admin/health)
         =================================================================== */}
      {!loading && section === 'health' && healthData && (
        <>
          <div className="acc-kpi-grid">
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">สถานะภาพรวมของแอปพลิเคชัน</span>
                <span className="acc-kpi-icon success"><Activity size={18} /></span>
              </div>
              <div className="acc-kpi-value">{healthData.overall_label}</div>
              <div className="acc-kpi-sub"><span>{healthData.summary_text}</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">หน่วยความจำ PHP Process</span>
                <span className="acc-kpi-icon info"><Server size={18} /></span>
              </div>
              <div className="acc-kpi-value">{healthData.resources?.php_memory_usage_mb} MB</div>
              <div className="acc-kpi-sub"><span>Limit: {healthData.resources?.php_memory_limit}</span></div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">พื้นที่ดิสก์ว่าง (Disk Free Space)</span>
                <span className="acc-kpi-icon info"><Server size={18} /></span>
              </div>
              <div className="acc-kpi-value">
                {healthData.resources?.disk_free_gb !== null ? `${healthData.resources.disk_free_gb} GB` : 'ไม่พร้อมใช้งาน'}
              </div>
              <div className="acc-kpi-sub">
                <span>ใช้งานแล้ว: {healthData.resources?.disk_used_percent !== null ? `${healthData.resources.disk_used_percent}%` : '-'}</span>
              </div>
            </article>
            <article className="acc-kpi-card">
              <div className="acc-kpi-top">
                <span className="acc-kpi-label">CPU Load Average</span>
                <span className="acc-kpi-icon info"><Activity size={18} /></span>
              </div>
              <div className="acc-kpi-value" style={{ fontSize: '1.1rem' }}>{healthData.resources?.cpu_load}</div>
              <div className="acc-kpi-sub"><span>ตรวจสอบจากระบบปฏิบัติการ</span></div>
            </article>
          </div>

          <div className="acc-grid-2">
            <section className="acc-card">
              <div className="acc-card-header">
                <div>
                  <h2 className="acc-card-title">สถานะบริการหลัก (Core Service Readiness)</h2>
                  <p className="acc-card-subtitle">ตรวจสอบการทำงานของ PHP-FPM, Database, Schema และ Backup</p>
                </div>
              </div>
              <div className="acc-table-wrap">
                <table className="acc-table">
                  <thead>
                    <tr>
                      <th>บริการ (Service)</th>
                      <th>สถานะ</th>
                      <th>รายละเอียด</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(healthData.services || []).map((svc) => (
                      <tr key={svc.name}>
                        <td><strong>{svc.name}</strong></td>
                        <td>
                          <span className={`acc-badge-pill ${svc.status === 'healthy' ? 'success' : 'warning'}`}>
                            {svc.status}
                          </span>
                        </td>
                        <td>{svc.detail}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            <section className="acc-card">
              <div className="acc-card-header">
                <div>
                  <h2 className="acc-card-title">สถานะ Scheduled Jobs & Background Tasks</h2>
                  <p className="acc-card-subtitle">ตรวจสอบการทำงานของงานตารางเวลา</p>
                </div>
              </div>
              <div className="acc-table-wrap">
                <table className="acc-table">
                  <thead>
                    <tr>
                      <th>ชื่องาน (Job Name)</th>
                      <th>ตารางเวลา</th>
                      <th>สถานะ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(healthData.scheduled_jobs || []).map((job) => (
                      <tr key={job.job}>
                        <td>
                          <strong>{job.job}</strong>
                          <br />
                          <small><code>{job.command}</code></small>
                        </td>
                        <td>{job.schedule}</td>
                        <td><span className="acc-badge-pill">{job.status}</span></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        </>
      )}

      {/* ===================================================================
          11. COMPLIANCE & OPERATIONAL REPORTS (/admin/reports)
         =================================================================== */}
      {!loading && section === 'reports' && reportsData && (
        <section className="acc-card">
          <div className="acc-card-header">
            <div>
              <h2 className="acc-card-title">ศูนย์รายงานผู้บริหาร การเงิน การตรวจสอบ และความมั่นคงปลอดภัย</h2>
              <p className="acc-card-subtitle">เลือกรายงานที่ได้รับอนุญาตตามสิทธิ์ของคุณเพื่อตรวจสอบหรือส่งออกไฟล์ CSV</p>
            </div>
          </div>
          <div className="acc-table-wrap">
            <table className="acc-table">
              <thead>
                <tr>
                  <th>รหัสรายงาน</th>
                  <th>ชื่อรายงาน</th>
                  <th>หมวดหมู่</th>
                  <th>สิทธิ์ในการเข้าถึง</th>
                  <th>การส่งออก</th>
                </tr>
              </thead>
              <tbody>
                {(reportsData.catalog || []).map((rep) => (
                  <tr key={rep.id}>
                    <td><code>{rep.id}</code></td>
                    <td>
                      <strong>{rep.title}</strong>
                      <br />
                      <small>{rep.description}</small>
                    </td>
                    <td><span className="acc-badge-pill">{rep.category}</span></td>
                    <td>
                      <span className={`acc-badge-pill ${rep.authorized ? 'success' : 'warning'}`}>
                        {rep.authorized ? 'ได้รับอนุญาต (Authorized)' : 'จำกัดสิทธิ์'}
                      </span>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="acc-btn primary"
                        disabled={!rep.authorized}
                        onClick={() =>
                          downloadCsv(
                            `${rep.id}-${new Date().toISOString().slice(0, 10)}.csv`,
                            ['Report ID', 'Title', 'Category', 'Generated At', 'Generated By', 'Role'],
                            [[rep.id, rep.title, rep.category, new Date().toISOString(), user?.name || '', user?.role || '']]
                          )
                        }
                      >
                        <FileSpreadsheet size={14} /> ส่งออก CSV
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {/* ===================================================================
          12. SETTINGS & PROFILE (/admin/settings)
         =================================================================== */}
      {!loading && section === 'settings' && (
        <div className="acc-grid-2">
          <section className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">ข้อมูลบัญชีผู้ดูแลระบบของคุณ (Administrator Profile)</h2>
                <p className="acc-card-subtitle">จัดการรหัสผ่านและตรวจสอบสถานะการผูกบัญชี</p>
              </div>
            </div>
            <div className="acc-table-wrap">
              <table className="acc-table">
                <tbody>
                  <tr>
                    <th>ชื่อ-นามสกุล</th>
                    <td><strong>{user?.name || '-'}</strong></td>
                  </tr>
                  <tr>
                    <th>ชื่อผู้ใช้ (Username)</th>
                    <td><code>{user?.username || '-'}</code></td>
                  </tr>
                  <tr>
                    <th>บทบาท (Role)</th>
                    <td><span className="acc-badge-pill success">{user?.roleName || user?.role}</span></td>
                  </tr>
                  <tr>
                    <th>หน่วยงาน</th>
                    <td>{user?.department || 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด'}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div style={{ marginTop: 16, display: 'flex', gap: 10, flexWrap: 'wrap' }}>
              <button type="button" className="acc-btn primary" onClick={() => setPasswordModalOpen(true)}>
                <Lock size={15} /> เปลี่ยนรหัสผ่านของฉัน
              </button>
              <Link to="/admin/mfa/enroll" className="acc-btn">
                <ShieldCheck size={15} /> ตรวจสอบการตั้งค่า 2FA / MFA
              </Link>
            </div>
          </section>

          <section className="acc-card">
            <div className="acc-card-header">
              <div>
                <h2 className="acc-card-title">พารามิเตอร์ความปลอดภัยของระบบ (Security & Runtime Posture)</h2>
                <p className="acc-card-subtitle">สถานะการตั้งค่าสำคัญในสภาพแวดล้อมปัจจุบัน (Read-Only)</p>
              </div>
            </div>
            <div className="acc-table-wrap">
              <table className="acc-table">
                <tbody>
                  <tr>
                    <th>การบังคับใช้ Mandatory MFA</th>
                    <td><span className="acc-badge-pill success">เปิดใช้งาน (Enforced for Staff/Admin)</span></td>
                  </tr>
                  <tr>
                    <th>การเข้ารหัส TOTP Secret</th>
                    <td><code>AES-256-GCM (Local SVG QR — BaconQrCode 3.1.1)</code></td>
                  </tr>
                  <tr>
                    <th>การป้องกัน CSRF & Same-Origin</th>
                    <td><span className="acc-badge-pill success">เปิดใช้งาน (CsrfMiddleware)</span></td>
                  </tr>
                  <tr>
                    <th>นโยบาย Database Migrations</th>
                    <td><span className="acc-badge-pill warning">Manual Approval Only (019/020 On Hold)</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      )}

      <ChangePasswordModal isOpen={passwordModalOpen} onClose={() => setPasswordModalOpen(false)} />
    </AdminControlCenterShell>
  );
}
