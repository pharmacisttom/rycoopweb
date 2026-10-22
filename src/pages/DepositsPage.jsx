import React, { useState, useEffect } from 'react';
import { Landmark, TrendingUp, ShieldCheck, CheckCircle2, FileText, Download } from 'lucide-react';
import { Link } from 'react-router-dom';
import { INTEREST_RATES } from '../data/mockData';
import { fetchInterestRates } from '../services/api';

export default function DepositsPage() {
  const [depositsList, setDepositsList] = useState(INTEREST_RATES.deposits);
  const [effectiveDate, setEffectiveDate] = useState(INTEREST_RATES.effectiveDate);

  useEffect(() => {
    let cancelled = false;
    fetchInterestRates().then(res => {
      if (!cancelled && res?.success && res?.data?.deposit?.length > 0) {
        const mapped = res.data.deposit.map(item => ({
          type: item.product_name,
          rate: `${parseFloat(item.rate).toFixed(2)}%`,
          minDeposit: item.min_amount != null ? `${Number(item.min_amount).toLocaleString('th-TH')} บาท` : 'ตามประกาศสหกรณ์',
          payFreq: item.condition_note || 'ตามประกาศสหกรณ์',
          highlight: parseFloat(item.rate) >= 1.75
        }));
        setDepositsList(mapped);
        if (res.data.deposit[0]?.effective_date) {
          const d = new Date(res.data.deposit[0].effective_date);
          if (!isNaN(d.getTime())) {
            setEffectiveDate(`${d.getDate()} ${['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'][d.getMonth()]} ${d.getFullYear() + 543}`);
          }
        }
      }
    }).catch(() => {});
    return () => { cancelled = true; };
  }, []);

  return (
    <div className="section">
      <div className="container">
        
        <div className="section-title-wrap">
          <span className="section-badge">ผลิตภัณฑ์เงินฝาก</span>
          <h1 className="section-title">เงินฝากและอัตราดอกเบี้ย</h1>
          <p className="section-subtitle">ออมเงินอย่างมั่นคง ดอกเบี้ยสูงกว่าธนาคารพาณิชย์ และไม่ถูกหักภาษี ณ ที่จ่าย</p>
          <div className="section-line" />
        </div>

        {/* Benefits Highlights */}
        <div className="grid-3" style={{ marginBottom: '2.5rem' }}>
          <div className="surface-card" style={{ padding: '1.5rem', display: 'flex', gap: '1rem', alignItems: 'flex-start' }}>
            <div style={{ padding: '0.65rem', borderRadius: '10px', background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)' }}>
              <TrendingUp size={24} />
            </div>
            <div>
              <h4 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '0.2rem' }}>ผลตอบแทนคุ้มค่า</h4>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>ให้อัตราดอกเบี้ยสูง ดอกเบี้ยเงินฝากออมทรัพย์พิเศษสูงสุดถึง 1.75% ต่อปี</p>
            </div>
          </div>

          <div className="surface-card" style={{ padding: '1.5rem', display: 'flex', gap: '1rem', alignItems: 'flex-start' }}>
            <div style={{ padding: '0.65rem', borderRadius: '10px', background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)' }}>
              <ShieldCheck size={24} />
            </div>
            <div>
              <h4 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '0.2rem' }}>ยกเว้นภาษีดอกเบี้ย</h4>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>ดอกเบี้ยเงินฝากสหกรณ์ได้รับการยกเว้นภาษีเงินได้บุคคลธรรมดา 100%</p>
            </div>
          </div>

          <div className="surface-card" style={{ padding: '1.5rem', display: 'flex', gap: '1rem', alignItems: 'flex-start' }}>
            <div style={{ padding: '0.65rem', borderRadius: '10px', background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
              <Landmark size={24} />
            </div>
            <div>
              <h4 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '0.2rem' }}>ใช้เป็นหลักประกันเงินกู้</h4>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>สามารถใช้สมุดบัญชีเงินฝากค้ำประกันเงินกู้ได้สูงสุดถึง 90 - 100%</p>
            </div>
          </div>
        </div>

        {/* Rates Table */}
        <div className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '3rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '0.5rem' }}>
            <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-800)' }}>ตารางประกาศอัตราดอกเบี้ยเงินฝาก</h3>
            <span className="badge badge-gold">มีผลบังคับใช้ {effectiveDate}</span>
          </div>

          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.92rem' }}>
              <thead>
                <tr style={{ background: 'var(--bg-subtle)', borderBottom: '2px solid var(--border-subtle)', textAlign: 'left' }}>
                  <th style={{ padding: '0.85rem' }}>ประเภทเงินฝาก</th>
                  <th style={{ padding: '0.85rem', textAlign: 'center' }}>อัตราดอกเบี้ย (% ต่อปี)</th>
                  <th style={{ padding: '0.85rem' }}>เปิดบัญชีขั้นต่ำ</th>
                  <th style={{ padding: '0.85rem' }}>การจ่ายดอกเบี้ย / เงื่อนไข</th>
                </tr>
              </thead>
              <tbody>
                {depositsList.map((item, idx) => (
                  <tr key={idx} style={{ borderBottom: '1px solid var(--border-subtle)', background: item.highlight ? 'rgba(37, 99, 235, 0.03)' : 'transparent' }}>
                    <td style={{ padding: '0.85rem', fontWeight: 600 }}>{item.type}</td>
                    <td style={{ padding: '0.85rem', textAlign: 'center', fontWeight: 800, color: 'var(--primary-600)', fontSize: '1.1rem', fontFamily: 'var(--font-display)' }}>
                      {item.rate}
                    </td>
                    <td style={{ padding: '0.85rem' }}>{item.minDeposit}</td>
                    <td style={{ padding: '0.85rem', color: 'var(--text-muted)' }}>{item.payFreq}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Account Opening Guide */}
        <div className="surface-card" style={{ padding: '2rem', borderRadius: 'var(--radius-lg)' }}>
          <h3 style={{ fontSize: '1.2rem', color: 'var(--primary-800)', marginBottom: '1rem' }}>หลักฐานประกอบการเปิดบัญชีเงินฝาก</h3>
          <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '0.6rem', fontSize: '0.9rem', color: 'var(--text-muted)', marginBottom: '1.5rem' }}>
            <li style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <CheckCircle2 size={16} style={{ color: 'var(--accent-teal)' }} />
              <span>สำเนาบัตรประจำตัวประชาชน พร้อมลงนามรับรองสำเนาถูกต้อง</span>
            </li>
            <li style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <CheckCircle2 size={16} style={{ color: 'var(--accent-teal)' }} />
              <span>สำเนาทะเบียนบ้าน</span>
            </li>
            <li style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <CheckCircle2 size={16} style={{ color: 'var(--accent-teal)' }} />
              <span>เงินสดสำหรับเปิดบัญชีตามยอดขั้นต่ำของแต่ละประเภทเงินฝาก</span>
            </li>
          </ul>

          <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
            <Link to="/documents" className="btn btn-outline btn-sm">
              <Download size={16} />
              <span>ดาวน์โหลดแบบฟอร์มเปิดบัญชี</span>
            </Link>
            <Link to="/contact" className="btn btn-subtle btn-sm">
              <FileText size={16} />
              <span>ติดต่อเจ้าหน้าที่การเงิน</span>
            </Link>
          </div>
        </div>

      </div>
    </div>
  );
}
