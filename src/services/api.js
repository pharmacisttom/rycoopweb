/**
 * React API Service Layer
 *
 * Centralized API calls to the PHP backend.
 * All data fetching goes through these functions instead of importing mockData.
 */

const getApiUrl = (path) => path.startsWith('/') ? path : `/${path}`;

/**
 * Core fetch wrapper with fallback URLs for development/production compatibility.
 */
const apiFetch = async (path, options = {}) => {
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
      if (res.status === 401) {
        return { success: false, authenticated: false, message: 'กรุณาเข้าสู่ระบบ' };
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
export const fetchHomeData = () => apiFetch('/api/public/home');

/** Fetch cooperative information & stats */
export const fetchCoopInfo = () => apiFetch('/api/public/coop-info');

/** Fetch interest rates (deposit & loan) */
export const fetchInterestRates = () => apiFetch('/api/public/interest-rates');

/** Fetch loan products list */
export const fetchLoanProducts = () => apiFetch('/api/public/loan-products');

/** Fetch news articles with pagination */
export const fetchNews = (page = 1) => apiFetch(`/api/public/news?page=${page}`);

/** Fetch announcements */
export const fetchAnnouncements = () => apiFetch('/api/public/announcements');

/** Fetch downloadable documents */
export const fetchDocuments = () => apiFetch('/api/public/documents');

/** Fetch welfare benefit types */
export const fetchWelfareTypes = () => apiFetch('/api/public/welfare');

/** Fetch FAQ items */
export const fetchFaqs = () => apiFetch('/api/public/faqs');

/** Fetch board of directors / staff */
export const fetchBoardMembers = () => apiFetch('/api/public/board-members');

/** Fetch financial statistics (historical) */
export const fetchStatistics = () => apiFetch('/api/public/statistics');

// ──────────────────────────────────────────────
// AUTH APIs
// ──────────────────────────────────────────────

/** Check if current user is authenticated */
export const fetchCurrentUser = () => apiFetch('/api/auth/me');

// ──────────────────────────────────────────────
// MEMBER DATA APIs (require authentication)
// ──────────────────────────────────────────────

/** Fetch member dashboard summary */
export const fetchMemberDashboard = () => apiFetch('/api/member/dashboard');

/** Fetch member profile data */
export const fetchMemberProfile = () => apiFetch('/api/member/profile');

/** Fetch member shares data */
export const fetchMemberShares = () => apiFetch('/api/member/shares');

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

// ──────────────────────────────────────────────
// POST APIs (mutations)
// ──────────────────────────────────────────────

/** Fetch CSRF token from server */
export const fetchCsrfToken = async () => {
  const data = await apiFetch('/csrf-token');
  return data?.token || data?.data?.token || '';
};

/**
 * Submit a form POST with CSRF token.
 * Automatically fetches CSRF token before submission.
 */
export const apiPost = async (path, body = {}) => {
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
      if (res.ok || [400, 401, 403, 419, 422].includes(res.status)) {
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
