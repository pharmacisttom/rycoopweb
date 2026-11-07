let cachedCsrfToken = null;

async function getCsrfToken(signal) {
  if (cachedCsrfToken) return cachedCsrfToken;
  try {
    const res = await fetch('/csrf-token', {
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal,
      cache: 'no-store'
    });
    if (res.ok) {
      const data = await res.json();
      if (data && data.token) {
        cachedCsrfToken = data.token;
        return cachedCsrfToken;
      }
    }
  } catch (e) {
    if (e.name === 'AbortError') throw e;
  }
  return '';
}

export async function memberAdminApi(path, options = {}) {
  const {
    data,
    body: rawBody,
    method: rawMethod,
    headers: customHeaders = {},
    signal,
    ...restOptions
  } = options;

  let method = (rawMethod || (data !== undefined ? 'POST' : 'GET')).toUpperCase();

  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...customHeaders,
  };

  const isWriteMethod = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);

  let body = rawBody;
  if (body === undefined && data !== undefined) {
    if (data instanceof FormData) {
      body = data;
    } else {
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
      body = JSON.stringify(data);
    }
  } else if (body !== undefined && typeof body === 'object' && !(body instanceof FormData) && !(body instanceof Blob) && !(body instanceof URLSearchParams)) {
    headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    body = JSON.stringify(body);
  }

  if (isWriteMethod && !headers['X-CSRF-TOKEN']) {
    const token = await getCsrfToken(signal);
    if (token) {
      headers['X-CSRF-TOKEN'] = token;
    }
  }

  const fetchOptions = {
    method,
    credentials: 'include',
    headers,
    signal,
    cache: 'no-store',
    ...restOptions,
  };

  if (!['GET', 'HEAD'].includes(method) && body !== undefined) {
    fetchOptions.body = body;
  }

  let response;
  try {
    response = await fetch(path, fetchOptions);
  } catch (error) {
    if (error.name === 'AbortError') throw error;
    throw new Error('ติดต่อเซิร์ฟเวอร์ไม่ได้ กรุณาตรวจการเชื่อมต่อแล้วลองใหม่');
  }

  const text = await response.text();
  let result;
  try {
    result = JSON.parse(text);
  } catch {
    throw Object.assign(
      new Error(`เซิร์ฟเวอร์ส่งข้อมูลไม่ถูกต้อง (HTTP ${response.status}) กรุณาให้ผู้ดูแลตรวจ PHP และเส้นทาง API`),
      { status: response.status, code: 'INVALID_API_RESPONSE' }
    );
  }

  if (!response.ok || !result.success) {
    // If CSRF token expired / failed, invalidate cached token
    if (response.status === 419 || (result.message && result.message.toLowerCase().includes('csrf'))) {
      cachedCsrfToken = null;
    }
    throw Object.assign(
      new Error(result.message || 'ไม่สามารถโหลดข้อมูลได้'),
      {
        status: response.status,
        code: result.code,
        reference: result.reference || result.correlation_id,
        context: result.context,
        missing: result.missing
      }
    );
  }

  return result;
}

export const thaiMoney = value => value == null ? '—' : Number(value).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const memberStatus = { active: 'ใช้งาน', suspended: 'ระงับ', resigned: 'พ้นสมาชิก' };
