/**
 * React API Service Layer
 *
 * Centralized API calls to the PHP backend.
 * All data fetching goes through these functions instead of importing mockData.
 */

export const isStaticMode = import.meta.env.VITE_PUBLIC_STATIC_MODE === 'true';

const getApiUrl = (path) => path.startsWith('/') ? path : `/${path}`;

/**
 * Core fetch wrapper with fallback URLs for development/production compatibility.
 */
const apiFetch = async (path, options = {}) => {
  if (isStaticMode) {
    return { success: false, available: false, message: 'ระบบอยู่ในโหมดเผยแพร่ข้อมูลสาธารณะแบบคงที่ (Static Mode)' };
  }
  const uniqueUrls = [getApiUrl(path)];

  const defaultOptions = {
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...options.headers,
    },
    credentials: 'include',
    ...options,
  };

  for (const url of uniqueUrls) {
    try {
      const res = await fetch(url, defaultOptions);
      if (res.ok) {
        return await res.json();
      }
      if (res.status === 403 || res.status === 503 || res.status === 429) {
        const result = await res.json();
        return result;
      }
      if (res.status === 401) {
        return { success: false, authenticated: false, message: 'กรุณาเข้าสู่ระบบ' };
      }
      if (res.status === 404) {
        return { success: false, message: 'ไม่พบข้อมูล' };
      }
    } catch (e) {
      // try next URL
    }
  }

  return { success: false, message: 'ไม่สามารถติดต่อ API ได้' };
};

// ──────────────────────────────────────────────
// PUBLIC DATA APIs
// ──────────────────────────────────────────────

/** Fetch all homepage data in a single call */
export const fetchHomeData = () => isStaticMode ? Promise.resolve({ success: false, message: 'Static mode' }) : apiFetch('/api/public/home');

/** Fetch cooperative information & stats */
export const fetchCoopInfo = () => isStaticMode ? Promise.resolve({ success: false, message: 'Static mode' }) : apiFetch('/api/public/coop-info');

/** Fetch interest rates (deposit & loan) */
export const fetchInterestRates = () => apiFetch('/api/public/interest-rates');

/** Fetch loan products list */
export const fetchLoanProducts = () => apiFetch('/api/public/loan-products');

/** Fetch news articles with pagination */
export const fetchNews = (page = 1) => isStaticMode ? Promise.resolve({ success: true, data: [], meta: { total: 0 } }) : apiFetch(`/api/public/news?page=${page}`);

/** Fetch announcements */
export const fetchAnnouncements = () => isStaticMode ? Promise.resolve({ success: true, data: [] }) : apiFetch('/api/public/announcements');

/** Fetch downloadable documents */
export const fetchDocuments = () => isStaticMode ? Promise.resolve({ success: false, message: 'Static mode' }) : apiFetch('/api/public/documents');

/** Fetch welfare benefit types */
export const fetchWelfareTypes = () => apiFetch('/api/public/welfare');

/** Fetch FAQ items */
export const fetchFaqs = () => isStaticMode ? Promise.resolve({ success: false, message: 'Static mode' }) : apiFetch('/api/public/faqs');

/** Fetch board of directors / staff */
export const fetchBoardMembers = () => apiFetch('/api/public/board-members');

/** Fetch financial statistics (historical) */
export const fetchStatistics = () => apiFetch('/api/public/statistics');

// ──────────────────────────────────────────────
// AUTH APIs
// ──────────────────────────────────────────────

const isPortalEnabled = () => import.meta.env.VITE_FEATURE_MEMBER_PORTAL === 'true';

/** Check if current user is authenticated */
export const fetchCurrentUser = () => {
  if (!isPortalEnabled()) {
    return Promise.resolve({ success: false, authenticated: false, message: 'ระบบสมาชิกยังไม่เปิดใช้งาน' });
  }
  return apiFetch('/api/auth/me', { cache: 'no-store' });
};

/** Fetch member dashboard summary */
export const fetchMemberDashboard = () => {
  if (!isPortalEnabled()) return Promise.resolve({ success: false, message: 'ระบบสมาชิกยังไม่เปิดใช้งาน' });
  return apiFetch('/api/member/dashboard');
};

/** Fetch member profile data */
export const fetchMemberProfile = () => {
  if (!isPortalEnabled()) return Promise.resolve({ success: false, message: 'ระบบสมาชิกยังไม่เปิดใช้งาน' });
  return apiFetch('/api/member/profile');
};

/** Fetch member shares data */
export const fetchMemberShares = () => {
  if (!isPortalEnabled()) return Promise.resolve({ success: false, message: 'ระบบสมาชิกยังไม่เปิดใช้งาน' });
  return apiFetch('/api/member/shares');
};

// ──────────────────────────────────────────────
// MEMBER DATA APIs (require authentication)
// ──────────────────────────────────────────────



/** Fetch member deposit accounts & transactions */
export const fetchMemberDeposits = (account, type) => {
  const params = new URLSearchParams();
  if (account) params.set('account', account);
  if (type) params.set('type', type);
  const qs = params.toString();
  return apiFetch(`/api/member/deposits${qs ? '?' + qs : ''}`);
};

/** Fetch member loan contracts & amortization */
export const fetchMemberLoans = (contract) => {
  const qs = contract ? `?contract=${contract}` : '';
  return apiFetch(`/api/member/loans${qs}`);
};

/** Fetch member notifications */
export const fetchMemberNotifications = () => apiFetch('/api/member/notifications');

/** Fetch member receipts */
export const fetchMemberReceipts = (year, month) => {
  const params = new URLSearchParams();
  if (year) params.set('year', year);
  if (month) params.set('month', month);
  const qs = params.toString();
  return apiFetch(`/api/member/receipts${qs ? '?' + qs : ''}`);
};

/** Fetch member online service requests */
export const fetchMemberOnlineServices = () => apiFetch('/api/member/online-services');

/** Fetch member financial summary */
export const fetchMemberFinancialSummary = () => apiFetch('/api/member/financial-summary');

/** Fetch member beneficiaries */
export const fetchMemberBeneficiaries = () => apiFetch('/api/member/beneficiaries');

/** Fetch member welfare applications */
export const fetchMemberWelfare = () => apiFetch('/api/member/welfare');

// ──────────────────────────────────────────────
// ADMIN DATA APIs (require super_admin role)
// ──────────────────────────────────────────────

/** Fetch admin dashboard data */
export const fetchAdminDashboard = () => apiFetch('/api/admin/dashboard');

/** Fetch admin user list and roles */
export const fetchAdminUsers = () => apiFetch('/api/admin/users');

/** Fetch admin complaints list */
export const fetchAdminComplaints = () => apiFetch('/api/admin/complaints');

/** Fetch admin audit logs */
export const fetchAdminAuditLogs = () => apiFetch('/api/admin/audit-logs');

/** Fetch database-backed news for the administrator dashboard */
export const fetchAdminNews = () => apiFetch('/api/admin/news');

/** Fetch interest rates, loan products, and rate change history for admin */
export const fetchAdminRates = () => apiFetch('/api/admin/rates');

/** Update an interest rate with audit history */
export const updateAdminRate = (id, data) => apiPost(`/api/admin/rates/${id}`, data);

/** Update loan product limits, terms, and conditions */
export const updateAdminLoanProduct = (id, data) => apiPost(`/api/admin/loan-products/${id}`, data);

// ──────────────────────────────────────────────
// POST APIs (mutations)
// ──────────────────────────────────────────────

/** Fetch CSRF token from server */
export const fetchCsrfToken = async () => {
  if (isStaticMode) {
    return '';
  }
  const data = await apiFetch('/csrf-token');
  return data?.token || data?.data?.token || '';
};

/**
 * Submit a form POST with CSRF token.
 * Automatically fetches CSRF token before submission.
 */
export const apiPost = async (path, body = {}) => {
  if (isStaticMode) {
    return { success: false, available: false, message: 'ระบบอยู่ในโหมดเผยแพร่ข้อมูลสาธารณะแบบคงที่ (Static Mode)' };
  }
  const token = await fetchCsrfToken();
  const formData = new FormData();
  Object.entries(body).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      formData.append(key, value);
    }
  });
  formData.append('_csrf_token', token);
  formData.append('ajax', '1');

  const uniqueUrls = [getApiUrl(path)];

  for (const url of uniqueUrls) {
    try {
      const res = await fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
        credentials: 'include',
      });
      if (res.ok || [400, 401, 403, 409, 419, 422, 429].includes(res.status)) {
        const contentType = res.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          return await res.json();
        }
        return {
          success: false,
          message: res.status === 401 || res.status === 403
            ? 'เซสชันหมดอายุหรือไม่มีสิทธิ์ใช้งาน กรุณาเข้าสู่ระบบใหม่'
            : 'ระบบตอบกลับข้อมูลไม่ถูกต้อง'
        };
      }
    } catch (e) {}
  }

  return { success: false, message: 'ไม่สามารถเชื่อมต่อกับระบบบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง' };
};

/** Publish a news item from the React administrator dashboard */
export const createAdminNews = (body) => apiPost('/api/admin/news', body);

/** Soft-delete a news item from the React administrator dashboard */
export const deleteAdminNews = (id) => apiPost(`/api/admin/news/${id}/delete`);

/** Fetch public visitor statistics summary */
export const fetchVisitorStats = () => isStaticMode ? Promise.resolve({ success: false, available: false, message: 'Static mode' }) : apiFetch('/api/public/visitors/summary');

/** Alias for public visitor summary */
export const fetchPublicVisitorSummary = () => fetchVisitorStats();

/** Fetch detailed visitor analytics for /visitor-stats */
export const fetchVisitorAnalytics = (days = 30) => isStaticMode ? Promise.resolve({ success: false, available: false, message: 'Static mode' }) : apiFetch(`/api/public/visitors/analytics?days=${encodeURIComponent(days)}`);

/** Fetch admin-level visitor analytics for /admin/analytics/visitors */
export const fetchAdminVisitorAnalytics = (days = 30) => apiFetch(`/api/admin/analytics/visitors?days=${encodeURIComponent(days)}`);

/** Authorized export of visitor analytics (logged in Audit Trail) */
export const exportAdminVisitorAnalytics = (days = 30, format = 'csv') => apiPost('/api/admin/analytics/visitors/export', { days, format });

/** Record page visit */
export const recordPageVisit = (payload) => isStaticMode ? Promise.resolve({ success: false, available: false, message: 'Disabled in static mode' }) : apiPost('/api/visits/record', payload);
