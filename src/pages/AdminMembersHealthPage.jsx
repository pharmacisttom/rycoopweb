import React, { useEffect, useState } from 'react';
import AdminMemberShell from '../components/member/AdminMemberShell';
import MemberAdminWarnings from '../components/member/MemberAdminWarnings';
import { memberAdminApi } from '../services/memberAdminApi';
export default function AdminMembersHealthPage() {
  const [data, setData] = useState(null), [error, setError] = useState(null), [refresh, setRefresh] = useState(0);
  useEffect(() => {
    const controller = new AbortController(); setData(null); setError(null);
    memberAdminApi('/api/admin/members/health', { signal: controller.signal }).then(r => setData(r.health)).catch(e => { if (e.name !== 'AbortError') setError(e); });
    return () => controller.abort();
  }, [refresh]);
  return <AdminMemberShell title="สถานะระบบสมาชิก" description="ตรวจโครงสร้างฐานข้อมูลและส่วนประกอบที่จำเป็นสำหรับระบบสมาชิก">
    <button className="btn btn-outline" onClick={() => setRefresh(n => n + 1)}>ตรวจสอบอีกครั้ง</button><MemberAdminWarnings error={error} />
    {!data && !error && <p role="status">กำลังตรวจสอบ...</p>}
    {data && <><section className="member-admin-card"><h2>{data.ready ? 'ส่วนประกอบพร้อมใช้งาน' : 'ยังมีส่วนประกอบที่ต้องอัปเดต'}</h2><p>ฐานข้อมูล: {data.missing.length ? 'ยังไม่ครบ' : 'ครบตามระบบสมาชิก'} · สิทธิ์ผู้ดูแลสมาชิก: {data.member_admin_role ? 'พร้อม' : 'ยังไม่มี'}</p>
      {data.missing.length > 0 && <><h3>ส่วนที่ขาด</h3><ul>{data.missing.map(name => <li key={name}>{name}</li>)}</ul></>}
      <div className="member-admin-scroll"><table><thead><tr><th>ส่วนประกอบ PHP</th><th>สถานะ</th></tr></thead><tbody>{Object.entries(data.extensions).map(([name, ready]) => <tr key={name}><td>{name}</td><td>{ready ? 'พร้อม' : 'ต้องเปิดใช้งาน'}</td></tr>)}</tbody></table></div>
    </section>{!data.ready && <section className="member-admin-card"><h2>ขั้นตอนสำหรับผู้ดูแลเซิร์ฟเวอร์</h2><p>สำรองฐานข้อมูลก่อน แล้วรันคำสั่งนี้จากโฟลเดอร์เว็บไซต์บน VPS โดยใช้บัญชี deploy</p><pre className="member-admin-command">php bin/console db:migrate{'\n'}php bin/check-member-system.php</pre><p>คำสั่งตรวจสถานะไม่แก้ข้อมูลหรือรหัสผ่าน หากยังพบข้อผิดพลาด ให้ส่งผลตรวจและรหัสอ้างอิงให้ผู้ดูแลระบบ</p></section>}</>}
  </AdminMemberShell>;
}
