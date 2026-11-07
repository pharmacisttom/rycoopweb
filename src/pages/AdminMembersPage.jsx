import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import AdminMemberShell from '../components/member/AdminMemberShell';
import MemberAdminWarnings from '../components/member/MemberAdminWarnings';
import { memberAdminApi, memberStatus, thaiMoney } from '../services/memberAdminApi';
import { maskIdCard, maskName } from '../utils/pdpa';
import { Search, RotateCcw, Filter, CheckCircle2, AlertCircle, Eye, ShieldCheck, UserCheck } from 'lucide-react';

export default function AdminMembersPage() {
  const [params, setParams] = useSearchParams();
  const [q, setQ] = useState(params.get('q') || '');
  const [status, setStatus] = useState(params.get('status') || '');
  const [account, setAccount] = useState(params.get('account') || '');
  const [year, setYear] = useState(params.get('year') || '');
  const [limit, setLimit] = useState(params.get('limit') || '25');
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refresh, setRefresh] = useState(0);

  useEffect(() => {
    setQ(params.get('q') || '');
    setStatus(params.get('status') || '');
    setAccount(params.get('account') || '');
    setYear(params.get('year') || '');
    setLimit(params.get('limit') || '25');
  }, [params]);

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setData(null);
    setError(null);

    memberAdminApi(`/api/admin/members?${params.toString()}`, { signal: controller.signal })
      .then((res) => {
        setData(res);
        setLoading(false);
      })
      .catch((e) => {
        if (e.name !== 'AbortError') {
          setError(e);
          setLoading(false);
        }
      });

    return () => controller.abort();
  }, [params, refresh]);

  function handleSearch(e) {
    e.preventDefault();
    const next = {};
    if (q.trim()) next.q = q.trim();
    if (status) next.status = status;
    if (account) next.account = account;
    if (year) next.year = year;
    if (limit !== '25') next.limit = limit;
    next.page = '1';
    setParams(next);
    setRefresh((n) => n + 1);
  }

  function handleReset() {
    setParams({});
    setQ('');
    setStatus('');
    setAccount('');
    setYear('');
    setLimit('25');
    setRefresh((n) => n + 1);
  }

  function handlePageChange(newPage) {
    const next = new URLSearchParams(params);
    next.set('page', String(newPage));
    setParams(next);
  }

  const breadcrumbs = [
    { label: 'หน้าแรกผู้ดูแล', to: '/admin/dashboard' },
    { label: 'ระบบสมาชิกและปันผล', to: '/admin/members/dashboard' },
    { label: 'ค้นหาและดูแลสมาชิก' },
  ];

  const totalPages = data ? Math.max(1, Math.ceil(data.total / data.limit)) : 1;
  const startRecord = data?.total ? (data.page - 1) * data.limit + 1 : 0;
  const endRecord = data?.total ? Math.min(data.page * data.limit, data.total) : 0;

  return (
    <AdminMemberShell
      title="ค้นหาและจัดการทะเบียนสมาชิก"
      description="ระบบค้นหาสมาชิก ตรวจสอบสถานะการเชื่อมโยงบัญชี (Identity Mapping) ข้อมูลปันผลสะสม และการคุ้มครองข้อมูลส่วนบุคคล (PDPA)"
      breadcrumbs={breadcrumbs}
    >
      {/* Search & Filter Bar */}
      <section className="member-admin-card" aria-label="ตัวกรองการค้นหาสมาชิก">
        <form className="member-admin-search" onSubmit={handleSearch}>
          <div className="member-admin-field">
            <label htmlFor="search-q">คำค้นหา</label>
            <input
              id="search-q"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="เลขสมาชิก / ชื่อ / สังกัด / เลขบัตรประชาชน"
              maxLength={100}
            />
          </div>

          <div className="member-admin-field">
            <label htmlFor="search-status">สถานะสมาชิก</label>
            <select
              id="search-status"
              value={status}
              onChange={(e) => setStatus(e.target.value)}
            >
              <option value="">ทุกสถานะ</option>
              {Object.entries(memberStatus).map(([val, label]) => (
                <option key={val} value={val}>{label}</option>
              ))}
            </select>
          </div>

          <div className="member-admin-field">
            <label htmlFor="search-account">การผูกบัญชีเข้าใช้</label>
            <select
              id="search-account"
              value={account}
              onChange={(e) => setAccount(e.target.value)}
            >
              <option value="">ทั้งหมด</option>
              <option value="linked">ผูกบัญชีแล้ว (มีสิทธิ์เข้าใช้)</option>
              <option value="unlinked">ยังไม่มีบัญชีเข้าใช้</option>
            </select>
          </div>

          <div className="member-admin-field">
            <label htmlFor="search-year">ปีที่มีปันผล</label>
            <input
              id="search-year"
              type="number"
              min="2400"
              max="2800"
              value={year}
              onChange={(e) => setYear(e.target.value)}
              placeholder="ทุกปี พ.ศ."
              list="member-dividend-years"
            />
            <datalist id="member-dividend-years">
              {data?.available_years?.map((y) => (
                <option key={y} value={y} />
              ))}
            </datalist>
          </div>

          <div className="member-admin-field">
            <label htmlFor="search-limit">แสดงต่อหน้า</label>
            <select
              id="search-limit"
              value={limit}
              onChange={(e) => setLimit(e.target.value)}
            >
              <option value="25">25 รายการ</option>
              <option value="50">50 รายการ</option>
              <option value="100">100 รายการ</option>
            </select>
          </div>

          <div style={{ display: 'flex', gap: '8px' }}>
            <button type="submit" className="acc-btn primary" title="ค้นหา">
              <Search size={15} />
              <span>ค้นหา</span>
            </button>
            <button
              type="button"
              className="acc-btn"
              onClick={handleReset}
              title="ล้างค่าตัวกรอง"
            >
              <RotateCcw size={15} />
              <span>ล้างค่า</span>
            </button>
          </div>
        </form>
      </section>

      <MemberAdminWarnings error={error} warnings={data?.warnings} />

      {error && (
        <div className="admin-error">
          <p>เกิดข้อผิดพลาดในการโหลดข้อมูล: {error.message || 'โปรดลองใหม่อีกครั้ง'}</p>
          <button type="button" className="acc-btn" onClick={() => setRefresh((n) => n + 1)}>
            โหลดข้อมูลอีกครั้ง
          </button>
        </div>
      )}

      {loading && !data && (
        <div className="admin-skeleton" role="status">
          กำลังค้นหาและเรียบเรียงข้อมูลสมาชิก...
        </div>
      )}

      {data && (
        <section className="member-admin-card">
          <div className="member-admin-card-heading">
            <div>
              <h2>
                รายชื่อสมาชิก
                {data.year ? ` · ปีบัญชี ${data.year}` : ''}
              </h2>
              <span>
                พบ {Number(data.total).toLocaleString('th-TH')} รายการ
                {data.total > 0 && ` · แสดงลำดับที่ ${startRecord}–${endRecord}`}
              </span>
            </div>
            {data.year && (
              <span className="member-admin-badge account-linked">
                กรองตามข้อมูลปันผลปี {data.year}
              </span>
            )}
          </div>

          <div
            className="admin-table-scroll"
            tabIndex={0}
            aria-label="ตารางรายชื่อสมาชิก สามารถเลื่อนแนวนอนเพื่อดูข้อมูลทั้งหมด"
          >
            <table className="admin-table">
              <thead>
                <tr>
                  <th scope="col" style={{ width: '110px' }}>เลขสมาชิก</th>
                  <th scope="col">ชื่อ-นามสกุล / สังกัด</th>
                  <th scope="col" style={{ width: '150px' }}>เลขบัตรประชาชน (PDPA)</th>
                  <th scope="col" style={{ width: '110px' }}>สถานะสมาชิก</th>
                  <th scope="col" style={{ width: '130px' }}>บัญชีเข้าใช้ระบบ</th>
                  <th scope="col" className="admin-numeric" style={{ width: '110px' }}>ปันผลสะสม</th>
                  {data.year && (
                    <>
                      <th scope="col" className="admin-numeric">รายรับปี {data.year}</th>
                      <th scope="col" className="admin-numeric">รายหักปี {data.year}</th>
                      <th scope="col" className="admin-numeric">ยอดสุทธิปี {data.year}</th>
                    </>
                  )}
                  <th scope="col" style={{ width: '120px' }}>จัดการ</th>
                </tr>
              </thead>
              <tbody>
                {data.items.map((member) => (
                  <tr key={member.id}>
                    <td>
                      <span className="member-no-mono">{member.member_no}</span>
                    </td>
                    <td>
                      <strong style={{ display: 'block', color: 'var(--acc-text, #0f172a)' }}>
                        {member.name_pdpa || maskName(member.name) || 'ยังไม่มีชื่อ'}
                      </strong>
                      <span style={{ fontSize: '0.8rem', color: 'var(--acc-text-secondary, #64748b)' }}>
                        {member.department || '—'}
                      </span>
                    </td>
                    <td style={{ fontFamily: 'monospace', fontSize: '0.85rem' }}>
                      {member.id_card_pdpa || maskIdCard(member.id_card_masked || member.id_card)}
                    </td>
                    <td>
                      <span className={`member-admin-badge ${member.status}`}>
                        {memberStatus[member.status] || member.status || 'ยังไม่ระบุ'}
                      </span>
                    </td>
                    <td>
                      {Number(member.has_account) ? (
                        <span className="member-admin-badge account-linked">
                          <CheckCircle2 size={12} />
                          <span>มีบัญชีแล้ว</span>
                        </span>
                      ) : (
                        <span className="member-admin-badge account-unlinked">
                          <span>ยังไม่มีบัญชี</span>
                        </span>
                      )}
                    </td>
                    <td className="admin-numeric">
                      {member.record_count == null ? '—' : `${member.record_count} ปี`}
                    </td>
                    {data.year && (
                      <>
                        <td className="admin-numeric" style={{ color: '#166534' }}>
                          {thaiMoney(member.total_income)}
                        </td>
                        <td className="admin-numeric" style={{ color: '#b91c1c' }}>
                          {thaiMoney(member.total_deductions)}
                        </td>
                        <td className="admin-numeric" style={{ fontWeight: 700, color: '#1d4ed8' }}>
                          {thaiMoney(member.net)}
                        </td>
                      </>
                    )}
                    <td>
                      <Link
                        to={`/admin/members/${member.id}`}
                        className="acc-btn"
                        style={{ padding: '6px 12px', fontSize: '0.82rem', textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                      >
                        <Eye size={13} />
                        <span>ดูข้อมูล</span>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {data.items.length === 0 && (
            <p className="admin-empty">
              ไม่พบสมาชิกตามเงื่อนไขการค้นหา โปรดตรวจสอบคำค้นหรือล้างตัวกรอง
            </p>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="member-admin-pagination" aria-label="การแบ่งหน้า">
              <button
                type="button"
                className="btn btn-outline"
                disabled={data.page <= 1}
                onClick={() => handlePageChange(1)}
              >
                หน้าแรก
              </button>
              <button
                type="button"
                className="btn btn-outline"
                disabled={data.page <= 1}
                onClick={() => handlePageChange(data.page - 1)}
              >
                ก่อนหน้า
              </button>
              <span>
                หน้า {data.page} จาก {totalPages}
              </span>
              <button
                type="button"
                className="btn btn-outline"
                disabled={data.page >= totalPages}
                onClick={() => handlePageChange(data.page + 1)}
              >
                ถัดไป
              </button>
              <button
                type="button"
                className="btn btn-outline"
                disabled={data.page >= totalPages}
                onClick={() => handlePageChange(totalPages)}
              >
                หน้าสุดท้าย
              </button>
            </div>
          )}
        </section>
      )}
    </AdminMemberShell>
  );
}
