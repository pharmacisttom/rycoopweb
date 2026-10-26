import { usePublicInformation } from '../../context/PublicContentContext';
import React from 'react';
import { Phone, Clock, Sun, Moon } from 'lucide-react';
import { useTheme } from '../../context/ThemeContext';


export default function TopBar() {
  const { COOP_INFO } = usePublicInformation();
  const { theme, toggleTheme } = useTheme();

  return (
    <div style={{
      background: 'var(--primary-950)',
      color: '#cbd5e1',
      fontSize: '0.82rem',
      padding: '0.45rem 0',
      borderBottom: '1px solid rgba(255,255,255,0.08)'
    }}>
      <div className="container" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem' }}>
        
        {/* Left: Contact Info & Rates Ticker */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '1.25rem', flexWrap: 'wrap' }}>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
            <Phone size={14} style={{ color: 'var(--accent-gold)' }} />
            <span>โทร: <strong>{COOP_INFO.phone.split(',')[0]}</strong></span>
          </span>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }} className="hide-mobile">
            <Clock size={14} style={{ color: 'var(--accent-teal)' }} />
            <span>{COOP_INFO.workHours}</span>
          </span>
          <span style={{ 
            background: 'rgba(245, 158, 11, 0.15)', 
            color: '#fbbf24', 
            padding: '0.15rem 0.5rem', 
            borderRadius: '4px',
            fontSize: '0.75rem',
            fontWeight: '600'
          }} className="hide-mobile">
            ตรวจสอบอัตราดอกเบี้ยและเงื่อนไขล่าสุดกับสหกรณ์
          </span>
        </div>

        {/* Right: Theme Toggle */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.85rem' }}>
          
          <button 
            onClick={toggleTheme}
            style={{ 
              display: 'inline-flex', 
              alignItems: 'center', 
              gap: '0.35rem', 
              background: 'rgba(255,255,255,0.08)', 
              color: '#e2e8f0', 
              padding: '0.25rem 0.65rem', 
              borderRadius: '6px',
              fontSize: '0.75rem',
              fontWeight: '500',
              border: '1px solid rgba(255,255,255,0.1)'
            }}
            title={theme === 'light' ? 'สลับเป็นโหมดกลางคืน' : 'สลับเป็นโหมดกลางวัน'}
          >
            {theme === 'light' ? <Moon size={14} style={{ color: '#fbbf24' }} /> : <Sun size={14} style={{ color: '#f59e0b' }} />}
            <span>{theme === 'light' ? 'Dark Mode' : 'Light Mode'}</span>
          </button>

        </div>

      </div>
    </div>
  );
}
