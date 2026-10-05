import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { fetchCurrentUser } from '../services/api';
import { normalizeSameOriginRedirect } from '../utils/navigation';

const AuthContext = createContext();

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    try {
      const saved = localStorage.getItem('coop_auth_user');
      return saved ? JSON.parse(saved) : null;
    } catch (e) {
      return null;
    }
  });

  const [showAuthModal, setShowAuthModal] = useState(false);
  const [sessionStatus, setSessionStatus] = useState(user ? 'checking' : 'guest');

  useEffect(() => {
    if (user) {
      try {
        localStorage.setItem('coop_auth_user', JSON.stringify(user));
      } catch (e) {}
    } else {
      localStorage.removeItem('coop_auth_user');
    }
  }, [user]);

  // localStorage only remembers the UI state. The PHP session remains the source
  // of truth for protected actions such as publishing news.
  useEffect(() => {
    if (!user) {
      setSessionStatus('guest');
      return undefined;
    }

    let cancelled = false;

    const checkSession = async () => {
      if (!navigator.onLine) {
        if (!cancelled) setSessionStatus('offline');
        return;
      }

      if (!cancelled) setSessionStatus('checking');
      try {
        const result = await fetchCurrentUser();
        if (cancelled) return;

        if (result?.success && result.authenticated) {
          setSessionStatus('online');
        } else if (result?.authenticated === false) {
          setUser(null);
          setSessionStatus('expired');
        } else {
          // Keep the cached identity when the server is temporarily unreachable.
          setSessionStatus('offline');
        }
      } catch (error) {
        if (!cancelled) setSessionStatus('offline');
      }
    };

    const handleOnline = () => checkSession();
    const handleOffline = () => setSessionStatus('offline');
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') checkSession();
    };

    checkSession();
    const intervalId = window.setInterval(checkSession, 60_000);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    document.addEventListener('visibilitychange', handleVisibility);

    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, [user?.id]);

  /** Real authentication against the same-origin PHP backend. */
  const login = async (usernameOrId, password) => {
    const inputUsername = (usernameOrId || '').trim();
    // A password may legitimately contain leading or trailing whitespace.
    const inputPassword = password || '';

    if (!inputUsername || !inputPassword) {
      return {
        success: false,
        message: 'กรุณากรอกชื่อผู้ใช้ / รหัสสมาชิก และรหัสผ่าน'
      };
    }

    try {
      const getApiUrl = (path) => path.startsWith('/') ? path : `/${path}`;

      const fetchWithFallback = async (path, options = {}) => {
        const uniqueUrls = [getApiUrl(path)];
        for (const url of uniqueUrls) {
          try {
            const res = await fetch(url, options);
            if (res.ok || [400, 401, 403, 419, 422, 429].includes(res.status)) {
              return res;
            }
          } catch (e) {}
        }
        return await fetch(getApiUrl(path), options);
      };

      const csrfResponse = await fetchWithFallback('/csrf-token', {
        headers: { Accept: 'application/json' },
        credentials: 'include'
      });
      if (!csrfResponse.ok) {
        return { success: false, message: 'ไม่สามารถเริ่มต้นเซสชันที่ปลอดภัยได้ กรุณารีเฟรชหน้าและลองใหม่' };
      }

      const csrfData = await csrfResponse.json();
      const formData = new FormData();
      formData.append('username', inputUsername);
      formData.append('password', inputPassword);
      formData.append('ajax', '1');
      formData.append('_csrf_token', csrfData.token || '');

      const response = await fetchWithFallback('/login', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        credentials: 'include'
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success && data.redirect && !data.user) {
          return { success: true, requiresTwoFactor: true, redirect: normalizeSameOriginRedirect(data.redirect, '/admin/2fa'), user: null };
        }
        if (data.success && data.user) {
              const roleSlug = data.user.role || data.user.role_slug || '';
              const authUser = {
                id: data.user.id,
                username: data.user.username || inputUsername,
                name: data.user.name || inputUsername,
                role: roleSlug,
                roleName: data.user.role_name || (roleSlug === 'super_admin' ? 'ผู้ดูแลระบบสูงสุด' : roleSlug === 'staff' ? 'เจ้าหน้าที่สินเชื่อ/การเงิน' : roleSlug === 'auditor' ? 'ผู้ตรวจสอบกิจการ' : 'สมาชิกสหกรณ์'),
                roleBadge: data.user.role_badge || (roleSlug === 'member_admin' ? 'ผู้ดูแลระบบสมาชิก' : roleSlug === 'super_admin' ? 'Super Admin' : roleSlug === 'staff' ? 'เจ้าหน้าที่สหกรณ์' : roleSlug === 'auditor' ? 'ผู้ตรวจสอบกิจการ' : 'สมาชิกสหกรณ์'),
                department: data.user.department || data.user.org_name || 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
                position: data.user.position || '',
                phone: data.user.phone || '',
                avatar: data.user.avatar || '',
                memberId: data.user.member_no || data.user.memberId || inputUsername,
                shares: data.user.shares_amount || data.user.shares || 0,
                monthlyShare: data.user.monthly_share || 0,
                savings: data.user.savings_balance || data.user.savings || 0,
                loanBalance: data.user.loan_balance || 0,
                dividendEstimated: data.user.dividend_estimated || 0,
                loanRefundEstimated: data.user.loan_refund_estimated || 0,
                accounts: data.user.accounts || [],
                loans: data.user.loans || [],
                recentReceipts: data.user.recent_receipts || []
              };

              setUser(authUser);
              setSessionStatus('online');
              setShowAuthModal(false);
              return {
                success: true,
                redirect: normalizeSameOriginRedirect(
                  data.redirect,
                  roleSlug === 'member_admin' ? '/admin/members/dashboard' : roleSlug === 'super_admin' ? '/admin/dashboard' : '/staff/dashboard'
                ),
                user: authUser
              };
        }
        return { success: false, message: data.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง' };
      }

      if ([400, 401, 403, 422, 429].includes(response.status)) {
        try {
          const errorData = await response.json();
          return {
            success: false,
            message: errorData.message || (response.status === 429
              ? 'เข้าสู่ระบบไม่สำเร็จหลายครั้ง กรุณารอสักครู่แล้วลองใหม่'
              : 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง')
          };
        } catch (e) {
          return { success: false, message: 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง' };
        }
      }

      if (response.status === 419) {
        return { success: false, message: 'เซสชันหมดอายุ กรุณารีเฟรชหน้าและลองใหม่' };
      }
      return { success: false, message: 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่อีกครั้ง' };
    } catch (err) {
      console.warn('Authentication server connection error:', err);
    }
    return {
      success: false,
      message: 'ไม่สามารถติดต่อระบบยืนยันตัวตนได้ กรุณาตรวจสอบการเชื่อมต่อแล้วลองใหม่'
    };
  };

  const updateProfile = ({ avatar, phone }) => {
    if (!user) return false;
    const updated = {
      ...user,
      avatar: avatar !== undefined ? avatar : (user.avatar || ''),
      phone: phone !== undefined ? phone : (user.phone || '')
    };
    setUser(updated);
    try {
      localStorage.setItem('coop_auth_user', JSON.stringify(updated));
    } catch (e) {}
    return true;
  };

  const invalidateSession = useCallback(() => {
    setUser(null);
    setSessionStatus('expired');
    try { localStorage.removeItem('coop_auth_user'); } catch {}
  }, []);

  const logout = async () => {
    try {
      const tokenResponse = await fetch('/csrf-token', {
        headers: { Accept: 'application/json' },
        credentials: 'include'
      });
      const tokenData = tokenResponse.ok ? await tokenResponse.json() : {};
      const body = new FormData();
      body.append('_csrf_token', tokenData.token || '');
      await fetch('/logout', {
        method: 'POST',
        body,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(tokenData.token ? { 'X-CSRF-TOKEN': tokenData.token } : {})
        },
        credentials: 'include'
      });
    } catch (e) {}
    setUser(null);
    setSessionStatus('guest');
    localStorage.removeItem('coop_auth_user');
  };

  return (
    <AuthContext.Provider value={{
      user,
      isLoggedIn: !!user,
      sessionStatus,
      login,
      logout,
      invalidateSession,
      updateProfile,
      showAuthModal,
      setShowAuthModal
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
