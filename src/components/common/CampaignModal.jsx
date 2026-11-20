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
    let cancelled = false;
    let timer;
    let fetching = false;
    const showCampaignAfterConsent = async () => {
      try {
        if (!localStorage.getItem('coop_cookie_consent_v1')) return;
        if (sessionStorage.getItem('coop_campaign_shown') || fetching || timer) return;
        fetching = true;
        const response = await fetch('/api/public/popup-campaign', {
          credentials: 'include', headers: { Accept: 'application/json' }, cache: 'no-store'
        });
        if (!response.ok || cancelled) return;
        const result = await response.json();
        const item = result?.success ? result.data : null;
        if (!item || cancelled) return; // No active database campaign; never use mock content.
        const data = {
          title: String(item.title || ''),
          subtitle: String(item.content || ''),
          imageUrl: item.image_url || '',
          buttonText: item.button_text || 'ดูรายละเอียดเพิ่มเติม',
          linkUrl: item.button_url || ''
        };
        if (!data.title) return;
        setCampaign(data);
        timer = setTimeout(() => {
          if (cancelled) return;
          setIsOpen(true);
          sessionStorage.setItem('coop_campaign_shown', 'true');
          timer = undefined;
        }, 1200);
      } catch {
        // Fail closed: unavailable public API must never trigger mock content.
      } finally {
        fetching = false;
      }
    };
    showCampaignAfterConsent();
    window.addEventListener('rycoop:cookie-consent-updated', showCampaignAfterConsent);
    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
      window.removeEventListener('rycoop:cookie-consent-updated', showCampaignAfterConsent);
    };
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
