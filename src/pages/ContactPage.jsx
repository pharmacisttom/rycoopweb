import React, { useState, useEffect } from 'react';
import { fetchFaqs } from '../services/api';
import RichTextContent from '../components/common/RichTextContent';
import {
  MapPin, Phone, Mail, Clock, MessageSquare,
  Send, CheckCircle2, ChevronDown, ChevronUp, Search, ShieldCheck, AlertCircle
} from 'lucide-react';
import { COOP_INFO, FAQS, MEMBER_COMPLAINTS } from '../data/mockData';

export default function ContactPage() {
  const [activeTab, setActiveTab] = useState('contact');
  const [submitted, setSubmitted] = useState(false);
  const [lastSubmittedId, setLastSubmittedId] = useState('');
  const [trackingId, setTrackingId] = useState('');
  const [trackingResult, setTrackingResult] = useState(null);
  const [openFaq, setOpenFaq] = useState(null);
  const [faqs,setFaqs]=useState([]);
  useEffect(()=>{let cancelled=false;fetchFaqs().then(result=>{if(!cancelled && result?.success)setFaqs(result.data.map(f=>({...f,q:f.question,a:f.answer})));});return()=>{cancelled=true;};},[]);

  const [form, setForm] = useState({
    name: '',
    phone: '',
    email: '',
    topic: 'สอบถามข้อมูลทั่วไป',
    message: ''
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const newId = `TKT-${Math.floor(100000 + Math.random() * 900000)}`;
    const newEntry = {
      id: newId,
      name: form.name,
      phone: form.phone,
      email: form.email || '-',
      department: 'สมาชิกทั่วไป',
      topic: form.topic,
      message: form.message,
      date: new Date().toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' }),
      status: 'รอดำเนินการ',
      adminReply: '',
      replyDate: ''
    };

    try {
      const saved = localStorage.getItem('coop_member_complaints');
      const list = saved ? JSON.parse(saved) : MEMBER_COMPLAINTS;
      const updated = [newEntry, ...list];
      localStorage.setItem('coop_member_complaints', JSON.stringify(updated));
      window.dispatchEvent(new Event('storage'));
    } catch (err) {
      // ignore
    }

    setLastSubmittedId(newId);
    setSubmitted(true);
  };

  const handleTrack = (e) => {
    e.preventDefault();
    if (!trackingId) return;

    let list = MEMBER_COMPLAINTS;
    try {
      const saved = localStorage.getItem('coop_member_complaints');
      if (saved) list = JSON.parse(saved);
    } catch (err) {
      // ignore
    }

    const found = list.find(item => item.id.toLowerCase() === trackingId.trim().toLowerCase());
    if (found) {
      setTrackingResult(found);
    } else {
      setTrackingResult({
        notFound: true,
        id: trackingId
      });
    }
  };

  return (
    <div className="section">
      <div className="container">

        <div className="section-title-wrap">
          <span className="section-badge">ติดต่อและบริการ</span>
          <h1 className="section-title">ติดต่อเรา & รับเรื่องร้องเรียน</h1>
          <p className="section-subtitle">ยินดีรับฟังทุกข้อเสนอแนะ ติดต่อสอบถาม หรือยื่นเรื่องร้องเรียนการให้บริการ</p>
          <div className="section-line" />
        </div>

        {/* Tab Selection */}
        <div style={{ display: 'flex', justifyContent: 'center', gap: '0.75rem', marginBottom: '2.5rem' }}>
          <button
            onClick={() => setActiveTab('contact')}
            className={`btn ${activeTab === 'contact' ? 'btn-primary' : 'btn-subtle'}`}
            style={{ borderRadius: 'var(--radius-full)', padding: '0.55rem 1.5rem' }}
          >
            แบบฟอร์มติดต่อ / ร้องเรียน
          </button>
          <button
            onClick={() => setActiveTab('track')}
            className={`btn ${activeTab === 'track' ? 'btn-primary' : 'btn-subtle'}`}
            style={{ borderRadius: 'var(--radius-full)', padding: '0.55rem 1.5rem' }}
          >
            ติดตามสถานะเรื่องร้องเรียน
          </button>
        </div>

        {activeTab === 'contact' ? (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '2.5rem', marginBottom: '4rem' }}>

            {/* Contact Info Card */}
            <div className="surface-card" style={{ padding: '2rem', display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
              <div>
                <span className="badge badge-primary" style={{ marginBottom: '0.5rem' }}>ที่ทำการสหกรณ์</span>
                <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-900)' }}>{COOP_INFO.nameTh}</h3>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'flex-start' }}>
                  <MapPin size={20} style={{ color: 'var(--primary-600)', flexShrink: 0, marginTop: '2px' }} />
                  <span style={{ fontSize: '0.9rem', color: 'var(--text-main)' }}>{COOP_INFO.address}</span>
                </div>

                <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
                  <Phone size={20} style={{ color: 'var(--primary-600)', flexShrink: 0 }} />
                  <span style={{ fontSize: '0.9rem', color: 'var(--text-main)' }}>{COOP_INFO.phone}</span>
                </div>

                <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
                  <Mail size={20} style={{ color: 'var(--primary-600)', flexShrink: 0 }} />
                  <span style={{ fontSize: '0.9rem', color: 'var(--text-main)' }}>{COOP_INFO.email}</span>
                </div>

                <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
                  <Clock size={20} style={{ color: 'var(--primary-600)', flexShrink: 0 }} />
                  <span style={{ fontSize: '0.9rem', color: 'var(--text-main)' }}>{COOP_INFO.workHours}</span>
                </div>
              </div>

              {/* Google Map Section */}
              <div style={{
                borderRadius: '12px',
                overflow: 'hidden',
                border: '1px solid var(--border-subtle)',
                background: 'var(--bg-subtle)'
              }}>
                <iframe
                  title="Rayong Coop Location Map"
                  src={COOP_INFO.mapEmbedUrl}
                  width="100%"
                  height="220"
                  style={{ border: 0, display: 'block' }}
                  allowFullScreen=""
                  loading="lazy"
                  referrerPolicy="no-referrer-when-downgrade"
                />
                <div style={{ padding: '0.75rem 1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'var(--bg-surface)' }}>
                  <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                    📍 142 ม.2 ถ.สุขุมวิท ต.เนินพระ อ.เมืองระยอง
                  </div>
                  <a
                    href={COOP_INFO.mapUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-primary btn-sm"
                    style={{ fontSize: '0.78rem', display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}
                  >
                    <MapPin size={14} />
                    <span>นำทางด้วย Google Maps</span>
                  </a>
                </div>
              </div>

            </div>

            {/* Form */}
            <div className="glass-card" style={{ padding: '2rem', borderRadius: 'var(--radius-xl)' }}>
              <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-800)', marginBottom: '0.5rem' }}>ส่งข้อความถึงสหกรณ์</h3>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginBottom: '1.5rem' }}>
                กรอกข้อมูลเพื่อให้เจ้าหน้าที่ติดต่อกลับหรือดำเนินการตามคำขอ
              </p>

              {submitted ? (
                <div style={{ textAlign: 'center', padding: '3rem 1rem' }}>
                  <div style={{ width: '60px', height: '60px', borderRadius: '50%', background: 'var(--accent-emerald-light)', color: 'var(--accent-emerald-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem auto' }}>
                    <CheckCircle2 size={32} />
                  </div>
                  <h4 style={{ fontSize: '1.2rem', marginBottom: '0.5rem' }}>ได้รับข้อความ/เรื่องร้องเรียนเรียบร้อยแล้ว</h4>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '0.5rem' }}>
                    รหัสติดตามเรื่องของคุณคือ:
                  </p>
                  <div style={{
                    fontSize: '1.4rem',
                    fontWeight: 800,
                    color: 'var(--primary-700)',
                    background: 'var(--bg-subtle)',
                    padding: '0.75rem 1.5rem',
                    borderRadius: '8px',
                    display: 'inline-block',
                    marginBottom: '1.5rem',
                    letterSpacing: '1px'
                  }}>
                    {lastSubmittedId}
                  </div>
                  <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginBottom: '1.5rem' }}>
                    ท่านสามารถนำรหัสนี้มาตรวจสอบสถานะการดำเนินงานและคำตอบจากเจ้าหน้าที่ได้ที่แท็บ "ติดตามสถานะเรื่องร้องเรียน"
                  </p>
                  <button onClick={() => setSubmitted(false)} className="btn btn-primary btn-sm">
                    <span>ส่งข้อความอื่นเพิ่มเติม</span>
                  </button>
                </div>
              ) : (
                <form onSubmit={handleSubmit}>
                  <div className="form-group">
                    <label className="form-label">หัวข้อเรื่อง</label>
                    <select className="form-control" value={form.topic} onChange={(e) => setForm({ ...form, topic: e.target.value })}>
                      <option value="สอบถามข้อมูลทั่วไป">สอบถามข้อมูลทั่วไป</option>
                      <option value="ข้อเสนอแนะการให้บริการ">ข้อเสนอแนะการให้บริการ</option>
                      <option value="ร้องเรียนการบริการ">ร้องเรียนการบริการ / พฤติกรรมเจ้าหน้าที่</option>
                      <option value="แจ้งปัญหาการใช้งานระบบออนไลน์">แจ้งปัญหาการใช้งานระบบออนไลน์</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label className="form-label">ชื่อ - นามสกุล ผู้ติดต่อ</label>
                    <input type="text" className="form-control" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="นาย/นาง/นางสาว..." />
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                    <div className="form-group">
                      <label className="form-label">เบอร์โทรศัพท์</label>
                      <input type="tel" className="form-control" required value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="08x-xxx-xxxx" />
                    </div>
                    <div className="form-group">
                      <label className="form-label">อีเมล</label>
                      <input type="email" className="form-control" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="example@mail.com" />
                    </div>
                  </div>

                  <div className="form-group">
                    <label className="form-label">รายละเอียดข้อความ / เรื่องร้องเรียน</label>
                    <textarea className="form-control" rows={4} required value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} placeholder="พิมพ์รายละเอียดที่ต้องการแจ้ง..." />
                  </div>

                  <button type="submit" className="btn btn-primary" style={{ width: '100%', padding: '0.8rem' }}>
                    <Send size={16} />
                    <span>ส่งข้อความ</span>
                  </button>
                </form>
              )}

            </div>

          </div>
        ) : (
          /* Track Complaint */
          <div className="glass-card" style={{ maxWidth: '640px', margin: '0 auto 4rem auto', padding: '2.5rem', borderRadius: 'var(--radius-xl)' }}>
            <h3 style={{ fontSize: '1.3rem', color: 'var(--primary-800)', marginBottom: '0.5rem', textAlign: 'center' }}>
              ติดตามสถานะเรื่องร้องเรียน / คำร้อง
            </h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', marginBottom: '2rem', textAlign: 'center' }}>
              กรอกรหัสติดตามเรื่อง (Ticket ID เช่น TKT-670301) ที่ได้รับเพื่อตรวจสอบความคืบหน้า
            </p>

            <form onSubmit={handleTrack} style={{ display: 'flex', gap: '0.5rem', marginBottom: '2rem' }}>
              <input
                type="text"
                className="form-control"
                placeholder="เช่น TKT-670301, TKT-670303"
                value={trackingId}
                onChange={(e) => setTrackingId(e.target.value)}
                required
              />
              <button type="submit" className="btn btn-primary">
                <Search size={16} />
                <span>ตรวจสอบ</span>
              </button>
            </form>

            {/* Tracking ID Format Hint */}
            <div style={{ marginBottom: '1.5rem', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
              <span>รูปแบบรหัสติดตาม: <strong>TKT-XXXXXX</strong> (ได้รับเมื่อส่งเรื่องร้องเรียนหรือข้อเสนอแนะสำเร็จ)</span>
            </div>

            {trackingResult && (
              trackingResult.notFound ? (
                <div style={{ background: 'rgba(239, 68, 68, 0.08)', padding: '1.5rem', borderRadius: '12px', border: '1px solid rgba(239, 68, 68, 0.2)', textAlign: 'center', color: 'var(--accent-rose)' }}>
                  <AlertCircle size={28} style={{ margin: '0 auto 0.5rem auto' }} />
                  <div style={{ fontWeight: 700, fontSize: '1rem', marginBottom: '0.25rem' }}>ไม่พบข้อมูลรหัส {trackingResult.id}</div>
                  <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>กรุณาตรวจสอบความถูกต้องของรหัสติดตามเรื่องแล้วลองใหม่อีกครั้ง</div>
                </div>
              ) : (
                <div style={{ background: 'var(--bg-subtle)', padding: '1.5rem', borderRadius: '12px', border: '1px solid var(--border-subtle)', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem' }}>
                    <div>
                      <span className="badge badge-primary">{trackingResult.id}</span>
                      <span style={{ fontWeight: 700, fontSize: '1rem', marginLeft: '0.5rem', color: 'var(--text-main)' }}>{trackingResult.topic}</span>
                    </div>
                    <span className={`badge badge-${trackingResult.status === 'ตอบกลับแล้ว' ? 'emerald' : trackingResult.status === 'กำลังตรวจสอบ' ? 'gold' : 'rose'}`}>
                      {trackingResult.status}
                    </span>
                  </div>

                  <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)', background: 'var(--bg-surface)', padding: '1rem', borderRadius: '8px', border: '1px solid var(--border-subtle)' }}>
                    <div style={{ fontWeight: 600, color: 'var(--text-main)', marginBottom: '0.35rem' }}>ข้อความของคุณ:</div>
                    {trackingResult.message}
                  </div>

                  {trackingResult.adminReply ? (
                    <div style={{ fontSize: '0.88rem', background: 'rgba(16, 185, 129, 0.08)', padding: '1rem', borderRadius: '8px', border: '1px solid rgba(16, 185, 129, 0.3)' }}>
                      <div style={{ fontWeight: 700, color: 'var(--accent-emerald-dark)', display: 'flex', alignItems: 'center', gap: '0.35rem', marginBottom: '0.35rem' }}>
                        <CheckCircle2 size={16} />
                        <span>ข้อความตอบกลับจากสหกรณ์:</span>
                      </div>
                      <p style={{ color: 'var(--text-main)', lineHeight: 1.6, margin: 0 }}>
                        {trackingResult.adminReply}
                      </p>
                      {trackingResult.replyDate && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.5rem', textAlign: 'right' }}>
                          ตอบกลับเมื่อ: {trackingResult.replyDate}
                        </div>
                      )}
                    </div>
                  ) : (
                    <div style={{ fontSize: '0.82rem', color: 'var(--accent-gold-dark)', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                      <Clock size={15} />
                      <span>เรื่องร้องเรียนอยู่ระหว่างการดำเนินการ เจ้าหน้าที่จะตอบกลับโดยเร็วที่สุด</span>
                    </div>
                  )}

                  <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', borderTop: '1px solid var(--border-subtle)', paddingTop: '0.5rem', display: 'flex', justifyContent: 'space-between' }}>
                    <span>ผู้ยื่น: {trackingResult.name}</span>
                    <span>วันที่รับเรื่อง: {trackingResult.date}</span>
                  </div>
                </div>
              )
            )}
          </div>
        )}

        {/* FAQs Accordion */}
        <div style={{ maxWidth: '800px', margin: '0 auto' }}>
          <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
            <h3 style={{ fontSize: '1.4rem', color: 'var(--primary-900)' }}>คำถามที่พบบ่อย (FAQs)</h3>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>คำถามที่สมาชิกสอบถามเข้ามาบ่อยที่สุด</p>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            {faqs.map((faq, idx) => (
              <div key={idx} className="surface-card" style={{ overflow: 'hidden' }}>
                <button
                  onClick={() => setOpenFaq(openFaq === idx ? null : idx)}
                  style={{
                    width: '100%',
                    padding: '1.2rem 1.5rem',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    textAlign: 'left',
                    fontWeight: 700,
                    fontSize: '0.95rem',
                    color: 'var(--text-main)'
                  }}
                >
                  <span>{faq.q}</span>
                  {openFaq === idx ? <ChevronUp size={18} /> : <ChevronDown size={18} />}
                </button>
                {openFaq === idx && (
                  <div style={{ padding: '0 1.5rem 1.25rem 1.5rem', color: 'var(--text-muted)', fontSize: '0.9rem', lineHeight: 1.6, borderTop: '1px solid var(--border-subtle)', paddingTop: '0.75rem' }}>
                    <RichTextContent value={faq.a} />
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>

      </div>
    </div>
  );
}
