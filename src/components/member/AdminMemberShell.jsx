import React from 'react';
import AdminAppShell from '../admin/AdminAppShell';
import '../../pages/AdminMembers.css';
export default function AdminMemberShell({title,description,children}) {
  return <AdminAppShell title={title} subtitle={description}><section className="member-admin">{children}</section></AdminAppShell>;
}
