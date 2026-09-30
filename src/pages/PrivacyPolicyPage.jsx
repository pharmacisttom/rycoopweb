import React from 'react';
import { Link } from 'react-router-dom';
import { ShieldCheck, Database, Scale, LockKeyhole, UserCheck, Mail, Phone } from 'lucide-react';
import { COOP_INFO } from '../data/mockData';

const topics = [
  ['ข้อมูลที่เก็บรวบรวม', Database, 'ข้อมูลระบุตัวตนและข้อมูลติดต่อ ข้อมูลสมาชิกและธุรกรรมทางการเงิน ข้อมูลการใช้บริการ เอกสารประกอบคำขอ และข้อมูลทางเทคนิคที่จำเป็นต่อความปลอดภัย โดยเก็บเท่าที่จำเป็นต่อการให้บริการ'],
  ['วัตถุประสงค์และฐานกฎหมาย', Scale, 'เพื่อสมัครและบริหารสมาชิก ให้บริการเงินฝาก สินเชื่อ สวัสดิการ ตรวจสอบตัวตน ติดต่อแจ้งข้อมูล ป้องกันการทุจริต และปฏิบัติตามสัญญา หน้าที่ตามกฎหมาย ประโยชน์โดยชอบด้วยกฎหมาย หรือความยินยอม'],
  ['การเปิดเผยข้อมูล', UserCheck, 'อาจเปิดเผยแก่ผู้ให้บริการที่จำเป็น หน่วยงานกำกับดูแล สถาบันการเงิน ผู้ตรวจสอบ หรือหน่วยงานของรัฐตามกฎหมาย โดยกำหนดมาตรการคุ้มครองที่เหมาะสม และไม่ขายข้อมูลเพื่อการตลาด'],
  ['การเก็บรักษาและความปลอดภัย', LockKeyhole, 'เก็บข้อมูลตามระยะเวลาที่จำเป็น อายุความ และข้อกำหนดทางบัญชีหรือกฎหมาย ก่อนลบ ทำลาย หรือทำให้ไม่สามารถระบุตัวบุคคล พร้อมควบคุมการเข้าถึงและใช้มาตรการความปลอดภัยตามความเสี่ยง'],
];

const rights = [
  'ขอเข้าถึงและรับสำเนาข้อมูลส่วนบุคคล',
  'ขอแก้ไขข้อมูลให้ถูกต้องและเป็นปัจจุบัน',
  'ขอลบ ทำลาย หรือทำให้ข้อมูลไม่สามารถระบุตัวบุคคลได้',
  'ขอระงับการใช้หรือขอให้โอนข้อมูลในกรณีที่กฎหมายรองรับ',
  'คัดค้านการประมวลผล และถอนความยินยอมโดยไม่กระทบการดำเนินการก่อนถอน',
  'ร้องเรียนต่อสำนักงานคณะกรรมการคุ้มครองข้อมูลส่วนบุคคล',
];

export default function PrivacyPolicyPage() {
  return (
    <div className="section" style={{ background: 'var(--bg-subtle)' }}>
      <div className="container" style={{ maxWidth: 1080 }}>
        <header className="surface-card" style={{ padding: 'clamp(1.75rem, 5vw, 3.5rem)', marginBottom: '1rem', background: 'linear-gradient(135deg, var(--primary-900), var(--primary-700))', color: '#fff' }}>
          <div style={{ display: 'flex', gap: '1rem', alignItems: 'center', marginBottom: '1rem' }}>
            <ShieldCheck size={44} aria-hidden="true" />
            <div><span style={{ fontSize: '.82rem', opacity: .8 }}>PRIVACY NOTICE</span><h1 style={{ color: '#fff', fontSize: 'clamp(1.65rem, 4vw, 2.5rem)' }}>นโยบายคุ้มครองข้อมูลส่วนบุคคล (PDPA)</h1></div>
          </div>
          <p style={{ maxWidth: 820, lineHeight: 1.8, opacity: .9 }}>สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด ให้ความสำคัญกับความเป็นส่วนตัวและจัดการข้อมูลส่วนบุคคลอย่างโปร่งใส ปลอดภัย และสอดคล้องกับพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562</p>
          <p style={{ marginTop: '.85rem', fontSize: '.85rem', opacity: .75 }}>ปรับปรุงล่าสุด: 30 กันยายน 2569</p>
        </header>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
          {topics.map(([title, Icon, content]) => (
            <section key={title} className="surface-card" style={{ padding: '1.5rem' }}>
              <Icon size={28} color="var(--primary-600)" aria-hidden="true" />
              <h2 style={{ fontSize: '1.1rem', margin: '.8rem 0 .6rem', color: 'var(--primary-900)' }}>{title}</h2>
              <p style={{ lineHeight: 1.8, color: 'var(--text-muted)' }}>{content}</p>
            </section>
          ))}
        </div>

        <section id="rights" className="surface-card" style={{ padding: 'clamp(1.5rem, 4vw, 2.5rem)', marginTop: '1rem' }}>
          <h2 style={{ color: 'var(--primary-900)', marginBottom: '1rem' }}>สิทธิของเจ้าของข้อมูลส่วนบุคคล</h2>
          <p style={{ lineHeight: 1.8, color: 'var(--text-muted)', marginBottom: '1rem' }}>ท่านสามารถยื่นคำขอใช้สิทธิตามกฎหมาย โดยสิทธิบางประการอาจมีเงื่อนไขหรือข้อยกเว้นตามกฎหมาย:</p>
          <ul style={{ display: 'grid', gap: '.7rem', paddingLeft: '1.25rem', lineHeight: 1.7 }}>{rights.map((right) => <li key={right}>{right}</li>)}</ul>
        </section>

        <section id="cookies" className="surface-card" style={{ padding: 'clamp(1.5rem, 4vw, 2.5rem)', marginTop: '1rem' }}>
          <h2 style={{ color: 'var(--primary-900)', marginBottom: '.75rem' }}>คุกกี้และบริการออนไลน์</h2>
          <p style={{ lineHeight: 1.8, color: 'var(--text-muted)' }}>เว็บไซต์ใช้คุกกี้ที่จำเป็นเพื่อความปลอดภัยและการทำงานของระบบ ส่วนคุกกี้ประเภทอื่นจะใช้ตามตัวเลือกที่ท่านให้ไว้ ท่านสามารถเปลี่ยนการตั้งค่าผ่านแบนเนอร์คุกกี้ของเว็บไซต์ได้</p>
        </section>

        <section id="contact" className="surface-card" style={{ padding: 'clamp(1.5rem, 4vw, 2.5rem)', marginTop: '1rem', borderLeft: '4px solid var(--accent-gold)' }}>
          <h2 style={{ color: 'var(--primary-900)', marginBottom: '.75rem' }}>ติดต่อหรือยื่นคำขอใช้สิทธิ</h2>
          <p style={{ lineHeight: 1.8, color: 'var(--text-muted)', marginBottom: '1rem' }}>โปรดระบุชื่อ ข้อมูลติดต่อ สิทธิที่ต้องการใช้ และข้อมูลที่ช่วยยืนยันตัวตน สหกรณ์อาจขอข้อมูลเพิ่มเติมเท่าที่จำเป็นก่อนดำเนินการ</p>
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '1rem 2rem' }}>
            <a href={`mailto:${COOP_INFO.email}`} style={{ display: 'inline-flex', alignItems: 'center', gap: '.5rem' }}><Mail size={18} /> {COOP_INFO.email}</a>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '.5rem' }}><Phone size={18} /> {COOP_INFO.phone}</span>
            <Link to="/contact" className="btn btn-outline btn-sm">ส่งข้อความถึงสหกรณ์</Link>
          </div>
        </section>

        <p style={{ marginTop: '1.25rem', color: 'var(--text-muted)', fontSize: '.85rem', lineHeight: 1.7 }}>สหกรณ์อาจปรับปรุงนโยบายนี้เมื่อกระบวนการหรือกฎหมายเปลี่ยนแปลง โดยจะแสดงฉบับล่าสุดและวันที่มีผลบนหน้านี้</p>
      </div>
    </div>
  );
}
