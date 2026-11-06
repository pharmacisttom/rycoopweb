import React from 'react';
import AdminControlCenterShell from '../AdminControlCenterShell';
import './AdminDesignSystem.css';

/**
 * Canonical Admin Layout wrapper.
 * Delegates directly to AdminControlCenterShell ensuring the Single Shell Rule
 * (exactly one Topbar, one Sidebar, unified responsive behavior) across all routes.
 */
export default function AdminLayout({ children, ...props }) {
  return <AdminControlCenterShell {...props}>{children}</AdminControlCenterShell>;
}
