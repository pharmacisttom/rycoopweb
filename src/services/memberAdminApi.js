export async function memberAdminApi(path, { data, signal } = {}) {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  const options = { credentials: 'include', headers, signal, cache: 'no-store' };
  if (data) {
    const csrf = await fetch('/csrf-token', { credentials: 'include', headers, signal, cache: 'no-store' });
    if (!csrf.ok) throw new Error('กรุณาเข้าสู่ระบบใหม่');
    const { token } = await csrf.json();
    options.method = 'POST'; headers['Content-Type'] = 'application/json'; headers['X-CSRF-TOKEN'] = token;
    options.body = JSON.stringify(data);
  }
  let response;
  try { response = await fetch(path, options); }
  catch (error) { if (error.name === 'AbortError') throw error; throw new Error('ติดต่อเซิร์ฟเวอร์ไม่ได้ กรุณาตรวจการเชื่อมต่อแล้วลองใหม่'); }
  const text = await response.text();
  let result;
  try { result = JSON.parse(text); }
  catch { throw Object.assign(new Error(`เซิร์ฟเวอร์ส่งข้อมูลไม่ถูกต้อง (HTTP ${response.status}) กรุณาให้ผู้ดูแลตรวจ PHP และเส้นทาง API`), { status: response.status, code: 'INVALID_API_RESPONSE' }); }
  if (!response.ok || !result.success) {
    throw Object.assign(new Error(result.message || 'ไม่สามารถโหลดข้อมูลได้'), { status: response.status, code: result.code, reference: result.reference, missing: result.missing });
  }
  return result;
}
export const thaiMoney = value => value == null ? '—' : Number(value).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const memberStatus = { active: 'ใช้งาน', suspended: 'ระงับ', resigned: 'พ้นสมาชิก' };
