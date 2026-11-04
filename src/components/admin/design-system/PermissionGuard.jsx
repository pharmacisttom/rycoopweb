import React from 'react';
import { useAuth } from '../../../context/AuthContext';
import { ShieldAlert } from 'lucide-react';

export default function PermissionGuard({
  permission = null,
  anyPermissions = null,
  allPermissions = null,
  role = null,
  roles = null,
  children,
  fallback = null,
  showNotice = false,
}) {
  const { user, can } = useAuth();

  if (!user) {
    return fallback;
  }

  // Super Admin always has full access
  if (user.role === 'super_admin' || user.role_slug === 'super_admin') {
    return children;
  }

  // Role check
  if (role && user.role !== role && user.role_slug !== role) {
    return showNotice ? <PermissionNotice /> : fallback;
  }
  if (roles && !roles.includes(user.role) && !roles.includes(user.role_slug)) {
    return showNotice ? <PermissionNotice /> : fallback;
  }

  // Permission checks
  if (permission && !can(permission)) {
    return showNotice ? <PermissionNotice /> : fallback;
  }
  if (anyPermissions && !anyPermissions.some((p) => can(p))) {
    return showNotice ? <PermissionNotice /> : fallback;
  }
  if (allPermissions && !allPermissions.every((p) => can(p))) {
    return showNotice ? <PermissionNotice /> : fallback;
  }

  return children;
}

function PermissionNotice() {
  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        padding: '16px',
        borderRadius: '10px',
        backgroundColor: '#fff1f2',
        border: '1px solid #fecdd3',
        color: '#9f1239',
        margin: '16px 0',
      }}
      role="alert"
    >
      <ShieldAlert size={20} aria-hidden="true" style={{ flexShrink: 0 }} />
      <div>
        <strong style={{ display: 'block', marginBottom: '2px' }}>ไม่มีสิทธิ์เข้าถึงส่วนนี้</strong>
        <span style={{ fontSize: '0.85rem' }}>
          บัญชีของคุณไม่มีสิทธิ์ที่จำเป็นในการดำเนินการนี้ กรุณาติดต่อผู้ดูแลระบบสูงสุด
        </span>
      </div>
    </div>
  );
}
