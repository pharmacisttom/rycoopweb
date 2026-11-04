import React from 'react';

const statusMap = {
  active: { label: 'ใช้งาน', className: 'status-badge-active' },
  inactive: { label: 'ไม่ใช้งาน', className: 'status-badge-inactive' },
  suspended: { label: 'ระงับ', className: 'status-badge-suspended' },
  pending: { label: 'รอตรวจสอบ', className: 'status-badge-pending' },
  approved: { label: 'อนุมัติ', className: 'status-badge-approved' },
  rejected: { label: 'ไม่อนุมัติ', className: 'status-badge-rejected' },
  published: { label: 'เผยแพร่แล้ว', className: 'status-badge-published' },
  draft: { label: 'ฉบับร่าง', className: 'status-badge-draft' },
  submitted: { label: 'ส่งตรวจ', className: 'status-badge-submitted' },
  expired: { label: 'หมดอายุ', className: 'status-badge-expired' },
  archived: { label: 'จัดเก็บ', className: 'status-badge-archived' },
};

export default function StatusBadge({ status, children, size = 'md' }) {
  const normalized = (status || '').toLowerCase();
  const config = statusMap[normalized] || {
    label: children || status || '—',
    className: 'status-badge-neutral',
  };

  return (
    <span
      className={`admin-status-badge ${config.className} ${size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-xs px-2.5 py-1'}`}
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: '4px',
        borderRadius: '9999px',
        fontWeight: 600,
        lineHeight: 1.2,
        whiteSpace: 'nowrap',
      }}
    >
      <span
        style={{
          width: '6px',
          height: '6px',
          borderRadius: '50%',
          backgroundColor: 'currentColor',
          display: 'inline-block',
        }}
        aria-hidden="true"
      />
      {children || config.label}
    </span>
  );
}
