import React, { useEffect, useRef } from 'react';
import { X, Save, AlertTriangle } from 'lucide-react';
import alertService from '../../../services/alertService';
import FormValidationSummary from './FormValidationSummary';

export default function CrudFormDrawer({
  open = false,
  title = 'แก้ไขข้อมูล',
  subtitle = null,
  isEdit = false,
  dirty = false,
  busy = false,
  errors = {},
  onClose,
  onSubmit,
  children,
  submitLabel = 'บันทึกข้อมูล',
  cancelLabel = 'ยกเลิก',
  maxWidth = '720px',
}) {
  const drawerRef = useRef(null);

  const handleClose = async () => {
    if (dirty) {
      const confirmDiscard = await alertService.confirmUnsavedChanges();
      if (!confirmDiscard) return;
    }
    onClose();
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && open && !busy) {
        handleClose();
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
  }, [open, dirty, busy]);

  if (!open) return null;

  return (
    <div
      className="admin-drawer-backdrop"
      style={{
        position: 'fixed',
        inset: 0,
        backgroundColor: 'rgba(15, 23, 42, 0.65)',
        backdropFilter: 'blur(3px)',
        display: 'flex',
        justifyContent: 'flex-end',
        zIndex: 9999,
        animation: 'fadeIn 0.2s ease-out',
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) {
          handleClose();
        }
      }}
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      <div
        ref={drawerRef}
        className="admin-form-drawer"
        style={{
          width: '100%',
          maxWidth,
          height: '100%',
          backgroundColor: 'var(--acc-surface, #ffffff)',
          color: 'var(--acc-text, #0f172a)',
          display: 'flex',
          flexDirection: 'column',
          boxShadow: '-8px 0 25px rgba(0, 0, 0, 0.15)',
          animation: 'slideInRight 0.25s ease-out',
          overflow: 'hidden',
        }}
      >
        {/* Drawer Header */}
        <header
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '20px 24px',
            borderBottom: '1px solid var(--acc-border, #e2e8f0)',
            backgroundColor: 'var(--acc-surface-elevated, #f8fafc)',
            flexShrink: 0,
          }}
        >
          <div>
            <h2 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 700 }}>{title}</h2>
            {subtitle && (
              <p style={{ margin: '4px 0 0 0', fontSize: '0.85rem', color: 'var(--acc-text-secondary, #64748b)' }}>
                {subtitle}
              </p>
            )}
          </div>
          <button
            type="button"
            onClick={handleClose}
            disabled={busy}
            style={{
              background: 'none',
              border: 'none',
              padding: '8px',
              borderRadius: '8px',
              cursor: busy ? 'not-allowed' : 'pointer',
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

        {/* Form Body */}
        <form
          onSubmit={onSubmit}
          style={{
            display: 'flex',
            flexDirection: 'column',
            flex: '1 1 auto',
            overflow: 'hidden',
          }}
        >
          <div
            style={{
              padding: '24px',
              overflowY: 'auto',
              flex: '1 1 auto',
            }}
          >
            <FormValidationSummary errors={errors} />
            <fieldset disabled={busy} style={{ border: 'none', padding: 0, margin: 0 }}>
              {children}
            </fieldset>
          </div>

          {/* Drawer Footer Actions */}
          <footer
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              padding: '16px 24px',
              borderTop: '1px solid var(--acc-border, #e2e8f0)',
              backgroundColor: 'var(--acc-surface-elevated, #f8fafc)',
              flexShrink: 0,
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              {dirty && (
                <span
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '4px',
                    fontSize: '0.8rem',
                    color: '#d97706',
                  }}
                >
                  <AlertTriangle size={14} /> มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก
                </span>
              )}
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
              <button
                type="button"
                className="acc-btn"
                onClick={handleClose}
                disabled={busy}
              >
                {cancelLabel}
              </button>
              <button
                type="submit"
                className="acc-btn primary"
                disabled={busy}
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '8px',
                }}
              >
                <Save size={16} />
                {busy ? 'กำลังบันทึก...' : submitLabel}
              </button>
            </div>
          </footer>
        </form>
      </div>
    </div>
  );
}
