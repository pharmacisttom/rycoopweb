import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import AdminMemberShell from '../components/member/AdminMemberShell';
import MemberAdminWarnings from '../components/member/MemberAdminWarnings';
import { memberAdminApi, memberStatus, thaiMoney } from '../services/memberAdminApi';

export default function AdminMembersPage() {
  const [params, setParams] = useSearchParams();
  const [q, setQ] = useState(params.get('q') || ''), [status, setStatus] = useState(params.get('status') || ''), [year, setYear] = useState(params.get('year') || '');
  const [limit, setLimit] = useState(params.get('limit') || '25');
  const [data, setData] = useState(null), [error, setError] = useState(null), [refresh, setRefresh] = useState(0);
  useEffect(() => { setQ(params.get('q') || ''); setStatus(params.get('status') || ''); setYear(params.get('year') || ''); setLimit(params.get('limit') || '25'); }, [params]);
  useEffect(() => {
    const controller = new AbortController(); setData(null); setError(null);
    memberAdminApi(`/api/admin/members?${params}`, { signal: controller.signal }).then(setData).catch(e => { if (e.name !== 'AbortError') setError(e); });
    return () => controller.abort();
  }, [params, refresh]);
  function search(e) { e.preventDefault(); setParams({ q: q.trim(), status, year, limit, page: '1' }); setRefresh(n => n + 1); }
  function page(number) { const next = new URLSearchParams(params); next.set('page', String(number)); setParams(next); }
  const pages = data ? Math.max(1, Math.ceil(data.total / data.limit)) : 1;
  const start = data?.total ? (data.page - 1) * data.limit + 1 : 0;
  return <AdminMemberShell title="ค้นหาและดูแลสมาชิก" description="ค้นหาชื่อ เลขสมาชิก เลขบัตรประชาชน หรือสังกัด แล้วเปิดข้อมูลเพื่อดูและแก้ไข">
    <section className="member-admin-card member-admin-search-card"><form className="member-admin-search" onSubmit={search}>
      <label>คำค้น<input value={q} onChange={e => setQ(e.target.value)} placeholder="ชื่อ / เลขสมาชิก / เลขบัตร / สังกัด" maxLength={100} /></label>
      <label>สถานะ<select value={status} onChange={e => setStatus(e.target.value)}><option value="">ทุกสถานะ</option>{Object.entries(memberStatus).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
      <label>ปีที่มีปันผล<input type="number" min="2400" max="2800" value={year} onChange={e => setYear(e.target.value)} placeholder="ทุกปี" list="member-dividend-years" /><datalist id="member-dividend-years">{data?.available_years?.map(y => <option key={y} value={y} />)}</datalist></label>
      <label>ต่อหน้า<select value={limit} onChange={e => setLimit(e.target.value)}>{[25,50,100].map(n => <option key={n} value={n}>{n} รายการ</option>)}</select></label>
      <button className="btn btn-primary">ค้นหา</button><button type="button" className="btn btn-outline" onClick={() => { setParams({}); setRefresh(n => n + 1); }}>ล้างตัวกรอง</button>
    </form></section>
    <MemberAdminWarnings error={error} warnings={data?.warnings} />
    {error && <button className="btn btn-outline" onClick={() => setRefresh(n => n + 1)}>ลองโหลดอีกครั้ง</button>}
    {!data && !error && <p role="status">กำลังค้นหา...</p>}
    {data && <section className="member-admin-card"><div className="member-admin-table-heading"><h2>รายชื่อสมาชิก{data.year ? ` · ปี ${data.year}` : ''}</h2><span>พบ {Number(data.total).toLocaleString('th-TH')} ราย · แสดง {start}–{Math.min(data.page * data.limit, data.total)}</span></div>
      <div className="member-admin-scroll" tabIndex={0} aria-label="ตารางสมาชิก เลื่อนได้เมื่อข้อมูลกว้างกว่าหน้าจอ"><table><thead><tr><th>เลขสมาชิก</th><th>ชื่อ / สังกัด</th><th>เลขบัตร</th><th>สถานะ</th><th>บัญชีเข้าใช้</th><th>ปันผลสะสม</th>{data.year && <><th>รายรับ</th><th>รายหัก</th><th>สุทธิปี {data.year}</th></>}<th>จัดการ</th></tr></thead><tbody>{data.items.map(member => <tr key={member.id}><td>{member.member_no}</td><td><strong>{member.name || 'ยังไม่มีชื่อ'}</strong><small className="member-admin-cell-subtitle">{member.department || '—'}</small></td><td className="nowrap">{member.id_card_masked}</td><td><span className={`member-admin-status ${member.status}`}>{memberStatus[member.status] || 'ยังไม่ระบุ'}</span></td><td>{Number(member.has_account) ? 'มีบัญชี' : 'ยังไม่มี'}</td><td>{member.record_count == null ? 'ยังไม่พร้อม' : `${member.record_count} ปี`}</td>{data.year && <><td className="money">{thaiMoney(member.total_income)}</td><td className="money">{thaiMoney(member.total_deductions)}</td><td className="money"><strong>{thaiMoney(member.net)}</strong></td></>}<td><Link className="member-admin-edit-link" to={`/admin/members/${member.id}`}>ดู / แก้ไข</Link></td></tr>)}</tbody></table></div>
      {!data.items.length && <p className="member-admin-empty">ไม่พบสมาชิกตามเงื่อนไข ลองล้างตัวกรองหรือค้นหาด้วยเลขสมาชิก</p>}
      <div className="member-admin-pagination"><button className="btn btn-outline" disabled={data.page <= 1} onClick={() => page(1)}>หน้าแรก</button><button className="btn btn-outline" disabled={data.page <= 1} onClick={() => page(data.page - 1)}>ก่อนหน้า</button><span>หน้า {data.page} / {pages}</span><button className="btn btn-outline" disabled={data.page >= pages} onClick={() => page(data.page + 1)}>ถัดไป</button><button className="btn btn-outline" disabled={data.page >= pages} onClick={() => page(pages)}>หน้าสุดท้าย</button></div>
    </section>}
  </AdminMemberShell>;
}
