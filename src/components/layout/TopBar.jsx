import React, { useState, useEffect } from 'react';
import { Phone, Clock, Sun, Moon } from 'lucide-react';
import { useTheme } from '../../context/ThemeContext';
import { COOP_INFO, INTEREST_RATES } from '../../data/mockData';
import { fetchInterestRates } from '../../services/api';

export default function TopBar() {
  const { theme, toggleTheme } = useTheme();
  const [ratesTicker, setRatesTicker] = useState({
    depositRate: '1.75%',
    emergencyLoanRate: '4.75%'
  });

  useEffect(() => {
    let cancelled = false;
    fetchInterestRates().then(res => {
      if (!cancelled && res?.success && res?.data) {
        let maxDep = '1.75%';
        if (Array.isArray(res.data.deposit) && res.data.deposit.length > 0) {
          const maxVal = Math.max(...res.data.deposit.map(d => parseFloat(d.rate) || 0));
          if (maxVal > 0) maxDep = `${maxVal.toFixed(2)}%`;
        }
        let emerg = '4.75%';
        if (Array.isArray(res.data.loan)) {
          const found = res.data.loan.find(l => l.product_name === 'เงินกู้ฉุกเฉิน');
          if (found) emerg = `${parseFloat(found.rate).toFixed(2)}%`;
        }
        setRatesTicker({ depositRate: maxDep, emergencyLoanRate: emerg });
      }
    }).catch(() => {});
    return () => { cancelled = true; };
  }, []);

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
            <span>เวลาทำการ: 08:30 - 16:30 น.</span>
          </span>
          <span style={{ 
            background: 'rgba(245, 158, 11, 0.15)', 
            color: '#fbbf24', 
            padding: '0.15rem 0.5rem', 
            borderRadius: '4px',
            fontSize: '0.75rem',
            fontWeight: '600'
          }} className="hide-mobile">
            ✨ ดอกเบี้ยเงินฝากสูงสุด {ratesTicker.depositRate} | กู้ฉุกเฉิน {ratesTicker.emergencyLoanRate}
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
            <span style={{ fontSize: '0.75rem' }}>{theme === 'light' ? 'โหมดมืด' : 'โหมดสว่าง'}</span>
          </button>

        </div>

      </div>
    </div>
  );
}
