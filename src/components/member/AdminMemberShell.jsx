import React from 'react';
import AdminAppShell from '../admin/AdminAppShell';
import '../../pages/AdminMembers.css';

export default function AdminMemberShell({
  title,
  description,
  subtitle,
  breadcrumbs = [],
  actions = null,
  children
}) {
  return (
    <AdminAppShell
      title={title}
      subtitle={description || subtitle}
      breadcrumbs={breadcrumbs}
      actions={actions}
    >
      <section className="member-admin">
        {children}
      </section>
    </AdminAppShell>
  );
}
