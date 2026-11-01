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
  const [privacy, setPrivacy] = useState(null);
  const [privacyBusy, setPrivacyBusy] = useState(false);
  const [noticeRead, setNoticeRead] = useState(false);
  const [optionalConsent, setOptionalConsent] = useState(false);
  const [privacyMessage, setPrivacyMessage] = useState('');
  async function savePrivacy(eventType, choice) {
    setPrivacyBusy(true); setPrivacyMessage('');
    try {
      const csrf = await fetch('/csrf-token', { credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!csrf.ok) throw new Error('กรุณาเข้าสู่ระบบใหม่');
      const { token } = await csrf.json();
      const form = new FormData();
      form.append('_csrf_token', token);
      form.append('event_type', eventType);
      form.append('choice', choice);
      form.append('notice_version', privacy.notice_version);
      const response = await fetch('/api/member/privacy', { method: 'POST', credentials: 'include', cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: form });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'บันทึกไม่สำเร็จ');
      setPrivacy(previous => ({ ...previous, ...result }));
      if (eventType === 'notice_acknowledgement') {
        setAttempt(a => a + 1);
      } else {
        setPrivacyMessage('บันทึกตัวเลือกแล้ว สามารถเปลี่ยนหรือถอนความยินยอมได้ทุกเมื่อ');
      }
    } catch (e) { setPrivacyMessage(e.message); }
    finally { setPrivacyBusy(false); }
  }
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true); setError(''); setData(null);
    (async () => {
      try {
        const statusResponse = await fetch('/api/member/privacy', { credentials: 'include', cache: 'no-store',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
        if (statusResponse.status === 401) {
          invalidateSession();
          navigate('/member/login', { replace: true, state: { sessionRequired: true } }); return;
        }
        const status = await statusResponse.json();
        if (!statusResponse.ok || !status.success) throw new Error(status.message || 'ไม่สามารถตรวจสอบการรับทราบ PDPA');
        if (controller.signal.aborted) return;
        setPrivacy(status);
        setOptionalConsent(status.optional_choice === 'accepted');
        if (!status.acknowledged) return;
        const response = await fetch('/api/member/dividends', { credentials: 'include', cache: 'no-store',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
        if (response.status === 401) {
          invalidateSession();
          navigate('/member/login', { replace: true, state: { sessionRequired: true } }); return;
        }
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'ไม่สามารถโหลดข้อมูลได้');
        if (controller.signal.aborted) return;
        setData(result); setYear(String(result.records[0]?.year || ''));
      } catch (e) {
        if (e.name !== 'AbortError') setError(e.message);
      } finally { if (!controller.signal.aborted) setLoading(false); }
    })();
    return () => controller.abort();
  }, [attempt, invalidateSession, navigate]);
  const record = data?.records.find(r => String(r.year) === year);
  return <main className="dividend-page container">
    <div className="dividend-actions"><Link to="/">กลับหน้าแรก</Link>{data && <div><button className="btn btn-outline" onClick={() => setPasswordOpen(true)}>เปลี่ยนรหัสผ่าน</button> <button className="btn btn-outline" onClick={async () => { await logout(); window.location.assign('/member/login'); }}>ออกจากระบบ</button></div>}</div>
    <h1>เงินปันผลและเงินเฉลี่ยคืน</h1>
    <p>ตรวจสอบรายการรายรับ รายหัก และยอดสุทธิประจำปีของคุณ</p>
    {privacy && !privacy.acknowledged && <section className="dividend-details" aria-labelledby="pdpa-notice-heading">
      <div className="member-admin-card">
        <h2 id="pdpa-notice-heading">รับทราบประกาศความเป็นส่วนตัว (PDPA)</h2>
        <p>ก่อนเข้าดูข้อมูลปันผลส่วนบุคคล กรุณาอ่านประกาศของสหกรณ์ฉบับวันที่ {privacy.notice_version} ซึ่งอธิบายการใช้ข้อมูล วัตถุประสงค์ ฐานกฎหมาย ระยะเวลาเก็บรักษา และสิทธิของสมาชิก</p>
        <p><a href={privacy.notice_url} target="_blank" rel="noopener noreferrer">อ่านประกาศความเป็นส่วนตัวฉบับเต็ม</a></p>
        <label><input type="checkbox" checked={noticeRead} disabled={privacyBusy}
          onChange={e => setNoticeRead(e.target.checked)} /> ฉันได้อ่านและรับทราบประกาศความเป็นส่วนตัวฉบับนี้แล้ว</label>
        <p>การรับทราบนี้ไม่ใช่การให้ความยินยอมสำหรับวัตถุประสงค์ที่ไม่จำเป็นต่อบริการ</p>
        <button className="btn btn-primary" type="button" disabled={!noticeRead || privacyBusy}
          onClick={() => savePrivacy('notice_acknowledgement','accepted')}>บันทึกการรับทราบและดูปันผลของฉัน</button>
      </div>
    </section>}
    {privacy?.acknowledged && <section className="dividend-note">
      <h2>การตั้งค่าความเป็นส่วนตัว</h2>
      <p>รับทราบประกาศฉบับ {privacy.notice_version} แล้ว · <a href={privacy.notice_url} target="_blank" rel="noopener noreferrer">อ่านประกาศ</a></p>
      <label><input type="checkbox" checked={optionalConsent} onChange={e => setOptionalConsent(e.target.checked)} />
      ฉันสมัครใจรับข่าวสารและกิจกรรมเพิ่มเติมจากสหกรณ์ (ไม่จำเป็นต่อการดูข้อมูลปันผล)</label>
      <p>สถานะความยินยอมเพิ่มเติม: {({accepted:'ยินยอม',declined:'ไม่ยินยอม',withdrawn:'ถอนแล้ว',not_selected:'ยังไม่เลือก'})[privacy.optional_choice] || 'ยังไม่เลือก'}</p>
      <button type="button" className="btn btn-outline" disabled={privacyBusy}
        onClick={() => savePrivacy('optional_consent',optionalConsent ? 'accepted' : 'declined')}>บันทึกตัวเลือก</button>{' '}
      <button type="button" className="btn btn-outline" disabled={privacyBusy || privacy.optional_choice !== 'accepted'}
        onClick={() => {setOptionalConsent(false); savePrivacy('optional_consent','withdrawn');}}>ถอนความยินยอม</button>
    </section>}
    {privacyMessage && <p role="status">{privacyMessage}</p>}
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
