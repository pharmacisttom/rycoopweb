import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  ExternalLink,
  Key,
  LogOut,
  Menu,
  Moon,
  PanelLeftClose,
  PanelLeftOpen,
  ShieldCheck,
  Sun,
  User,
} from 'lucide-react';
import { useAuth } from '../../../context/AuthContext';
import { useTheme } from '../../../context/ThemeContext';
import alertService from '../../../services/alertService';
import ChangePasswordModal from '../../common/ChangePasswordModal';

export default function AdminTopbar({
  sidebarCollapsed = false,
  onToggleSidebar,
  onOpenMobileSidebar,
}) {
  const { user, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const [showPasswordModal, setShowPasswordModal] = useState(false);

  const handleLogout = async () => {
    const confirmed = await alertService.confirm({
      title: 'ยืนยันการออกจากระบบ',
      text: 'คุณต้องการออกจากระบบการจัดการหรือไม่?',
      confirmButtonText: 'ออกจากระบบ',
      cancelButtonText: 'ยกเลิก',
      isDanger: true,
      icon: 'question',
    });

    if (confirmed) {
      alertService.toast({ icon: 'info', title: 'กำลังออกจากระบบ...' });
      logout();
      navigate('/admin/login');
    }
  };

  const isUatEnv = window.location.hostname.includes('test') || window.location.port !== '';

  return (
    <>
      <header
        className="admin-topbar"
        style={{
          height: '64px',
          backgroundColor: 'var(--acc-surface, #ffffff)',
          borderBottom: '1px solid var(--acc-border, #e2e8f0)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '0 24px',
          position: 'sticky',
          top: 0,
          zIndex: 100,
          boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
        }}
      >
        {/* Left Section: Mobile Toggle & Desktop Sidebar Toggle */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          {/* Mobile hamburger */}
          <button
            type="button"
            className="mobile-hamburger-btn"
            onClick={onOpenMobileSidebar}
            style={{
              background: 'none',
              border: 'none',
              padding: '8px',
              cursor: 'pointer',
              color: 'var(--acc-text, #0f172a)',
              display: 'flex',
              alignItems: 'center',
              borderRadius: '8px',
            }}
            aria-label="เปิดเมนูนำทาง"
          >
            <Menu size={22} />
          </button>

          {/* Desktop collapse toggle */}
          <button
            type="button"
            className="desktop-sidebar-btn"
            onClick={onToggleSidebar}
            style={{
              background: 'none',
              border: 'none',
              padding: '8px',
              cursor: 'pointer',
              color: 'var(--acc-text-secondary, #64748b)',
              display: 'flex',
              alignItems: 'center',
              borderRadius: '8px',
            }}
            aria-label={sidebarCollapsed ? 'ขยายแถบเมนูข้าง' : 'ย่อแถบเมนูข้าง'}
          >
            {sidebarCollapsed ? <PanelLeftOpen size={20} /> : <PanelLeftClose size={20} />}
          </button>

          {/* Environment Tag */}
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
              padding: '4px 10px',
              borderRadius: '9999px',
              fontSize: '0.75rem',
              fontWeight: 700,
              backgroundColor: isUatEnv ? '#eff6ff' : '#ecfdf5',
              color: isUatEnv ? '#1d4ed8' : '#047857',
              border: `1px solid ${isUatEnv ? '#bfdbfe' : '#a7f3d0'}`,
            }}
          >
            <span
              style={{
                width: '6px',
                height: '6px',
                borderRadius: '50%',
                backgroundColor: isUatEnv ? '#2563eb' : '#059669',
              }}
            />
            {isUatEnv ? 'UAT TEST SANDBOX' : 'PRODUCTION'}
          </span>
        </div>

        {/* Right Section: Public site, Theme, User info, Logout */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          {/* View Public Website Link */}
          <a
            href="/"
            target="_blank"
            rel="noopener noreferrer"
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
              fontSize: '0.85rem',
              color: 'var(--acc-text-secondary, #64748b)',
              textDecoration: 'none',
              padding: '6px 10px',
              borderRadius: '8px',
              border: '1px solid var(--acc-border, #e2e8f0)',
            }}
            title="เปิดหน้าเว็บไซต์สหกรณ์ในแท็บใหม่"
          >
            <span>หน้าเว็บไซต์</span>
            <ExternalLink size={14} />
          </a>

          {/* Theme switcher */}
          {toggleTheme && (
            <button
              type="button"
              onClick={toggleTheme}
              style={{
                background: 'none',
                border: '1px solid var(--acc-border, #e2e8f0)',
                padding: '7px 9px',
                borderRadius: '8px',
                cursor: 'pointer',
                color: 'var(--acc-text-secondary, #64748b)',
                display: 'flex',
                alignItems: 'center',
              }}
              aria-label={theme === 'dark' ? 'เปลี่ยนเป็นธีมสว่าง' : 'เปลี่ยนเป็นธีมมืด'}
              title={theme === 'dark' ? 'เปลี่ยนเป็นธีมสว่าง' : 'เปลี่ยนเป็นธีมมืด'}
            >
              {theme === 'dark' ? <Sun size={17} /> : <Moon size={17} />}
            </button>
          )}

          {/* User profile capsule */}
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '10px',
              padding: '4px 12px 4px 6px',
              borderRadius: '9999px',
              backgroundColor: 'var(--acc-surface-elevated, #f8fafc)',
              border: '1px solid var(--acc-border, #e2e8f0)',
            }}
          >
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '50%',
                backgroundColor: '#0284c7',
                color: '#ffffff',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: 700,
                fontSize: '0.85rem',
              }}
            >
              {user?.name ? user.name.charAt(0) : <User size={16} />}
            </div>
            <div style={{ display: 'flex', flexDirection: 'column' }}>
              <span style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--acc-text, #0f172a)', lineHeight: 1.2 }}>
                {user?.name || 'ผู้ดูแลระบบ'}
              </span>
              <span style={{ fontSize: '0.72rem', color: 'var(--acc-text-secondary, #64748b)' }}>
                {user?.role_name || user?.role_slug || user?.role || 'Staff'}
              </span>
            </div>
          </div>

          {/* Change Password button */}
          <button
            type="button"
            onClick={() => setShowPasswordModal(true)}
            style={{
              background: 'none',
              border: '1px solid var(--acc-border, #e2e8f0)',
              padding: '7px 9px',
              borderRadius: '8px',
              cursor: 'pointer',
              color: 'var(--acc-text-secondary, #64748b)',
              display: 'flex',
              alignItems: 'center',
            }}
            title="เปลี่ยนรหัสผ่าน"
            aria-label="เปลี่ยนรหัสผ่าน"
          >
            <Key size={16} />
          </button>

          {/* Logout Button */}
          <button
            type="button"
            onClick={handleLogout}
            style={{
              background: 'none',
              border: '1px solid #fecdd3',
              color: '#dc2626',
              padding: '6px 12px',
              borderRadius: '8px',
              cursor: 'pointer',
              fontSize: '0.85rem',
              fontWeight: 600,
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
            }}
            title="ออกจากระบบ"
          >
            <LogOut size={15} />
            <span className="logout-text">ออก</span>
          </button>
        </div>
      </header>

      {/* Change Password Modal */}
      {showPasswordModal && (
        <ChangePasswordModal
          isOpen={showPasswordModal}
          onClose={() => setShowPasswordModal(false)}
        />
      )}
    </>
  );
}
