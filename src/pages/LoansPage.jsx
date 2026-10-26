import React from 'react';
import { Banknote, CheckCircle2, Download, FileCheck, Home, Zap } from 'lucide-react';
import { Link } from 'react-router-dom';
import { usePublicInformation } from '../context/PublicContentContext';

function makeCategories(LOAN_PRODUCTS) {
const loanById = (id) => LOAN_PRODUCTS.find((product) => product.id === id);

const categories = [
  {
    id: 'emergency', icon: Zap, eyebrow: 'สำหรับความจำเป็นเร่งด่วน', title: 'เงินกู้เพื่อเหตุฉุกเฉิน',
    description: 'ทางเลือกสำหรับเหตุจำเป็นเฉพาะหน้า ด้วยวงเงินและระยะเวลาผ่อนชำระที่เหมาะกับการแก้ปัญหาเร่งด่วน',
    products: [loanById('emergency')], color: 'var(--accent-rose)', background: 'var(--accent-rose-light)',
  },
  {
    id: 'ordinary', icon: Banknote, eyebrow: 'เพื่อสวัสดิการและการศึกษา', title: 'เงินกู้สามัญ & สามัญศึกษา',
    description: 'รองรับแผนการเงินระยะกลางถึงระยะยาว ทั้งการพัฒนาคุณภาพชีวิตของสมาชิกและการศึกษาของสมาชิกหรือบุตร',
    products: [loanById('ordinary'), loanById('education')], color: 'var(--accent-gold-dark)', background: 'var(--accent-gold-light)',
  },
  {
    id: 'housing', icon: Home, eyebrow: 'เพื่อที่อยู่อาศัยของสมาชิก', title: 'เงินกู้พิเศษเพื่อเคหะ',
    description: 'สำหรับซื้อที่ดิน สร้างบ้าน รีไฟแนนซ์ หรือดำเนินการเกี่ยวกับอสังหาริมทรัพย์ โดยใช้หลักทรัพย์เป็นประกัน',
    products: [loanById('special'), loanById('life_security')], color: 'var(--accent-teal-dark)', background: 'var(--accent-teal-light)',
  },
  {
    id: 'other', icon: Banknote, eyebrow: 'สินเชื่อประเภทอื่น', title: 'สินค้า คุณภาพชีวิต และรถยนต์',
    description: 'ข้อมูลตามประเภทเงินกู้ในเว็บไซต์เดิมของสหกรณ์',
    products: [loanById('goods_installment'), loanById('life_development'), loanById('car_purchase')],
    color: 'var(--primary-700)', background: 'var(--primary-100)',
  },
 ];
const assigned = new Set(categories.flatMap(c=>c.products.filter(Boolean).map(p=>p.id)));
categories[categories.length-1].products.push(...LOAN_PRODUCTS.filter(p=>!assigned.has(p.id)));
return categories;

}

function LoanProductCard({ product }) {
  return (
    <article className="surface-card" style={{ padding: '1.75rem', display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
      <div>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '0.75rem', marginBottom: '1rem' }}>
          <span className={`badge badge-${product.badgeColor}`}>{product.badge}</span>
          <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', textAlign: 'right' }}>{product.period}</span>
        </div>
        <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', marginBottom: '0.4rem' }}>{product.title}</h3>
        <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)', marginBottom: '1.25rem', lineHeight: 1.6 }}>{product.subtitle}</p>
        <div style={{ background: 'var(--bg-subtle)', padding: '1rem', borderRadius: 'var(--radius-md)', marginBottom: '1.25rem' }}>
          <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>วงเงินกู้สูงสุด</div>
          <div style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--primary-700)' }}>{product.maxAmount}</div>
          <div style={{ fontSize: '0.9rem', fontWeight: 700, color: 'var(--accent-gold-dark)', marginTop: '0.25rem' }}>อัตราดอกเบี้ย {product.interestRate}</div>
          <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: '0.35rem' }}>หลักประกัน: {product.guarantee}</div>
        </div>
        <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '0.5rem', marginBottom: '1.5rem', fontSize: '0.87rem' }}>
          {product.features.map((feature) => (
            <li key={feature} style={{ display: 'flex', alignItems: 'flex-start', gap: '0.45rem' }}>
              <CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" />
              <span>{feature}</span>
            </li>
          ))}
        </ul>
      </div>
      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
        <Link to={`/calculator?loan=${product.id}`} className="btn btn-outline btn-sm" style={{ flex: '1 1 140px' }}>คำนวณค่างวด</Link>
        <Link to="/eservice" className="btn btn-primary btn-sm" style={{ flex: '1 1 140px' }}>ยื่นคำขอกู้</Link>
      </div>
    </article>
  );
}

export default function LoansPage() {
  const { LOAN_PRODUCTS, INTEREST_RATES } = usePublicInformation();
  const LOAN_CATEGORIES = makeCategories(LOAN_PRODUCTS);
  return (
    <div className="section">
      <div className="container">
        <div className="section-title-wrap">
          <span className="section-badge">ผลิตภัณฑ์สินเชื่อ</span>
          <h1 className="section-title">สินเชื่อและอัตราดอกเบี้ยเงินกู้</h1>
          <p className="section-subtitle">เลือกประเภทสินเชื่อให้เหมาะกับวัตถุประสงค์และแผนการชำระเงินของคุณ</p>
          <div className="section-line" />
          <p className="section-subtitle">{INTEREST_RATES.loanSource.note}{' '}<a href={INTEREST_RATES.loanSource.url}>ดูข้อมูลอ้างอิง</a></p>
        </div>

        <nav aria-label="ประเภทเงินกู้" style={{ display: 'flex', justifyContent: 'center', gap: '0.65rem', flexWrap: 'wrap', marginBottom: '2.5rem' }}>
          {LOAN_CATEGORIES.map((category) => <a key={category.id} href={`#${category.id}`} className="btn btn-subtle btn-sm">{category.title}</a>)}
        </nav>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem', marginBottom: '3rem' }}>
          {LOAN_CATEGORIES.map((category) => {
            const Icon = category.icon;
            const products = category.products.filter(Boolean);
            return (
              <section key={category.id} id={category.id} aria-labelledby={`${category.id}-heading`} className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', scrollMarginTop: '8rem' }}>
                <div style={{ display: 'flex', gap: '1rem', alignItems: 'flex-start', marginBottom: '1.5rem' }}>
                  <div style={{ width: '46px', height: '46px', borderRadius: 'var(--radius-md)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, background: category.background, color: category.color }}><Icon size={24} aria-hidden="true" /></div>
                  <div>
                    <span style={{ fontSize: '0.8rem', fontWeight: 700, color: category.color }}>{category.eyebrow}</span>
                    <h2 id={`${category.id}-heading`} style={{ fontSize: '1.5rem', color: 'var(--primary-800)', marginTop: '0.15rem', marginBottom: '0.35rem' }}>{category.title}</h2>
                    <p style={{ color: 'var(--text-muted)', lineHeight: 1.6 }}>{category.description}</p>
                  </div>
                </div>
                <div className={products.length > 1 ? 'grid-2' : undefined} style={products.length === 1 ? { display: 'grid', gridTemplateColumns: 'minmax(0, 1fr)' } : undefined}>
                  {products.map((product) => <LoanProductCard key={product.id} product={product} />)}
                </div>
              </section>
            );
          })}
        </div>

        <div style={{ background: 'var(--gradient-hero)', color: '#ffffff', borderRadius: 'var(--radius-lg)', padding: '2rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1.5rem' }}>
          <div>
            <h2 style={{ fontSize: '1.3rem', color: '#fff', marginBottom: '0.4rem' }}>ต้องการตรวจเช็คความพร้อมและเอกสารก่อนยื่นกู้?</h2>
            <p style={{ color: '#cbd5e1', fontSize: '0.9rem' }}>ใช้เครื่องมือตรวจสอบคุณสมบัติ หรือดาวน์โหลดแบบฟอร์มเพื่อเตรียมยื่นเรื่อง</p>
          </div>
          <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
            <Link to="/loan-checklist" className="btn btn-gold"><FileCheck size={18} aria-hidden="true" />ตรวจเช็คความพร้อมกู้</Link>
            <Link to="/documents" className="btn btn-outline" style={{ color: '#fff', borderColor: 'rgba(255,255,255,0.4)', background: 'rgba(255,255,255,0.1)' }}><Download size={18} aria-hidden="true" />ดาวน์โหลดแบบฟอร์ม</Link>
          </div>
        </div>
      </div>
    </div>
  );
}
