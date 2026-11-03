import React from 'react';
import { Link } from 'react-router-dom';
import {
  Calendar,
  Download,
  AlertCircle,
  FileText,
  Calculator,
  ChevronRight,
  Clock,
  CheckCircle2,
  PhoneCall
} from 'lucide-react';

const SCHEDULE_2569 = [
  { month: 'มกราคม 2569', deadline: '15 ม.ค. 2569', meeting: '23 ม.ค. 2569', transfer: '26 ม.ค. 2569', note: 'ปกติ' },
  { month: 'กุมภาพันธ์ 2569', deadline: '13 ก.พ. 2569', meeting: '20 ก.พ. 2569', transfer: '24 ก.พ. 2569', note: 'ปกติ' },
  { month: 'มีนาคม 2569', deadline: '16 มี.ค. 2569', meeting: '23 มี.ค. 2569', transfer: '26 มี.ค. 2569', note: 'ปกติ' },
  { month: 'เมษายน 2569', deadline: '10 เม.ย. 2569', meeting: '23 เม.ย. 2569', transfer: '27 เม.ย. 2569', note: 'ก่อนวันหยุดสงกรานต์' },
  { month: 'พฤษภาคม 2569', deadline: '15 พ.ค. 2569', meeting: '22 พ.ค. 2569', transfer: '26 พ.ค. 2569', note: 'ปกติ' },
  { month: 'มิถุนายน 2569', deadline: '15 มิ.ย. 2569', meeting: '23 มิ.ย. 2569', transfer: '26 มิ.ย. 2569', note: 'ปกติ' },
  { month: 'กรกฎาคม 2569', deadline: '15 ก.ค. 2569', meeting: '23 ก.ค. 2569', transfer: '27 ก.ค. 2569', note: 'ปกติ' },
  { month: 'สิงหาคม 2569', deadline: '14 ส.ค. 2569', meeting: '21 ส.ค. 2569', transfer: '25 ส.ค. 2569', note: 'ปกติ' },
  { month: 'กันยายน 2569', deadline: '15 ก.ย. 2569', meeting: '23 ก.ย. 2569', transfer: '28 ก.ย. 2569', note: 'ปิดงวดสิ้นปีงบประมาณ' },
  { month: 'ตุลาคม 2569', deadline: '15 ต.ค. 2569', meeting: '22 ต.ค. 2569', transfer: '27 ต.ค. 2569', note: 'ปกติ' },
  { month: 'พฤศจิกายน 2569', deadline: '13 พ.ย. 2569', meeting: '20 พ.ย. 2569', transfer: '25 พ.ย. 2569', note: 'ปกติ' },
  { month: 'ธันวาคม 2569', deadline: '11 ธ.ค. 2569', meeting: '18 ธ.ค. 2569', transfer: '24 ธ.ค. 2569', note: 'จ่ายเงินก่อนวันสิ้นปี' }
];

export default function LoanSchedulePage() {
  return (
    <div style={{ background: 'var(--bg-main)', minHeight: '80vh', paddingBottom: '4rem' }}>
      {/* Header Banner */}
      <section style={{
        background: 'linear-gradient(135deg, #0f2b5c 0%, #1e40af 100%)',
        color: '#ffffff',
        padding: '3.5rem 0 3rem 0',
        borderBottom: '1px solid rgba(255,255,255,0.1)'
      }}>
        <div className="container">
          <div style={{ maxWidth: '800px' }}>
            <div style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '0.5rem',
              padding: '0.35rem 0.95rem',
              background: 'rgba(255,255,255,0.12)',
              borderRadius: '9999px',
              fontSize: '0.85rem',
              fontWeight: 600,
              color: '#fbbf24',
              marginBottom: '1rem',
              border: '1px solid rgba(251,191,36,0.3)'
            }}>
              <Calendar size={16} />
              <span>ปฏิทินสินเชื่อสหกรณ์</span>
            </div>
            <h1 style={{
              fontSize: 'clamp(1.8rem, 3.5vw, 2.75rem)',
              fontWeight: 800,
              lineHeight: 1.25,
              marginBottom: '1rem'
            }}>
              กำหนดการยื่นกู้และจ่ายเงินกู้ ประจำปี 2569
            </h1>
            <p style={{
              fontSize: '1.1rem',
              color: '#e2e8f0',
              lineHeight: 1.6
            }}>
              ตารางกำหนดวันสิ้นสุดการรับคำขอกู้สามัญ วันประชุมคณะกรรมการเงินกู้ และวันโอนเงินกู้เข้าบัญชีสมาชิก สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
            </p>
          </div>
        </div>
      </section>

      {/* Main Content */}
      <div className="container" style={{ marginTop: '2.5rem' }}>
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))',
          gap: '2rem',
          alignItems: 'start'
        }}>
          {/* Left Column: Interactive Table */}
          <div style={{ gridColumn: 'span 2' }}>
            <div className="surface-card" style={{
              padding: '1.75rem',
              borderRadius: 'var(--radius-xl)',
              boxShadow: 'var(--shadow-sm)',
              background: 'var(--bg-surface)'
            }}>
              <div style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                flexWrap: 'wrap',
                gap: '1rem',
                marginBottom: '1.5rem',
                paddingBottom: '1rem',
                borderBottom: '1px solid var(--border-color)'
              }}>
                <div>
                  <h2 style={{ fontSize: '1.25rem', fontWeight: 700, margin: 0 }}>
                    ตารางกำหนดการรายเดือน ประจำปี 2569
                  </h2>
                  <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                    โปรดยื่นเอกสารให้ครบถ้วนก่อนวันสิ้นสุดการรับคำขอในแต่ละเดือน
                  </span>
                </div>
                <a
                  href="/assets/img/pay2569.png"
                  download="pay2569.png"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn btn-primary btn-sm"
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}
                >
                  <Download size={16} />
                  <span>ดาวน์โหลดประกาศฉบับเต็ม</span>
                </a>
              </div>

              {/* Responsive Table */}
              <div style={{ overflowX: 'auto' }}>
                <table style={{
                  width: '100%',
                  borderCollapse: 'collapse',
                  textAlign: 'left',
                  fontSize: '0.95rem'
                }}>
                  <thead>
                    <tr style={{
                      background: 'rgba(15, 43, 92, 0.05)',
                      borderBottom: '2px solid var(--border-color)'
                    }}>
                      <th style={{ padding: '0.85rem 1rem', fontWeight: 700 }}>ประจำเดือน</th>
                      <th style={{ padding: '0.85rem 1rem', fontWeight: 700, color: '#dc2626' }}>วันสิ้นสุดการยื่นกู้</th>
                      <th style={{ padding: '0.85rem 1rem', fontWeight: 700, color: '#d97706' }}>วันประชุมพิจารณา</th>
                      <th style={{ padding: '0.85rem 1rem', fontWeight: 700, color: '#16a34a' }}>วันโอนเงินกู้</th>
                      <th style={{ padding: '0.85rem 1rem', fontWeight: 700 }}>หมายเหตุ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {SCHEDULE_2569.map((item, idx) => (
                      <tr
                        key={item.month}
                        style={{
                          borderBottom: '1px solid var(--border-color)',
                          background: idx % 2 === 0 ? 'transparent' : 'rgba(0, 0, 0, 0.02)'
                        }}
                      >
                        <td style={{ padding: '0.85rem 1rem', fontWeight: 600 }}>{item.month}</td>
                        <td style={{ padding: '0.85rem 1rem', color: '#b91c1c', fontWeight: 600 }}>{item.deadline}</td>
                        <td style={{ padding: '0.85rem 1rem', color: '#b45309' }}>{item.meeting}</td>
                        <td style={{ padding: '0.85rem 1rem', color: '#15803d', fontWeight: 700 }}>{item.transfer}</td>
                        <td style={{ padding: '0.85rem 1rem', color: 'var(--text-muted)', fontSize: '0.85rem' }}>{item.note}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Terms and Guidelines */}
            <div className="surface-card" style={{
              marginTop: '1.5rem',
              padding: '1.5rem',
              borderRadius: 'var(--radius-lg)',
              background: 'rgba(239, 246, 255, 0.6)',
              border: '1px solid rgba(59, 130, 246, 0.2)'
            }}>
              <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'flex-start' }}>
                <AlertCircle size={22} style={{ color: '#2563eb', flexShrink: 0, marginTop: '2px' }} />
                <div>
                  <h3 style={{ fontSize: '1.05rem', fontWeight: 700, color: '#1e3a8a', marginBottom: '0.5rem' }}>
                    ข้อปฏิบัติสำคัญสำหรับการยื่นคำขอกู้
                  </h3>
                  <ul style={{
                    margin: 0,
                    paddingLeft: '1.25rem',
                    fontSize: '0.9rem',
                    color: '#334155',
                    lineHeight: 1.7
                  }}>
                    <li>สมาชิกต้องยื่นคำขอกู้พร้อมเอกสารหลักฐานและลายมือชื่อผู้ค้ำประกันให้ครบถ้วน ภายในเวลา 16.30 น. ของวันสิ้นสุดการรับคำขอในแต่ละเดือน</li>
                    <li>เอกสารที่ส่งหลังจากวันสิ้นสุดการรับคำขอ จะถูกยกยอดไปพิจารณาในการประชุมรอบเดือนถัดไป</li>
                    <li>การจ่ายเงินกู้ สหกรณ์จะโอนเงินเข้าบัญชีเงินฝากที่สมาชิกระบุไว้ในคำขอกู้ ในวันที่กำหนดการโอนเงินกู้</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column: Poster Preview & Quick Links */}
          <div>
            {/* Poster Card */}
            <div className="surface-card" style={{
              padding: '1.5rem',
              borderRadius: 'var(--radius-xl)',
              background: 'var(--bg-surface)',
              marginBottom: '1.5rem',
              textAlign: 'center'
            }}>
              <h3 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '1rem', textAlign: 'left' }}>
                ประกาศทางการ (Poster)
              </h3>
              <div style={{
                borderRadius: 'var(--radius-md)',
                overflow: 'hidden',
                border: '1px solid var(--border-color)',
                marginBottom: '1rem'
              }}>
                <img
                  src="/assets/img/pay2569.png"
                  alt="กำหนดการยื่นกู้และจ่ายเงินกู้ ประจำปี 2569"
                  style={{ width: '100%', height: 'auto', display: 'block' }}
                />
              </div>
              <a
                href="/assets/img/pay2569.png"
                target="_blank"
                rel="noopener noreferrer"
                className="btn btn-outline btn-block"
                style={{ width: '100%' }}
              >
                <span>เปิดดูรูปขนาดเต็ม</span>
              </a>
            </div>

            {/* Quick Action Navigation */}
            <div className="surface-card" style={{
              padding: '1.5rem',
              borderRadius: 'var(--radius-xl)',
              background: 'var(--bg-surface)'
            }}>
              <h3 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '1rem' }}>
                บริการที่เกี่ยวข้อง
              </h3>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                <Link
                  to="/documents?category=loans"
                  className="surface-card"
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '0.85rem 1rem',
                    borderRadius: 'var(--radius-md)',
                    border: '1px solid var(--border-color)',
                    textDecoration: 'none',
                    color: 'inherit'
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <FileText size={18} style={{ color: '#2563eb' }} />
                    <span style={{ fontWeight: 600, fontSize: '0.92rem' }}>ดาวน์โหลดแบบฟอร์มกู้</span>
                  </div>
                  <ChevronRight size={16} style={{ color: 'var(--text-muted)' }} />
                </Link>

                <Link
                  to="/calculator"
                  className="surface-card"
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '0.85rem 1rem',
                    borderRadius: 'var(--radius-md)',
                    border: '1px solid var(--border-color)',
                    textDecoration: 'none',
                    color: 'inherit'
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <Calculator size={18} style={{ color: '#d97706' }} />
                    <span style={{ fontWeight: 600, fontSize: '0.92rem' }}>คำนวณเงินกู้ออนไลน์</span>
                  </div>
                  <ChevronRight size={16} style={{ color: 'var(--text-muted)' }} />
                </Link>

                <Link
                  to="/loans"
                  className="surface-card"
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '0.85rem 1rem',
                    borderRadius: 'var(--radius-md)',
                    border: '1px solid var(--border-color)',
                    textDecoration: 'none',
                    color: 'inherit'
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <CheckCircle2 size={18} style={{ color: '#16a34a' }} />
                    <span style={{ fontWeight: 600, fontSize: '0.92rem' }}>ดูเงินกู้ของสหกรณ์</span>
                  </div>
                  <ChevronRight size={16} style={{ color: 'var(--text-muted)' }} />
                </Link>

                <div style={{
                  padding: '1rem',
                  borderRadius: 'var(--radius-md)',
                  background: 'rgba(15, 23, 42, 0.04)',
                  fontSize: '0.85rem',
                  color: 'var(--text-muted)',
                  marginTop: '0.5rem'
                }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: 'var(--text-main)', fontWeight: 600, marginBottom: '0.25rem' }}>
                    <PhoneCall size={16} />
                    <span>ติดต่อฝ่ายสินเชื่อ</span>
                  </div>
                  <span>โทร. 038-616-258, 038-617-380 ในวันและเวลาทำการ</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

