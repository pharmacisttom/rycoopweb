import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import AdminMemberShell from '../components/member/AdminMemberShell';
import MemberAdminWarnings from '../components/member/MemberAdminWarnings';
import { memberAdminApi, thaiMoney, memberStatus } from '../services/memberAdminApi';
import { maskIdCard, maskName } from '../utils/pdpa';
import {
  ArrowLeft,
  User,
  ShieldCheck,
  ShieldAlert,
  FileSpreadsheet,
  History,
  Lock,
  Eye,
  EyeOff,
  CheckCircle2,
  AlertTriangle,
  Info
} from 'lucide-react';

const profileLabels = {
  member_no: 'เลขทะเบียนสมาชิก',
  id_card: 'เลขประจำตัวประชาชน (13 หลัก)',
  prefix: 'คำนำหน้าชื่อ',
  first_name: 'ชื่อ',
  last_name: 'นามสกุล',
  department: 'หน่วยงาน / สังกัด',
  position: 'ตำแหน่ง',
  phone: 'เบอร์โทรศัพท์',
  email: 'อีเมล',
  address: 'ที่อยู่ตามทะเบียน',
  status: 'สถานะสมาชิกภาพ',
  join_date: 'วันที่เข้าเป็นสมาชิก',
};

function formatHistoryDiff(json) {
  if (!json) return {};
  try {
    const data = typeof json === 'string' ? JSON.parse(json) : json;
    const res = {};
    Object.entries(data).forEach(([key, val]) => {
      if (key === 'income' || key === 'deductions') {
        Object.entries(val || {}).forEach(([item, amount]) => {
          res[`${key === 'income' ? 'รายรับ' : 'รายหัก'} · ${item}`] = typeof amount === 'number' ? amount.toLocaleString('th-TH', { minimumFractionDigits: 2 }) : amount;
        });
      } else if (profileLabels[key]) {
        res[profileLabels[key]] = key === 'status' ? memberStatus[val] || val : val;
      } else {
        res[key] = val;
      }
    });
    return res;
  } catch (e) {
    return { ข้อมูล: String(json) };
  }
}

function HistoryDiffViewer({ beforeJson, afterJson }) {
  const before = formatHistoryDiff(beforeJson);
  const after = formatHistoryDiff(afterJson);
  const allKeys = [...new Set([...Object.keys(before), ...Object.keys(after)])];

  if (!allKeys.length) {
    return <p style={{ fontSize: '0.85rem', color: '#64748b' }}>ไม่มีรายละเอียดค่าที่เปลี่ยนแปลง</p>;
  }

  return (
    <div className="admin-table-scroll" style={{ marginTop: '10px' }}>
      <table className="admin-table" style={{ fontSize: '0.85rem' }}>
        <thead>
          <tr>
            <th scope="col" style={{ width: '30%' }}>รายการ</th>
            <th scope="col" style={{ width: '35%' }}>ค่าเดิมก่อนแก้ไข</th>
            <th scope="col" style={{ width: '35%' }}>ค่าหลังแก้ไข</th>
          </tr>
        </thead>
        <tbody>
          {allKeys.map((key) => {
            const isDiff = before[key] !== after[key];
            return (
              <tr key={key} style={isDiff ? { background: '#fefce8' } : undefined}>
                <td><strong>{key}</strong></td>
                <td style={{ color: isDiff ? '#b91c1c' : '#64748b' }}>{before[key] ?? '—'}</td>
                <td style={{ color: isDiff ? '#15803d' : '#0f172a', fontWeight: isDiff ? 600 : 400 }}>{after[key] ?? '—'}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

export default function AdminMemberDetailPage() {
  const { id } = useParams();
  const [data, setData] = useState(null);
  const [profile, setProfile] = useState(null);
  const [tab, setTab] = useState('profile');
  const [unmaskPii, setUnmaskPii] = useState(false);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const [selectedDividend, setSelectedDividend] = useState(null);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setData(null);
    setProfile(null);
    setMessage('');

    memberAdminApi(`/api/admin/members/${id}`, { signal: controller.signal })
      .then((res) => {
        setData(res);
        setProfile(res.member);
        setLoading(false);
      })
      .catch((e) => {
        if (e.name !== 'AbortError') {
          setMessage(e.message || 'ไม่สามารถโหลดข้อมูลสมาชิกได้');
          setLoading(false);
        }
      });

    return () => controller.abort();
  }, [id]);

  const breadcrumbs = [
    { label: 'หน้าแรกผู้ดูแล', to: '/admin/dashboard' },
    { label: 'ระบบสมาชิกและปันผล', to: '/admin/members/dashboard' },
    { label: 'ค้นหาสมาชิก', to: '/admin/members' },
    { label: profile ? `สมาชิก ${profile.member_no}` : 'รายละเอียดสมาชิก' },
  ];

  const actions = (
    <Link to="/admin/members" className="acc-btn">
      <ArrowLeft size={15} />
      <span>กลับหน้ารายชื่อสมาชิก</span>
    </Link>
  );

  const hasDuplicateNo = (data?.duplicates?.member_no || 0) > 0;
  const hasDuplicateIdCard = (data?.duplicates?.id_card || 0) > 0;
  const isLinked = Boolean(profile?.user_id && data?.account);

  return (
    <AdminMemberShell
      title={profile ? `รายละเอียดสมาชิกเลขที่ ${profile.member_no}` : 'รายละเอียดสมาชิก'}
      description="ระบบบริหารจัดการข้อมูลสมาชิก การเชื่อมโยงอัตลักษณ์ (Identity Mapping) และประวัติเงินปันผลรายปี"
      breadcrumbs={breadcrumbs}
      actions={actions}
    >
      {message && (
        <div className="admin-error">
          <p>{message}</p>
          <Link to="/admin/members" className="acc-btn">กลับรายชื่อสมาชิก</Link>
        </div>
      )}

      {loading && !data && (
        <div className="admin-skeleton" role="status">
          กำลังโหลดรายละเอียดสมาชิกและการตรวจสอบอัตลักษณ์...
        </div>
      )}

      {data && profile && (
        <>
          <MemberAdminWarnings warnings={data.warnings} />

          {/* Navigation Tabs */}
          <nav className="member-admin-tabs" aria-label="หมวดหมู่ข้อมูลสมาชิก">
            <button
              type="button"
              className={`member-admin-tab-btn ${tab === 'profile' ? 'active' : ''}`}
              onClick={() => setTab('profile')}
            >
              <User size={16} style={{ display: 'inline', verticalAlign: '-2px', marginRight: '6px' }} />
              ข้อมูลสมาชิกและการผูกบัญชี
            </button>
            <button
              type="button"
              className={`member-admin-tab-btn ${tab === 'dividends' ? 'active' : ''}`}
              onClick={() => setTab('dividends')}
            >
              <FileSpreadsheet size={16} style={{ display: 'inline', verticalAlign: '-2px', marginRight: '6px' }} />
              ประวัติเงินปันผลรายปี ({data.dividends?.length || 0})
            </button>
            <button
              type="button"
              className={`member-admin-tab-btn ${tab === 'history' ? 'active' : ''}`}
              onClick={() => setTab('history')}
            >
              <History size={16} style={{ display: 'inline', verticalAlign: '-2px', marginRight: '6px' }} />
              ประวัติการเปลี่ยนแปลง ({data.history?.length || 0})
            </button>
          </nav>

          {/* TAB 1: Profile & Identity Mapping */}
          {tab === 'profile' && (
            <>
              {/* Identity Mapping Status Card */}
              <section className={`member-identity-card ${isLinked ? '' : 'unlinked'}`}>
                <h3>
                  {isLinked ? (
                    <>
                      <ShieldCheck size={22} />
                      <span>สถานะการผูกอัตลักษณ์: เชื่อมโยงบัญชีผู้ใช้ระบบสมบูรณ์ (1:1 Verified)</span>
                    </>
                  ) : (
                    <>
                      <ShieldAlert size={22} />
                      <span>สถานะการผูกอัตลักษณ์: ยังไม่มีการเชื่อมโยงบัญชีผู้ใช้ระบบ (Unlinked Account)</span>
                    </>
                  )}
                </h3>
                <p className="member-identity-desc">
                  {isLinked
                    ? 'สมาชิกรายนี้มีบัญชีเข้าใช้บริการของสหกรณ์แล้ว เลขประจำตัวประชาชนและเลขสมาชิกถูกผูกกับบัญชีผู้ใช้แบบ 1 ต่อ 1 ปลอดภัยตามมาตรฐานความปลอดภัย'
                    : 'สมาชิกรายนี้ยังไม่มีบัญชีเข้าใช้งานระบบออนไลน์ หากสมาชิกประสงค์จะเข้าใช้ สามารถดำเนินการผ่านขั้นตอนลงทะเบียนยืนยันตัวตนตามข้อกำหนด'}
                </p>

                <div className="member-identity-details">
                  <div className="member-identity-sub">
                    <span>รหัสบัญชีผู้ใช้ (User ID)</span>
                    <strong>{profile.user_id ? `#${profile.user_id}` : '— ไม่มีบัญชีเชื่อมโยง —'}</strong>
                  </div>
                  <div className="member-identity-sub">
                    <span>ชื่อผู้ใช้สำหรับเข้าสู่ระบบ</span>
                    <strong style={{ fontFamily: 'monospace' }}>
                      {unmaskPii ? profile.id_card : (profile.id_card_pdpa || maskIdCard(profile.id_card))}
                    </strong>
                  </div>
                  <div className="member-identity-sub">
                    <span>สถานะบัญชีออนไลน์</span>
                    <strong>
                      {data.account?.status === 'active' ? (
                        <span className="member-admin-badge active">ใช้งานปกติ (Active)</span>
                      ) : data.account?.status === 'suspended' ? (
                        <span className="member-admin-badge suspended">ระงับการใช้งาน</span>
                      ) : (
                        <span className="member-admin-badge resigned">ยังไม่เปิดใช้</span>
                      )}
                    </strong>
                  </div>
                </div>

                {/* Duplication Alerts */}
                {(hasDuplicateNo || hasDuplicateIdCard) && (
                  <div className="admin-warning" style={{ marginTop: '16px' }}>
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                      <AlertTriangle size={18} />
                      <strong>ตรวจพบความผิดปกติของข้อมูลอัตลักษณ์:</strong>
                    </div>
                    <ul style={{ margin: '8px 0 0 20px', padding: 0 }}>
                      {hasDuplicateNo && (
                        <li>เลขสมาชิก {profile.member_no} ซ้ำกับรายการอื่นในฐานข้อมูล!</li>
                      )}
                      {hasDuplicateIdCard && (
                        <li>เลขบัตรประชาชนซ้ำกับรายการอื่นในฐานข้อมูล!</li>
                      )}
                    </ul>
                  </div>
                )}
              </section>

              {/* Member Details Grid (Read-Only) */}
              <section className="member-admin-card">
                <div className="member-admin-card-heading">
                  <div>
                    <h2>ข้อมูลทะเบียนสมาชิก (Member Profile)</h2>
                    <span>ข้อมูลจากฐานข้อมูลหลัก แสดงผลแบบคุ้มครองข้อมูลส่วนบุคคล (PDPA)</span>
                  </div>
                  <button
                    type="button"
                    className="acc-btn"
                    onClick={() => setUnmaskPii((v) => !v)}
                    title={unmaskPii ? 'ซ่อนข้อมูลส่วนบุคคล' : 'แสดงข้อมูลส่วนบุคคล'}
                  >
                    {unmaskPii ? <EyeOff size={15} /> : <Eye size={15} />}
                    <span>{unmaskPii ? 'ซ่อนข้อมูล PDPA' : 'แสดงข้อมูลตัวเต็ม'}</span>
                  </button>
                </div>

                <div className="member-profile-grid">
                  <div className="member-profile-item">
                    <span className="label">เลขทะเบียนสมาชิก</span>
                    <strong className="value member-no-mono" style={{ fontSize: '1.15rem' }}>
                      {profile.member_no}
                    </strong>
                  </div>

                  <div className="member-profile-item">
                    <span className="label">สถานะสมาชิก</span>
                    <div>
                      <span className={`member-admin-badge ${profile.status}`}>
                        {memberStatus[profile.status] || profile.status}
                      </span>
                    </div>
                  </div>

                  <div className="member-profile-item">
                    <span className="label">ชื่อ-นามสกุล</span>
                    <strong className="value">
                      {unmaskPii
                        ? `${profile.prefix || ''}${profile.first_name || ''} ${profile.last_name || ''}`
                        : (profile.name_pdpa || maskName(`${profile.prefix || ''}${profile.first_name || ''} ${profile.last_name || ''}`))}
                    </strong>
                  </div>

                  <div className="member-profile-item">
                    <span className="label">เลขประจำตัวประชาชน (13 หลัก)</span>
                    <strong className="value" style={{ fontFamily: 'monospace' }}>
                      {unmaskPii ? profile.id_card : (profile.id_card_pdpa || maskIdCard(profile.id_card))}
                    </strong>
                  </div>

                  <div className="member-profile-item">
                    <span className="label">หน่วยงาน / สังกัด</span>
                    <strong className="value">{profile.department || '—'}</strong>
                  </div>

                  <div className="member-profile-item">
                    <span className="label">ตำแหน่ง</span>
                    <strong className="value">{profile.position || '—'}</strong>
                  </div>

                  <div className="member-profile-item">
                    <span className="label">เบอร์โทรศัพท์</span>
                    <strong className="value">{profile.phone || '—'}</strong>
                  </div>

                  <div className="member-profile-item">
                    <span className="label">อีเมล</span>
                    <strong className="value">{profile.email || '—'}</strong>
                  </div>

                  <div className="member-profile-item" style={{ gridColumn: '1 / -1' }}>
                    <span className="label">ที่อยู่ตามทะเบียน</span>
                    <strong className="value" style={{ fontWeight: 400 }}>{profile.address || '—'}</strong>
                  </div>
                </div>

                <div className="admin-notice" style={{ marginTop: '20px', fontSize: '0.85rem' }}>
                  <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    <Info size={16} color="#1d4ed8" />
                    <span>
                      <strong>การคุ้มครองข้อมูลสมาชิก:</strong> การเรียกดูข้อมูลสมาชิกในหน้านี้ถูกบันทึกประวัติการเข้าถึงใน Security Audit Log ทุกครั้ง และไม่อนุญาตให้แก้ไขข้อมูลโดยตรงนอกเหนือจากกระบวนการรับรองสิทธิ์
                    </span>
                  </div>
                </div>
              </section>
            </>
          )}

          {/* TAB 2: Read-Only Historical Dividends */}
          {tab === 'dividends' && (
            <section className="member-admin-card">
              {/* Protected Dividend Notice */}
              <div className="member-dividend-notice">
                <Lock size={22} style={{ color: '#1d4ed8', flexShrink: 0, marginTop: '2px' }} />
                <div>
                  <h3>ข้อมูลเงินปันผลรายปีได้รับการคุ้มครองความถูกต้อง (Read-Only Protection)</h3>
                  <p>
                    เพื่อรักษาความถูกต้องทางบัญชีสหกรณ์และป้องกันข้อผิดพลาดทางการเงิน ระบบไม่อนุญาตให้แก้ไขตัวเลขเงินปันผลย้อนหลังโดยตรง
                    ยอดเงินปันผลแต่ละปีถูกคำนวณและตรวจสอบผ่านกระบวนการ Preflight & Import อย่างเป็นทางการเท่านั้น
                  </p>
                </div>
              </div>

              <div className="member-admin-card-heading">
                <div>
                  <h2>ประวัติเงินปันผลและเฉลี่ยคืนรายปี</h2>
                  <span>พบรายการข้อมูลปันผลทั้งหมด {data.dividends?.length || 0} ปีบัญชี</span>
                </div>
              </div>

              {(!data.dividends || data.dividends.length === 0) ? (
                <p className="admin-empty">ไม่พบประวัติข้อมูลเงินปันผลของสมาชิกรายนี้ในระบบ</p>
              ) : (
                <div className="admin-table-scroll" tabIndex={0} aria-label="ตารางประวัติเงินปันผลรายปี">
                  <table className="admin-table">
                    <thead>
                      <tr>
                        <th scope="col" style={{ width: '100px' }}>ปี พ.ศ.</th>
                        <th scope="col" className="admin-numeric">รายรับรวม (บาท)</th>
                        <th scope="col" className="admin-numeric">รายหักรวม (บาท)</th>
                        <th scope="col" className="admin-numeric">ยอดสุทธิ (บาท)</th>
                        <th scope="col" className="admin-numeric">อัตราปันผล</th>
                        <th scope="col" className="admin-numeric">อัตราเฉลี่ยคืน</th>
                        <th scope="col">วันที่บันทึกนำเข้า</th>
                        <th scope="col" style={{ width: '110px' }}>รายละเอียด</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.dividends.map((d) => {
                        const rec = d.record || {};
                        const isSelected = selectedDividend?.record?.year === rec.year;
                        return (
                          <tr key={rec.year} style={isSelected ? { background: '#f0fdf4' } : undefined}>
                            <td>
                              <strong style={{ fontSize: '1rem', color: '#1d4ed8' }}>{rec.year}</strong>
                            </td>
                            <td className="admin-numeric" style={{ color: '#166534', fontWeight: 600 }}>
                              {thaiMoney(rec.total_income)}
                            </td>
                            <td className="admin-numeric" style={{ color: '#b91c1c' }}>
                              {thaiMoney(rec.total_deductions)}
                            </td>
                            <td className="admin-numeric" style={{ fontWeight: 700, color: '#1d4ed8', fontSize: '0.95rem' }}>
                              {thaiMoney(rec.net)}
                            </td>
                            <td className="admin-numeric">
                              {rec.dividend_rate ? `${rec.dividend_rate}%` : '—'}
                            </td>
                            <td className="admin-numeric">
                              {rec.refund_rate ? `${rec.refund_rate}%` : '—'}
                            </td>
                            <td>{d.updated_at || '—'}</td>
                            <td>
                              <button
                                type="button"
                                className="acc-btn"
                                style={{ padding: '5px 10px', fontSize: '0.8rem' }}
                                onClick={() => setSelectedDividend(isSelected ? null : d)}
                              >
                                {isSelected ? 'ปิด' : 'ดูแจกแจง'}
                              </button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}

              {/* Itemized Breakdown Panel */}
              {selectedDividend && (
                <div
                  className="admin-card"
                  style={{
                    marginTop: '20px',
                    border: '2px solid #2563eb',
                    background: '#f8fafc',
                    borderRadius: '12px'
                  }}
                >
                  <div className="member-admin-card-heading">
                    <h3 style={{ margin: 0, color: '#1e40af' }}>
                      รายละเอียดแจกแจงเงินปันผลปีบัญชี {selectedDividend.record?.year}
                    </h3>
                    <button
                      type="button"
                      className="acc-btn"
                      style={{ padding: '4px 10px', fontSize: '0.8rem' }}
                      onClick={() => setSelectedDividend(null)}
                    >
                      ปิดหน้ารายละเอียด
                    </button>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    {/* Income items */}
                    <div style={{ background: '#ffffff', padding: '16px', borderRadius: '10px', border: '1px solid #dcfce7' }}>
                      <h4 style={{ margin: '0 0 12px 0', color: '#166534' }}>รายการรายรับ (Income)</h4>
                      <table style={{ width: '100%', fontSize: '0.85rem', borderCollapse: 'collapse' }}>
                        <tbody>
                          {Object.entries(selectedDividend.record?.income || {}).map(([item, val]) => (
                            <tr key={item} style={{ borderBottom: '1px solid #f1f5f9' }}>
                              <td style={{ padding: '6px 0' }}>{item}</td>
                              <td style={{ textAlign: 'right', padding: '6px 0', fontWeight: 600, color: '#166534' }}>
                                {thaiMoney(val)} บาท
                              </td>
                            </tr>
                          ))}
                          <tr style={{ fontWeight: 700, borderTop: '2px solid #166534' }}>
                            <td style={{ padding: '8px 0' }}>รายรับรวม</td>
                            <td style={{ textAlign: 'right', padding: '8px 0', color: '#166534' }}>
                              {thaiMoney(selectedDividend.record?.total_income)} บาท
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    {/* Deduction items */}
                    <div style={{ background: '#ffffff', padding: '16px', borderRadius: '10px', border: '1px solid #fee2e2' }}>
                      <h4 style={{ margin: '0 0 12px 0', color: '#b91c1c' }}>รายการรายหัก (Deductions)</h4>
                      <table style={{ width: '100%', fontSize: '0.85rem', borderCollapse: 'collapse' }}>
                        <tbody>
                          {Object.entries(selectedDividend.record?.deductions || {}).map(([item, val]) => (
                            <tr key={item} style={{ borderBottom: '1px solid #f1f5f9' }}>
                              <td style={{ padding: '6px 0' }}>{item}</td>
                              <td style={{ textAlign: 'right', padding: '6px 0', fontWeight: 600, color: '#b91c1c' }}>
                                {thaiMoney(val)} บาท
                              </td>
                            </tr>
                          ))}
                          <tr style={{ fontWeight: 700, borderTop: '2px solid #b91c1c' }}>
                            <td style={{ padding: '8px 0' }}>รายหักรวม</td>
                            <td style={{ textAlign: 'right', padding: '8px 0', color: '#b91c1c' }}>
                              {thaiMoney(selectedDividend.record?.total_deductions)} บาท
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div style={{ marginTop: '16px', textAlign: 'right', fontSize: '1.05rem', fontWeight: 700, color: '#1d4ed8' }}>
                    ยอดเงินปันผลสุทธิ: {thaiMoney(selectedDividend.record?.net)} บาท
                  </div>
                </div>
              )}
            </section>
          )}

          {/* TAB 3: Change History & Audit Logs */}
          {tab === 'history' && (
            <section className="member-admin-card">
              <div className="member-admin-card-heading">
                <div>
                  <h2>ประวัติการเปลี่ยนแปลงข้อมูลสมาชิก (Immutable Audit Trail)</h2>
                  <span>บันทึกความเปลี่ยนแปลงทุกครั้ง พร้อมเหตุผลและผู้ดำเนินการ</span>
                </div>
              </div>

              {(!data.history || data.history.length === 0) ? (
                <p className="admin-empty">ยังไม่มีบันทึกประวัติการเปลี่ยนแปลงของสมาชิกรายนี้</p>
              ) : (
                <ul className="member-history-list">
                  {data.history.map((h) => (
                    <li key={h.id} className="member-history-item">
                      <div className="member-history-header">
                        <span className="member-history-action">
                          {h.action === 'profile_update' ? 'ปรับปรุงข้อมูลทะเบียนสมาชิก'
                            : h.action === 'account_activation' ? 'เปิดใช้งานบัญชีผู้ใช้'
                            : h.action}
                        </span>
                        <span className="member-history-meta">
                          {h.created_at} · โดย {h.actor || 'ระบบ CLI'}
                        </span>
                      </div>
                      <p className="member-history-reason">
                        <strong>เหตุผล:</strong> {h.reason || '—'}
                      </p>
                      <details style={{ marginTop: '8px' }}>
                        <summary style={{ fontSize: '0.82rem', color: '#1d4ed8', cursor: 'pointer', fontWeight: 600 }}>
                          ดูรายละเอียดค่าก่อนและหลังแก้ไข
                        </summary>
                        <HistoryDiffViewer beforeJson={h.before_json} afterJson={h.after_json} />
                      </details>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          )}
        </>
      )}
    </AdminMemberShell>
  );
}
