import { assetUrl } from '../../utils/environment';
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { X, Sparkles, ArrowRight } from 'lucide-react';

export default function CampaignModal() {
  const [isOpen, setIsOpen] = useState(false);
  const [campaign, setCampaign] = useState(null);

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

  if (!isOpen || !campaign) return null;

  return (
    <div style={{
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      zIndex: 2500,
      background: 'rgba(15, 23, 42, 0.75)',
      backdropFilter: 'blur(8px)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '1rem'
    }}>
      <div
        className="glass-card animate-fade-in"
        style={{
          width: '100%',
          maxWidth: '500px',
          background: 'var(--bg-surface)',
          borderRadius: 'var(--radius-xl)',
          overflow: 'hidden',
          boxShadow: 'var(--shadow-xl)',
          border: '1px solid var(--border-subtle)',
          position: 'relative'
        }}
      >
        {/* Close Button */}
        <button
          onClick={() => setIsOpen(false)}
          style={{
            position: 'absolute',
            top: '0.85rem',
            right: '0.85rem',
            zIndex: 10,
            color: '#ffffff',
            background: 'rgba(15, 23, 42, 0.6)',
            padding: '0.35rem',
            borderRadius: '50%',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center'
          }}
          aria-label="Close campaign popup"
        >
          <X size={18} />
        </button>

        {/* Campaign Banner Image */}
        <div style={{ height: '220px', background: 'var(--gradient-hero)', position: 'relative', overflow: 'hidden' }}>
          <img
            src={assetUrl(campaign.imageUrl || '/assets/img/popup_dividend.jpg')}
            alt={campaign.title}
            style={{ width: '100%', height: '100%', objectFit: 'cover' }}
            onError={(e) => {
              e.target.style.display = 'none';
            }}
          />
          <div style={{
            position: 'absolute',
            bottom: 0,
            left: 0,
            right: 0,
            padding: '1rem 1.25rem',
            background: 'linear-gradient(to top, rgba(15, 23, 42, 0.95), transparent)',
            color: '#ffffff'
          }}>
            <span className="badge badge-gold" style={{ marginBottom: '0.25rem' }}>
              <Sparkles size={12} /> สิทธิประโยชน์สมาชิก
            </span>
            <h3 style={{ fontSize: '1.15rem', fontWeight: 800, color: '#ffffff', lineHeight: 1.3 }}>
              {campaign.title}
            </h3>
          </div>
        </div>

        {/* Content & Action */}
        <div style={{ padding: '1.5rem' }}>
          <p style={{ fontSize: '0.88rem', color: 'var(--text-muted)', lineHeight: 1.6, marginBottom: '1.5rem' }}>
            {campaign.subtitle}
          </p>

          <div style={{ display: 'flex', gap: '0.75rem' }}>
            <button
              onClick={() => setIsOpen(false)}
              className="btn btn-subtle"
              style={{ flex: 1 }}
            >
              ปิดหน้าต่าง
            </button>
            <Link
              to={campaign.linkUrl || '/dividend-estimator'}
              onClick={() => setIsOpen(false)}
              className="btn btn-primary"
              style={{ flex: 1.5 }}
            >
              <span>{campaign.buttonText || 'ดูรายละเอียด'}</span>
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>

      </div>
    </div>
  );
}
