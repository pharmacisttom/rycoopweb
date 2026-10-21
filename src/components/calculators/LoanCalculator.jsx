import React, { useState, useMemo, useEffect } from 'react';
import { fetchInterestRates } from '../../services/api';
import { Calculator, DollarSign, Calendar, Percent, FileText, CheckCircle, RefreshCw, Printer, Sparkles } from 'lucide-react';
import { LOAN_PRODUCTS } from '../../data/mockData';

export default function LoanCalculator() {
  const [products, setProducts] = useState(LOAN_PRODUCTS);
  const [selectedProductId, setSelectedProductId] = useState('ordinary');
  const [principal, setPrincipal] = useState(300000);
  const [rate, setRate] = useState(6.15);
  const [months, setMonths] = useState(60);
  const [showAmortization, setShowAmortization] = useState(false);


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

  useEffect(() => {
    const currentProd = products.find(p => p.id === selectedProductId);
    if (currentProd && currentProd.rateValue !== rate) {
      setRate(currentProd.rateValue);
    }
  }, [products, selectedProductId]);
  // Active product details
  const activeProduct = useMemo(() => {
    return products.find(p => p.id === selectedProductId) || products[0];
  }, [products, selectedProductId]);

  // Handle loan type selection - strictly sets matching Master Data rate
  const handleTypeChange = (productId) => {
    const prod = products.find(p => p.id === productId);
    if (!prod) return;

    setSelectedProductId(prod.id);
    setRate(prod.rateValue);

    // Adjust principal and term within product limits
    if (principal > prod.maxLimit) {
      setPrincipal(Math.min(300000, prod.maxLimit));
    } else if (principal < 10000) {
      setPrincipal(Math.min(50000, prod.maxLimit));
    }

    if (months > prod.maxTerm) {
      setMonths(prod.maxTerm);
    }
  };

  // Calculate monthly payment (PMT) - Effective Rate (Amortization)
  const calculation = useMemo(() => {
    const p = parseFloat(principal) || 0;
    const r = (parseFloat(rate) || 0) / 100 / 12;
    const n = parseInt(months, 10) || 1;

    if (p <= 0 || n <= 0) return { monthly: 0, totalInterest: 0, totalPayment: 0, schedule: [] };

    let monthly = 0;
    if (r > 0) {
      monthly = (p * r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
    } else {
      monthly = p / n;
    }

    // Generate amortization schedule preview
    let balance = p;
    let totalInterest = 0;
    const schedule = [];

    for (let i = 1; i <= n; i++) {
      const interest = balance * r;
      const principalPaid = monthly - interest;
      balance = Math.max(0, balance - principalPaid);
      totalInterest += interest;

      if (i <= 24 || i === n || i % 12 === 0) {
        schedule.push({
          month: i,
          payment: monthly,
          principalPaid,
          interest,
          balance,
        });
      }
    }

    return {
      monthly: Math.round(monthly),
      totalInterest: Math.round(totalInterest),
      totalPayment: Math.round(p + totalInterest),
      schedule,
    };
  }, [principal, rate, months]);

  return (
    <div className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>
      
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '1rem', marginBottom: '1.75rem' }}>
        <div>
          <h3 style={{ fontSize: '1.4rem', color: 'var(--primary-800)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Calculator className="text-primary" />
            <span>โปรแกรมคำนวณเงินกู้สหกรณ์</span>
          </h3>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>
            ประมาณการค่างวดแบบลดต้นลดดอก โดยใช้อัตราอ้างอิงจากเว็บเดิม กรุณาตรวจสอบประกาศและสิทธิของสมาชิกก่อนยื่นกู้
          </p>
        </div>

        {/* Selected Product Rate Badge */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <span className="badge badge-gold" style={{ fontSize: '0.85rem', padding: '0.4rem 0.75rem' }}>
            ดอกเบี้ย {activeProduct.title}: <strong>{activeProduct.interestRate}</strong>
          </span>
        </div>
      </div>

      {/* Loan Type Selector */}
      <div style={{ marginBottom: '1.75rem' }}>
        <label className="form-label" style={{ fontWeight: 700, marginBottom: '0.6rem', display: 'block' }}>
          เลือกประเภทสินเชื่อ (10 ประเภทตามประกาศสหกรณ์):
        </label>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '0.5rem' }}>
          {products.map((p) => {
            const isSelected = selectedProductId === p.id;
            return (
              <button
                key={p.id}
                type="button"
                onClick={() => handleTypeChange(p.id)}
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'flex-start',
                  padding: '0.65rem 0.85rem',
                  borderRadius: '10px',
                  border: isSelected ? '2px solid var(--primary-600)' : '1px solid var(--border-subtle)',
                  background: isSelected ? 'var(--primary-50, rgba(37, 99, 235, 0.08))' : 'var(--bg-surface)',
                  cursor: 'pointer',
                  textAlign: 'left',
                  transition: 'all 0.15s ease',
                  boxShadow: isSelected ? 'var(--shadow-sm)' : 'none'
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', alignItems: 'center' }}>
                  <span style={{ fontWeight: isSelected ? 700 : 600, fontSize: '0.88rem', color: isSelected ? 'var(--primary-700)' : 'var(--text-main)' }}>
                    {p.title}
                  </span>
                  <span style={{ 
                    fontSize: '0.8rem', 
                    fontWeight: 800, 
                    color: isSelected ? 'var(--primary-700)' : 'var(--accent-gold-dark)',
                    fontFamily: 'var(--font-display)'
                  }}>
                    {p.rateValue.toFixed(2)}%
                  </span>
                </div>
                <div style={{ fontSize: '0.74rem', color: 'var(--text-muted)', marginTop: '0.2rem' }}>
                  ผ่อนสูงสุด {p.maxTerm} งวด
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Input Grid & Summary */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '2rem' }}>
        
        {/* Input Controls */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          
          {/* Principal Amount */}
          <div className="form-group" style={{ marginBottom: 0 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.4rem' }}>
              <label className="form-label" style={{ marginBottom: 0 }}>วงเงินกู้ที่ต้องการ (บาท)</label>
              <span style={{ fontWeight: 800, color: 'var(--primary-600)', fontSize: '1.1rem' }}>
                {Number(principal).toLocaleString()} บาท
              </span>
            </div>
            <input 
              type="range"
              min="10000"
              max={activeProduct.maxLimit}
              step="5000"
              value={principal}
              onChange={(e) => setPrincipal(Number(e.target.value))}
              style={{ width: '100%', accentColor: 'var(--primary-600)', height: '6px', cursor: 'pointer' }}
            />
            <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem', flexWrap: 'wrap' }}>
              {[50000, 100000, 300000, 500000, 1000000, 2000000, 3000000]
                .filter(a => a <= activeProduct.maxLimit)
                .map((amt) => (
                  <button
                    key={amt}
                    type="button"
                    onClick={() => setPrincipal(amt)}
                    className="btn btn-subtle btn-sm"
                    style={{ fontSize: '0.75rem', padding: '0.2rem 0.5rem' }}
                  >
                    {amt >= 1000000 ? `${amt / 1000000}M` : `${amt / 1000}k`}
                  </button>
                ))}
            </div>
          </div>

          {/* Interest Rate */}
          <div className="form-group" style={{ marginBottom: 0 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.4rem' }}>
              <label className="form-label" style={{ marginBottom: 0 }}>
                อัตราดอกเบี้ย (% ต่อปี) - <small className="text-muted">{activeProduct.title}</small>
              </label>
              <span style={{ fontWeight: 700, color: 'var(--accent-gold-dark)' }}>{rate}% ต่อปี</span>
            </div>
            <input 
              type="number"
              step="0.05"
              className="form-control"
              value={rate}
              onChange={(e) => setRate(Number(e.target.value))}
            />
          </div>

          {/* Repayment Term */}
          <div className="form-group" style={{ marginBottom: 0 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.4rem' }}>
              <label className="form-label" style={{ marginBottom: 0 }}>ระยะเวลาผ่อนชำระ (งวด / เดือน)</label>
              <span style={{ fontWeight: 700, color: 'var(--accent-teal-dark)' }}>
                {months} งวด ({(months / 12).toFixed(1)} ปี)
              </span>
            </div>
            <input 
              type="range"
              min="6"
              max={activeProduct.maxTerm}
              step="6"
              value={months}
              onChange={(e) => setMonths(Number(e.target.value))}
              style={{ width: '100%', accentColor: 'var(--accent-teal)', height: '6px', cursor: 'pointer' }}
            />
            <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem', flexWrap: 'wrap' }}>
              {[12, 36, 60, 120, 180, 240, 360]
                .filter(m => m <= activeProduct.maxTerm)
                .map((m) => (
                  <button
                    key={m}
                    type="button"
                    onClick={() => setMonths(m)}
                    className="btn btn-subtle btn-sm"
                    style={{ fontSize: '0.75rem', padding: '0.2rem 0.5rem' }}
                  >
                    {m} งวด
                  </button>
                ))}
            </div>
          </div>

        </div>

        {/* Calculation Summary Card */}
        <div style={{
          background: 'var(--gradient-hero)',
          color: '#ffffff',
          borderRadius: 'var(--radius-lg)',
          padding: '1.75rem',
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'space-between',
          boxShadow: 'var(--shadow-lg)'
        }}>
          <div>
            <span style={{ 
              background: 'rgba(255,255,255,0.15)', 
              padding: '0.25rem 0.75rem', 
              borderRadius: 'var(--radius-full)', 
              fontSize: '0.78rem',
              fontWeight: 600 
            }}>
              ผลการคำนวณประมาณการ: {activeProduct.title}
            </span>

            <div style={{ marginTop: '1.25rem', marginBottom: '1.5rem' }}>
              <div style={{ fontSize: '0.9rem', color: '#cbd5e1' }}>ยอดผ่อนชำระต่องวด (ประมาณการ)</div>
              <div style={{ fontSize: '2.4rem', fontWeight: 800, color: '#fbbf24', lineHeight: 1.2, fontFamily: 'var(--font-display)' }}>
                {calculation.monthly.toLocaleString()} <span style={{ fontSize: '1.1rem', fontWeight: 500, color: '#ffffff' }}>บาท/เดือน</span>
              </div>
            </div>

            <div style={{ borderTop: '1px solid rgba(255,255,255,0.15)', paddingTop: '1rem', display: 'flex', flexDirection: 'column', gap: '0.75rem', fontSize: '0.88rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: '#cbd5e1' }}>วงเงินกู้ต้น:</span>
                <strong>{Number(principal).toLocaleString()} บาท</strong>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: '#cbd5e1' }}>อัตราดอกเบี้ย:</span>
                <strong style={{ color: '#fbbf24' }}>{rate}% ต่อปี</strong>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: '#cbd5e1' }}>ดอกเบี้ยรวมตลอดสัญญา:</span>
                <strong style={{ color: '#93c5fd' }}>{calculation.totalInterest.toLocaleString()} บาท</strong>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: '#cbd5e1' }}>รวมยอดเงินที่ต้องชำระทั้งหมด:</span>
                <strong>{calculation.totalPayment.toLocaleString()} บาท</strong>
              </div>
            </div>
          </div>

          <div style={{ marginTop: '1.5rem', display: 'flex', gap: '0.5rem' }}>
            <button
              onClick={() => setShowAmortization(!showAmortization)}
              className="btn btn-teal btn-sm"
              style={{ flex: 1 }}
            >
              <FileText size={15} />
              <span>{showAmortization ? 'ซ่อนตารางผ่อน' : 'ดูตารางผ่อนชำระ'}</span>
            </button>
            <button
              onClick={() => window.print()}
              className="btn btn-subtle btn-sm"
              style={{ background: 'rgba(255,255,255,0.15)', color: '#fff', border: 'none' }}
              title="พิมพ์ผลคำนวณ"
            >
              <Printer size={15} />
            </button>
          </div>

        </div>

      </div>

      {/* Amortization Table Accordion */}
      {showAmortization && (
        <div className="animate-fade-in" style={{ marginTop: '2rem', borderTop: '1px solid var(--border-subtle)', paddingTop: '1.5rem' }}>
          <h4 style={{ fontSize: '1.1rem', marginBottom: '1rem', color: 'var(--text-main)' }}>
            ตารางจำลองการผ่อนชำระรายงวด ({activeProduct.title} - ดอกเบี้ย {rate}%)
          </h4>
          <div className="table-scroll-hint">
            <span>👈 เลื่อนในแนวนอนเพื่อดูรายละเอียด 👉</span>
          </div>
          <div className="table-scroll-container">
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem', textAlign: 'right' }}>
              <thead>
                <tr style={{ background: 'var(--bg-subtle)', color: 'var(--text-main)', borderBottom: '2px solid var(--border-subtle)' }}>
                  <th style={{ padding: '0.6rem', textAlign: 'center' }}>งวดที่</th>
                  <th style={{ padding: '0.6rem' }}>ยอดผ่อนชำระ</th>
                  <th style={{ padding: '0.6rem' }}>ชำระเงินต้น</th>
                  <th style={{ padding: '0.6rem' }}>ชำระดอกเบี้ย</th>
                  <th style={{ padding: '0.6rem' }}>เงินต้นคงเหลือ</th>
                </tr>
              </thead>
              <tbody>
                {calculation.schedule.map((row) => (
                  <tr key={row.month} style={{ borderBottom: '1px solid var(--border-subtle)' }}>
                    <td style={{ padding: '0.6rem', textAlign: 'center', fontWeight: 600 }}>{row.month}</td>
                    <td style={{ padding: '0.6rem', fontWeight: 600, color: 'var(--primary-600)' }}>{Math.round(row.payment).toLocaleString()}</td>
                    <td style={{ padding: '0.6rem', color: 'var(--accent-teal-dark)' }}>{Math.round(row.principalPaid).toLocaleString()}</td>
                    <td style={{ padding: '0.6rem', color: 'var(--accent-rose)' }}>{Math.round(row.interest).toLocaleString()}</td>
                    <td style={{ padding: '0.6rem', fontWeight: 600 }}>{Math.round(row.balance).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.5rem' }}>
            * ตารางนี้เป็นเพียงการคำนวณประมาณการเบื้องต้น ดอกเบี้ยจริงจะคำนวณตามจำนวนวันในแต่ละเดือนและยอดเงินต้นคงเหลือ ณ วันที่ตัดยอด
          </p>
        </div>
      )}

    </div>
  );
}
