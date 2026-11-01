import React, { useState } from 'react';
import { Link } from 'react-router-dom';

export default function MemberActivationPage() {
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [success, setSuccess] = useState(false);
  async function activate(event) {
    event.preventDefault();
    setBusy(true); setMessage('');
    try {
      if (password.length < 12 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password) || password !== confirm) {
        throw new Error('รหัสผ่านต้องมีอย่างน้อย 12 ตัวอักษร ประกอบด้วยตัวอักษรภาษาอังกฤษและตัวเลข และกรอกตรงกัน');
      }
      const csrf = await fetch('/csrf-token', { credentials: 'include', cache: 'no-store',
        headers: { Accept: 'application/json' } });
      if (!csrf.ok) throw new Error('ไม่สามารถเริ่มเซสชันได้');
      const { token } = await csrf.json();
      const body = new FormData();
      body.append('_csrf_token', token);
      body.append('invitation_code', code.trim().toLowerCase());
      body.append('new_password', password);
      body.append('confirm_password', confirm);
      const response = await fetch('/api/member/activation/redeem', { method: 'POST', credentials: 'include', cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'ไม่สามารถเปิดบัญชีได้');
      setSuccess(true); setCode(''); setPassword(''); setConfirm('');
      setMessage('ตั้งรหัสผ่านสำเร็จ โปรดเข้าสู่ระบบสมาชิก แล้วอ่านและรับทราบประกาศ PDPA');
    } catch (error) { setMessage(error.message); }
    finally { setBusy(false); }
  }
  return <main className="section"><div className="container" style={{ maxWidth: 560 }}>
    <section className="surface-card" style={{ padding: '2rem' }}>
      <h1>เปิดใช้งานบัญชีสมาชิกครั้งแรก</h1>
      <p>สำหรับสมาชิกที่ผ่านการยืนยันตัวตนกับเจ้าหน้าที่แล้วเท่านั้น ใช้รหัสเชิญใช้ครั้งเดียวที่ได้รับโดยตรงจากเจ้าหน้าที่ รหัสมีอายุ 20 นาที</p>
      {message && <p role="status">{message}</p>}
      {success ? <Link to="/member/login" className="btn btn-primary">เข้าสู่ระบบสมาชิก</Link> : <form onSubmit={activate}>
        <label htmlFor="invite-code">รหัสเชิญใช้ครั้งเดียว</label>
        <input id="invite-code" className="form-control" value={code} onChange={e => setCode(e.target.value)}
          autoComplete="off" required minLength={64} maxLength={64} pattern="[a-fA-F0-9]{64}" />
        <label htmlFor="member-new-password">ตั้งรหัสผ่านใหม่</label>
        <input id="member-new-password" className="form-control" type="password" autoComplete="new-password"
          value={password} onChange={e => setPassword(e.target.value)} required minLength={12} maxLength={128} />
        <label htmlFor="member-confirm-password">ยืนยันรหัสผ่าน</label>
        <input id="member-confirm-password" className="form-control" type="password" autoComplete="new-password"
          value={confirm} onChange={e => setConfirm(e.target.value)} required minLength={12} maxLength={128} />
        <p>อย่าใช้เลขสมาชิก เลขบัตรประชาชน หรือรหัสผ่านเดียวกับระบบอื่น</p>
        <button className="btn btn-primary" type="submit" disabled={busy}>{busy ? 'กำลังตรวจสอบ...' : 'ยืนยันเปิดใช้งานบัญชี'}</button>
      </form>}
      <p style={{ marginTop: '1rem' }}><Link to="/privacy/policy">อ่านประกาศความเป็นส่วนตัว (PDPA)</Link></p>
      <p><Link to="/contact">มีปัญหาในการเปิดใช้งาน? ติดต่อสหกรณ์</Link></p>
    </section>
  </div></main>;
}
