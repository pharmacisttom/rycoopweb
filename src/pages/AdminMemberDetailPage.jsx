import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import AdminMemberShell from '../components/member/AdminMemberShell';
import { memberAdminApi, thaiMoney, memberStatus } from '../services/memberAdminApi';
const labels = { prefix: 'คำนำหน้า', first_name: 'ชื่อ', last_name: 'นามสกุล', member_no: 'เลขสมาชิก', id_card: 'เลขบัตรประชาชน', department: 'สังกัด', phone: 'โทรศัพท์', email: 'อีเมล', address: 'ที่อยู่' };
const historyLabels = { ...labels, status: 'สถานะ', year: 'ปีบัญชี', name: 'ชื่อในรายงาน', total_income: 'รายรับรวม', total_deductions: 'รายหักรวม', net: 'ยอดสุทธิ', received: 'เงินที่ได้รับตามรายงาน', dividend_rate: 'อัตราปันผล (%)', refund_rate: 'อัตราเฉลี่ยคืน (%)' };
function historyValues(json) {
  const values = {}, record = json ? JSON.parse(json) : {};
  Object.entries(record).forEach(([key, value]) => {
    if (key === 'income' || key === 'deductions') Object.entries(value).forEach(([label, amount]) => { values[`${key === 'income' ? 'รายรับ' : 'รายหัก'} · ${label}`] = amount; });
    else if (historyLabels[key]) values[historyLabels[key]] = key === 'status' ? memberStatus[value] || value : value;
  });
  return values;
}
function HistoryValues({ item }) {
  const before = historyValues(item.before_json), after = historyValues(item.after_json);
  const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])].filter(key => before[key] !== after[key]);
  return <div className="member-admin-scroll"><table><thead><tr><th>รายการ</th><th>ก่อนแก้ไข</th><th>หลังแก้ไข</th></tr></thead><tbody>{keys.map(key => <tr key={key}><td>{key}</td><td>{before[key] ?? '—'}</td><td>{after[key] ?? '—'}</td></tr>)}</tbody></table>{!keys.length && <p>ไม่มีค่าที่เปลี่ยนแปลง</p>}</div>;
}
export default function AdminMemberDetailPage() {
  const { id } = useParams();
  const [data, setData] = useState(null), [profile, setProfile] = useState(null), [tab, setTab] = useState('profile'), [dividend, setDividend] = useState(null), [reason, setReason] = useState(''), [message, setMessage] = useState(''), [busy, setBusy] = useState(false), [refresh, setRefresh] = useState(0);
  useEffect(() => { const c = new AbortController(); setData(null); setProfile(null); setDividend(null); setReason(''); memberAdminApi(`/api/admin/members/${id}`, { signal: c.signal }).then(r => { setData(r); setProfile(r.member); }).catch(e => { if (e.name !== 'AbortError') setMessage(e.message); }); return () => c.abort(); }, [id, refresh]);
  async function save(e, financial) {
    e.preventDefault(); setBusy(true); setMessage('');
    try {
      const path = financial ? `/api/admin/members/${id}/dividends/${dividend.record.year}` : `/api/admin/members/${id}`;
      const input = financial ? { ...dividend.record, version: dividend.version, reason } : { ...profile, version: data.version, reason };
      const result = await memberAdminApi(path, { data: input }); setMessage(result.message); setRefresh(n => n + 1);
    } catch (e) { setMessage(e.message); } finally { setBusy(false); }
  }
  return <AdminMemberShell title={profile ? `สมาชิก ${profile.member_no}` : 'ข้อมูลสมาชิก'} description="แก้ไขข้อมูลและรายการปันผล โดยบันทึกเหตุผลและประวัติทุกครั้ง">
    <Link to="/admin/members">กลับรายชื่อสมาชิก</Link>{message && <p role="status" className="member-admin-message">{message}</p>}{!data && !message && <p role="status">กำลังโหลดข้อมูล...</p>}
    {data && <><div className="member-admin-tabs"><button className={tab === 'profile' ? 'active' : ''} onClick={() => { setTab('profile'); setReason(''); }}>ข้อมูลสมาชิก</button><button className={tab === 'dividends' ? 'active' : ''} onClick={() => { setTab('dividends'); setReason(''); }}>ปันผลรายปี</button><button className={tab === 'history' ? 'active' : ''} onClick={() => setTab('history')}>ประวัติแก้ไข</button></div>
      {tab === 'profile' && <form className="member-admin-card" onSubmit={e => save(e, false)}><fieldset disabled={busy}><div className="member-admin-fields">{Object.entries(labels).map(([field, label]) => <label key={field}>{label}<input value={profile[field] || ''} onChange={e => setProfile(p => ({ ...p, [field]: e.target.value }))} type={field === 'email' ? 'email' : 'text'} required={['first_name', 'id_card', 'member_no'].includes(field)} /></label>)}<label>สถานะ<select value={profile.status} onChange={e => setProfile(p => ({ ...p, status: e.target.value }))}>{Object.entries(memberStatus).map(([v, label]) => <option key={v} value={v}>{label}</option>)}</select></label></div><p>การแก้เลขบัตรจะเปลี่ยน User สำหรับเข้าสู่ระบบ รหัสผ่านเดิมจะคงอยู่ การระงับหรือพ้นสมาชิกจะปิดการเข้าใช้</p><label>เหตุผลการแก้ไข<textarea required maxLength={500} value={reason} onChange={e => setReason(e.target.value)} placeholder="เช่น สมาชิกแจ้งเปลี่ยนชื่อ พร้อมตรวจเอกสารแล้ว" /></label><button className="btn btn-primary" disabled={busy}>{busy ? 'กำลังบันทึก...' : 'บันทึกข้อมูลสมาชิก'}</button></fieldset></form>}
      {tab === 'dividends' && <section className="member-admin-card"><h2>ปันผลของสมาชิก</h2><div className="member-admin-scroll"><table><thead><tr><th>ปี</th><th>รายรับ</th><th>รายหัก</th><th>สุทธิ</th><th>แก้ไข</th></tr></thead><tbody>{data.dividends.map(d => <tr key={d.record.year}><td>{d.record.year}</td><td>{thaiMoney(d.record.total_income)}</td><td>{thaiMoney(d.record.total_deductions)}</td><td>{thaiMoney(d.record.net)}</td><td><button className="btn btn-outline" onClick={() => { setDividend(JSON.parse(JSON.stringify(d))); setReason(''); }}>แก้ไข</button></td></tr>)}</tbody></table></div>{!data.dividends.length && <p>ไม่มีรายการปันผล กรุณาเพิ่มผ่านการนำเข้า Excel</p>}
        {dividend && <form onSubmit={e => save(e, true)}><fieldset disabled={busy}><h3>แก้ไขปี {dividend.record.year}</h3><div className="member-admin-fields">{['income', 'deductions'].flatMap(type => Object.entries(dividend.record[type]).map(([label, value]) => <label key={label}>{label}<input type="number" step="0.01" min="0" required value={value} onChange={e => setDividend(d => ({ ...d, record: { ...d.record, [type]: { ...d.record[type], [label]: e.target.value } } }))} /></label>))}{[['received', 'เงินที่ได้รับตามรายงาน'], ['dividend_rate', 'อัตราปันผล (%)'], ['refund_rate', 'อัตราเฉลี่ยคืน (%)']].map(([field, label]) => <label key={field}>{label}<input type="number" step="0.01" required value={dividend.record[field]} onChange={e => setDividend(d => ({ ...d, record: { ...d.record, [field]: e.target.value } }))} /></label>)}</div><p>ยอดสุทธิที่จะบันทึก: <strong>{thaiMoney(Object.values(dividend.record.income).reduce((a, n) => a + Number(n || 0), 0) - Object.values(dividend.record.deductions).reduce((a, n) => a + Number(n || 0), 0))} บาท</strong> ระบบคำนวณรายรับ รายหัก และยอดสุทธิใหม่</p><label>เหตุผลการแก้ไข<textarea required maxLength={500} value={reason} onChange={e => setReason(e.target.value)} /></label><button className="btn btn-primary" disabled={busy}>{busy ? 'กำลังบันทึก...' : 'บันทึกปันผล'}</button> <button type="button" className="btn btn-outline" onClick={() => setDividend(null)}>ยกเลิก</button></fieldset></form>}
      </section>}
      {tab === 'history' && <section className="member-admin-card"><h2>ประวัติการเปลี่ยนแปลง</h2>{!data.history.length && <p>ยังไม่มีการเปลี่ยนแปลงในระบบใหม่</p>}<ul className="member-admin-history">{data.history.map(h => <li key={h.id}><strong>{h.reason}</strong><p>{h.created_at} · {h.actor || 'เครื่องมือ CLI'}</p><details><summary>ดูข้อมูลก่อนและหลังแก้ไข</summary><HistoryValues item={h} /></details></li>)}</ul></section>}
    </>}
  </AdminMemberShell>;
}
