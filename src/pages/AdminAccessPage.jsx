import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { memberAdminApi } from '../services/memberAdminApi';

const emptyRole = () => ({ name: '', slug: '', description: '', permissions: [] });
const emptyUser = () => ({ name: '', username: '', email: '', status: 'active', role_id: '', password: '' });

export default function AdminAccessPage() {
  const [catalog, setCatalog] = useState(null);
  const [accounts, setAccounts] = useState(null);
  const [role, setRole] = useState(emptyRole);
  const [account, setAccount] = useState(emptyUser);
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [refresh, setRefresh] = useState(0);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true); setError('');
    Promise.all([
      memberAdminApi('/api/admin/access/roles', { signal: controller.signal }),
      memberAdminApi(`/api/admin/access/users?q=${encodeURIComponent(query)}&page=${page}`, { signal: controller.signal }),
    ]).then(([roles, users]) => { setCatalog(roles); setAccounts(users); }).catch(e => {
      if (e.name !== 'AbortError') { setError(e.message); setCatalog(null); setAccounts(null); }
    }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [query, page, refresh]);
  async function mutate(path, data, onSuccess) {
    if (busy) return;
    setBusy(true); setError(''); setMessage('');
    try { await memberAdminApi(path, { data }); onSuccess(); setMessage('บันทึกข้อมูลเรียบร้อยแล้ว'); setRefresh(value => value + 1); }
    catch (e) { setError(e.message); }
    finally { setBusy(false); }
  }
  const selectableRoles = (catalog?.roles || []).filter(item => item.slug !== 'member');
  return <main className="container section">
    <Link to="/admin/workspace">กลับพื้นที่ทำงาน</Link>
    <h1>จัดการผู้ใช้งานและสิทธิ์</h1>
    <p>สำหรับผู้ดูแลระบบสูงสุด บัญชีสมาชิกให้จัดการผ่านระบบสมาชิก</p>
    {error && <p role="alert">{error}</p>}
    {message && <p role="status">{message}</p>}
    <button className="btn btn-outline" disabled={busy || loading} onClick={() => setRefresh(value => value + 1)}>โหลดข้อมูลล่าสุด</button>
    {loading && <p role="status">กำลังโหลดข้อมูล...</p>}
    {catalog && <>
      <section className="surface-card" style={{ padding: '1.5rem', margin: '1.5rem 0' }}>
        <h2>บทบาทและสิทธิ์ (Role)</h2>
        <div style={{ overflowX: 'auto' }}><table style={{ width: '100%' }}><thead><tr><th>บทบาท</th><th>รหัส</th><th>ผู้ใช้</th><th>จัดการ</th></tr></thead><tbody>
          {catalog.roles.map(item => <tr key={item.id}><td>{item.name}</td><td>{item.slug}</td><td>{item.users}</td><td>
            <button disabled={busy || loading || item.protected} onClick={() => setRole({ ...item, permissions: item.permissions || [] })}>แก้ไข</button>{' '}
            <button disabled={busy || loading || item.builtin || item.users > 0} onClick={() => {
              if (window.confirm(`ยืนยันลบบทบาท ${item.name}?`)) mutate(`/api/admin/access/roles/${item.id}/delete`, { version: item.version }, () => setRole(emptyRole()));
            }}>ลบ</button>
          </td></tr>)}
        </tbody></table></div>
        <form onSubmit={event => { event.preventDefault(); mutate(role.id ? `/api/admin/access/roles/${role.id}/update` : '/api/admin/access/roles', role, () => setRole(emptyRole())); }}>
          <fieldset disabled={busy || loading} style={{ display: 'grid', gap: '0.75rem', marginTop: '1rem' }}>
            <legend>{role.id ? 'แก้ไขบทบาท' : 'เพิ่มบทบาท'}</legend>
            <label>ชื่อบทบาท <input required maxLength={50} value={role.name} onChange={e => setRole({ ...role, name: e.target.value })} /></label>
            <label>รหัสภาษาอังกฤษ <input required pattern="[a-z][a-z0-9_]*" maxLength={50} disabled={!!role.id} value={role.slug} onChange={e => setRole({ ...role, slug: e.target.value })} /></label>
            <label>คำอธิบาย <input maxLength={255} value={role.description || ''} onChange={e => setRole({ ...role, description: e.target.value })} /></label>
            {Object.entries(catalog.catalog).map(([module, actions]) => <fieldset key={module}><legend>{catalog.modules[module]}</legend>{actions.map(action => {
              const key = `${module}.${action}`;
              return <label key={key} style={{ marginRight: '1rem' }}><input type="checkbox" checked={role.permissions.includes(key)} onChange={e => setRole({ ...role, permissions: e.target.checked ? [...role.permissions, key] : role.permissions.filter(item => item !== key) })} /> {catalog.actions[action]}</label>;
            })}</fieldset>)}
            <div><button className="btn btn-primary" type="submit">บันทึกบทบาท</button>{' '}<button type="button" onClick={() => setRole(emptyRole())}>ยกเลิก / เพิ่มใหม่</button></div>
          </fieldset>
        </form>
      </section>
      <section className="surface-card" style={{ padding: '1.5rem' }}>
        <h2>บัญชีเจ้าหน้าที่</h2>
        <form onSubmit={e => { e.preventDefault(); setPage(1); setQuery(search.trim()); }}><label>ค้นหาชื่อ ชื่อผู้ใช้ หรืออีเมล <input value={search} onChange={e => setSearch(e.target.value)} /></label> <button disabled={busy || loading}>ค้นหา</button></form>
        <div style={{ overflowX: 'auto' }}><table style={{ width: '100%' }}><thead><tr><th>ชื่อ</th><th>ชื่อผู้ใช้</th><th>บทบาท / สถานะ</th><th>จัดการ</th></tr></thead><tbody>
          {(accounts?.users || []).map(item => <tr key={item.id}><td>{item.name}</td><td>{item.username}</td><td>{item.roles.map(r => r.name).join(', ')} / {item.status}</td><td>
            <button disabled={busy || loading} onClick={() => setAccount({ ...item, role_id: item.roles.length === 1 ? item.roles[0].id : '', password: '' })}>แก้ไข</button>{' '}
            <button disabled={busy || loading} onClick={() => { if (window.confirm(`ยืนยันปิดบัญชี ${item.username}?`)) mutate(`/api/admin/access/users/${item.id}/delete`, { version: item.version }, () => setAccount(emptyUser())); }}>ปิดบัญชี</button>
          </td></tr>)}
        </tbody></table></div>
        {accounts && !accounts.users.length && <p>ไม่พบบัญชีเจ้าหน้าที่</p>}
        <div><button disabled={busy || loading || (accounts?.page || 1) <= 1} onClick={() => setPage((accounts?.page || 1) - 1)}>ก่อนหน้า</button> หน้า {accounts?.page || 1} <button disabled={busy || loading || !accounts || accounts.page * 25 >= accounts.total} onClick={() => setPage(accounts.page + 1)}>ถัดไป</button></div>
        <form onSubmit={e => { e.preventDefault(); const { password, ...fields } = account; mutate(account.id ? `/api/admin/access/users/${account.id}/update` : '/api/admin/access/users', account.id ? fields : { ...fields, password }, () => setAccount(emptyUser())); }}>
          <fieldset disabled={busy || loading} style={{ display: 'grid', gap: '0.75rem', marginTop: '1rem' }}><legend>{account.id ? 'แก้ไขบัญชีเจ้าหน้าที่' : 'เพิ่มบัญชีเจ้าหน้าที่'}</legend>
            <label>ชื่อ <input required maxLength={100} value={account.name} onChange={e => setAccount({ ...account, name: e.target.value })} /></label>
            <label>ชื่อผู้ใช้ <input required pattern="[A-Za-z][A-Za-z0-9_.\-]{2,49}" maxLength={50} value={account.username} onChange={e => setAccount({ ...account, username: e.target.value })} /></label>
            <label>อีเมล <input required type="email" maxLength={100} value={account.email} onChange={e => setAccount({ ...account, email: e.target.value })} /></label>
            <label>บทบาท <select required value={account.role_id} onChange={e => setAccount({ ...account, role_id: e.target.value })}><option value="">เลือกบทบาท</option>{selectableRoles.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}</select></label>
            <label>สถานะ <select value={account.status} onChange={e => setAccount({ ...account, status: e.target.value })}><option value="active">ใช้งาน</option><option value="inactive">ไม่ใช้งาน</option><option value="suspended">ระงับ</option></select></label>
            {!account.id && <label>รหัสผ่านเริ่มต้น <input type="password" required minLength={12} maxLength={200} autoComplete="new-password" value={account.password} onChange={e => setAccount({ ...account, password: e.target.value })} /></label>}
            <div><button className="btn btn-primary">บันทึกบัญชี</button>{' '}<button type="button" onClick={() => setAccount(emptyUser())}>ยกเลิก / เพิ่มใหม่</button></div>
          </fieldset>
        </form>
      </section>
    </>}
  </main>;
}
