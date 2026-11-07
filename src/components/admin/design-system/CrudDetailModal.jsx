import React, { useEffect, useRef } from 'react';
import { X } from 'lucide-react';

export default function CrudDetailModal({
  open = false,
  title = 'รายละเอียดข้อมูล',
  subtitle = null,
  badge = null,
  onClose,
  children,
  actions = null,
  maxWidth = '760px',
}) {
  const dialogRef = useRef(null);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && open) {
        onClose();
      }
    };
    if (open) {
      window.addEventListener('keydown', handleKeyDown);
      document.body.style.overflow = 'hidden';
    }
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      className="admin-modal-backdrop"
      style={{
        position: 'fixed',
        inset: 0,
        backgroundColor: 'rgba(15, 23, 42, 0.65)',
        backdropFilter: 'blur(3px)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 9999,
        padding: '16px',
        animation: 'fadeIn 0.15s ease-out',
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      <div
        ref={dialogRef}
        className="admin-detail-modal"
        style={{
          width: '100%',
          maxWidth,
          maxHeight: '90vh',
          display: 'flex',
          flexDirection: 'column',
          backgroundColor: 'var(--acc-surface, #ffffff)',
          color: 'var(--acc-text, #0f172a)',
          borderRadius: '16px',
          boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)',
          border: '1px solid var(--acc-border, #e2e8f0)',
          overflow: 'hidden',
          animation: 'slideUp 0.2s ease-out',
        }}
      >
        {/* Header */}
        <header
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '20px 24px',
            borderBottom: '1px solid var(--acc-border, #e2e8f0)',
            backgroundColor: 'var(--acc-surface-elevated, #f8fafc)',
          }}
        >
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <h2 style={{ margin: 0, fontSize: '1.2rem', fontWeight: 700 }}>{title}</h2>
              {badge}
            </div>
            {subtitle && (
              <p style={{ margin: '4px 0 0 0', fontSize: '0.85rem', color: 'var(--acc-text-secondary, #64748b)' }}>
                {subtitle}
              </p>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            style={{
              background: 'none',
              border: 'none',
              padding: '6px',
              borderRadius: '8px',
              cursor: 'pointer',
              color: 'var(--acc-text-secondary, #64748b)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
            aria-label="ปิดหน้าต่าง"
          >
            <X size={20} />
          </button>
        </header>

        {/* Content Body */}
        <div
          style={{
            padding: '24px',
            overflowY: 'auto',
            flex: '1 1 auto',
          }}
        >
          {children}
        </div>

        {/* Footer Actions */}
        {actions && (
          <footer
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'flex-end',
              gap: '12px',
              padding: '16px 24px',
              borderTop: '1px solid var(--acc-border, #e2e8f0)',
              backgroundColor: 'var(--acc-surface-elevated, #f8fafc)',
            }}
          >
            {actions}
          </footer>
        )}
      </div>
    </div>
  );
}
