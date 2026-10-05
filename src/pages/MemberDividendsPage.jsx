import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import ChangePasswordModal from '../components/common/ChangePasswordModal';
import './MemberDividendsPage.css';

const money = value => Number(value).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export default function MemberDividendsPage() {
  const { logout, invalidateSession } = useAuth();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [year, setYear] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [attempt, setAttempt] = useState(0);
  const [passwordOpen, setPasswordOpen] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true); setError(''); setData(null);
    fetch('/api/member/dividends', { credentials: 'include', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
      .then(async response => {
        if (controller.signal.aborted) return;
        if (response.status === 401) {
          invalidateSession();
          navigate('/member/login', { replace: true, state: { sessionRequired: true } });
          return;
        }
        const result = await response.json();
        if (controller.signal.aborted) return;
        if (!response.ok || !result.success) throw new Error(result.message || 'ไม่สามารถโหลดข้อมูลได้');
        setData(result); setYear(String(result.records[0]?.year || ''));
      }).catch(e => { if (e.name !== 'AbortError') setError(e.message); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [attempt, invalidateSession, navigate]);
  const record = data?.records.find(r => String(r.year) === year);
  return <main className="dividend-page container">
    <div className="dividend-actions"><Link to="/">กลับหน้าแรก</Link>{data && <div><button className="btn btn-outline" onClick={() => setPasswordOpen(true)}>เปลี่ยนรหัสผ่าน</button> <button className="btn btn-outline" onClick={async () => { await logout(); window.location.assign('/member/login'); }}>ออกจากระบบ</button></div>}</div>
    <h1>เงินปันผลและเงินเฉลี่ยคืน</h1>
    <p>ตรวจสอบรายการรายรับ รายหัก และยอดสุทธิประจำปีของคุณ</p>
    {data && <p className="dividend-note">รหัสผ่านเริ่มต้นคือเลขสมาชิก รวมเลขศูนย์นำหน้า เช่น 00025 กรุณาเปลี่ยนเป็นรหัสผ่านส่วนตัว</p>}
    {loading && <p role="status">กำลังโหลดข้อมูลสมาชิก...</p>}
    {error && <div role="alert" className="dividend-error">{error} <Link to="/member/login">เข้าสู่ระบบ</Link> <button onClick={() => setAttempt(a => a + 1)}>ลองใหม่</button></div>}
    {data && <><p><strong>{data.member.name}</strong> · เลขทะเบียนสมาชิก {data.member.member_no}</p>
      {!data.records.length ? <p>ยังไม่มีข้อมูลปันผลของคุณ กรุณาติดต่อสหกรณ์</p> : <>
        <div className="dividend-actions"><label>ปีบัญชี <select value={year} onChange={e => setYear(e.target.value)}>{data.records.map(r => <option key={r.year} value={r.year}>{r.year}</option>)}</select></label><button className="btn btn-primary" onClick={() => window.print()}>พิมพ์ / บันทึก PDF</button></div>
        {record && <><section className="dividend-summary" aria-label="สรุปยอดเงิน"><article><span>รายรับทั้งหมด</span><strong>{money(record.total_income)} บาท</strong></article><article><span>รายหักทั้งหมด</span><strong>{money(record.total_deductions)} บาท</strong></article><article className="dividend-net"><span>ยอดเงินคงเหลือสุทธิ ปี {year}</span><strong>{money(record.net)} บาท</strong></article></section>
          <div className="dividend-details">{[['รายรับ', record.income, record.total_income], ['รายหัก', record.deductions, record.total_deductions]].map(([title, items, total]) => <section key={title}><h2>{title}</h2><table><caption className="sr-only">รายละเอียด{title}ปี {year}</caption><tbody>{Object.entries(items).map(([label, amount]) => <tr key={label}><th scope="row">{label}</th><td>{money(amount)}</td></tr>)}<tr><th scope="row">รวม{title}</th><td><strong>{money(total)}</strong></td></tr></tbody></table></section>)}</div>
          <p>อัตราปันผล {money(record.dividend_rate)}% · อัตราเฉลี่ยคืน {money(record.refund_rate)}%</p><p>เงินที่ได้รับตามรายงาน: <strong>{money(record.received)} บาท</strong></p>
          <p className="dividend-note">ข้อมูลจากรายงานปี {year} ของสหกรณ์ จำนวนเงินแสดงเป็นบาท หากพบข้อมูลคลาดเคลื่อน กรุณา<Link to="/contact">ติดต่อสหกรณ์</Link> รายการนี้ใช้ตรวจสอบข้อมูลและไม่ใช่หลักฐานการโอนเงิน</p>
        </>}
      </>}
    </>}
    <ChangePasswordModal isOpen={passwordOpen} onClose={() => setPasswordOpen(false)} />
  </main>;
}
