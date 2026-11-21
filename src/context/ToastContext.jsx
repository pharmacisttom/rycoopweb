import React, { createContext, useContext, useState, useCallback, useEffect, useRef } from 'react';
import { CheckCircle2, AlertTriangle, AlertCircle, Info, X, ShieldAlert } from 'lucide-react';
import { showConfirm, showDeleteConfirm, showToast as swalToast } from '../utils/swal';

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const [confirmState, setConfirmState] = useState(null);
  const confirmResolveRef = useRef(null);

  // Auto-dismiss helper for toasts
  const removeToast = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const showToast = useCallback(({ type = 'info', title = '', message = '', duration = 4000 }) => {
    const id = Date.now() + Math.random().toString(36).substring(2, 7);
    const newToast = { id, type, title, message, duration };

    setToasts((prev) => [...prev.slice(-4), newToast]); // keep at most 5 toasts

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id);
      }, duration);
    }
    return id;
  }, [removeToast]);

  // Shortcut helpers
  const toast = {
    success: (message, title = 'เธชเธณเน€เธฃเนเธ') => showToast({ type: 'success', title, message }),
    error: (message, title = 'เธเนเธญเธเธดเธ”เธเธฅเธฒเธ”') => showToast({ type: 'error', title, message }),
    warning: (message, title = 'เนเธเนเธเน€เธ•เธทเธญเธ') => showToast({ type: 'warning', title, message }),
    info: (message, title = 'เธเนเธญเธกเธนเธฅ') => showToast({ type: 'info', title, message }),
  };

  // Standardized SweetAlert2 Confirm Dialog returning Promise<boolean>
  const confirmDialog = useCallback(async ({
    title = 'เธขเธทเธเธขเธฑเธเธเธฒเธฃเธ—เธณเธฃเธฒเธขเธเธฒเธฃ',
    message = 'เธ—เนเธฒเธเธ•เนเธญเธเธเธฒเธฃเธ”เธณเน€เธเธดเธเธเธฒเธฃเธ•เนเธญเธซเธฃเธทเธญเนเธกเน?',
    confirmText = 'เธขเธทเธเธขเธฑเธ',
    cancelText = 'เธขเธเน€เธฅเธดเธ',
    type = 'danger' // 'danger' | 'primary' | 'warning'
  }) => {
    if (type === 'danger') {
      return showDeleteConfirm(title, message, { confirmText, cancelButtonText: cancelText });
    }
    return showConfirm(title, message, {
      confirmText,
      cancelButtonText: cancelText,
      icon: type === 'warning' ? 'warning' : 'question'
    });
  }, []);

  const handleConfirmClose = (result) => {
    setConfirmState(null);
    if (confirmResolveRef.current) {
      confirmResolveRef.current(result);
      confirmResolveRef.current = null;
    }
  };

  // Handle ESC key for Confirm Modal
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && confirmState?.isOpen) {
        handleConfirmClose(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [confirmState]);

  return (
    <ToastContext.Provider value={{ showToast, toast, confirmDialog }}>
      {children}

      {/* Floating Toast Notification Stack */}
      <div
        className="toast-container"
        aria-live="polite"
        role="region"
        aria-label="เธเธฒเธฃเนเธเนเธเน€เธ•เธทเธญเธเธฃเธฐเธเธ"
        style={{
          position: 'fixed',
          top: '1.25rem',
          right: '1.25rem',
          zIndex: 9999,
          display: 'flex',
          flexDirection: 'column',
          gap: '0.65rem',
          maxWidth: '420px',
          width: 'calc(100% - 2.5rem)',
          pointerEvents: 'none'
        }}
      >
        {toasts.map((t) => {
          const typeStyles = {
            success: {
              border: '1px solid var(--accent-emerald)',
              bg: 'var(--bg-surface)',
              icon: <CheckCircle2 size={20} color="var(--accent-emerald)" />,
              indicatorColor: 'var(--accent-emerald)'
            },
            error: {
              border: '1px solid var(--accent-rose)',
              bg: 'var(--bg-surface)',
              icon: <AlertCircle size={20} color="var(--accent-rose)" />,
              indicatorColor: 'var(--accent-rose)'
            },
            warning: {
              border: '1px solid var(--accent-gold)',
              bg: 'var(--bg-surface)',
              icon: <AlertTriangle size={20} color="var(--accent-gold)" />,
              indicatorColor: 'var(--accent-gold)'
            },
            info: {
              border: '1px solid var(--primary-500)',
              bg: 'var(--bg-surface)',
              icon: <Info size={20} color="var(--primary-500)" />,
              indicatorColor: 'var(--primary-500)'
            }
          }[t.type] || {
            border: '1px solid var(--border-subtle)',
            bg: 'var(--bg-surface)',
            icon: <Info size={20} color="var(--primary-500)" />,
            indicatorColor: 'var(--primary-500)'
          };

          return (
            <div
              key={t.id}
              role="alert"
              className="toast-card animate-fade-in shadow-xl"
              style={{
                pointerEvents: 'auto',
                background: typeStyles.bg,
                border: typeStyles.border,
                borderRadius: 'var(--radius-lg)',
                padding: '0.9rem 1.1rem',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '0.75rem',
                position: 'relative',
                overflow: 'hidden',
                boxShadow: 'var(--shadow-lg)'
              }}
            >
              <div style={{ flexShrink: 0, marginTop: '2px' }}>
                {typeStyles.icon}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                {t.title && (
                  <div style={{ fontSize: '0.92rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '0.15rem' }}>
                    {t.title}
                  </div>
                )}
                <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)', lineHeight: 1.45, wordBreak: 'break-word' }}>
                  {t.message}
                </div>
              </div>
              <button
                type="button"
                onClick={() => removeToast(t.id)}
                aria-label="เธเธดเธ”เธเธฒเธฃเนเธเนเธเน€เธ•เธทเธญเธ"
                style={{
                  background: 'none',
                  border: 'none',
                  color: 'var(--text-light)',
                  cursor: 'pointer',
                  padding: '4px',
                  borderRadius: '6px',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  flexShrink: 0
                }}
              >
                <X size={16} />
              </button>
            </div>
          );
        })}
      </div>

      {/* Unified Confirm Modal */}
      {confirmState?.isOpen && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="confirm-dialog-title"
          className="confirm-dialog-backdrop animate-fade-in"
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: 10000,
            background: 'rgba(15, 23, 42, 0.75)',
            backdropFilter: 'blur(8px)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '1rem'
          }}
          onClick={(e) => {
            if (e.target === e.currentTarget) handleConfirmClose(false);
          }}
        >
          <div
            className="surface-card animate-scale-up shadow-2xl"
            style={{
              width: '100%',
              maxWidth: '440px',
              background: 'var(--bg-surface)',
              borderRadius: 'var(--radius-xl)',
              padding: '1.75rem',
              border: '1px solid var(--border-subtle)',
              position: 'relative'
            }}
          >
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '1rem', marginBottom: '1.25rem' }}>
              <div
                style={{
                  width: '46px',
                  height: '46px',
                  borderRadius: '12px',
                  background: confirmState.type === 'danger' ? 'var(--accent-rose-light)' : 'var(--primary-50)',
                  color: confirmState.type === 'danger' ? 'var(--accent-rose)' : 'var(--primary-600)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  flexShrink: 0
                }}
              >
                {confirmState.type === 'danger' ? <ShieldAlert size={24} /> : <AlertTriangle size={24} />}
              </div>
              <div style={{ flex: 1 }}>
                <h3 id="confirm-dialog-title" style={{ fontSize: '1.15rem', fontWeight: 800, color: 'var(--primary-900)', margin: '0 0 0.4rem 0' }}>
                  {confirmState.title}
                </h3>
                <p style={{ fontSize: '0.88rem', color: 'var(--text-muted)', margin: 0, lineHeight: 1.5 }}>
                  {confirmState.message}
                </p>
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', flexWrap: 'wrap' }}>
              <button
                type="button"
                className="btn btn-subtle"
                style={{ minHeight: '44px', minWidth: '88px', fontWeight: 600 }}
                onClick={() => handleConfirmClose(false)}
                autoFocus
              >
                {confirmState.cancelText}
              </button>
              <button
                type="button"
                className={confirmState.type === 'danger' ? 'btn btn-outline' : 'btn btn-primary'}
                style={{
                  minHeight: '44px',
                  minWidth: '96px',
                  fontWeight: 700,
                  ...(confirmState.type === 'danger' ? {
                    color: 'var(--accent-rose)',
                    borderColor: 'var(--accent-rose)',
                    background: 'var(--accent-rose-light)'
                  } : {})
                }}
                onClick={() => handleConfirmClose(true)}
              >
                {confirmState.confirmText}
              </button>
            </div>
          </div>
        </div>
      )}
    </ToastContext.Provider>
  );
}

export function useToast() {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
}
