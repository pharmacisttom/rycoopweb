import React, { useState, useEffect } from 'react';
import { Banknote, CheckCircle2, Download, FileCheck, Home, Zap, Shield, Car, BookOpen, ShoppingBag, RefreshCw, Calculator, Percent } from 'lucide-react';
import { Link } from 'react-router-dom';
import { LOAN_PRODUCTS } from '../data/mockData';
import { fetchInterestRates } from '../services/api';

const CATEGORY_DEFINITIONS = [
  {
    id: 'emergency', icon: Zap, eyebrow: 'สำหรับความจำเป็นเร่งด่วน', title: 'เงินกู้ฉุกเฉิน',
    description: 'ทางเลือกสำหรับเหตุจำเป็นเฉพาะหน้า ด้วยวงเงินและระยะเวลาผ่อนชำระที่เหมาะกับการแก้ปัญหาเร่งด่วน',
    productIds: ['emergency'], color: 'var(--accent-rose)', background: 'var(--accent-rose-light)',
  },
  {
    id: 'ordinary', icon: Banknote, eyebrow: 'เพื่อการครองชีพและการศึกษา', title: 'เงินกู้สามัญ & เพื่อการศึกษา',
    description: 'รองรับแผนการเงินระยะกลางถึงระยะยาว ทั้งการเสริมสภาพคล่อง การศึกษาของสมาชิกหรือบุตร และการรวมหนี้',
    productIds: ['ordinary', 'education', 'debt_restructure'], color: 'var(--accent-gold-dark)', background: 'var(--accent-gold-light)',
  },
  {
    id: 'housing', icon: Home, eyebrow: 'เพื่อที่อยู่อาศัยและความมั่นคง', title: 'เงินกู้พิเศษ & ความมั่นคงในชีวิต',
    description: 'สำหรับซื้อที่ดิน สร้างบ้าน รีไฟแนนซ์ หรือเสริมความมั่นคงในชีวิตระยะยาว โดยใช้หลักทรัพย์เป็นประกัน',
    productIds: ['special', 'life_security'], color: 'var(--accent-teal-dark)', background: 'var(--accent-teal-light)',
  },
  {
    id: 'welfare', icon: ShoppingBag, eyebrow: 'เพื่อสวัสดิการและยานพาหนะ', title: 'สวัสดิการ & พัฒนาคุณภาพชีวิต',
    description: 'สินเชื่อจัดหาสินค้าจำเป็น ยานพาหนะ พัฒนาความเป็นอยู่ครอบครัว และกู้โดยใช้เงินฝากค้ำประกันดอกเบี้ยต่ำ',
    productIds: ['life_development', 'goods_installment', 'car_purchase', 'guaranteed_loan'], color: 'var(--primary-700)', background: 'var(--primary-100)',
  },
];

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
          {product.features?.map((feature) => (
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
  const [products, setProducts] = useState(LOAN_PRODUCTS);

  useEffect(() => {
    let cancelled = false;
    fetchInterestRates().then(res => {
      if (!cancelled && res?.success && res?.data?.loan?.length > 0) {
        setProducts(current => current.map(p => {
          const match = res.data.loan.find(r => r.product_name === p.title);
          if (match) {
            const val = parseFloat(match.rate);
            return {
              ...p,
              rateValue: val,
              interestRate: `${val.toFixed(2)}% ต่อปี`,
              maxLimit: match.max_amount != null ? parseFloat(match.max_amount) : p.maxLimit
            };
          }
          return p;
        }));
      }
    }).catch(() => {});
    return () => { cancelled = true; };
  }, []);

  const getProduct = (id) => products.find(p => p.id === id);

  return (
    <div className="section">
      <div className="container">
        <div className="section-title-wrap">
          <span className="section-badge">ผลิตภัณฑ์สินเชื่อ</span>
          <h1 className="section-title">สินเชื่อและอัตราดอกเบี้ยเงินกู้</h1>
          <p className="section-subtitle">เลือกประเภทสินเชื่อให้เหมาะกับวัตถุประสงค์และแผนการชำระเงินของคุณ (คิดดอกเบี้ยแบบลดต้นลดดอก)</p>
          <div className="section-line" />
        </div>

        {/* 10-Product Master Rate Summary Card */}
        <div className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '3rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.25rem', flexWrap: 'wrap', gap: '0.5rem' }}>
            <div>
              <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', margin: 0, display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <Percent size={20} className="text-primary" />
                <span>ตารางสรุปอัตราดอกเบี้ยเงินกู้ 10 ประเภท (ตามประกาศสหกรณ์)</span>
              </h3>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', margin: '0.2rem 0 0 0' }}>
                คิดดอกเบี้ยแบบลดต้นลดดอก (Effective Rate) มีผลบังคับใช้ตามประกาศล่าสุด
              </p>
            </div>
            <Link to="/calculator" className="btn btn-primary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}>
              <Calculator size={15} />
              <span>เปิดโปรแกรมคำนวณเงินกู้</span>
            </Link>
          </div>

          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.92rem' }}>
              <thead>
                <tr style={{ background: 'var(--bg-subtle)', borderBottom: '2px solid var(--border-subtle)', textAlign: 'left' }}>
                  <th style={{ padding: '0.85rem', width: '45px' }}>#</th>
                  <th style={{ padding: '0.85rem' }}>ประเภทเงินกู้</th>
                  <th style={{ padding: '0.85rem', textAlign: 'center' }}>อัตราดอกเบี้ย</th>
                  <th style={{ padding: '0.85rem' }}>วงเงินกู้สูงสุด</th>
                  <th style={{ padding: '0.85rem' }}>ผ่อนชำระสูงสุด</th>
                  <th style={{ padding: '0.85rem', textAlign: 'center' }}>คำนวณ</th>
                </tr>
              </thead>
              <tbody>
                {products.map((p, idx) => (
                  <tr key={p.id} style={{ borderBottom: '1px solid var(--border-subtle)', background: idx % 2 === 0 ? 'transparent' : 'rgba(0,0,0,0.015)' }}>
                    <td style={{ padding: '0.85rem', color: 'var(--text-muted)', fontWeight: 600 }}>{idx + 1}</td>
                    <td style={{ padding: '0.85rem', fontWeight: 700, color: 'var(--primary-900)' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                        <span>{p.title}</span>
                        <span className={`badge badge-${p.badgeColor}`} style={{ fontSize: '0.7rem' }}>{p.badge}</span>
                      </div>
                    </td>
                    <td style={{ padding: '0.85rem', textAlign: 'center', fontWeight: 800, color: 'var(--accent-gold-dark)', fontSize: '1.15rem', fontFamily: 'var(--font-display)' }}>
                      {p.rateValue.toFixed(2)}%
                    </td>
                    <td style={{ padding: '0.85rem', fontWeight: 600, color: 'var(--primary-700)' }}>{p.maxAmount}</td>
                    <td style={{ padding: '0.85rem' }}>{p.period}</td>
                    <td style={{ padding: '0.85rem', textAlign: 'center' }}>
                      <Link to={`/calculator?loan=${p.id}`} className="btn btn-subtle btn-sm" style={{ padding: '0.25rem 0.6rem', fontSize: '0.78rem' }}>
                        คำนวณ
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Category Navigation */}
        <nav aria-label="ประเภทเงินกู้" style={{ display: 'flex', justifyContent: 'center', gap: '0.65rem', flexWrap: 'wrap', marginBottom: '2.5rem' }}>
          {CATEGORY_DEFINITIONS.map((category) => (
            <a key={category.id} href={`#${category.id}`} className="btn btn-subtle btn-sm">{category.title}</a>
          ))}
        </nav>

        {/* Categories with Cards */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem', marginBottom: '3rem' }}>
          {CATEGORY_DEFINITIONS.map((category) => {
            const Icon = category.icon;
            const categoryProducts = category.productIds.map(getProduct).filter(Boolean);
            return (
              <section key={category.id} id={category.id} aria-labelledby={`${category.id}-heading`} className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', scrollMarginTop: '8rem' }}>
                <div style={{ display: 'flex', gap: '1rem', alignItems: 'flex-start', marginBottom: '1.5rem' }}>
                  <div style={{ width: '46px', height: '46px', borderRadius: 'var(--radius-md)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, background: category.background, color: category.color }}>
                    <Icon size={24} aria-hidden="true" />
                  </div>
                  <div>
                    <span style={{ fontSize: '0.8rem', fontWeight: 700, color: category.color }}>{category.eyebrow}</span>
                    <h2 id={`${category.id}-heading`} style={{ fontSize: '1.5rem', color: 'var(--primary-800)', marginTop: '0.15rem', marginBottom: '0.35rem' }}>{category.title}</h2>
                    <p style={{ color: 'var(--text-muted)', lineHeight: 1.6 }}>{category.description}</p>
                  </div>
                </div>
                <div className={categoryProducts.length > 1 ? 'grid-2' : undefined} style={categoryProducts.length === 1 ? { display: 'grid', gridTemplateColumns: 'minmax(0, 1fr)' } : undefined}>
                  {categoryProducts.map((product) => <LoanProductCard key={product.id} product={product} />)}
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
