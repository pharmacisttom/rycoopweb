import React from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter } from 'react-router-dom';
import CookieConsent from '../../src/components/common/CookieConsent';
import { ToastProvider, useToast } from '../../src/context/ToastContext';
import '../../src/index.css';

function TestActions() {
  const { toast, confirmDialog } = useToast();
  return <div>
    <button id="test-toast" onClick={() => toast.success('ตรวจสอบ SweetAlert2 สำเร็จ', 'ระบบแจ้งเตือน')}>Test toast</button>
    <button id="test-confirm" onClick={() => { void confirmDialog({title:'ยืนยันการทดสอบ',message:'ทดสอบปุ่มยืนยัน',type:'primary'}); }}>Test confirm</button>
    <CookieConsent />
  </div>;
}

createRoot(document.getElementById('root')).render(
  <MemoryRouter initialEntries={['/admin/cms']}>
    <ToastProvider><TestActions /></ToastProvider>
  </MemoryRouter>
);
