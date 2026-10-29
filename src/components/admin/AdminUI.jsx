import React, { useEffect, useId, useRef } from 'react';
import { Link } from 'react-router-dom';
import './AdminUI.css';

export function AdminBreadcrumb({ items = [] }) {return <nav className="acc-breadcrumbs" aria-label="เส้นทางหน้าปัจจุบัน">{items.map((item,index)=><React.Fragment key={`${item.label}-${index}`}>{index>0&&<span aria-hidden="true">/</span>}{item.to?<Link to={item.to}>{item.label}</Link>:<span aria-current={index===items.length-1?'page':undefined}>{item.label}</span>}</React.Fragment>)}</nav>;}

export function AdminPageHeader({ title, description, actions }) {
  return <header className="admin-page-heading"><div><h1>{title}</h1>{description && <p>{description}</p>}</div>{actions && <div className="admin-actions">{actions}</div>}</header>;
}
export function AdminCard({ title, description, children, actions }) {
  return <section className="admin-card">{title && <header className="admin-card-heading"><div><h2>{title}</h2>{description && <p>{description}</p>}</div>{actions}</header>}{children}</section>;
}
export function AdminFormField({ label, children, help, required = false }) {
  return <label className="admin-field"><span>{label}{required && <span aria-hidden="true"> *</span>}</span>{children}{help && <small>{help}</small>}</label>;
}
const statuses = { active: 'ใช้งาน', inactive: 'ไม่ใช้งาน', suspended: 'ระงับ', pending: 'รอตรวจสอบ', approved: 'อนุมัติ', rejected: 'ไม่อนุมัติ', expired: 'หมดอายุ', cancelled: 'ยกเลิก', published: 'เผยแพร่', draft: 'ฉบับร่าง', submitted: 'ส่งตรวจ' };
export function AdminStatusBadge({ status, children }) {
  return <span className={`admin-status admin-status--${['active','approved','published'].includes(status)?'success':['suspended','rejected','expired'].includes(status)?'danger':'neutral'}`}>{children || statuses[status] || status || '—'}</span>;
}
export function AdminEmptyState({ children = 'ยังไม่มีข้อมูล' }) { return <p className="admin-empty" role="status">{children}</p>; }
export function AdminErrorState({ message, onRetry }) { return <div className="admin-error" role="alert"><p>{message}</p>{onRetry && <button type="button" className="acc-btn" onClick={onRetry}>ลองใหม่</button>}</div>; }
export function AdminSkeleton() { return <div className="admin-skeleton" role="status" aria-label="กำลังโหลดข้อมูล">กำลังโหลดข้อมูล…</div>; }
export function AdminDataTable({ label, columns, rows, rowKey = 'id', emptyMessage }) {
  return <><p className="admin-table-hint">เลื่อนตารางแนวนอนเพื่อดูคอลัมน์และปุ่มจัดการทั้งหมด</p><div className="admin-table-scroll" role="region" aria-label={label} tabIndex={0}><table className="admin-table">
    <caption className="admin-sr-only">{label}</caption><colgroup>{columns.map(column => <col key={column.key} style={column.width?{width:column.width}:undefined}/>)}</colgroup>
    <thead><tr>{columns.map(column => <th scope="col" key={column.key} className={column.numeric?'admin-numeric':''}>{column.label}</th>)}</tr></thead>
    <tbody>{rows.length ? rows.map((row, index) => <tr key={row[rowKey]??index}>{columns.map(column => <td key={column.key} className={column.numeric?'admin-numeric':''}>{column.render?column.render(row):row[column.key]??'—'}</td>)}</tr>) : <tr><td colSpan={columns.length}><AdminEmptyState>{emptyMessage}</AdminEmptyState></td></tr>}</tbody>
  </table></div></>;
}
export function AdminPagination({ page, total, perPage = 25, onChange, disabled }) {
  const pages=Math.max(1,Math.ceil(total/perPage));
  return <nav className="admin-pagination" aria-label="หน้าตาราง"><span>{total.toLocaleString('th-TH')} รายการ · หน้า {page} / {pages}</span><div className="admin-actions">
    <button type="button" className="acc-btn" disabled={disabled||page<=1} onClick={()=>onChange(page-1)}>ก่อนหน้า</button>
    <button type="button" className="acc-btn" disabled={disabled||page>=pages} onClick={()=>onChange(page+1)}>ถัดไป</button>
  </div></nav>;
}
export function AdminModal({ open, title, children, onClose, busy = false }) {
  const dialog=useRef(null);const titleId=useId();
  useEffect(()=>{if(open&&!dialog.current.open)dialog.current.showModal();else if(!open&&dialog.current.open)dialog.current.close();},[open]);
  return <dialog ref={dialog} className="admin-dialog" aria-labelledby={titleId} aria-busy={busy}
    onCancel={event=>{event.preventDefault();if(!busy)onClose();}} onClick={event=>{if(event.target===event.currentTarget&&!busy)onClose();}}>
    <header className="admin-dialog-heading"><h2 id={titleId}>{title}</h2><button type="button" className="acc-btn" disabled={busy} onClick={onClose} aria-label="ปิดหน้าต่าง">ปิด</button></header>{children}
  </dialog>;
}
export function AdminPermissionMatrix({ catalog, modules, actions, value, onChange, disabled }) {
  const actionKeys=[...new Set(Object.values(catalog).flat())];
  return <div className="admin-table-scroll" role="region" aria-label="ตารางสิทธิ์ตามโมดูล" tabIndex={0}><table className="admin-table admin-permission-table"><caption>เลือกเฉพาะสิทธิ์ที่จำเป็นต่อหน้าที่</caption>
    <thead><tr><th scope="col">โมดูล</th>{actionKeys.map(action=><th scope="col" key={action}>{actions[action]||action}</th>)}</tr></thead><tbody>
      {Object.entries(catalog).map(([module, available])=><tr key={module}><th scope="row">{modules[module]||module}</th>{actionKeys.map(action=>{
        const key=`${module}.${action}`,sensitive=/manage|restore|approve|suspend|delete|revoke/.test(key);
        return <td key={action}>{available.includes(action)?<label className={sensitive?'admin-sensitive-permission':''}><input type="checkbox" disabled={disabled} checked={value.includes(key)} aria-label={`${modules[module]||module}: ${actions[action]||action}${sensitive?' (สิทธิ์สำคัญ)':''}`} onChange={event=>onChange(event.target.checked?[...value,key]:value.filter(item=>item!==key))}/>{sensitive&&<span aria-hidden="true"> ⚠</span>}</label>:<span aria-label="ไม่รองรับสิทธิ์นี้">—</span>}</td>;
      })}</tr>)}
    </tbody></table></div>;
}
