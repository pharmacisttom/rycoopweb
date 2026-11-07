import React from 'react';

export default function LoadingSkeleton({
  lines = 4,
  height = 20,
  variant = 'table', // 'table' | 'card' | 'form'
}) {
  if (variant === 'table') {
    return (
      <div
        className="admin-skeleton-table"
        role="status"
        aria-label="กำลังโหลดข้อมูลตาราง..."
        style={{
          width: '100%',
          padding: '16px',
          background: 'var(--acc-surface, #ffffff)',
          borderRadius: '12px',
          border: '1px solid var(--acc-border, #e2e8f0)',
        }}
      >
        <div
          style={{
            height: '36px',
            background: 'var(--acc-surface-elevated, #f1f5f9)',
            borderRadius: '6px',
            marginBottom: '16px',
            animation: 'pulse 1.5s infinite',
          }}
        />
        {Array.from({ length: lines }).map((_, i) => (
          <div
            key={i}
            style={{
              display: 'flex',
              gap: '12px',
              padding: '12px 0',
              borderBottom: '1px solid var(--acc-border, #f1f5f9)',
            }}
          >
            <div
              style={{
                width: '20%',
                height: `${height}px`,
                background: 'var(--acc-surface-elevated, #f1f5f9)',
                borderRadius: '4px',
                animation: 'pulse 1.5s infinite',
              }}
            />
            <div
              style={{
                width: '45%',
                height: `${height}px`,
                background: 'var(--acc-surface-elevated, #f1f5f9)',
                borderRadius: '4px',
                animation: 'pulse 1.5s infinite',
              }}
            />
            <div
              style={{
                width: '15%',
                height: `${height}px`,
                background: 'var(--acc-surface-elevated, #f1f5f9)',
                borderRadius: '4px',
                animation: 'pulse 1.5s infinite',
              }}
            />
            <div
              style={{
                width: '20%',
                height: `${height}px`,
                background: 'var(--acc-surface-elevated, #f1f5f9)',
                borderRadius: '4px',
                animation: 'pulse 1.5s infinite',
              }}
            />
          </div>
        ))}
      </div>
    );
  }

  return (
    <div
      className="admin-skeleton-box"
      role="status"
      aria-label="กำลังโหลดข้อมูล..."
      style={{
        display: 'flex',
        flexDirection: 'column',
        gap: '12px',
        padding: '24px',
        background: 'var(--acc-surface, #ffffff)',
        borderRadius: '12px',
        border: '1px solid var(--acc-border, #e2e8f0)',
      }}
    >
      {Array.from({ length: lines }).map((_, i) => (
        <div
          key={i}
          style={{
            height: `${height}px`,
            width: i === 0 ? '40%' : i === lines - 1 ? '60%' : '100%',
            background: 'var(--acc-surface-elevated, #f1f5f9)',
            borderRadius: '6px',
            animation: 'pulse 1.5s infinite',
          }}
        />
      ))}
    </div>
  );
}
