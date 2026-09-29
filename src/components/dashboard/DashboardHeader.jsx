import React from 'react';
import { User, LogOut, Edit3, Calendar, Phone, KeyRound } from 'lucide-react';
import UserStatusBadge from '../common/UserStatusBadge';

/**
 * Reusable Dashboard Header with Profile Info, Role Badge, and Actions
 */
export default function DashboardHeader({
  user,
  onEditProfile,
  onChangePassword,
  onLogout,
  quickActions
}) {
  const role = user?.role || 'member';

  const getRoleConfig = (r) => {
    switch (r) {
      case 'super_admin':
        return {
          title: 'ผู้ดูแลระบบสูงสุด',
          badgeClass: 'badge-rose',
          gradient: 'linear-gradient(135deg, #ef4444, #991b1b)',
          defaultAvatar: '👑'
        };
      case 'staff':
        return {
          title: 'เจ้าหน้าที่สินเชื่อ/การเงิน',
          badgeClass: 'badge-primary',
          gradient: 'var(--gradient-primary)',
          defaultAvatar: '💼'
        };
      case 'auditor':
        return {
          title: 'ผู้ตรวจสอบกิจการ / ผู้จัดการ',
          badgeClass: 'badge-gold',
          gradient: 'var(--gradient-gold)',
          defaultAvatar: '🔍'
        };
      default:
        return {
          title: 'สมาชิกสหกรณ์',
          badgeClass: 'badge-emerald',
          gradient: 'var(--gradient-teal)',
          defaultAvatar: '👤'
        };
    }
  };

  const roleConfig = getRoleConfig(role);
  const todayStr = new Date().toLocaleDateString('th-TH', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });

  return (
    <div
      className="surface-card dashboard-hero-header"
      style={{
        borderRadius: 'var(--radius-xl)',
        marginBottom: '2rem',
        border: '1px solid var(--border-subtle)',
        boxShadow: 'var(--shadow-sm)'
      }}
    >
      <div className="dashboard-header-inner">
        {/* User Identity Info */}
        <div className="dashboard-user-info">
          <div
            className="dashboard-avatar"
            style={{
              background: roleConfig.gradient,
              border: '2px solid var(--bg-surface)'
            }}
          >
            {user?.avatar ? (
              <img src={user.avatar} alt={user.name || 'User Avatar'} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
            ) : (
              roleConfig.defaultAvatar
            )}
          </div>

          <div className="dashboard-user-details">
            <div className="dashboard-user-name-row">
              <h2 className="dashboard-user-name">
                {user?.name || 'ผู้ใช้งานสหกรณ์'}
              </h2>
              <span className={`badge ${roleConfig.badgeClass}`}>
                {user?.roleBadge || user?.roleName || roleConfig.title}
              </span>
              <UserStatusBadge />
            </div>

            <div className="dashboard-user-dept">
              <span>{user?.department || 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด'}</span>
              {user?.position && <span> ({user.position})</span>}
            </div>

            <div className="dashboard-user-meta">
              <span>รหัส: <strong>{user?.memberId || user?.username || '-'}</strong></span>
              {user?.phone && (
                <>
                  <span className="meta-dot">•</span>
                  <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem', color: 'var(--primary-700)', fontWeight: 600 }}>
                    <Phone size={13} />
                    {user.phone}
                  </span>
                </>
              )}
              <span className="meta-dot">•</span>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem', color: 'var(--text-muted)' }}>
                <Calendar size={13} />
                {todayStr}
              </span>
            </div>
          </div>
        </div>

        {/* Header Action Buttons */}
        <div className="dashboard-header-actions">
          {quickActions}

          {onEditProfile && (
            <button
              type="button"
              onClick={onEditProfile}
              className="btn btn-subtle btn-sm dashboard-action-btn"
              title="แก้ไขโปรไฟล์และข้อมูลติดต่อ"
            >
              <Edit3 size={15} style={{ color: 'var(--primary-600)' }} />
              <span>แก้ไขข้อมูลส่วนตัว</span>
            </button>
          )}

          {onChangePassword && (
            <button
              type="button"
              onClick={onChangePassword}
              className="btn btn-subtle btn-sm dashboard-action-btn"
              title="เปลี่ยนรหัสผ่านเพื่อความปลอดภัย"
            >
              <KeyRound size={15} style={{ color: 'var(--accent-gold-dark)' }} />
              <span>เปลี่ยนรหัสผ่าน</span>
            </button>
          )}

          {onLogout && (
            <button
              type="button"
              onClick={onLogout}
              className="btn btn-outline btn-sm dashboard-action-btn"
              style={{
                color: 'var(--accent-rose)',
                borderColor: 'var(--accent-rose)'
              }}
              title="ออกจากระบบ"
            >
              <LogOut size={15} />
              <span>ออกจากระบบ</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
