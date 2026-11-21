import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Cookie, ShieldCheck } from 'lucide-react';

const CHOICE_KEY = 'coop_cookie_consent_v1';
const RECEIPT_KEY = 'coop_cookie_consent_recorded_v2';
const ID_KEY = 'coop_cookie_consent_anonymous_id_v2';

function read(key) {
  try { return window.localStorage.getItem(key); } catch { return null; }
}

export default function CookieConsent() {
  const { pathname } = useLocation();
  // Re-prompt pre-RC12 visitors once so choices previously held only in
  // localStorage can be recorded with clear user action, never inferred.
  const [isVisible, setIsVisible] = useState(() => !read(RECEIPT_KEY));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const saveChoice = async choice => {
    if (saving) return;
    setSaving(true);
    setError('');
    try {
      const existingId = read(ID_KEY);
      const anonymousId = existingId && /^[a-f0-9-]{36}$/.test(existingId)
        ? existingId : window.crypto.randomUUID();
      const tokenRes = await fetch('/csrf-token', {
        credentials: 'include', cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!tokenRes.ok) throw new Error('รับรหัสยืนยันความปลอดภัยไม่สำเร็จ');
      const { token } = await tokenRes.json();
      if (!token) throw new Error('ไม่มีรหัสยืนยันความปลอดภัย');
      const isAccepted = choice === 'accepted';
      const response = await fetch('/api/cookie-consent', {
        method: 'POST',
        credentials: 'include',
        cache: 'no-store',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({
          anonymous_consent_id: anonymousId,
          consent_version: '2.0',
          necessary: 1,
          functional: Number(isAccepted),
          analytics: Number(isAccepted),
          marketing: Number(isAccepted),
          third_party: Number(isAccepted)
        })
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) {
        throw new Error(result.message || 'ยังไม่สามารถบันทึกการตั้งค่าคุกกี้ได้');
      }
      // Mark consent only AFTER the server has acknowledged persistence.
      window.localStorage.setItem(ID_KEY, anonymousId);
      window.localStorage.setItem(CHOICE_KEY, choice);
      window.localStorage.setItem(RECEIPT_KEY, '2.0');
      window.dispatchEvent(new CustomEvent('rycoop:cookie-consent-updated', { detail: { choice } }));
      setIsVisible(false);
    } catch (e) {
      setError(e?.message || 'บันทึกไม่สำเร็จ กรุณาลองอีกครั้ง');
    } finally {
      setSaving(false);
    }
  };

  // Never interrupt OAuth/TOTP enrollment or verification with a privacy dialog.
  // The prompt appears on the next regular page if acknowledgment is missing.
  if (/^\/(?:admin\/(?:login|mfa|2fa)(?:\/|$)|member\/login(?:\/|$)|login(?:\/|$))/.test(pathname)) return null;

  if (!isVisible) {
    return <button type="button" className="cookie-consent__reopen"
      style={{position:'fixed',bottom:12,left:12,zIndex:11001,padding:'7px 12px',borderRadius:24,border:'1px solid #b9c8d8',background:'#fff',color:'#143960',boxShadow:'0 3px 12px #0002',cursor:'pointer',fontSize:12}}
      onClick={() => { setError(''); setIsVisible(true); }}
      aria-label="เปลี่ยนการตั้งค่าคุกกี้">ตั้งค่าคุกกี้</button>;
  }

  return (
    <aside className="cookie-consent" role="dialog" aria-modal="true" aria-labelledby="rycoop-cookie-title" aria-describedby="cookie-consent-description">
      <div className="cookie-consent__panel">
        <div className="cookie-consent__icon" aria-hidden="true"><Cookie size={24} /></div>
        <div className="cookie-consent__content">
          <h2 id="rycoop-cookie-title">เว็บไซต์นี้ใช้คุกกี้</h2>
          <p id="cookie-consent-description">
            เราใช้คุกกี้จำเป็นเพื่อให้เว็บไซต์ทำงาน และขอความยินยอมสำหรับคุกกี้ประเภทอื่น
            คุณสามารถยอมรับ ปฏิเสธ หรือเปลี่ยนการเลือกได้ภายหลัง
            {' '}<Link to="/privacy/cookies">อ่านนโยบายคุกกี้</Link>
          </p>
          {error && <p role="alert" style={{color:'#a71d2a'}}>{error} — ยังไม่ได้บันทึกการเลือกครั้งนี้</p>}
        </div>
        <div className="cookie-consent__actions" aria-describedby="cookie-consent-description">
          <button type="button" className="cookie-consent__reject" disabled={saving} onClick={() => saveChoice('rejected')}>ปฏิเสธ</button>
          <button type="button" className="cookie-consent__accept" disabled={saving} onClick={() => saveChoice('accepted')}>
            <ShieldCheck size={18} aria-hidden="true" />{saving ? 'กำลังบันทึก...' : 'ยอมรับทั้งหมด'}
          </button>
        </div>
      </div>
    </aside>
  );
}
