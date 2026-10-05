import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import AdminMemberShell from '../components/member/AdminMemberShell';
import { memberAdminApi, memberStatus } from '../services/memberAdminApi';
export default function AdminMembersPage() {
  const [params, setParams] = useSearchParams();
  const [q, setQ] = useState(params.get('q') || ''), [status, setStatus] = useState(params.get('status') || ''), [year, setYear] = useState(params.get('year') || '');
  const [data, setData] = useState(null), [error, setError] = useState('');
  useEffect(() => { const c = new AbortController(); setData(null); setError(''); memberAdminApi(`/api/admin/members?${params}`, { signal: c.signal }).then(setData).catch(e => { if (e.name !== 'AbortError') setError(e.message); }); return () => c.abort(); }, [params]);
  function search(e) { e.preventDefault(); setParams({ q: q.trim(), status, year, page: '1' }); }
  return <AdminMemberShell title="จัดการข้อมูลสมาชิก" description="ค้นหาด้วยชื่อ เลขสมาชิก เลขบัตรประชาชน หรือสังกัด แล้วเปิดข้อมูลเพื่อแก้ไข">
    <form className="member-admin-search" onSubmit={search}><label>คำค้น<input value={q} onChange={e => setQ(e.target.value)} placeholder="ชื่อ / เลขสมาชิก / เลขบัตร" maxLength={100} /></label><label>สถานะ<select value={status} onChange={e => setStatus(e.target.value)}><option value="">ทุกสถานะ</option>{Object.entries(memberStatus).map(([v, label]) => <option key={v} value={v}>{label}</option>)}</select></label><label>ปีที่มีปันผล<input type="number" min="2400" max="2800" value={year} onChange={e => setYear(e.target.value)} placeholder="ทุกปี" /></label><button className="btn btn-primary">ค้นหา</button></form>
    {error && <p role="alert">{error}</p>}{!data && !error && <p role="status">กำลังค้นหา...</p>}
    {data && <section className="member-admin-card"><p>พบ {Number(data.total).toLocaleString('th-TH')} สมาชิก</p><div className="member-admin-scroll"><table><thead><tr><th>เลขสมาชิก</th><th>ชื่อ</th><th>เลขบัตร</th><th>สังกัด</th><th>สถานะ</th><th>ปันผล</th><th>จัดการ</th></tr></thead><tbody>{data.items.map(m => <tr key={m.id}><td>{m.member_no}</td><td>{m.name}</td><td>{m.id_card_masked}</td><td>{m.department || '—'}</td><td>{memberStatus[m.status]}</td><td>{m.record_count} ปี</td><td><Link to={`/admin/members/${m.id}`}>ดู / แก้ไข</Link></td></tr>)}</tbody></table></div>{!data.items.length && <p>ไม่พบสมาชิกตามเงื่อนไข</p>}<div className="member-admin-pagination"><button className="btn btn-outline" disabled={data.page <= 1} onClick={() => { const p = new URLSearchParams(params); p.set('page', data.page - 1); setParams(p); }}>ก่อนหน้า</button><span>หน้า {data.page} / {Math.max(1, Math.ceil(data.total / data.limit))}</span><button className="btn btn-outline" disabled={data.page * data.limit >= data.total} onClick={() => { const p = new URLSearchParams(params); p.set('page', data.page + 1); setParams(p); }}>ถัดไป</button></div></section>}
  </AdminMemberShell>;
}
