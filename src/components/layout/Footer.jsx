import React from 'react';
import { Link } from 'react-router-dom';
import { 
  MapPin, Phone, Mail, Clock, Shield, Award, 
  ExternalLink, MessageCircle, FileText, ChevronRight, LockKeyhole
} from 'lucide-react';
import { COOP_INFO } from '../../data/mockData';

export default function Footer() {
  return (
    <footer style={{
      background: 'var(--primary-950)',
      color: '#94a3b8',
      fontSize: '0.9rem',
      paddingTop: '4rem',
      paddingBottom: '2rem',
      borderTop: '1px solid rgba(255,255,255,0.08)',
      marginTop: 'auto'
    }}>
      <div className="container">
        
        {/* Top Grid */}
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
          gap: '3rem',
          marginBottom: '3.5rem'
        }}>
          
          {/* Col 1: About & Trust */}
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '1.25rem' }}>
              <img 
                src="/assets/img/logo.webp" 
                alt="Logo" 
                style={{ width: '44px', height: '44px', objectFit: 'contain' }}
                onError={(e) => { e.target.src = '/img/logo.webp'; }}
              />
              <div>
                <h4 style={{ color: '#ffffff', fontSize: '1.05rem', lineHeight: 1.3 }}>{COOP_INFO.nameTh}</h4>
                <p style={{ fontSize: '0.75rem', color: '#64748b' }}>ก่อตั้งเมื่อปี พ.ศ. {COOP_INFO.establishedYear}</p>
              </div>
            </div>
            
            <p style={{ color: '#cbd5e1', fontSize: '0.88rem', lineHeight: 1.6, marginBottom: '1.5rem' }}>
              {COOP_INFO.slogan} ยึดมั่นในหลักธรรมาภิบาล มุ่งพัฒนาคุณภาพชีวิตของสมาชิกบุคลากรสาธารณสุขจังหวัดระยองอย่างยั่งยืน
            </p>

            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.4rem',
                background: 'rgba(255, 255, 255, 0.06)',
                padding: '0.4rem 0.8rem',
                borderRadius: '8px',
                fontSize: '0.8rem',
                color: '#38bdf8'
              }}>
                <Shield size={16} />
                <span>ความมั่นคงทางการเงินมาตรฐาน A+</span>
              </div>
            </div>
          </div>

          {/* Col 2: Quick Links */}
          <div>
            <h4 style={{ color: '#ffffff', fontSize: '1.05rem', marginBottom: '1.25rem', borderBottom: '2px solid var(--primary-600)', display: 'inline-block', paddingBottom: '0.35rem' }}>
              บริการทางการเงิน
            </h4>
            <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <li>
                <Link to="/deposits" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-gold)' }} />
                  <span>เงินฝากและอัตราดอกเบี้ย</span>
                </Link>
              </li>
              <li>
                <Link to="/loans" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-gold)' }} />
                  <span>เงินกู้ฉุกเฉิน / สามัญ / พิเศษ</span>
                </Link>
              </li>
              <li>
                <Link to="/calculator" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-gold)' }} />
                  <span>โปรแกรมคำนวณค่างวดเงินกู้</span>
                </Link>
              </li>
              <li>
                <Link to="/dividend-estimator" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-gold)' }} />
                  <span>คำนวณเงินปันผล - เฉลี่ยคืน</span>
                </Link>
              </li>
              <li>
                <Link to="/loan-checklist" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-gold)' }} />
                  <span>ตรวจเช็คความพร้อมการกู้เงิน</span>
                </Link>
              </li>
            </ul>
          </div>

          {/* Col 3: Member & e-Services */}
          <div>
            <h4 style={{ color: '#ffffff', fontSize: '1.05rem', marginBottom: '1.25rem', borderBottom: '2px solid var(--accent-teal)', display: 'inline-block', paddingBottom: '0.35rem' }}>
              สวัสดิการและสมาชิก
            </h4>
            <ul style={{ listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <li>
                <Link to="/welfare" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-teal)' }} />
                  <span>กองทุนสวัสดิการสมาชิก</span>
                </Link>
              </li>
              <li>
                <Link to="/eservice" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-teal)' }} />
                  <span>ศูนย์บริการ e-Services ออนไลน์</span>
                </Link>
              </li>
              <li>
                <Link to="/documents" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-teal)' }} />
                  <span>ดาวน์โหลดแบบฟอร์ม & ระเบียบ</span>
                </Link>
              </li>
              <li>
                <Link to="/verify-receipt" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-teal)' }} />
                  <span>ตรวจสอบใบเสร็จรับเงิน (e-Receipt)</span>
                </Link>
              </li>
              <li>
                <Link to="/board" style={footerLinkStyle}>
                  <ChevronRight size={14} style={{ color: 'var(--accent-teal)' }} />
                  <span>คณะกรรมการและฝ่ายจัดการ</span>
                </Link>
              </li>
            </ul>
          </div>

          {/* Col 4: Contact Info */}
          <div>
            <h4 style={{ color: '#ffffff', fontSize: '1.05rem', marginBottom: '1.25rem', borderBottom: '2px solid var(--accent-gold)', display: 'inline-block', paddingBottom: '0.35rem' }}>
              ติดต่อสำนักงาน
            </h4>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
              <a 
                href={COOP_INFO.mapUrl} 
                target="_blank" 
                rel="noopener noreferrer" 
                style={{ display: 'flex', alignItems: 'flex-start', gap: '0.65rem', color: '#cbd5e1', textDecoration: 'none', transition: 'color 0.2s' }}
                title="คลิกเพื่อดูแผนที่ Google Maps"
              >
                <MapPin size={18} style={{ color: 'var(--accent-gold)', flexShrink: 0, marginTop: '3px' }} />
                <span style={{ fontSize: '0.85rem' }}>{COOP_INFO.address}</span>
              </a>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <Phone size={16} style={{ color: 'var(--accent-gold)', flexShrink: 0 }} />
                <span style={{ fontSize: '0.85rem', color: '#cbd5e1' }}>{COOP_INFO.phone}</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <Mail size={16} style={{ color: 'var(--accent-gold)', flexShrink: 0 }} />
                <span style={{ fontSize: '0.85rem', color: '#cbd5e1' }}>{COOP_INFO.email}</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <Clock size={16} style={{ color: 'var(--accent-gold)', flexShrink: 0 }} />
                <span style={{ fontSize: '0.85rem', color: '#cbd5e1' }}>{COOP_INFO.workHours}</span>
              </div>

              {/* Social Channels */}
              <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem' }}>
                <a 
                  href={`https://${COOP_INFO.facebook}`} 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  style={socialIconStyle}
                  title="Facebook Fanpage"
                >
                  Facebook
                </a>
                <a 
                  href="https://line.me" 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  style={{ ...socialIconStyle, background: 'rgba(6, 199, 85, 0.2)', color: '#4ade80' }}
                  title="LINE Official Account"
                >
                  LINE ID: {COOP_INFO.lineId}
                </a>
              </div>
            </div>
          </div>

        </div>

        {/* Bottom Bar */}
        <div style={{
          borderTop: '1px solid rgba(255,255,255,0.08)',
          paddingTop: '1.5rem',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '1rem',
          fontSize: '0.8rem'
        }}>
          <div>
            © {new Date().getFullYear()} {COOP_INFO.nameTh} สงวนลิขสิทธิ์ตามกฎหมาย
          </div>
          <div style={{ display: 'flex', gap: '1.5rem', flexWrap: 'wrap' }}>
            <Link
              to="/admin/login"
              style={{ color: '#fbbf24', display: 'inline-flex', alignItems: 'center', gap: '0.4rem', fontWeight: 700 }}
              aria-label="เข้าสู่ระบบหลังบ้านสำหรับเจ้าหน้าที่"
            >
              <LockKeyhole size={14} /> เข้าสู่ระบบเจ้าหน้าที่
            </Link>
            <Link to="/contact" style={{ color: '#94a3b8' }}>รับเรื่องร้องเรียน</Link>
            <Link to="/documents" style={{ color: '#94a3b8' }}>นโยบายคุ้มครองข้อมูลส่วนบุคคล (PDPA)</Link>
            <Link to="/documents" style={{ color: '#94a3b8' }}>ข้อกำหนดและเงื่อนไข</Link>
          </div>
        </div>

      </div>
    </footer>
  );
}

const footerLinkStyle = {
  display: 'inline-flex',
  alignItems: 'center',
  gap: '0.4rem',
  color: '#cbd5e1',
  transition: 'all 0.2s ease'
};

const socialIconStyle = {
  display: 'inline-flex',
  alignItems: 'center',
  padding: '0.35rem 0.75rem',
  background: 'rgba(59, 130, 246, 0.2)',
  color: '#60a5fa',
  borderRadius: '6px',
  fontSize: '0.78rem',
  fontWeight: '600'
};
