import React, { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import ChangePasswordModal from '../common/ChangePasswordModal';
import '../../pages/AdminMembers.css';
export default function AdminMemberShell({ title, description, children }) {
  const { user } = useAuth();
  const [passwordOpen, setPasswordOpen] = useState(false);
  return <main className="member-admin container"><div className="member-admin-actions">{user?.role === 'super_admin' && <Link to="/admin/dashboard" className="member-admin-back">กลับหน้าผู้ดูแล</Link>}<button className="btn btn-outline" onClick={() => setPasswordOpen(true)}>เปลี่ยนรหัสผ่านของฉัน</button></div><div className="member-admin-heading"><div><span>ระบบสมาชิกสหกรณ์</span><h1>{title}</h1><p>{description}</p></div></div><nav className="member-admin-tabs" aria-label="จัดการสมาชิก"><NavLink to="/admin/members/dashboard">ภาพรวม</NavLink><NavLink end to="/admin/members">สมาชิก</NavLink><NavLink to="/admin/dividends/import">นำเข้าปันผล</NavLink></nav>{children}<ChangePasswordModal isOpen={passwordOpen} onClose={() => setPasswordOpen(false)} /></main>;
}
