import React from 'react';
import { Link } from 'react-router-dom';
export default function MemberAdminWarnings({ warnings = [], error }) {
  const issues = error ? [{ message: error.message || String(error), reference: error.reference, missing: error.missing, code: error.code }] : warnings;
  if (!issues.length) return null;
  return <div className="member-admin-message member-admin-warning" role="alert"><strong>{error ? 'ยังโหลดข้อมูลส่วนนี้ไม่สำเร็จ' : 'แสดงข้อมูลได้บางส่วน'}</strong><ul>{issues.map((issue, index) => <li key={index}>{issue.message}{issue.reference && <small> รหัสอ้างอิง: {issue.reference}</small>}{issue.missing?.length > 0 && <p>ส่วนที่ยังไม่พร้อม: {issue.missing.join(', ')}</p>}</li>)}</ul><Link to="/admin/members/health">ตรวจสถานะระบบ</Link></div>;
}
