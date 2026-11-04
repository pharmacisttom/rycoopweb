import React from 'react';
import { AlertCircle } from 'lucide-react';

export default function FormValidationSummary({
  errors = {},
  title = 'กรุณาตรวจสอบข้อมูลที่ไม่ถูกต้อง',
}) {
  if (!errors) return null;

  const errorList = [];
  if (typeof errors === 'string') {
    errorList.push(errors);
  } else if (Array.isArray(errors)) {
    errorList.push(...errors);
  } else if (typeof errors === 'object') {
    Object.entries(errors).forEach(([field, list]) => {
      if (Array.isArray(list)) {
        errorList.push(...list);
      } else if (typeof list === 'string') {
        errorList.push(list);
      }
    });
  }

  if (errorList.length === 0) return null;

  return (
    <div
      className="admin-validation-summary"
      style={{
        padding: '14px 16px',
        backgroundColor: '#fffbeb',
        border: '1px solid #fde68a',
        borderRadius: '10px',
        color: '#92400e',
        marginBottom: '20px',
        fontSize: '0.9rem',
      }}
      role="alert"
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: '8px', fontWeight: 600, marginBottom: '6px' }}>
        <AlertCircle size={18} style={{ color: '#d97706' }} />
        <span>{title}</span>
      </div>
      <ul style={{ margin: 0, paddingLeft: '24px', lineHeight: 1.6 }}>
        {errorList.map((err, idx) => (
          <li key={idx}>{err}</li>
        ))}
      </ul>
    </div>
  );
}
