import React, { useEffect, useState } from 'react';
import { Clock, Eye, ChevronRight, Search, X } from 'lucide-react';
import { fetchNews } from '../services/api';
import { normalizeNewsItem } from '../utils/news';
import SafeImage from '../components/common/SafeImage';

export default function NewsPage() {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedNews, setSelectedNews] = useState(null);
  const [newsList, setNewsList] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    fetchNews().then((result) => {
      if (!cancelled && result?.success && Array.isArray(result.data)) {
        setNewsList(result.data.map(normalizeNewsItem));
      }
    }).finally(() => {
      if (!cancelled) setLoading(false);
    });
    return () => { cancelled = true; };
  }, []);

  const filteredNews = newsList.filter(item =>
    item.title.toLowerCase().includes(searchTerm.toLowerCase()) || 
    item.category.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="section">
      <div className="container">
        
        <div className="section-title-wrap">
          <span className="section-badge">ข่าวสารและกิจกรรม</span>
          <h1 className="section-title">ข่าวสารประชาสัมพันธ์</h1>
          <p className="section-subtitle">ติดตามข่าวสาร กิจกรรม และความเคลื่อนไหวล่าสุดของสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด</p>
          <div className="section-line" />
        </div>

        {/* Search Bar */}
        <div style={{ maxWidth: '500px', margin: '0 auto 2.5rem auto', position: 'relative' }}>
          <Search size={18} style={{ position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
          <input 
            type="text"
            className="form-control"
            placeholder="ค้นหาหัวข้อข่าว หรือกิจกรรม..."
            style={{ paddingLeft: '2.75rem' }}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>

        {/* News Grid */}
        <div className="grid-3">
          {filteredNews.map((item) => (
            <div key={item.id} className="surface-card" style={{ overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
              <div style={{ height: '180px', background: 'var(--primary-100)', position: 'relative' }}>
                <SafeImage
                  src={item.image} 
                  alt={item.title} 
                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                />
                <span style={{ position: 'absolute', top: '12px', left: '12px', background: 'rgba(15, 23, 42, 0.8)', color: '#fff', padding: '0.25rem 0.65rem', borderRadius: '4px', fontSize: '0.75rem', fontWeight: 600 }}>
                  {item.category}
                </span>
              </div>

              <div style={{ padding: '1.5rem', flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
                <div>
                  <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.5rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <span style={{ display: 'flex', alignItems: 'center', gap: '0.3rem' }}><Clock size={13} /> {item.date}</span>
                    <span style={{ display: 'flex', alignItems: 'center', gap: '0.3rem' }}><Eye size={13} /> {item.views} ครั้ง</span>
                  </div>
                  <h3 style={{ fontSize: '1.1rem', fontWeight: 700, lineHeight: 1.4, marginBottom: '0.65rem', color: 'var(--text-main)' }}>
                    {item.title}
                  </h3>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', lineHeight: 1.6, marginBottom: '1.25rem' }}>
                    {item.excerpt}
                  </p>
                </div>

                <button 
                  onClick={() => setSelectedNews(item)}
                  className="btn btn-outline btn-sm"
                  style={{ width: '100%' }}
                >
                  <span>อ่านต่อฉบับเต็ม</span>
                  <ChevronRight size={14} />
                </button>
              </div>
            </div>
          ))}
        </div>

        {!loading && filteredNews.length === 0 && (
          <p style={{ textAlign: 'center', color: 'var(--text-muted)', marginTop: '2rem' }}>
            ยังไม่พบข่าวสารที่ตรงกับเงื่อนไข
          </p>
        )}

        {/* News Detail Modal */}
        {selectedNews && (
          <div style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: 2000,
            background: 'rgba(15, 23, 42, 0.75)',
            backdropFilter: 'blur(8px)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '1rem'
          }}>
            <div className="glass-card animate-fade-in" style={{ width: '100%', maxWidth: '680px', maxHeight: '90vh', overflowY: 'auto', background: 'var(--bg-surface)', borderRadius: 'var(--radius-xl)', padding: '2rem', position: 'relative' }}>
              
              <button 
                onClick={() => setSelectedNews(null)} 
                style={{ position: 'absolute', top: '1rem', right: '1rem', padding: '0.4rem', borderRadius: '50%', background: 'var(--bg-subtle)', color: 'var(--text-main)' }}
              >
                <X size={20} />
              </button>

              <span className="badge badge-primary" style={{ marginBottom: '0.75rem' }}>{selectedNews.category}</span>
              <h2 style={{ fontSize: '1.4rem', color: 'var(--primary-900)', marginBottom: '0.75rem', lineHeight: 1.35 }}>
                {selectedNews.title}
              </h2>
              
              <div style={{ display: 'flex', gap: '1rem', fontSize: '0.82rem', color: 'var(--text-muted)', marginBottom: '1.5rem', borderBottom: '1px solid var(--border-subtle)', paddingBottom: '0.75rem' }}>
                <span>วันที่เผยแพร่: {selectedNews.date}</span>
                <span>ยอดผู้เข้าชม: {selectedNews.views} ครั้ง</span>
              </div>

              <div style={{ lineHeight: 1.8, color: 'var(--text-main)', fontSize: '0.95rem' }}>
                <p style={{ marginBottom: '1rem' }}>{selectedNews.excerpt}</p>
                <p style={{ marginBottom: '1rem' }}>
                  สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด มุ่งมั่นที่จะมอบบริการที่ดีที่สุด และสร้างความมั่นคงให้แก่สมาชิกทุกท่านอย่างยั่งยืน หากสมาชิกมีข้อสงสัยหรือต้องการสอบถามข้อมูลเพิ่มเติม สามารถติดต่อได้ที่สำนักงานสหกรณ์ หรือโทรศัพท์ 038-611-199 ในวันและเวลาทำการ
                </p>
              </div>

              <div style={{ marginTop: '2rem', display: 'flex', justifyContent: 'flex-end' }}>
                <button onClick={() => setSelectedNews(null)} className="btn btn-primary">
                  <span>ปิดหน้าต่าง</span>
                </button>
              </div>

            </div>
          </div>
        )}

      </div>
    </div>
  );
}
