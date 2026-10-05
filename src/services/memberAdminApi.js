export async function memberAdminApi(path, { data, signal } = {}) {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  const options = { credentials: 'include', headers, signal };
  if (data) {
    const csrf = await fetch('/csrf-token', { credentials: 'include', headers, signal });
    if (!csrf.ok) throw new Error('กรุณาเข้าสู่ระบบใหม่');
    const { token } = await csrf.json();
    options.method = 'POST'; headers['Content-Type'] = 'application/json'; headers['X-CSRF-TOKEN'] = token;
    options.body = JSON.stringify(data);
  }
  const response = await fetch(path, options);
  const result = await response.json();
  if (!response.ok || !result.success) throw new Error(result.message || 'ไม่สามารถโหลดข้อมูลได้');
  return result;
}
export const thaiMoney = value => Number(value || 0).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const memberStatus = { active: 'ใช้งาน', suspended: 'ระงับ', resigned: 'พ้นสมาชิก' };
