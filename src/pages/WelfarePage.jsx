import React, { useState, useEffect } from 'react';
import { HeartHandshake, Download, CheckCircle2, ShieldCheck, Heart, Award, GraduationCap, Stethoscope, Gift, ShieldAlert, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';

const getIconForWelfare = (title = '', category = '') => {
  const text = (title + ' ' + category).toLowerCase();
  if (text.includes('ศพ') || text.includes('ถึงแก่กรรม') || text.includes('มรณ')) return ShieldAlert;
  if (text.includes('การศึกษา') || text.includes('ทุน') || text.includes('เรียน')) return GraduationCap;
  if (text.includes('สมรส') || text.includes('คลอด') || text.includes('บุตร') || text.includes('ขวัญ')) return Gift;
  if (text.includes('รักษา') || text.includes('พยาบาล') || text.includes('ป่วย')) return Stethoscope;
  if (text.includes('เกียรติ') || text.includes('อาวุโส') || text.includes('เกษียณ')) return Award;
  return HeartHandshake;
};

export default function WelfarePage() {
  const [welfareList, setWelfareList] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    fetch('/api/public/welfare')
      .then((res) => res.json())
      .then((data) => {
        if (!cancelled && data?.success && Array.isArray(data.data)) {
          setWelfareList(data.data);
        }
      })
      .catch(() => {})
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  return (
    <div className="section">
      <div className="container">
        
        <div className="section-title-wrap">
          <span className="section-badge">กองทุนสวัสดิการ</span>
          <h1 className="section-title">สวัสดิการสมาชิกและครอบครัว</h1>
          <p className="section-subtitle">ความคุ้มครองและเงินช่วยเหลือเพื่อยกระดับคุณภาพชีวิตและสร้างความอุ่นใจแด่มวลสมาชิก</p>
          <div className="section-line" />
        </div>

        {/* Welfare Cards Grid */}
        <div className="grid-3" style={{ marginBottom: '3rem' }}>
          {welfareList.map((item) => {
            const IconComponent = getIconForWelfare(item.title, item.category);
            return (
              <div key={item.id} className="surface-card" style={{ padding: '2rem', display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
                <div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.25rem' }}>
                    <div style={{ width: '48px', height: '48px', borderRadius: '12px', background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      <IconComponent size={24} />
                    </div>
                    <span className="badge badge-teal">{item.category}</span>
                  </div>

                  <h3 style={{ fontSize: '1.2rem', color: 'var(--primary-800)', marginBottom: '0.4rem' }}>{item.title}</h3>
                  <div style={{ fontSize: '1.3rem', fontWeight: 800, color: 'var(--accent-teal-dark)', marginBottom: '0.75rem', fontFamily: 'var(--font-display)' }}>
                    {item.benefit_amount || 'ตามระเบียบกำหนด'}
                  </div>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', lineHeight: 1.6, marginBottom: '0.75rem' }}>
                    {item.short_description || item.full_description}
                  </p>
                  {item.eligibility && (
                    <div style={{ fontSize: '0.8rem', color: 'var(--navy-600, #475569)', background: '#f8fafc', padding: '0.5rem 0.75rem', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                      <strong>เงื่อนไข:</strong> {item.eligibility}
                    </div>
                  )}
                </div>

                <div style={{ marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border-subtle)' }}>
                  <Link to="/documents?category=welfare" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem', fontSize: '0.85rem', fontWeight: 600, color: 'var(--primary-600)' }}>
                    <Download size={14} />
                    <span>ดาวน์โหลดแบบขอรับสวัสดิการ</span>
                  </Link>
                </div>
              </div>
            );
          })}
        </div>

        {/* =========================================================================
            PARTNER ASSOCIATIONS SECTION (สสธท. / กสธท. / สส.ชสอ.)
            ========================================================================= */}
        <div id="associations" style={{ marginTop: '2.5rem', marginBottom: '3rem' }}>
          <div className="section-title-wrap" style={{ textAlign: 'left', marginBottom: '1.5rem' }}>
            <span className="section-badge" style={{ background: '#EDF5FF', color: '#1769C2' }}>พันธมิตรและสมาคมฌาปนกิจสงเคราะห์</span>
            <h2 style={{ fontSize: '1.5rem', fontWeight: 800, color: '#17315A', margin: '0.5rem 0' }}>
              สมาคมฌาปนกิจสงเคราะห์และกองทุนสวัสดิการ
            </h2>
            <p style={{ color: '#526783', fontSize: '0.95rem', margin: 0 }}>
              ลิงก์ตรวจสอบข้อมูลและแบบฟอร์มสมาคมฌาปนกิจสงเคราะห์ที่สหกรณ์ร่วมดำเนินงาน
            </p>
          </div>

          <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
            gap: '1.5rem'
          }}>
            {/* Card 1: สสธท. */}
            <div className="surface-card" style={{
              background: '#FFFFFF',
              border: '1px solid #DCE5F0',
              borderRadius: '0.875rem',
              padding: '1.75rem',
              boxShadow: '0 4px 18px rgba(16, 47, 104, 0.055)',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between'
            }}>
              <div>
                <span style={{
                  display: 'inline-block',
                  fontSize: '0.75rem',
                  fontWeight: 700,
                  padding: '0.2rem 0.6rem',
                  background: '#EDF5FF',
                  color: '#1769C2',
                  borderRadius: '9999px',
                  marginBottom: '0.75rem'
                }}>สสธท.</span>
                <h3 style={{ fontSize: '1.15rem', fontWeight: 700, color: '#17315A', marginBottom: '0.5rem' }}>
                  สมาคมฌาปนกิจสงเคราะห์สมาชิก สสธท.
                </h3>
                <p style={{ fontSize: '0.85rem', color: '#526783', lineHeight: 1.6, marginBottom: '1.25rem' }}>
                  สมาคมฌาปนกิจสงเคราะห์สมาชิกสหกรณ์ออมทรัพย์สาธารณสุขไทย มอบความคุ้มครองและเงินสงเคราะห์ครอบครัว
                </p>
              </div>
              <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                <a
                  href="https://ss-th.org"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn btn-primary btn-sm"
                  style={{ background: '#1769C2', color: '#FFFFFF', padding: '0.45rem 0.95rem' }}
                >
                  เข้าสู่เว็บไซต์ สสธท.
                </a>
                <Link
                  to="/documents?category=associations"
                  className="btn btn-outline btn-sm"
                  style={{ borderColor: '#DCE5F0', color: '#1769C2', padding: '0.45rem 0.95rem' }}
                >
                  แบบฟอร์ม
                </Link>
              </div>
            </div>

            {/* Card 2: กสธท. */}
            <div className="surface-card" style={{
              background: '#FFFFFF',
              border: '1px solid #DCE5F0',
              borderRadius: '0.875rem',
              padding: '1.75rem',
              boxShadow: '0 4px 18px rgba(16, 47, 104, 0.055)',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between'
            }}>
              <div>
                <span style={{
                  display: 'inline-block',
                  fontSize: '0.75rem',
                  fontWeight: 700,
                  padding: '0.2rem 0.6rem',
                  background: '#EDF5FF',
                  color: '#1769C2',
                  borderRadius: '9999px',
                  marginBottom: '0.75rem'
                }}>กสธท.</span>
                <h3 style={{ fontSize: '1.15rem', fontWeight: 700, color: '#17315A', marginBottom: '0.5rem' }}>
                  กองทุนสวัสดิการสมาชิก กสธท.
                </h3>
                <p style={{ fontSize: '0.85rem', color: '#526783', lineHeight: 1.6, marginBottom: '1.25rem' }}>
                  กองทุนสวัสดิการสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขไทย เสริมสร้างความมั่นคงและสวัสดิการระยะยาวแก่สมาชิก
                </p>
              </div>
              <div>
                <a
                  href="https://matpf.com"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn btn-primary btn-sm"
                  style={{ background: '#1769C2', color: '#FFFFFF', padding: '0.45rem 0.95rem' }}
                >
                  เข้าสู่เว็บไซต์ กสธท.
                </a>
              </div>
            </div>

            {/* Card 3: สส.ชสอ. */}
            <div className="surface-card" style={{
              background: '#FFFFFF',
              border: '1px solid #DCE5F0',
              borderRadius: '0.875rem',
              padding: '1.75rem',
              boxShadow: '0 4px 18px rgba(16, 47, 104, 0.055)',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between'
            }}>
              <div>
                <span style={{
                  display: 'inline-block',
                  fontSize: '0.75rem',
                  fontWeight: 700,
                  padding: '0.2rem 0.6rem',
                  background: '#EDF5FF',
                  color: '#1769C2',
                  borderRadius: '9999px',
                  marginBottom: '0.75rem'
                }}>สส.ชสอ.</span>
                <h3 style={{ fontSize: '1.15rem', fontWeight: 700, color: '#17315A', marginBottom: '0.5rem' }}>
                  สมาคมฌาปนกิจสงเคราะห์ สส.ชสอ.
                </h3>
                <p style={{ fontSize: '0.85rem', color: '#526783', lineHeight: 1.6, marginBottom: '1.25rem' }}>
                  สมาคมฌาปนกิจสงเคราะห์สมาชิกชุมนุมสหกรณ์ออมทรัพย์แห่งประเทศไทย บริการสงเคราะห์ครอบครัวสมาชิก
                </p>
              </div>
              <div>
                <Link
                  to="/documents?category=associations"
                  className="btn btn-outline btn-sm"
                  style={{ borderColor: '#DCE5F0', color: '#1769C2', padding: '0.45rem 0.95rem' }}
                >
                  ดาวน์โหลดแบบฟอร์ม
                </Link>
              </div>
            </div>
          </div>
        </div>

        {/* Claim Procedure Guide */}
        <div className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>
          <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-800)', marginBottom: '1rem' }}>
            ขั้นตอนการขอรับเงินสวัสดิการ
          </h3>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1.5rem' }}>
            <div style={{ background: 'var(--bg-surface)', padding: '1.25rem', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
              <div style={{ fontWeight: 800, color: 'var(--primary-600)', fontSize: '1.2rem', marginBottom: '0.25rem' }}>ขั้นตอนที่ 1</div>
              <h4 style={{ fontSize: '1rem', marginBottom: '0.35rem' }}>ดาวน์โหลดและกรอกแบบฟอร์ม</h4>
              <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>ดาวน์โหลดแบบขอรับสวัสดิการตามประเภทที่ต้องการ พร้อมแนบเอกสารหลักฐาน</p>
            </div>
            <div style={{ background: 'var(--bg-surface)', padding: '1.25rem', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
              <div style={{ fontWeight: 800, color: 'var(--primary-600)', fontSize: '1.2rem', marginBottom: '0.25rem' }}>ขั้นตอนที่ 2</div>
              <h4 style={{ fontSize: '1rem', marginBottom: '0.35rem' }}>ยื่นเอกสาร</h4>
              <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>ยื่นเอกสาร ณ สำนักงานสหกรณ์ หรือผ่านระบบ e-Services ภายในเวลาที่กำหนด</p>
            </div>
            <div style={{ background: 'var(--bg-surface)', padding: '1.25rem', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
              <div style={{ fontWeight: 800, color: 'var(--primary-600)', fontSize: '1.2rem', marginBottom: '0.25rem' }}>ขั้นตอนที่ 3</div>
              <h4 style={{ fontSize: '1rem', marginBottom: '0.35rem' }}>ตรวจสอบและอนุมัติ</h4>
              <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>เจ้าหน้าที่ตรวจสอบคุณสมบัติและนำเสนอคณะกรรมการอนุมัติจ่ายเงิน</p>
            </div>
            <div style={{ background: 'var(--bg-surface)', padding: '1.25rem', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
              <div style={{ fontWeight: 800, color: 'var(--primary-600)', fontSize: '1.2rem', marginBottom: '0.25rem' }}>ขั้นตอนที่ 4</div>
              <h4 style={{ fontSize: '1rem', marginBottom: '0.35rem' }}>โอนเงินสวัสดิการ</h4>
              <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>สหกรณ์โอนเงินเข้าบัญชีเงินฝากของสมาชิกโดยตรง พร้อม SMS แจ้งเตือน</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
