import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import AdminMemberShell from '../components/member/AdminMemberShell';
import MemberAdminWarnings from '../components/member/MemberAdminWarnings';
import { memberAdminApi } from '../services/memberAdminApi';
import {
  Activity,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Database,
  ShieldCheck,
  Server,
  Layers,
  ArrowLeft
} from 'lucide-react';

export default function AdminMembersHealthPage() {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [refresh, setRefresh] = useState(0);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setData(null);
    setError(null);

    memberAdminApi('/api/admin/members/health', { signal: controller.signal })
      .then((r) => {
        setData(r.health);
        setLoading(false);
      })
      .catch((e) => {
        if (e.name !== 'AbortError') {
          setError(e);
          setLoading(false);
        }
      });

    return () => controller.abort();
  }, [refresh]);

  const breadcrumbs = [
    { label: 'หน้าแรกผู้ดูแล', to: '/admin/dashboard' },
    { label: 'ระบบสมาชิกและปันผล', to: '/admin/members/dashboard' },
    { label: 'สถานะระบบสมาชิก' },
  ];

  const actions = (
    <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
      <button
        type="button"
        className="acc-btn"
        onClick={() => setRefresh((n) => n + 1)}
        disabled={loading}
      >
        <RefreshCw size={15} style={{ animation: loading ? 'spin 1s linear infinite' : 'none' }} />
        <span>ตรวจสอบสถานะอีกครั้ง</span>
      </button>
      <Link to="/admin/members/dashboard" className="acc-btn">
        <ArrowLeft size={15} />
        <span>กลับแดชบอร์ดสมาชิก</span>
      </Link>
    </div>
  );

  return (
    <AdminMemberShell
      title="สถานะระบบสมาชิกและโครงสร้างข้อมูล"
      description="ตรวจสอบความพร้อมของโครงสร้างฐานข้อมูล บทบาท RBAC ส่วนขยาย PHP และความปลอดภัยของระบบสมาชิก"
      breadcrumbs={breadcrumbs}
      actions={actions}
    >
      <MemberAdminWarnings error={error} />

      {error && (
        <div className="admin-error">
          <p>เกิดข้อผิดพลาดในการตรวจสอบสถานะ: {error.message || 'โปรดลองใหม่อีกครั้ง'}</p>
          <button type="button" className="acc-btn" onClick={() => setRefresh((n) => n + 1)}>
            ลองใหม่
          </button>
        </div>
      )}

      {loading && !data && (
        <div className="admin-skeleton" role="status">
          กำลังตรวจสอบโครงสร้างฐานข้อมูลและระบบสมาชิก...
        </div>
      )}

      {data && (
        <>
          {/* Health Overview Cards */}
          <section className="member-health-grid">
            <div className={`member-health-card ${data.ready ? 'ready' : 'warning'}`}>
              <div style={{ display: 'flex', gap: '10px', alignItems: 'center', marginBottom: '8px' }}>
                {data.ready ? (
                  <CheckCircle2 size={24} color="#15803d" />
                ) : (
                  <AlertTriangle size={24} color="#a16207" />
                )}
                <h3 style={{ margin: 0, fontSize: '1.15rem' }}>
                  {data.ready ? 'ระบบสมาชิกพร้อมใช้งานสมบูรณ์' : 'ยังมีส่วนประกอบที่ต้องอัปเดต'}
                </h3>
              </div>
              <p style={{ margin: '6px 0 0', fontSize: '0.88rem', color: '#334155', lineHeight: 1.5 }}>
                {data.ready
                  ? 'ตารางฐานข้อมูล สิทธิ์การใช้งาน และส่วนขยายที่จำเป็นทั้งหมดตรงตามข้อกำหนดระบบสมาชิก'
                  : 'ตรวจพบคอลัมน์หรือส่วนประกอบที่ยังไม่ครบถ้วน กรุณาดำเนินการตรวจสอบตามคำแนะนำ'}
              </p>
            </div>

            <div className="member-health-card" style={{ background: '#f8fafc' }}>
              <div style={{ display: 'flex', gap: '10px', alignItems: 'center', marginBottom: '8px' }}>
                <ShieldCheck size={24} color="#1d4ed8" />
                <h3 style={{ margin: 0, fontSize: '1.15rem', color: '#1e40af' }}>
                  สิทธิ์ผู้ดูแลระบบสมาชิก (RBAC)
                </h3>
              </div>
              <p style={{ margin: '6px 0 0', fontSize: '0.88rem', color: '#334155' }}>
                บทบาท `member_admin`: <strong>{data.member_admin_role ? 'พร้อมใช้งาน (Configured)' : 'ยังไม่มีบทบาทนี้ในระบบ'}</strong>
              </p>
              <span style={{ display: 'block', fontSize: '0.8rem', color: '#64748b', marginTop: '4px' }}>
                ผู้ดูแลที่มีสิทธิ์ members.view และ members.update สามารถบริหารจัดการข้อมูลได้
              </span>
            </div>
          </section>

          {/* Database & Schema Integrity */}
          <section className="member-admin-card">
            <div className="member-admin-card-heading">
              <div>
                <h2>ความครบถ้วนของโครงสร้างฐานข้อมูล (Database Schema)</h2>
                <span>ตรวจสอบความพร้อมของตาราง members, member_dividends, member_data_changes และตารางที่เกี่ยวข้อง</span>
              </div>
              <span className={`member-admin-badge ${data.missing.length === 0 ? 'active' : 'suspended'}`}>
                {data.missing.length === 0 ? 'โครงสร้างครบ 100%' : `ขาด ${data.missing.length} ส่วน`}
              </span>
            </div>

            {data.missing.length > 0 ? (
              <div className="admin-warning">
                <strong>รายการคอลัมน์หรือตารางที่ขาดหาย:</strong>
                <ul style={{ margin: '8px 0 0 20px' }}>
                  {data.missing.map((col) => (
                    <li key={col} style={{ fontFamily: 'monospace' }}>{col}</li>
                  ))}
                </ul>
              </div>
            ) : (
              <p style={{ margin: '0 0 16px', color: '#166534', fontSize: '0.9rem', display: 'flex', alignItems: 'center', gap: '6px' }}>
                <CheckCircle2 size={16} />
                <span>ตารางหลัก 7 ตารางพร้อมใช้งาน (members, users, roles, user_roles, member_dividends, member_import_runs, member_data_changes)</span>
              </p>
            )}

            {/* PHP Extensions */}
            <div className="admin-table-scroll" tabIndex={0} aria-label="ตารางส่วนขยาย PHP ที่จำเป็น">
              <table className="admin-table">
                <thead>
                  <tr>
                    <th scope="col">ส่วนขยาย PHP (Extensions)</th>
                    <th scope="col">วัตถุประสงค์การใช้งาน</th>
                    <th scope="col" style={{ width: '140px' }}>สถานะ</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(data.extensions || {}).map(([ext, isLoaded]) => (
                    <tr key={ext}>
                      <td>
                        <strong style={{ fontFamily: 'monospace' }}>{ext}</strong>
                      </td>
                      <td>
                        {ext === 'pdo_mysql' ? 'การเชื่อมต่อฐานข้อมูล MySQL ประสิทธิภาพสูง'
                          : ext === 'mbstring' ? 'การประมวลผลข้อความภาษาไทยและ UTF-8'
                          : ext === 'zip' ? 'การอ่านไฟล์นำเข้า Excel (.xlsx)'
                          : ext === 'simplexml' ? 'การอ่านโครงสร้าง XML ของสเปรดชีต'
                          : 'ส่วนขยายระบบ'}
                      </td>
                      <td>
                        {isLoaded ? (
                          <span className="member-admin-badge active">
                            <CheckCircle2 size={12} />
                            <span>พร้อมใช้งาน</span>
                          </span>
                        ) : (
                          <span className="member-admin-badge suspended">
                            <AlertTriangle size={12} />
                            <span>ต้องเปิดใช้งาน</span>
                          </span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {/* Deployment Guidance (Read-Only) */}
          {!data.ready && (
            <section className="member-admin-card">
              <div className="member-admin-card-heading">
                <div>
                  <h2>คำแนะนำสำหรับผู้ดูแลระบบเซิร์ฟเวอร์ (Server Operations)</h2>
                  <span>ขั้นตอนการรัน migration แบบปลอดภัยโดยไม่กระทบข้อมูลเดิม</span>
                </div>
              </div>
              <p style={{ fontSize: '0.9rem', color: '#334155' }}>
                สำรองฐานข้อมูลก่อนดำเนินการ จากนั้นรันคำสั่งต่อไปนี้จากรากโฟลเดอร์ของเว็บไซต์บน VPS:
              </p>
              <pre className="member-admin-command" style={{ background: '#0f172a', color: '#38bdf8', padding: '16px', borderRadius: '10px' }}>
php bin/console db:migrate{'\n'}php bin/check-member-system.php
              </pre>
              <p style={{ fontSize: '0.85rem', color: '#64748b', marginTop: '10px' }}>
                คำสั่งตรวจสถานะข้างต้นเป็นแบบ Read-Only ไม่แก้ไขรหัสผ่านหรือข้อมูลทางการเงินของสมาชิก
              </p>
            </section>
          )}
        </>
      )}
    </AdminMemberShell>
  );
}
