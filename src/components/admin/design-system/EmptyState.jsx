import React from 'react';
import { Inbox, Plus } from 'lucide-react';

export default function EmptyState({
  title = 'ไม่พบข้อมูล',
  description = 'ยังไม่มีข้อมูลในหมวดนี้ หรือไม่พบข้อมูลตามคำค้นหาที่ระบุ',
  actionLabel = null,
  onAction = null,
  icon: Icon = Inbox,
}) {
  return (
    <div
      className="admin-empty-state"
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '48px 24px',
        textAlign: 'center',
        borderRadius: '12px',
        border: '1px dashed var(--acc-border, #cbd5e1)',
        backgroundColor: 'var(--acc-surface-elevated, #f8fafc)',
        margin: '16px 0',
      }}
      role="status"
    >
      <div
        style={{
          width: '56px',
          height: '56px',
          borderRadius: '50%',
          backgroundColor: '#e0f2fe',
          color: '#0284c7',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          marginBottom: '16px',
        }}
        aria-hidden="true"
      >
        <Icon size={28} />
      </div>
      <h3
        style={{
          margin: '0 0 8px 0',
          fontSize: '1.1rem',
          fontWeight: 600,
          color: 'var(--acc-text, #0f172a)',
        }}
      >
        {title}
      </h3>
      <p
        style={{
          margin: 0,
          maxWidth: '420px',
          fontSize: '0.9rem',
          color: 'var(--acc-text-secondary, #64748b)',
          lineHeight: 1.6,
        }}
      >
        {description}
      </p>
      {actionLabel && onAction && (
        <button
          type="button"
          onClick={onAction}
          className="acc-btn primary"
          style={{
            marginTop: '20px',
            display: 'inline-flex',
            alignItems: 'center',
            gap: '8px',
          }}
        >
          <Plus size={16} />
          {actionLabel}
        </button>
      )}
    </div>
  );
}
