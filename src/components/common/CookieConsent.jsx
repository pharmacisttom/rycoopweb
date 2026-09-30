import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Cookie, ShieldCheck } from 'lucide-react';

const COOKIE_CONSENT_KEY = 'coop_cookie_consent_v1';

function hasSavedConsent() {
  try {
    return Boolean(window.localStorage.getItem(COOKIE_CONSENT_KEY));
  } catch {
    return false;
  }
}

export default function CookieConsent() {
  const [isVisible, setIsVisible] = useState(() => !hasSavedConsent());

  const saveChoice = (choice) => {
    try {
      window.localStorage.setItem(COOKIE_CONSENT_KEY, choice);
    } finally {
      setIsVisible(false);
    }
  };

  if (!isVisible) return null;

  return (
    <aside className="cookie-consent" aria-label="การตั้งค่าคุกกี้">
      <div className="cookie-consent__panel">
        <div className="cookie-consent__icon" aria-hidden="true">
          <Cookie size={24} />
        </div>
        <div className="cookie-consent__content">
          <h2>เว็บไซต์นี้ใช้คุกกี้</h2>
          <p id="cookie-consent-description">
            เราใช้คุกกี้ที่จำเป็นต่อการทำงานของเว็บไซต์และการจดจำการตั้งค่าของคุณ เพื่อให้การใช้งานสะดวกและเหมาะสมยิ่งขึ้น
            {' '}<Link to="/privacy/cookies">อ่านนโยบายความเป็นส่วนตัว</Link>
          </p>
        </div>
        <div className="cookie-consent__actions" aria-describedby="cookie-consent-description">
          <button type="button" className="cookie-consent__reject" onClick={() => saveChoice('rejected')}>
            ปฏิเสธ
          </button>
          <button type="button" className="cookie-consent__accept" onClick={() => saveChoice('accepted')}>
            <ShieldCheck size={18} aria-hidden="true" />
            ยอมรับทั้งหมด
          </button>
        </div>
      </div>
    </aside>
  );
}
