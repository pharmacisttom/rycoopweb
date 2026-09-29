import React from 'react';
import { useAuth } from '../../context/AuthContext';

const STATUS_CONFIG = {
  online: { label: 'ออนไลน์', className: 'is-online' },
  checking: { label: 'กำลังตรวจสอบ', className: 'is-checking' },
  offline: { label: 'ออฟไลน์', className: 'is-offline' },
  expired: { label: 'เซสชันหมดอายุ', className: 'is-expired' },
  guest: { label: 'ยังไม่ได้เข้าสู่ระบบ', className: 'is-guest' },
};

export default function UserStatusBadge({ compact = false, className = '' }) {
  const { sessionStatus } = useAuth();
  const status = STATUS_CONFIG[sessionStatus] || STATUS_CONFIG.checking;

  return (
    <span
      className={`user-status-badge ${status.className} ${compact ? 'is-compact' : ''} ${className}`.trim()}
      role="status"
      aria-live="polite"
      title={`สถานะการใช้งาน: ${status.label}`}
    >
      <span className="user-status-dot" aria-hidden="true" />
      {!compact && <span>{status.label}</span>}
      <span className="sr-only">สถานะการใช้งาน: {status.label}</span>
    </span>
  );
}
