import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import AdminMemberShell from '../components/member/AdminMemberShell';
import { memberAdminApi, thaiMoney } from '../services/memberAdminApi';
export default function AdminMembersDashboardPage() {
  const [data, setData] = useState(null), [error, setError] = useState(''), [refresh, setRefresh] = useState(0);
  useEffect(() => { const c = new AbortController(); setError(''); setData(null); memberAdminApi('/api/admin/members/dashboard', { signal: c.signal }).then(setData).catch(e => { if (e.name !== 'AbortError') setError(e.message); }); return () => c.abort(); }, [refresh]);
  return <AdminMemberShell title="ภาพรวมสมาชิก" description="ข้อมูลจากฐานข้อมูลจริง สรุปสมาชิก ปันผลรายปี และรายการเปลี่ยนแปลงล่าสุด">
    <button className="btn btn-outline" onClick={() => setRefresh(r => r + 1)}>โหลดข้อมูลล่าสุด</button>
    {error && <p role="alert" className="member-admin-message">{error}</p>}{!data && !error && <p role="status">กำลังโหลดข้อมูล...</p>}
    {data && <><div className="member-admin-kpis">{[['สมาชิกทั้งหมด', data.members.total], ['สมาชิกใช้งาน', data.members.active], ['ระงับ / พ้นสมาชิก', Number(data.members.suspended) + Number(data.members.resigned)], ['ยังไม่มีบัญชีเข้าใช้', data.members.no_account]].map(([label, value]) => <article key={label}><span>{label}</span><strong>{Number(value || 0).toLocaleString('th-TH')}</strong></article>)}</div>
      <section className="member-admin-card"><h2>เงินปันผลรายปี</h2><div className="member-admin-scroll"><table><thead><tr><th>ปี พ.ศ.</th><th>รายการ</th><th>รายรับรวม</th><th>รายหักรวม</th><th>ยอดสุทธิรวม</th><th>จัดการ</th></tr></thead><tbody>{data.years.map(y => <tr key={y.year}><td>{y.year}</td><td>{Number(y.records).toLocaleString('th-TH')}</td><td>{thaiMoney(y.total_income)}</td><td>{thaiMoney(y.total_deductions)}</td><td>{thaiMoney(y.net_total)}</td><td><Link to={`/admin/members?year=${y.year}`}>ดูสมาชิก</Link></td></tr>)}</tbody></table></div>{!data.years.length && <p>ยังไม่มีข้อมูลปันผล <Link to="/admin/dividends/import">เริ่มนำเข้า</Link></p>}</section>
      <section className="member-admin-card"><h2>ประวัตินำเข้าล่าสุด</h2><div className="member-admin-scroll"><table><thead><tr><th>เวลา</th><th>ไฟล์ / ปี</th><th>ใหม่</th><th>อัปเดต</th><th>เดิม</th><th>ผู้ดำเนินการ</th></tr></thead><tbody>{data.runs.map(r => <tr key={r.id}><td>{r.created_at}</td><td>{r.source_name}<br />{r.year || 'หลายปี'}</td><td>{r.created_count}</td><td>{r.updated_count}</td><td>{r.unchanged_count}</td><td>{r.actor || 'เครื่องมือ CLI'}</td></tr>)}</tbody></table></div>{!data.runs.length && <p>ยังไม่มีประวัตินำเข้าในระบบใหม่ ข้อมูลที่นำเข้าก่อนหน้านี้ยังคงอยู่</p>}</section>
      <section className="member-admin-card"><h2>การแก้ไขล่าสุด</h2>{data.changes.length ? <ul className="member-admin-history">{data.changes.map(c => <li key={c.id}><Link to={`/admin/members/${c.member_id}`}>{c.member_no} · {c.member_name}</Link><p>{c.reason}</p><small>{c.created_at} · {c.actor || 'เครื่องมือ CLI'}</small></li>)}</ul> : <p>ยังไม่มีการแก้ไขข้อมูล</p>}</section></>}
  </AdminMemberShell>;
}
