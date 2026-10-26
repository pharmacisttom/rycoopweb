import { usePublicInformation } from '../context/PublicContentContext';
import React from 'react';
import { Landmark, Target, Shield, Heart, Award, CheckCircle2 } from 'lucide-react';


export default function AboutPage() {
  const { COOP_INFO } = usePublicInformation();
  return (
    <div className="section">
      <div className="container">
        
        {/* Page Header */}
        <div className="section-title-wrap">
          <span className="section-badge">ข้อมูลองค์กร</span>
          <h1 className="section-title">เกี่ยวกับสหกรณ์</h1>
          <p className="section-subtitle">
            <span style={{ display: 'block' }}>{COOP_INFO.nameTh}</span>
            <span style={{ display: 'block', marginTop: '0.2rem' }}>({COOP_INFO.nameEn})</span>
          </p>
          <div className="section-line" />
        </div>

        {/* History */}
        <div className="glass-card" style={{ padding: '2.5rem', borderRadius: 'var(--radius-xl)', marginBottom: '2.5rem' }}>
          <span className="badge badge-primary" style={{ marginBottom: '0.75rem' }}>ความเป็นมา</span>
          <h2 style={{ fontSize: '1.75rem', color: 'var(--primary-800)', marginBottom: '2rem' }}>
            รากฐานของการออม เพื่อความมั่นคงของสมาชิก
          </h2>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
            <section aria-labelledby="thai-health-coops-heading">
              <h3 id="thai-health-coops-heading" style={{ fontSize: '1.25rem', color: 'var(--primary-700)', marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <Landmark size={20} aria-hidden="true" />
                สหกรณ์สาธารณสุขของไทย
              </h3>
              <p style={{ color: 'var(--text-muted)', lineHeight: 1.8, marginBottom: '1rem' }}>
                สหกรณ์ออมทรัพย์สาธารณสุขอยู่บนรากฐานของขบวนการสหกรณ์ออมทรัพย์ไทย ซึ่งรวมสมาชิกที่มีอาชีพหรือชุมชนร่วมกันเพื่อออมเงิน สร้างกองทุน และช่วยเหลือกันทางการเงินตามหลักช่วยตนเองและช่วยเหลือซึ่งกันและกัน
              </p>
              <p style={{ color: 'var(--text-muted)', lineHeight: 1.8, marginBottom: '1rem' }}>
                ประเทศไทยเริ่มศึกษาวิธีการสหกรณ์ใน พ.ศ. 2457 และมีสหกรณ์แห่งแรกคือ “สหกรณ์วัดจันทร์ ไม่จำกัดสินใช้” จดทะเบียนเมื่อ 26 กุมภาพันธ์ พ.ศ. 2459 ต่อมา สหกรณ์ออมทรัพย์แห่งแรกของไทยจดทะเบียนเมื่อ 28 กันยายน พ.ศ. 2492
              </p>
              <p style={{ color: 'var(--text-muted)', lineHeight: 1.8 }}>
                ในบริบทบุคลากรสาธารณสุข รูปแบบนี้ช่วยให้สมาชิกมีแหล่งออม แหล่งเงินกู้ตามความจำเป็น และสวัสดิการที่ร่วมกันดูแลผ่านองค์กรของสมาชิกเอง
              </p>
            </section>

            <section aria-labelledby="rayong-coop-history-heading" style={{ background: 'var(--gradient-card)', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-lg)', padding: '2rem' }}>
              <h3 id="rayong-coop-history-heading" style={{ fontSize: '1.25rem', color: 'var(--accent-teal-dark)', marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <Target size={20} aria-hidden="true" />
                สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
              </h3>
              <p style={{ color: 'var(--text-muted)', lineHeight: 1.8, marginBottom: '1rem' }}>
                <strong>{COOP_INFO.nameTh}</strong> จัดตั้งเมื่อวันที่ 21 สิงหาคม พ.ศ. 2530 มีสมาชิกแรกตั้ง 230 คน ทุนเรือนหุ้นแรกตั้ง 878,370 บาท และคณะผู้จัดตั้ง 18 คน
              </p>
              <p style={{ color: 'var(--text-muted)', lineHeight: 1.8, marginBottom: '1rem' }}>
                สหกรณ์มีเป้าหมายส่งเสริมการประหยัดและอดออม รวบรวมเงินออมเป็นกองทุนเพื่อให้สมาชิกกู้ ส่งเสริมการลงทุนประกอบอาชีพ และจัดสวัสดิการรวมถึงการสงเคราะห์แก่สมาชิกและครอบครัว
              </p>
              <p style={{ color: 'var(--text-muted)', lineHeight: 1.8, marginBottom: '1rem' }}>
                คณะผู้จัดตั้งนำโดยนายแพทย์อรรณพ สมาธิวัฒน์ ประธานคณะผู้จัดตั้ง ร่วมกับนายแพทย์มนูญ จิรัฐติกาลกิจ และนายแพทย์สาโรจน์ คงประศาสตร์ ในตำแหน่งรองประธาน โดยนางสาวอุษา อิศรางกูร ณ อยุธยา รับหน้าที่เหรัญญิก และนายบุญจอม ณ นคร รับหน้าที่เลขานุการ
              </p>
              <p style={{ color: 'var(--text-muted)', lineHeight: 1.8 }}>
                ระยะแรกสหกรณ์ใช้ห้องเล็กของตึกอำนวยการด้านหน้าเป็นสำนักงาน ก่อนต่อเติมอาคารด้านหลังในราว พ.ศ. 2542 และต่อมาปรับปรุงชั้นล่างอาคารควบคุมโรคเฉลิมพระเกียรติพระบรมราชินีนาถ เพื่อเป็นสำนักงานสหกรณ์ในปัจจุบัน
              </p>
            </section>
          </div>
        </div>

        <div className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '2.5rem' }}>
          <h2 style={{ fontSize: '1.35rem', color: 'var(--primary-800)', marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Shield size={21} aria-hidden="true" />
            วิสัยทัศน์และพันธกิจ
          </h2>
          <blockquote style={{ fontSize: '1.05rem', fontWeight: 600, color: 'var(--text-main)', borderLeft: '4px solid var(--accent-gold)', paddingLeft: '1rem', fontStyle: 'italic', marginBottom: '1.5rem' }}>
            "เป็นสถาบันการเงินชั้นนำของบุคลากรสาธารณสุข บริหารงานโปร่งใส ใช้เทคโนโลยีดิจิทัล พัฒนาคุณภาพชีวิตสมาชิกอย่างยั่งยืน"
          </blockquote>
          <ul style={{ listStyle: 'none', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '0.75rem 1.5rem', fontSize: '0.9rem', color: 'var(--text-muted)' }}>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>ส่งเสริมการออมทรัพย์และสร้างวินัยทางการเงินแก่สมาชิก</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>ให้บริการสินเชื่ออัตราดอกเบี้ยเป็นธรรม เพื่อสวัสดิการและที่อยู่อาศัย</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>จัดสวัสดิการที่ครอบคลุมทุกช่วงชีวิตของสมาชิกและครอบครัว</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>พัฒนาระบบเทคโนโลยีดิจิทัลเพื่อความสะดวกรวดเร็วและปลอดภัย</span></li>
          </ul>
        </div>

        {/* Core Values */}
        <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
          <h2 style={{ fontSize: '1.6rem', color: 'var(--primary-800)', marginBottom: '0.5rem' }}>ค่านิยมหลักขององค์กร (Core Values)</h2>
          <p style={{ color: 'var(--text-muted)' }}>หลักการทำงานที่บุคลากรสหกรณ์ทุกคนยึดถือปฏิบัติ</p>
        </div>

        <div className="grid-4">
          <div className="surface-card" style={{ padding: '1.75rem', textAlign: 'center' }}>
            <div style={{ width: '50px', height: '50px', borderRadius: '50%', background: 'var(--primary-100)', color: 'var(--primary-700)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem auto' }}>
              <Shield size={24} />
            </div>
            <h4 style={{ fontSize: '1.1rem', marginBottom: '0.4rem' }}>ความซื่อสัตย์โปร่งใส</h4>
            <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>ดำเนินงานตามหลักธรรมาภิบาล ตรวจสอบได้ทุกขั้นตอน</p>
          </div>

          <div className="surface-card" style={{ padding: '1.75rem', textAlign: 'center' }}>
            <div style={{ width: '50px', height: '50px', borderRadius: '50%', background: 'var(--accent-teal-light)', color: 'var(--accent-teal-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem auto' }}>
              <Heart size={24} />
            </div>
            <h4 style={{ fontSize: '1.1rem', marginBottom: '0.4rem' }}>ใส่ใจบริการ</h4>
            <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>ให้บริการสมาชิกด้วยความอบอุ่น รวดเร็ว และเป็นมิตร</p>
          </div>

          <div className="surface-card" style={{ padding: '1.75rem', textAlign: 'center' }}>
            <div style={{ width: '50px', height: '50px', borderRadius: '50%', background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem auto' }}>
              <Award size={24} />
            </div>
            <h4 style={{ fontSize: '1.1rem', marginBottom: '0.4rem' }}>มุ่งมั่นพัฒนา</h4>
            <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>นำเทคโนโลยีใหม่ๆ มายกระดับการให้บริการสมาชิกรวดเร็วยิ่งขึ้น</p>
          </div>

          <div className="surface-card" style={{ padding: '1.75rem', textAlign: 'center' }}>
            <div style={{ width: '50px', height: '50px', borderRadius: '50%', background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem auto' }}>
              <Landmark size={24} />
            </div>
            <h4 style={{ fontSize: '1.1rem', marginBottom: '0.4rem' }}>ความมั่นคงยั่งยืน</h4>
            <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>บริหารความเสี่ยงอย่างรอบคอบ เพื่อความปลอดภัยของเงินฝาก</p>
          </div>
        </div>

      </div>
    </div>
  );
}
