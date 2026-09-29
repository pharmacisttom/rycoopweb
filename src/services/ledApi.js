/**
 * RYCOOP LED Member Check - API Service
 *
 * Dedicated client-side API layer for interacting with the LED backend endpoints.
 * Includes CSRF protection and multi-fallback URL resolution.
 */

const getApiUrl = (path) => path.startsWith('/') ? path : `/${path}`;

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
      if ([400, 401, 403, 404, 419, 422, 429, 500].includes(res.status)) {
        const contentType = res.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          return await res.json();
        }
      }
    } catch (e) {
      // try next URL
    }
  }

  return { success: false, message: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ระบบได้' };
};

export const fetchCsrfToken = async () => {
  try {
    const data = await apiFetch('/csrf-token');
    return data?.token || data?.data?.token || '';
  } catch (e) {
    return '';
  }
};

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

      const contentType = res.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        return await res.json();
      }
      if (res.ok) {
        return { success: true };
      }
    } catch (e) {}
  }

  return { success: false, message: 'ไม่สามารถเชื่อมต่อเพื่อส่งข้อมูลได้ กรุณาลองใหม่อีกครั้ง' };
};

// ──────────────────────────────────────────────
// LED API FUNCTIONS
// ──────────────────────────────────────────────

/** Fetch LED Dashboard summary, KPIs, and recent runs */
export const fetchLedDashboard = () => apiFetch('/api/admin/led/dashboard');

/** Fetch LED CKAN Datastore Schema */
export const fetchLedSchema = () => apiFetch('/api/admin/led/schema');

/** Force refresh LED Schema from CKAN API */
export const refreshLedSchema = () => apiPost('/api/admin/led/schema/refresh');

/** Search members by name, surname, or member_no for LED check */
export const searchLedMembers = (query) => {
  const clean = encodeURIComponent(query.trim());
  return apiFetch(`/api/admin/led/members/search?query=${clean}`);
};

/** Perform LED check for a single member */
export const checkLedMember = (memberId) => apiPost('/api/admin/led/check-member', { member_id: memberId });

/** Fetch LED Review Queue list */
export const fetchLedReviewQueue = (params = {}) => {
  const qs = new URLSearchParams(params).toString();
  return apiFetch(`/api/admin/led/review${qs ? '?' + qs : ''}`);
};

/** Fetch LED Check Result detail with candidates */
export const fetchLedReviewDetail = (id) => apiFetch(`/api/admin/led/review/${id}`);

/** Submit Human Review Decision (VERIFIED_MATCH, FALSE_MATCH, REVIEW_REQUIRED) */
export const submitLedReview = (id, decision, reviewNote = '') => {
  return apiPost(`/api/admin/led/review/${id}`, {
    decision,
    review_note: reviewNote,
  });
};

/** Start a Batch Run */
export const startLedBatchRun = (payload = {}) => apiPost('/api/admin/led/batch', payload);

/** Fetch Batch Runs History */
export const fetchLedRuns = (params = {}) => {
  const qs = new URLSearchParams(params).toString();
  return apiFetch(`/api/admin/led/runs${qs ? '?' + qs : ''}`);
};

/** Fetch detail of a single Batch Run */
export const fetchLedRunDetail = (id) => apiFetch(`/api/admin/led/runs/${id}`);
