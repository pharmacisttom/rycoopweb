import React from 'react';
import { Landmark, TrendingUp, ShieldCheck, CheckCircle2, FileText, Download } from 'lucide-react';
import { Link } from 'react-router-dom';
import { INTEREST_RATES } from '../data/mockData';

export default function DepositsPage() {
  return (
    <div className="section">
      <div className="container">
        
        <div className="section-title-wrap">
          <span className="section-badge">ผลิตภัณฑ์เงินฝาก</span>
          <h1 className="section-title">เงินฝากและอัตราดอกเบี้ย</h1>
          <p className="section-subtitle">ประเภทเงินฝากและข้อมูลอ้างอิงจากเว็บไซต์สหกรณ์</p>
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
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>ตรวจสอบอัตราดอกเบี้ยและวันที่มีผลตามประกาศล่าสุดก่อนฝากเงิน</p>
            </div>
          </div>

          <div className="surface-card" style={{ padding: '1.5rem', display: 'flex', gap: '1rem', alignItems: 'flex-start' }}>
            <div style={{ padding: '0.65rem', borderRadius: '10px', background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)' }}>
              <ShieldCheck size={24} />
            </div>
            <div>
              <h4 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '0.2rem' }}>เงื่อนไขบัญชีเงินฝาก</h4>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>สอบถามเงื่อนไขการจ่ายดอกเบี้ยและภาษีที่เกี่ยวข้องกับเจ้าหน้าที่</p>
            </div>
          </div>

          <div className="surface-card" style={{ padding: '1.5rem', display: 'flex', gap: '1rem', alignItems: 'flex-start' }}>
            <div style={{ padding: '0.65rem', borderRadius: '10px', background: 'var(--primary-100)', color: 'var(--primary-700)' }}>
              <Landmark size={24} />
            </div>
            <div>
              <h4 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '0.2rem' }}>ใช้เป็นหลักประกันเงินกู้</h4>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>การใช้เงินฝากเป็นหลักประกันขึ้นอยู่กับประเภทเงินกู้และระเบียบที่เกี่ยวข้อง</p>
            </div>
          </div>
        </div>

        {/* Rates Table */}
        <div className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '3rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '0.5rem' }}>
            <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-800)' }}>ตารางประกาศอัตราดอกเบี้ยเงินฝาก</h3>
            <span className="badge badge-gold">ตรวจเทียบหน้าเว็บเดิม {INTEREST_RATES.depositSource.checkedOn}</span>
          </div>
          <p style={{ marginBottom: '1rem', color: 'var(--text-muted)' }}>
            {INTEREST_RATES.depositSource.note}{' '}
            <a href={INTEREST_RATES.depositSource.url} target="_blank" rel="noopener noreferrer">ดูแหล่งข้อมูล</a>
          </p>

          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.92rem' }}>
              <thead>
                <tr style={{ background: 'var(--bg-subtle)', borderBottom: '2px solid var(--border-subtle)', textAlign: 'left' }}>
                  <th style={{ padding: '0.85rem' }}>ประเภทเงินฝาก</th>
                  <th style={{ padding: '0.85rem', textAlign: 'center' }}>อัตราดอกเบี้ย (% ต่อปี)</th>
                  <th style={{ padding: '0.85rem' }}>เปิดบัญชีขั้นต่ำ</th>
                  <th style={{ padding: '0.85rem' }}>การจ่ายดอกเบี้ย</th>
                </tr>
              </thead>
              <tbody>
                {INTEREST_RATES.deposits.map((item, idx) => (
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

          <div style={{ display: 'flex', gap: '1rem' }}>
            <Link to="/documents" className="btn btn-outline">
              <Download size={16} />
              <span>ดาวน์โหลดคำขอเปิดบัญชีเงินฝาก</span>
            </Link>
          </div>
        </div>

      </div>
    </div>
  );
}
