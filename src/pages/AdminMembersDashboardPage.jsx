import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import AdminMemberShell from '../components/member/AdminMemberShell';
import MemberAdminWarnings from '../components/member/MemberAdminWarnings';
import { memberAdminApi, thaiMoney } from '../services/memberAdminApi';
import { Users, UserCheck, UserX, ShieldCheck, RefreshCw, Search, Activity, FileSpreadsheet } from 'lucide-react';

export default function AdminMembersDashboardPage() {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [refresh, setRefresh] = useState(0);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError(null);

    memberAdminApi('/api/admin/members/dashboard', { signal: controller.signal })
      .then((res) => {
        setData(res);
        setLoading(false);
      })
      .catch((e) => {
        if (e.name !== 'AbortError') {
          setError(e);
          setData(null);
          setLoading(false);
        }
      });

    return () => controller.abort();
  }, [refresh]);

  const breadcrumbs = [
    { label: 'หน้าแรกผู้ดูแล', to: '/admin/dashboard' },
    { label: 'ระบบสมาชิกและปันผล', to: '/admin/members/dashboard' },
    { label: 'ภาพรวมสมาชิก' },
  ];

  const actions = (
    <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
      <button
        type="button"
        className="acc-btn"
        onClick={() => setRefresh((r) => r + 1)}
        disabled={loading}
        title="โหลดข้อมูลล่าสุด"
      >
        <RefreshCw size={15} style={{ animation: loading ? 'spin 1s linear infinite' : 'none' }} />
        <span>โหลดข้อมูลล่าสุด</span>
      </button>
      <Link to="/admin/members" className="acc-btn primary">
        <Search size={15} />
        <span>ค้นหาสมาชิก</span>
      </Link>
      <Link to="/admin/members/health" className="acc-btn">
        <Activity size={15} />
        <span>สถานะระบบ</span>
      </Link>
      <Link to="/admin/dividends/import" className="acc-btn">
        <FileSpreadsheet size={15} />
        <span>นำเข้าปันผล</span>
      </Link>
    </div>
  );

  const totalMembers = Number(data?.members?.total || 0);
  const activeMembers = Number(data?.members?.active || 0);
  const inactiveMembers = Number(data?.members?.suspended || 0) + Number(data?.members?.resigned || 0);
  const noAccountMembers = Number(data?.members?.no_account || 0);
  const linkedMembers = Math.max(0, totalMembers - noAccountMembers);

  return (
    <AdminMemberShell
      title="ภาพรวมสมาชิกและทะเบียนข้อมูล"
      description="ข้อมูลสรุปสมาชิก การผูกบัญชีผู้ใช้ ข้อมูลปันผลรายปี และประวัติการเปลี่ยนแปลงตามฐานข้อมูลจริง"
      breadcrumbs={breadcrumbs}
      actions={actions}
    >
      <MemberAdminWarnings error={error} warnings={data?.warnings} />

      {loading && !data && (
        <div className="admin-skeleton" role="status" aria-label="กำลังโหลดข้อมูล">
          กำลังประมวลผลข้อมูลสมาชิก...
        </div>
      )}

      {data && (
        <>
          {/* KPI Summary Cards */}
          <section className="member-admin-kpis" aria-label="สรุปสถิติสมาชิก">
            <article className="member-admin-kpi-card">
              <div className="member-admin-kpi-header">
                <span className="member-admin-kpi-label">สมาชิกทั้งหมดในระบบ</span>
                <div className="member-admin-kpi-icon" style={{ background: '#eff6ff', color: '#1d4ed8' }}>
                  <Users size={20} />
                </div>
              </div>
              <strong className="member-admin-kpi-value">
                {totalMembers.toLocaleString('th-TH')}
              </strong>
              <span className="member-admin-kpi-sub">
                ข้อมูลทะเบียนสมาชิกทั้งหมดที่บันทึก
              </span>
            </article>

            <article className="member-admin-kpi-card">
              <div className="member-admin-kpi-header">
                <span className="member-admin-kpi-label">สมาชิกสถานะปกติ (Active)</span>
                <div className="member-admin-kpi-icon" style={{ background: '#dcfce7', color: '#166534' }}>
                  <UserCheck size={20} />
                </div>
              </div>
              <strong className="member-admin-kpi-value" style={{ color: '#15803d' }}>
                {activeMembers.toLocaleString('th-TH')}
              </strong>
              <span className="member-admin-kpi-sub">
                คิดเป็น {totalMembers > 0 ? ((activeMembers / totalMembers) * 100).toFixed(1) : 0}% ของสมาชิกทั้งหมด
              </span>
            </article>

            <article className="member-admin-kpi-card">
              <div className="member-admin-kpi-header">
                <span className="member-admin-kpi-label">ผูกบัญชีเข้าใช้แล้ว (Linked)</span>
                <div className="member-admin-kpi-icon" style={{ background: '#e0f2fe', color: '#0369a1' }}>
                  <ShieldCheck size={20} />
                </div>
              </div>
              <strong className="member-admin-kpi-value" style={{ color: '#0284c7' }}>
                {linkedMembers.toLocaleString('th-TH')}
              </strong>
              <span className="member-admin-kpi-sub">
                ยังไม่มีบัญชี {noAccountMembers.toLocaleString('th-TH')} ราย
              </span>
            </article>

            <article className="member-admin-kpi-card">
              <div className="member-admin-kpi-header">
                <span className="member-admin-kpi-label">ระงับ / พ้นสภาพสมาชิก</span>
                <div className="member-admin-kpi-icon" style={{ background: '#fee2e2', color: '#b91c1c' }}>
                  <UserX size={20} />
                </div>
              </div>
              <strong className="member-admin-kpi-value" style={{ color: '#dc2626' }}>
                {inactiveMembers.toLocaleString('th-TH')}
              </strong>
              <span className="member-admin-kpi-sub">
                ระงับ: {Number(data.members?.suspended || 0)} · พ้น: {Number(data.members?.resigned || 0)}
              </span>
            </article>
          </section>

          {/* Annual Dividend Breakdown */}
          <section className="member-admin-card">
            <div className="member-admin-card-heading">
              <div>
                <h2>สรุปข้อมูลเงินปันผลรายปี (Historical Dividends)</h2>
                <span>ยอดเงินปันผลสะสมและรายการที่มีการนำเข้าในแต่ละปี พ.ศ.</span>
              </div>
              <Link to="/admin/dividends/import" className="btn btn-outline" style={{ fontSize: '0.85rem' }}>
                นำเข้าข้อมูลปันผล
              </Link>
            </div>

            {data.years === null ? (
              <p className="admin-notice">ยังไม่สามารถโหลดข้อมูลปันผลได้ กรุณาตรวจสถานะระบบ</p>
            ) : data.years.length === 0 ? (
              <p className="admin-empty">ยังไม่มีรายการเงินปันผลที่บันทึกในระบบ</p>
            ) : (
              <div className="admin-table-scroll" tabIndex={0} aria-label="ตารางข้อมูลเงินปันผลรายปี">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th scope="col" style={{ width: '100px' }}>ปี พ.ศ.</th>
                      <th scope="col" className="admin-numeric">จำนวนรายการ</th>
                      <th scope="col" className="admin-numeric">รายรับรวม (บาท)</th>
                      <th scope="col" className="admin-numeric">รายหักรวม (บาท)</th>
                      <th scope="col" className="admin-numeric">ยอดสุทธิรวม (บาท)</th>
                      <th scope="col">อัปเดตล่าสุด</th>
                      <th scope="col" style={{ width: '150px' }}>เรียกดูรายชื่อ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.years.map((y) => (
                      <tr key={y.year}>
                        <td>
                          <strong>{y.year}</strong>
                        </td>
                        <td className="admin-numeric">
                          {Number(y.records).toLocaleString('th-TH')}
                        </td>
                        <td className="admin-numeric" style={{ color: '#166534', fontWeight: 600 }}>
                          {thaiMoney(y.total_income)}
                        </td>
                        <td className="admin-numeric" style={{ color: '#b91c1c' }}>
                          {thaiMoney(y.total_deductions)}
                        </td>
                        <td className="admin-numeric" style={{ fontWeight: 700, color: '#1d4ed8' }}>
                          {thaiMoney(y.net_total)}
                        </td>
                        <td>{y.updated_at || '—'}</td>
                        <td>
                          <Link
                            to={`/admin/members?year=${y.year}`}
                            className="member-admin-badge account-linked"
                            style={{ textDecoration: 'none' }}
                          >
                            ดูสมาชิกปี {y.year}
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          {/* Recent Import History */}
          <section className="member-admin-card">
            <div className="member-admin-card-heading">
              <div>
                <h2>ประวัติการนำเข้าไฟล์ปันผลล่าสุด</h2>
                <span>บันทึกประวัติการนำเข้าไฟล์ Excel และสรุปจำนวนรายการ</span>
              </div>
            </div>

            {data.runs === null ? (
              <p className="admin-notice">ยังไม่สามารถโหลดประวัตินำเข้าได้</p>
            ) : data.runs.length === 0 ? (
              <p className="admin-empty">ยังไม่มีประวัติการนำเข้าไฟล์ใหม่</p>
            ) : (
              <div className="admin-table-scroll" tabIndex={0} aria-label="ตารางประวัติการนำเข้าไฟล์">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th scope="col" style={{ width: '150px' }}>วัน-เวลาที่นำเข้า</th>
                      <th scope="col">ชื่อไฟล์ที่นำเข้า / ปี</th>
                      <th scope="col" className="admin-numeric">รวมทั้งหมด</th>
                      <th scope="col" className="admin-numeric">สร้างใหม่</th>
                      <th scope="col" className="admin-numeric">อัปเดต</th>
                      <th scope="col" className="admin-numeric">คงเดิม</th>
                      <th scope="col" className="admin-numeric">ยอดสุทธิรวม</th>
                      <th scope="col">ผู้ดำเนินการ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.runs.map((r) => (
                      <tr key={r.id}>
                        <td>{r.created_at}</td>
                        <td>
                          <strong>{r.source_name}</strong>
                          <span style={{ display: 'block', fontSize: '0.8rem', color: '#64748b' }}>
                            ปีบัญชี: {r.year || 'หลายปี'}
                          </span>
                        </td>
                        <td className="admin-numeric">{Number(r.record_count).toLocaleString('th-TH')}</td>
                        <td className="admin-numeric" style={{ color: '#166534' }}>{r.created_count}</td>
                        <td className="admin-numeric" style={{ color: '#0284c7' }}>{r.updated_count}</td>
                        <td className="admin-numeric">{r.unchanged_count}</td>
                        <td className="admin-numeric" style={{ fontWeight: 600 }}>{thaiMoney(r.net_total)}</td>
                        <td>{r.actor || 'ระบบ CLI'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>

          {/* Recent Profile Audit Log Changes */}
          <section className="member-admin-card">
            <div className="member-admin-card-heading">
              <div>
                <h2>บันทึกการปรับปรุงข้อมูลสมาชิก (Audit Trail)</h2>
                <span>ประวัติการแก้ไขข้อมูลสมาชิกและการยืนยันเปิดใช้งานบัญชี</span>
              </div>
            </div>

            {data.changes === null ? (
              <p className="admin-notice">ยังไม่สามารถโหลดประวัติการแก้ไขได้</p>
            ) : data.changes.length === 0 ? (
              <p className="admin-empty">ยังไม่มีประวัติการแก้ไขข้อมูลสมาชิกในระบบใหม่</p>
            ) : (
              <ul className="member-history-list">
                {data.changes.map((c) => (
                  <li key={c.id} className="member-history-item">
                    <div className="member-history-header">
                      <Link
                        to={`/admin/members/${c.member_id}`}
                        style={{ fontWeight: 700, color: '#1d4ed8', textDecoration: 'none' }}
                      >
                        สมาชิก {c.member_no} · {c.member_name}
                      </Link>
                      <span className="member-history-meta">
                        {c.created_at} · โดย {c.actor || 'ระบบ CLI'}
                      </span>
                    </div>
                    <p className="member-history-reason">
                      <strong>เหตุผล:</strong> {c.reason}
                    </p>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      )}
    </AdminMemberShell>
  );
}
