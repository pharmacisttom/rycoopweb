import { AdminPageHeader, AdminErrorState } from '../components/admin/AdminUI';
import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import ChangePasswordModal from '../components/common/ChangePasswordModal';

const destinations = [
  ['coop_info.view', '/admin/public-content', 'ข้อมูลสหกรณ์'],
  ['people.view', '/admin/public-content', 'กรรมการและเจ้าหน้าที่'],
  ['rates.view', '/admin/public-content', 'อัตราดอกเบี้ย'],
  ['products.view', '/admin/public-content', 'ผลิตภัณฑ์'],
  ['members.view', '/admin/members/dashboard', 'ดูแลสมาชิกและปันผล'],
  ['dividends.import', '/admin/dividends/import', 'นำเข้าปันผลรายปี'],
  ['news.view', '/admin/content?tab=news', 'จัดการข่าวประชาสัมพันธ์'],
  ['faqs.view', '/admin/content?tab=faqs', 'จัดการคำถามที่พบบ่อย'],
];

export default function AdminWorkspacePage() {
  const { user, can, logout } = useAuth();
  const [passwordOpen, setPasswordOpen] = React.useState(false);
  const [error, setError] = React.useState('');
  const links = destinations.filter(([permission]) => can(permission));
  return <section className="admin-module">
    <AdminPageHeader title="พื้นที่ทำงานเจ้าหน้าที่" description={`${user?.name||''} · ${user?.role_name||user?.role||''}`}/>
    <div className="grid-3" style={{ margin: '1.5rem 0' }}>
      {links.map(([permission, path, label]) => <Link className="surface-card" style={{ padding: '1.5rem' }} key={permission} to={path}>{label}</Link>)}
      {user?.role === 'super_admin' && <Link className="btn btn-outline" to="/admin/access">จัดการผู้ใช้งานและสิทธิ์</Link>}
    </div>
    {!links.length && user?.role !== 'super_admin' && <p role="status">ยังไม่มีสิทธิ์ในโมดูลเหล่านี้ กรุณาติดต่อผู้ดูแลระบบ</p>}
    {error && <AdminErrorState message={error}/>}
    <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
      <Link className="btn btn-primary" to="/admin/dashboard">ศูนย์ควบคุมผู้ดูแลระบบ 3.0</Link>
      <button className="btn btn-outline" onClick={() => setPasswordOpen(true)}>เปลี่ยนรหัสผ่านของฉัน</button>
      <button className="btn btn-outline" onClick={async () => { try { await logout(); } catch { setError('ออกจากระบบไม่สำเร็จ กรุณาลองใหม่'); } }}>ออกจากระบบ</button>
      <Link className="btn btn-outline" to="/">กลับเว็บไซต์สหกรณ์</Link>
    </div>
    <ChangePasswordModal isOpen={passwordOpen} onClose={() => setPasswordOpen(false)} />
  </section>;
}
