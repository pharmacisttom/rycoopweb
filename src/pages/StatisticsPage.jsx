import React, { useEffect, useState } from 'react';
import { Award, AlertCircle } from 'lucide-react';

export default function StatisticsPage() {
  const [stats, setStats] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    fetch('/api/public/statistics')
      .then((res) => (res.ok ? res.json() : null))
      .then((json) => {
        if (!cancelled && json?.success && Array.isArray(json.data)) {
          setStats(json.data);
        }
      })
      .catch(() => {})
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const latest = stats.length > 0 ? stats[0] : null;

  // Format Millions Baht
  const formatMillions = (val) => {
    if (val === null || val === undefined || isNaN(val) || Number(val) === 0) return null;
    const millions = Number(val) / 1000000;
    return millions.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const keyCards = [
    {
      label: 'จำนวนสมาชิกทั้งหมด',
      value: latest?.total_members ? Number(latest.total_members).toLocaleString() : null,
      unit: 'คน',
      sourceNote: latest?.year ? `ข้อมูลรอบปี ${latest.year}` : '',
    },
    {
      label: 'ทุนเรือนหุ้นรวม',
      value: formatMillions(latest?.share_capital),
      unit: 'ล้านบาท',
      sourceNote: latest?.year ? `ข้อมูลรอบปี ${latest.year}` : '',
    },
    {
      label: 'สินทรัพย์รวม',
      value: formatMillions(latest?.total_assets),
      unit: 'ล้านบาท',
      sourceNote: latest?.year ? `ข้อมูลรอบปี ${latest.year}` : '',
    },
    {
      label: 'ทุนสำรองสะสม',
      value: formatMillions(latest?.reserve_fund),
      unit: 'ล้านบาท',
      sourceNote: latest?.year ? `ข้อมูลรอบปี ${latest.year}` : '',
    },
    {
      label: 'อัตราเงินปันผลตามหุ้น',
      value: latest?.dividend_rate ? `${Number(latest.dividend_rate).toFixed(2)}` : null,
      unit: '% ต่อปี',
      sourceNote: latest?.year ? `รอบปีบัญชี ${latest.year}` : '',
    },
  ];

  return (
    <div className="section">
      <div className="container">
        
        <div className="section-title-wrap">
          <span className="section-badge">ความมั่นคงทางการเงิน</span>
          <h1 className="section-title">ฐานะทางการเงินและสถิติ</h1>
          <p className="section-subtitle">รายงานผลการดำเนินงาน ฐานะการเงิน และประวัติการจัดสรรกำไรสุทธิตามฐานข้อมูลสหกรณ์</p>
          <div className="section-line" />
        </div>

        {/* Key Stats Grid */}
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
          gap: '1.5rem',
          marginBottom: '3rem'
        }}>
          {keyCards.map((stat, idx) => (
            <div key={idx} className="surface-card" style={{ padding: '1.75rem 1.5rem', textAlign: 'center' }}>
              <div style={{ fontSize: '0.88rem', color: 'var(--text-muted)', marginBottom: '0.4rem', fontWeight: 600 }}>
                {stat.label}
              </div>
              {stat.value ? (
                <>
                  <div style={{ fontSize: '2.4rem', fontWeight: 800, color: 'var(--primary-700)', fontFamily: 'var(--font-display)' }}>
                    {stat.value}
                  </div>
                  <div style={{ fontSize: '0.9rem', fontWeight: 600, color: 'var(--text-main)', marginTop: '0.2rem' }}>
                    {stat.unit}
                  </div>
                  {stat.sourceNote && (
                    <div style={{ marginTop: '0.75rem', display: 'inline-block', fontSize: '0.75rem', padding: '0.2rem 0.6rem', borderRadius: 'var(--radius-full)', background: 'var(--bg-subtle)', color: 'var(--text-muted)', fontWeight: 500 }}>
                      {stat.sourceNote}
                    </div>
                  )}
                </>
              ) : (
                <div style={{ padding: '1rem 0', color: 'var(--text-muted)', fontSize: '0.9rem' }}>
                  {loading ? 'กำลังโหลดข้อมูล...' : 'ยังไม่มีข้อมูลที่ได้รับการบันทึกในระบบ'}
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Financial History Table Card */}
        <div className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '2.5rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '1.5rem' }}>
            <Award size={24} style={{ color: 'var(--accent-gold)' }} />
            <div>
              <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-800)' }}>ประวัติฐานะการเงินและผลการดำเนินงาน</h3>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>ข้อมูลทางการเงินที่ได้รับการรับรองและปิดงบตามรอบบัญชีสหกรณ์</p>
            </div>
          </div>

          {stats.length > 0 ? (
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.92rem' }}>
                <thead>
                  <tr style={{ background: 'var(--bg-subtle)', borderBottom: '2px solid var(--border-subtle)', textAlign: 'left' }}>
                    <th style={{ padding: '0.85rem' }}>รอบบัญชี (พ.ศ.)</th>
                    <th style={{ padding: '0.85rem', textAlign: 'center' }}>อัตราปันผล (% ต่อปี)</th>
                    <th style={{ padding: '0.85rem', textAlign: 'right' }}>จำนวนสมาชิก</th>
                    <th style={{ padding: '0.85rem', textAlign: 'right' }}>ทุนเรือนหุ้น (ล้านบาท)</th>
                    <th style={{ padding: '0.85rem', textAlign: 'right' }}>สินทรัพย์รวม (ล้านบาท)</th>
                    <th style={{ padding: '0.85rem', textAlign: 'right' }}>กำไรสุทธิ (ล้านบาท)</th>
                  </tr>
                </thead>
                <tbody>
                  {stats.map((row) => (
                    <tr key={`${row.year}-${row.month}`} style={{ borderBottom: '1px solid var(--border-subtle)' }}>
                      <td style={{ padding: '0.85rem', fontWeight: 700, color: 'var(--primary-700)' }}>
                        {row.year} {row.month === 6 ? '(ครึ่งปีแรก)' : row.month === 12 ? '(สิ้นปี)' : ''}
                      </td>
                      <td style={{ padding: '0.85rem', textAlign: 'center', fontWeight: 700, color: 'var(--accent-gold-dark)' }}>
                        {row.dividend_rate ? `${Number(row.dividend_rate).toFixed(2)}%` : '-'}
                      </td>
                      <td style={{ padding: '0.85rem', textAlign: 'right' }}>
                        {row.total_members ? Number(row.total_members).toLocaleString() : '-'}
                      </td>
                      <td style={{ padding: '0.85rem', textAlign: 'right' }}>
                        {formatMillions(row.share_capital) || '-'}
                      </td>
                      <td style={{ padding: '0.85rem', textAlign: 'right' }}>
                        {formatMillions(row.total_assets) || '-'}
                      </td>
                      <td style={{ padding: '0.85rem', textAlign: 'right', fontWeight: 600, color: 'var(--accent-emerald-dark)' }}>
                        {formatMillions(row.net_profit) || '-'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '2rem 1rem', color: 'var(--text-muted)' }}>
              <div style={{ display: 'inline-flex', padding: '0.75rem', borderRadius: '50%', background: 'var(--bg-subtle)', marginBottom: '0.75rem' }}>
                <AlertCircle size={24} />
              </div>
              <p style={{ margin: 0, fontSize: '0.95rem' }}>
                {loading ? 'กำลังโหลดข้อมูลสถิติ...' : 'ยังไม่มีข้อมูลที่ได้รับการบันทึกในระบบ'}
              </p>
            </div>
          )}
        </div>

      </div>
    </div>
  );
}
