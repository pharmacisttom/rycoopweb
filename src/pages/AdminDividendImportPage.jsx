import React, { useState } from 'react';
import { Link } from 'react-router-dom';

export default function AdminDividendImportPage() {
  const [file, setFile] = useState(null);
  const [year, setYear] = useState('2568');
  const [preview, setPreview] = useState(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [errors, setErrors] = useState([]);
  async function submit(confirm) {
    setBusy(true); setMessage(''); setErrors([]);
    try {
      const csrf = await fetch('/csrf-token', { credentials: 'include', headers: { Accept: 'application/json' } });
      if (!csrf.ok) throw new Error('เริ่มเซสชันไม่สำเร็จ กรุณาเข้าสู่ระบบใหม่');
      const { token } = await csrf.json();
      const body = new FormData(); body.append('_csrf_token', token);
      if (confirm) body.append('token', preview.token);
      else { body.append('file', file); body.append('year', year); setPreview(null); }
      const response = await fetch(`/api/admin/dividends/${confirm ? 'confirm' : 'preview'}`, { method: 'POST', credentials: 'include', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body });
      const result = await response.json();
      if (!response.ok || !result.success) { setErrors(result.errors || []); throw new Error(result.message || 'ดำเนินการไม่สำเร็จ'); }
      if (confirm) { setPreview(null); setMessage(`นำเข้าสำเร็จ ${result.count.toLocaleString('th-TH')} รายการ`); }
      else setPreview(result);
    } catch (e) { setMessage(e.message); }
    finally { setBusy(false); }
  }
  return <main className="container" style={{ maxWidth: 850, padding: '32px 20px', minHeight: '65vh' }}>
    <Link to="/admin/dashboard">กลับหน้าผู้ดูแล</Link><h1>นำเข้าเงินปันผลรายปี</h1>
    <p>ใช้ไฟล์มาตรฐาน XLSX หรือ CSV UTF-8 ขนาดไม่เกิน 5 MB ระบบตรวจยอดรวมและข้อมูลสมาชิกก่อนบันทึก</p>
    <p><a href="/templates/member-dividends-template.xlsx" download>ดาวน์โหลดแม่แบบ Excel</a> · <a href="/templates/member-dividends-template.csv" download>ดาวน์โหลดแม่แบบ CSV</a></p>
    <p>เก็บเลขบัตรประชาชนและเลขทะเบียนสมาชิกเป็นข้อความเพื่อรักษาเลขศูนย์นำหน้า แม่แบบมีตัวอย่างสมมติหนึ่งแถว ให้แทนที่ด้วยข้อมูลจริง</p>
    <form onSubmit={e => { e.preventDefault(); submit(false); }}>
      <label htmlFor="dividend-year">ปีบัญชี พ.ศ.</label><input id="dividend-year" className="form-control" type="number" min="2400" max="2800" required value={year} disabled={busy} onChange={e => { setYear(e.target.value); setPreview(null); }} />
      <label htmlFor="dividend-file">ไฟล์ข้อมูล</label><input id="dividend-file" className="form-control" type="file" accept=".xlsx,.csv" required disabled={busy} onChange={e => { setFile(e.target.files[0] || null); setPreview(null); }} />
      <button className="btn btn-primary" style={{ marginTop: 20 }} disabled={busy || !file}>{busy ? 'กำลังดำเนินการ...' : 'ตรวจสอบไฟล์'}</button>
    </form>
    {message && <p role="status">{message}</p>}
    {!!errors.length && <table style={{ width: '100%', marginTop: 20 }}><thead><tr><th>แถว</th><th>รายการที่ต้องแก้ไข</th></tr></thead><tbody>{errors.map((e, i) => <tr key={i}><td>{e.row}</td><td>{e.message}</td></tr>)}</tbody></table>}
    {preview && <section style={{ padding: 24, marginTop: 24, border: '1px solid #cbd5e1', borderRadius: 12 }}><h2>ตรวจสอบผ่าน ปี {preview.year}</h2><p>{preview.count.toLocaleString('th-TH')} รายการ · ยอดสุทธิรวม {Number(preview.total_net).toLocaleString('th-TH', { minimumFractionDigits: 2 })} บาท</p><p>ข้อมูลสมาชิกและปีที่มีอยู่จะถูกอัปเดตด้วยยอดจากไฟล์นี้ รหัสผ่านเดิมจะคงอยู่ ผลตรวจสอบมีอายุ 30 นาที</p><button className="btn btn-primary" disabled={busy} onClick={() => submit(true)}>ยืนยันนำเข้า</button></section>}
  </main>;
}
