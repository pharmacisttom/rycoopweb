import React, { useState, useEffect, useRef } from 'react';
import './CampaignModal.css';
import { Link } from 'react-router-dom';
import { X, Sparkles, ArrowRight } from 'lucide-react';

export default function CampaignModal() {
  const [isOpen, setIsOpen] = useState(false);
  const [campaign, setCampaign] = useState(null);
  const dialogRef = useRef(null);
  const closeRef = useRef(null);

  useEffect(() => {
    try {
      const saved = localStorage.getItem('coop_popup_campaign');
      const data = saved ? JSON.parse(saved) : {
        enabled: true,
        title: 'โครงการประมาณการเงินปันผลและเฉลี่ยคืน ประจำปี 2567',
        subtitle: 'สมาชิกสามารถคำนวณและตรวจสอบสิทธิประโยชน์ได้แล้ววันนี้ผ่านระบบดิจิทัล',
        imageUrl: '/assets/img/popup_dividend.jpg',
        buttonText: 'ประมาณการเงินปันผลทันที',
        linkUrl: '/dividend-estimator'
      };

      if (data && data.enabled) {
        setCampaign(data);
        // Check if shown in current session
        const alreadyShown = sessionStorage.getItem('coop_campaign_shown');
        if (!alreadyShown) {
          const timer = setTimeout(() => {
            setIsOpen(true);
            sessionStorage.setItem('coop_campaign_shown', 'true');
          }, 1200);
          return () => clearTimeout(timer);
        }
      }
    } catch (e) {
      // ignore
    }
  }, []);

  useEffect(() => {
    if (!isOpen) return undefined;
    const previousFocus = document.activeElement;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    closeRef.current?.focus();
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setIsOpen(false);
      }
      if (event.key === 'Tab' && dialogRef.current) {
        const controls = [...dialogRef.current.querySelectorAll('button:not([disabled]),a[href]')];
        if (controls.length === 0) return;
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = previousOverflow;
      previousFocus?.focus?.();
    };
  }, [isOpen]);

  if (!isOpen || !campaign) return null;

  const close = () => setIsOpen(false);
  const route = typeof campaign.linkUrl === 'string' && /^\/(?!\/)[A-Za-z0-9/_#?=&%-]*$/.test(campaign.linkUrl)
    ? campaign.linkUrl : null;

  return (
    <div className="rycoop-campaign-overlay" onMouseDown={event => {
      if (event.target === event.currentTarget) close();
    }}>
      <div ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="rycoop-campaign-title"
        aria-describedby="rycoop-campaign-description" className="rycoop-campaign-modal">
        <button ref={closeRef} type="button" className="rycoop-campaign-close" onClick={close} aria-label="ปิดหน้าต่างประชาสัมพันธ์">
          <X size={26} />
        </button>
        <div className="rycoop-campaign-visual">
          {campaign.imageUrl && <img src={campaign.imageUrl} alt="" className="rycoop-campaign-image"
            onError={event => { event.currentTarget.style.display = 'none'; }} />}
          <div className="rycoop-campaign-visual-shade" />
          <div className="rycoop-campaign-heading">
            <span className="rycoop-campaign-eyebrow"><Sparkles size={18} aria-hidden="true" /> ข่าวประชาสัมพันธ์สหกรณ์</span>
            <h2 id="rycoop-campaign-title">{campaign.title}</h2>
          </div>
        </div>
        <div className="rycoop-campaign-details">
          <p id="rycoop-campaign-description">{campaign.subtitle}</p>
          <div className="rycoop-campaign-actions">
            <button type="button" className="rycoop-campaign-dismiss" onClick={close}>ปิดหน้าต่าง</button>
            {route && <Link to={route} onClick={close} className="rycoop-campaign-primary">
              <span>{campaign.buttonText || 'ดูรายละเอียดเพิ่มเติม'}</span><ArrowRight size={21} aria-hidden="true" />
            </Link>}
          </div>
        </div>
      </div>
    </div>
  );
}
