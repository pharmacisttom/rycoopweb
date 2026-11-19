import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { X, ArrowRight } from 'lucide-react';
import CoopBrandLogo from '../branding/CoopBrandLogo';

// Campaigns are published records only. Never manufacture a promotion from localStorage.
export default function CampaignModal() {
  const [campaigns, setCampaigns] = useState([]);
  const [index, setIndex] = useState(-1);
  const campaign = campaigns[index];

  useEffect(() => {
    const controller = new AbortController();
    let timeout;
    fetch('/api/public/popups', { signal: controller.signal, cache: 'no-cache', credentials: 'same-origin' })
      .then(response => response.ok ? response.json() : null)
      .then(payload => {
        if (!payload?.success || !Array.isArray(payload.data)) return;
        const eligible = payload.data.filter(item => item?.id && item.title
          && !sessionStorage.getItem(`rycoop-popup-seen-${item.id}`)).slice(0, 3);
        if (eligible.length === 0) return;
        setCampaigns(eligible);
        const seconds = Math.max(0, Math.min(Number(eligible[0].delay_seconds) || 0, 10));
        timeout = window.setTimeout(() => setIndex(0), seconds * 1000);
      })
      .catch(() => {});
    return () => { controller.abort(); if (timeout) window.clearTimeout(timeout); };
  }, []);

  const close = () => {
    if (campaign) sessionStorage.setItem(`rycoop-popup-seen-${campaign.id}`, '1');
    setIndex(previous => previous + 1 < campaigns.length ? previous + 1 : -1);
  };

  useEffect(() => {
    if (!campaign) return undefined;
    const onKey = event => { if (event.key === 'Escape') close(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [campaign]);

  if (!campaign) return null;
  const image = campaign.desktop_image || campaign.mobile_image;
  const href = typeof campaign.button_url === 'string' && campaign.button_url.startsWith('/')
    && !campaign.button_url.startsWith('//') ? campaign.button_url : null;

  return <div role="presentation" style={{
    position: 'fixed', inset: 0, zIndex: 2500, background: 'rgba(9,25,48,.82)',
    backdropFilter: 'blur(8px)', display: 'flex', alignItems: 'center',
    justifyContent: 'center', padding: 16
  }}>
    <section role="dialog" aria-modal="true" aria-label={campaign.title}
      style={{ position: 'relative', width: 'min(94vw, 940px)', maxHeight: '92vh',
        overflowY: 'auto', borderRadius: 20, background: 'var(--bg-surface)',
        boxShadow: '0 24px 90px rgba(0,0,0,.35)' }}>
      <button type="button" onClick={close} autoFocus aria-label="ปิดประกาศ"
        style={{position:'absolute',top:12,right:12,zIndex:3,background:'#152842',
          color:'#fff',borderRadius:30,padding:10}}><X size={22}/></button>
      <div style={{padding:'20px 26px 12px',display:'flex',alignItems:'center',gap:14}}>
        <CoopBrandLogo variant="compact" showEnglish={false}/>
        <strong style={{fontSize:'1rem'}}>ประกาศประชาสัมพันธ์สหกรณ์</strong>
      </div>
      {image && <img src={image} alt={campaign.title} loading="eager"
        style={{width:'100%',maxHeight:'min(56vh,560px)',objectFit:'contain',background:'#eff5fb'}}/>}
      <div style={{padding:'20px 28px 28px'}}>
        <h2 style={{fontSize:'clamp(1.2rem,2.4vw,1.75rem)',marginBottom:10}}>{campaign.title}</h2>
        {campaign.content && <p style={{whiteSpace:'pre-line',overflowWrap:'anywhere'}}>{campaign.content}</p>}
        <div style={{display:'flex',flexWrap:'wrap',gap:12,marginTop:20}}>
          <button type="button" onClick={close} className="btn btn-subtle">ปิดประกาศ</button>
          {href && <Link to={href} onClick={close} className="btn btn-primary">
            {campaign.button_text || 'ดูรายละเอียด'} <ArrowRight size={16}/>
          </Link>}
        </div>
      </div>
    </section>
  </div>;
}
