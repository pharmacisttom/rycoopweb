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
          <div style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', padding: '0.35rem 0.85rem', background: 'var(--primary-100)', borderRadius: '9999px', fontSize: '0.8rem', fontWeight: 600, color: 'var(--primary-700)', marginBottom: '1rem' }}>
            <span>แผนยุทธศาสตร์ ประจำปี 2569 - 2573</span>
          </div>
          <h2 style={{ fontSize: '1.35rem', color: 'var(--primary-800)', marginBottom: '0.75rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Shield size={21} aria-hidden="true" />
            วิสัยทัศน์ (Vision)
          </h2>
          <blockquote style={{ fontSize: '1.2rem', fontWeight: 700, color: 'var(--text-main)', borderLeft: '4px solid var(--accent-gold)', paddingLeft: '1rem', marginBottom: '0.75rem' }}>
            "เป็นสหกรณ์ชั้นนำ ก้าวล้ำเทคโนโลยี เพื่อคุณภาพชีวิตที่ดีของสมาชิก"
          </blockquote>
          <p style={{ fontSize: '0.95rem', color: 'var(--text-muted)', marginBottom: '1.5rem', paddingLeft: '1rem' }}>
            <strong style={{ color: 'var(--text-main)' }}>คำขวัญ (Motto):</strong> การเงินมั่นคง ซื่อตรงโปร่งใส บริการฉับไว ใส่ใจสมาชิก
          </p>

          <h3 style={{ fontSize: '1.1rem', color: 'var(--primary-800)', marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Award size={19} aria-hidden="true" />
            พันธกิจ (Mission)
          </h3>
          <ul style={{ listStyle: 'none', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '0.75rem 1.5rem', fontSize: '0.925rem', color: 'var(--text-main)' }}>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>1. เสริมสร้างความมั่นคงด้านการเงิน โดยคำนึงถึงประโยชน์ของสมาชิก</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>2. พัฒนาประสิทธิภาพการให้บริการแก่สมาชิก</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>3. จัดสวัสดิการที่เหมาะสมให้แก่สมาชิก</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>4. ส่งเสริมการมีส่วนร่วมของสมาชิก</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>5. พัฒนาระบบการบริหารจัดการตามหลักธรรมาภิบาล</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>6. พัฒนาระบบเทคโนโลยีสารสนเทศ</span></li>
            <li style={{ display: 'flex', alignItems: 'flex-start', gap: '0.5rem' }}><CheckCircle2 size={16} style={{ color: 'var(--accent-teal)', flexShrink: 0, marginTop: '0.2rem' }} aria-hidden="true" /><span>7. สนับสนุนกิจกรรมเพื่อชุมชน สังคมและสิ่งแวดล้อม</span></li>
          </ul>
        </div>

        {/* Restored from the historical expanded About page (2026-09-09). */}
        <section aria-labelledby="founding-committee" className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '2.5rem' }}>
          <span className="badge badge-primary" style={{ marginBottom: '0.75rem' }}>ประวัติและบุคคลสำคัญ</span>
          <h2 id="founding-committee" style={{ fontSize: '1.6rem', color: 'var(--primary-800)', marginBottom: '0.75rem' }}>
            คณะผู้จัดตั้งสหกรณ์ 5 ท่านแรก
          </h2>
          <p style={{ color: 'var(--text-muted)', marginBottom: '1.25rem' }}>บุคคลผู้ริเริ่มก่อตั้งสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด เพื่อความมั่นคงและการเกื้อกูลสมาชิก</p>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 230px), 1fr))', gap: '1rem' }}>
            {[
              ['นายแพทย์อรรณพ สมาธิวัฒน์', 'ประธาน'],
              ['นายแพทย์มนูญ จิรัฐติกาลกิจ', 'รองประธาน'],
              ['นายแพทย์สาโรจน์ คงประศาสตร์', 'รองประธาน'],
              ['นางสาวอุษา อิศรางกูร ณ อยุธยา', 'เหรัญญิก'],
              ['นายบุญจอม ณ นคร', 'เลขานุการ'],
            ].map(([name, role], index) => (
              <article key={name} className="surface-card" style={{ padding: '1.25rem', display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
                <span aria-hidden="true" style={{ display: 'grid', placeItems: 'center', width: 38, height: 38, flexShrink: 0, borderRadius: '50%', background: 'var(--primary-700)', color: '#fff', fontWeight: 700 }}>{index + 1}</span>
                <div><strong style={{ display: 'block', color: 'var(--text-main)' }}>{name}</strong><span style={{ color: 'var(--text-muted)', fontSize: '0.86rem' }}>{role}</span></div>
              </article>
            ))}
          </div>
        </section>

        <section aria-labelledby="cooperative-objectives" className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)', marginBottom: '2.5rem' }}>
          <span className="badge badge-primary" style={{ marginBottom: '0.75rem' }}>อุดมการณ์และความมุ่งมั่น</span>
          <h2 id="cooperative-objectives" style={{ fontSize: '1.6rem', color: 'var(--primary-800)', marginBottom: '1rem' }}>หลักการและวัตถุประสงค์ของสหกรณ์</h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 330px), 1fr))', gap: '1.5rem' }}>
            <div className="surface-card" style={{ padding: '1.5rem' }}>
              <h3 style={{ color: 'var(--primary-800)', marginBottom: '0.75rem' }}>หลักการสหกรณ์</h3>
              <p style={{ color: 'var(--text-main)', lineHeight: 1.8, marginBottom: '1rem' }}>สหกรณ์เป็นการรวมตัวกันโดยสมัครใจเพื่อประโยชน์ทางเศรษฐกิจและสังคม บนพื้นฐานการช่วยตนเองและช่วยเหลือซึ่งกันและกัน</p>
              <p style={{ color: 'var(--text-muted)', lineHeight: 1.8 }}>การดำเนินงานยึดหลักประชาธิปไตย ความเสมอภาคของสมาชิก ธรรมาภิบาล และการกระจายผลประโยชน์อย่างเป็นธรรม</p>
            </div>
            <div className="surface-card" style={{ padding: '1.5rem' }}>
              <h3 style={{ color: 'var(--primary-800)', marginBottom: '1rem' }}>วัตถุประสงค์ของสหกรณ์ 5 ประการ</h3>
              <ol style={{ display: 'grid', gap: '0.85rem', paddingLeft: '1.5rem', lineHeight: 1.7, color: 'var(--text-main)' }}>
                <li><strong>ส่งเสริมการประหยัด</strong> และปลูกฝังวินัยทางการเงินแก่สมาชิกและครอบครัว</li>
                <li><strong>ส่งเสริมการออมและการสะสมทุน</strong> ผ่านทุนเรือนหุ้นและเงินฝากของสมาชิก</li>
                <li><strong>ให้เงินกู้ที่จำเป็นแก่สมาชิก</strong> ด้วยเงื่อนไขและอัตราดอกเบี้ยที่เหมาะสม</li>
                <li><strong>ส่งเสริมการลงทุนประกอบอาชีพ</strong> เพื่อสร้างรายได้และพัฒนาคุณภาพชีวิต</li>
                <li><strong>จัดสวัสดิการและการสงเคราะห์</strong> แก่สมาชิกและครอบครัวตามหลักเกณฑ์สหกรณ์</li>
              </ol>
            </div>
          </div>
        </section>

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
