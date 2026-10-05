import React, { useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { LayoutDashboard, Users, Upload, Activity, KeyRound, LogOut, ShieldCheck, ArrowLeft, Sun, Moon } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { useTheme } from '../../context/ThemeContext';
import ChangePasswordModal from '../common/ChangePasswordModal';
import '../../pages/AdminMembers.css';

const menu = [
  ['/admin/members/dashboard', 'ภาพรวมสมาชิก', LayoutDashboard],
  ['/admin/members', 'ค้นหา / ดูแลสมาชิก', Users],
  ['/admin/dividends/import', 'นำเข้าปันผลรายปี', Upload],
  ['/admin/members/health', 'สถานะระบบ', Activity],
];
export default function AdminMemberShell({ title, description, children }) {
  const { user, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const [passwordOpen, setPasswordOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  async function signOut() {
    setLoggingOut(true);
    try { await logout(); navigate('/admin/login', { replace: true }); }
    finally { setLoggingOut(false); }
  }
  return <div className="member-admin-layout">
    <aside className="member-admin-sidebar" aria-label="เมนูระบบสมาชิก">
      <Link to="/admin/members/dashboard" className="member-admin-brand"><ShieldCheck size={32} /><span>ระบบสมาชิก<strong>สหกรณ์ สธ.ระยอง</strong></span></Link>
      <div className="member-admin-identity"><strong>{user?.name || 'ผู้ดูแลระบบสมาชิก'}</strong><span>{user?.role === 'super_admin' ? 'ผู้ดูแลระบบหลัก' : 'ผู้ดูแลสมาชิกและปันผล'}</span></div>
      <nav>{menu.map(([path, label, Icon]) => <NavLink key={path} end to={path}><Icon size={19} /><span>{label}</span></NavLink>)}</nav>
      <div className="member-admin-sidebar-footer">
        <button onClick={toggleTheme}>{theme === 'light' ? <Moon size={18} /> : <Sun size={18} />}{theme === 'light' ? 'โหมดมืด' : 'โหมดสว่าง'}</button>
        <button onClick={() => setPasswordOpen(true)}><KeyRound size={18} />เปลี่ยนรหัสผ่านของฉัน</button>
        <button onClick={signOut} disabled={loggingOut}><LogOut size={18} />{loggingOut ? 'กำลังออกจากระบบ...' : 'ออกจากระบบ'}</button>
        <Link to={user?.role === 'super_admin' ? '/admin/dashboard' : '/'}><ArrowLeft size={18} />{user?.role === 'super_admin' ? 'กลับหน้าผู้ดูแลหลัก' : 'กลับเว็บไซต์สหกรณ์'}</Link>
      </div>
    </aside>
    <section className="member-admin" aria-label={title}><header className="member-admin-heading"><span>ระบบสมาชิกสหกรณ์</span><h1>{title}</h1><p>{description}</p></header>{children}</section>
    <ChangePasswordModal isOpen={passwordOpen} onClose={() => setPasswordOpen(false)} />
  </div>;
}
