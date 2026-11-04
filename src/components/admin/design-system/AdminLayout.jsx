import React, { useEffect, useState } from 'react';
import ResponsiveSidebar from './ResponsiveSidebar';
import AdminTopbar from './AdminTopbar';
import './AdminDesignSystem.css';

export default function AdminLayout({ children }) {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
    try {
      return localStorage.getItem('rycoop_admin_sidebar_collapsed') === 'true';
    } catch {
      return false;
    }
  });

  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

  const toggleSidebar = () => {
    setSidebarCollapsed((prev) => {
      const next = !prev;
      try {
        localStorage.setItem('rycoop_admin_sidebar_collapsed', String(next));
      } catch {}
      return next;
    });
  };

  return (
    <div
      className="rycoop-admin-layout"
      style={{
        display: 'flex',
        minHeight: '100vh',
        backgroundColor: 'var(--acc-bg, #f8fafc)',
        color: 'var(--acc-text, #0f172a)',
        fontFamily: "'Sarabun', -apple-system, BlinkMacSystemFont, sans-serif",
      }}
    >
      {/* Sidebar */}
      <ResponsiveSidebar
        collapsed={sidebarCollapsed}
        onToggleCollapse={toggleSidebar}
        mobileOpen={mobileSidebarOpen}
        onCloseMobile={() => setMobileSidebarOpen(false)}
      />

      {/* Main Area */}
      <div
        className="admin-layout-main"
        style={{
          flex: '1 1 auto',
          minWidth: 0,
          display: 'flex',
          flexDirection: 'column',
          minHeight: '100vh',
        }}
      >
        <AdminTopbar
          sidebarCollapsed={sidebarCollapsed}
          onToggleSidebar={toggleSidebar}
          onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
        />

        <main
          className="admin-layout-content"
          style={{
            flex: '1 1 auto',
            padding: '28px 32px',
            maxWidth: '1600px',
            width: '100%',
            boxSizing: 'border-box',
            margin: '0 auto',
          }}
        >
          {children}
        </main>
      </div>
    </div>
  );
}
