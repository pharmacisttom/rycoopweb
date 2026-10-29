import React, { useRef, useState } from 'react';
import coopLogo from '../../assets/branding/coop-logo.webp';
import './CoopBrandLogo.css';

export const COOP_LOGO_ALT = 'ตราสัญลักษณ์สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด';
export default function CoopBrandLogo({ variant = 'horizontal', tone = 'light', showEnglish = false,
  showEstablished = false, establishedYear = 2530, nameTh = 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
  nameEn = 'Rayong Public Health Savings and Credit Cooperative Limited', className = '', size }) {
  const reported = useRef(false);
  const [failed, setFailed] = useState(false);
  return <span className={`coop-brand coop-brand--${variant} coop-brand--${tone} ${className}`}
    style={size ? { '--coop-logo-size': `${size}px` } : undefined} data-load-state={failed ? 'failed' : 'ready'}>
    <img src={coopLogo} alt={COOP_LOGO_ALT} width="56" height="56" className="coop-brand-logo"
      onError={() => {
        if (reported.current) return;
        reported.current = true; setFailed(true);
        // Fixed diagnostic only: no session data, document URLs or credentials.
        console.warn('COOP_BRAND_IMAGE_UNAVAILABLE');
      }} />
    {variant !== 'emblem' && <span className="coop-brand-text">
      <span className="coop-brand-name">{nameTh === 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด'
        ? <>สหกรณ์ออมทรัพย์สาธารณสุข<span className="coop-brand-name-line">ระยอง จำกัด</span></> : nameTh}</span>
      {showEnglish && <span className="coop-brand-english">{nameEn}</span>}
      {showEstablished && <span className="coop-brand-caption">ก่อตั้งเมื่อปี พ.ศ. {establishedYear}</span>}
    </span>}
  </span>;
}
