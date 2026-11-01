import { maskIdCard, maskName } from '../utils/pdpa';
import React, { useState } from 'react';
import AdminMemberShell from '../components/member/AdminMemberShell';

export default function AdminDividendImportPage() {
  const [file, setFile] = useState(null);
  const [year, setYear] = useState('2568');
  const [preview, setPreview] = useState(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [errors, setErrors] = useState([]);
  const [errorCount, setErrorCount] = useState(0);
  const [updateProfiles, setUpdateProfiles] = useState(false);
  const [acknowledged, setAcknowledged] = useState(false);
  async function submit(confirm) {
    if (confirm && !acknowledged) { setMessage('กรุณายืนยันว่าตรวจสอบจำนวนรายการและยอดเงินก่อนนำเข้า'); return; }
    setBusy(true); setMessage(''); setErrors([]); setErrorCount(0);
    try {
      const csrf = await fetch('/csrf-token', { credentials: 'include', headers: { Accept: 'application/json' } });
      if (!csrf.ok) throw new Error('เริ่มเซสชันไม่สำเร็จ กรุณาเข้าสู่ระบบใหม่');
      const { token } = await csrf.json();
      const body = new FormData(); body.append('_csrf_token', token);
      if (confirm) body.append('token', preview.token);
      else { body.append('file', file); body.append('year', year); body.append('update_profiles', updateProfiles ? '1' : '0'); setPreview(null); setAcknowledged(false); }
      const response = await fetch(`/api/admin/dividends/${confirm ? 'confirm' : 'preview'}`, { method: 'POST', credentials: 'include', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body });
      const result = await response.json();
      if (!response.ok || !result.success) { setErrors(result.errors || []); setErrorCount(result.error_count || 0); if (confirm) setPreview(null); throw new Error(result.message || 'ดำเนินการไม่สำเร็จ'); }
      if (confirm) { setPreview(null); setAcknowledged(false); setMessage(`นำเข้าสำเร็จ ${result.count.toLocaleString('th-TH')} รายการ`); }
      else setPreview(result);
    } catch (e) { setMessage(e.message); }
    finally { setBusy(false); }
  }
  return <AdminMemberShell title="นำเข้าเงินปันผลรายปี" description="ตรวจข้อมูลจาก Excel เทียบฐานข้อมูล ก่อนยืนยันบันทึก">
    <p>นำเข้าเป็นรายปีด้วยไฟล์ XLSX หรือ CSV UTF-8 ขนาดไม่เกิน 5 MB ระบบจับคู่ด้วยชื่อหัวคอลัมน์ ตรวจเลขสมาชิก เลขบัตรประชาชนและยอดเงินก่อนบันทึก ไม่รองรับไฟล์ XLS แบบเก่า (ให้บันทึกใหม่เป็น XLSX)</p>
    <p role="note"><strong>ความปลอดภัย:</strong> ไฟล์ปันผลรายปีจะไม่สร้างสมาชิกหรือบัญชีใหม่ ต้องมีทะเบียนสมาชิกและบัญชีที่ผ่าน Identity Mapping อยู่แล้ว ระบบไม่เปลี่ยนรหัสผ่านหรือสถานะ Login และไม่เปิด Member Portal</p>
    <p><a href="/templates/member-dividends-template.xlsx" download>ดาวน์โหลดแม่แบบ Excel</a> · <a href="/templates/member-dividends-template.csv" download>ดาวน์โหลดแม่แบบ CSV</a></p>
    <p>เก็บเลขบัตรประชาชนและเลขทะเบียนสมาชิกเป็นข้อความเพื่อรักษาเลขศูนย์นำหน้า แม่แบบมีตัวอย่างสมมติหนึ่งแถว ให้แทนที่ด้วยข้อมูลจริง</p>
    <form onSubmit={e => { e.preventDefault(); submit(false); }}>
      <label htmlFor="dividend-year">ปีบัญชี พ.ศ.</label><input id="dividend-year" className="form-control" type="number" min="2400" max="2800" required value={year} disabled={busy} onChange={e => { setYear(e.target.value); setPreview(null); setAcknowledged(false); }} />
      <label htmlFor="dividend-file">ไฟล์ข้อมูล</label><input id="dividend-file" className="form-control" type="file" accept=".xlsx,.csv" required disabled={busy} onChange={e => { setFile(e.target.files[0] || null); setPreview(null); setAcknowledged(false); }} />
      <label><input type="checkbox" checked={updateProfiles} disabled={busy} onChange={e => { setUpdateProfiles(e.target.checked); setPreview(null); setAcknowledged(false); }} />อัปเดตชื่อและสังกัดของสมาชิกเดิมจากไฟล์นี้</label>
      <p>หากไม่เลือก ข้อมูลส่วนตัวที่แอดมินแก้ไว้จะคงเดิม ปีที่ไม่อยู่ในไฟล์จะคงอยู่ และรหัสผ่านเดิมจะไม่เปลี่ยน</p>
      <button className="btn btn-primary" style={{ marginTop: 20 }} disabled={busy || !file}>{busy ? 'กำลังดำเนินการ...' : 'ตรวจสอบไฟล์'}</button>
    </form>
    {message && <p role="status">{message}</p>}
    {errorCount > 0 && <p role="alert">พบ {errorCount.toLocaleString('th-TH')} รายการที่ต้องแก้ไข{errorCount > errors.length ? ` (แสดง ${errors.length} รายการแรก)` : ''}</p>}
    {!!errors.length && <div className="member-admin-scroll"><table style={{ marginTop: 20 }}><thead><tr><th scope="col">แถว</th><th scope="col">เลขสมาชิก</th><th scope="col">เลขบัตรประชาชน</th><th scope="col">รายการที่ต้องแก้ไข</th></tr></thead><tbody>{errors.map((e, i) => <tr key={i}><td>{e.row}</td><td>{e.member_no || '—'}</td><td>{e.id_card ? maskIdCard(e.id_card) : '—'}</td><td>{e.message}</td></tr>)}</tbody></table></div>}
    {preview && <section className="member-admin-card"><h2>ตรวจสอบผ่าน ปี {preview.year}</h2><p>{preview.count.toLocaleString('th-TH')} รายการ · ยอดสุทธิรวม {Number(preview.total_net).toLocaleString('th-TH', { minimumFractionDigits: 2 })} บาท</p><div className="member-admin-preview"><p>รายการใหม่ <strong>{preview.counts.created}</strong></p><p>อัปเดตยอด <strong>{preview.counts.updated}</strong></p><p>ยอดเดิม <strong>{preview.counts.unchanged}</strong></p><p>สมาชิกใหม่ <strong>{preview.counts.new_members}</strong></p></div><p>ต้องมีทะเบียนสมาชิกหลักพร้อมบัญชีที่เชื่อมโยงครบก่อนนำเข้าปันผล · ไม่สร้างสมาชิกใหม่จากไฟล์นี้</p><p>อัปเดตชื่อ / สังกัดของสมาชิกเดิม {preview.counts.profile_updates} รายการ</p><h3>ตัวอย่าง 20 รายการแรก</h3><div className="member-admin-scroll"><table><thead><tr><th scope="col">เลขสมาชิก</th><th scope="col">ชื่อ</th><th scope="col">ยอดเดิม</th><th scope="col">ยอดใหม่</th><th scope="col">ผลนำเข้า</th></tr></thead><tbody>{preview.sample.map(r => <tr key={`${r.year}-${r.member_no}`}><td>{r.member_no}</td><td>{maskName(r.name)}</td><td>{r.previous_net ?? '—'}</td><td>{r.net}</td><td>{{created:'เพิ่มใหม่',updated:'อัปเดต',unchanged:'ยอดเดิม'}[r.action]}</td></tr>)}</tbody></table></div><p>ผลตรวจสอบมีอายุ 30 นาที หากมีผู้แก้ข้อมูลระหว่างนี้ ระบบจะให้ตรวจสอบใหม่ก่อนนำเข้า</p><label><input type="checkbox" checked={acknowledged} onChange={e => setAcknowledged(e.target.checked)} disabled={busy} /> ฉันตรวจสอบปี {preview.year} จำนวน {preview.count.toLocaleString('th-TH')} รายการ และยอดสุทธิรวมแล้ว และยืนยันว่ามีสำเนาฐานข้อมูล TEST ก่อนนำเข้าจริง</label><button className="btn btn-primary" disabled={busy || !acknowledged} onClick={() => submit(true)}>ยืนยันนำเข้าปี {preview.year}</button></section>}
  </AdminMemberShell>;
}
