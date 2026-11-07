import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Download, FileText, Search, Filter, CheckCircle2, FileSpreadsheet, ExternalLink } from 'lucide-react';
import { useToast } from '../context/ToastContext';

// Comprehensive 11 categories mapped to legacy documents and modern cooperative needs
export const CATEGORY_MAP = [
  { slug: 'all', label: 'ทั้งหมด' },
  { slug: 'loans', label: 'เงินกู้ของสหกรณ์' },
  { slug: 'deposits', label: 'เงินฝาก' },
  { slug: 'membership', label: 'สมาชิกและหุ้น' },
  { slug: 'welfare', label: 'สวัสดิการสมาชิก' },
  { slug: 'associations', label: 'สมาคมฌาปนกิจ (สสธท./กสธท./สส.ชสอ.)' },
  { slug: 'pdpa', label: 'การคุ้มครองข้อมูลส่วนบุคคล (PDPA)' },
  { slug: 'regulations', label: 'ระเบียบและข้อบังคับ' },
  { slug: 'annual_reports', label: 'รายงานประจำปี / ผลการดำเนินงาน' },
  { slug: 'strategic', label: 'แผนกลยุทธ์สหกรณ์' },
  { slug: 'forms_complaints', label: 'แบบฟอร์มคำขอทั่วไปและเรื่องร้องเรียน' },
  { slug: 'announcements_misc', label: 'ประกาศและเอกสารอื่นๆ' }
];

export const OFFICIAL_DOCUMENTS = [
  // 1. Loans
  { id: 'loan-1', title: 'คำขอกู้เงินสามัญและหนังสือยินยอมให้หักเงินเดือน', category: 'เงินกู้ของสหกรณ์', slug: 'loans', format: 'PDF', size: '540 KB' },
  { id: 'loan-2', title: 'คำขอกู้เงินเพื่อเหตุฉุกเฉิน (ฉบับปรับปรุง)', category: 'เงินกู้ของสหกรณ์', slug: 'loans', format: 'PDF', size: '320 KB' },
  { id: 'loan-3', title: 'หนังสือสัญญาค้ำประกันเงินกู้สามัญ', category: 'เงินกู้ของสหกรณ์', slug: 'loans', format: 'PDF', size: '410 KB' },
  { id: 'loan-4', title: 'คำขอกู้เงินพิเศษเพื่อที่อยู่อาศัย / ความมั่นคงในชีวิต', category: 'เงินกู้ของสหกรณ์', slug: 'loans', format: 'PDF', size: '680 KB' },
  { id: 'loan-5', title: 'ตารางกำหนดการยื่นกู้และจ่ายเงินกู้ ประจำปี 2569 (pay2569.png)', category: 'เงินกู้ของสหกรณ์', slug: 'loans', format: 'PNG', size: '667 KB', fileUrl: '/assets/img/pay2569.png' },

  // 2. Deposits
  { id: 'dep-1', title: 'คำขอเปิดบัญชีเงินฝากออมทรัพย์ / ออมทรัพย์พิเศษ / ประจำ', category: 'เงินฝาก', slug: 'deposits', format: 'PDF', size: '280 KB' },
  { id: 'dep-2', title: 'ใบคำขอถอนเงินฝากและหนังสือมอบอำนาจทำธุรกรรมเงินฝาก', category: 'เงินฝาก', slug: 'deposits', format: 'PDF', size: '210 KB' },

  // 3. Membership & Shares
  { id: 'mem-1', title: 'ใบสมัครเข้าเป็นสมาชิกสามัญ สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด', category: 'สมาชิกและหุ้น', slug: 'membership', format: 'PDF', size: '390 KB' },
  { id: 'mem-2', title: 'ใบสมัครเข้าเป็นสมาชิกสมทบ', category: 'สมาชิกและหุ้น', slug: 'membership', format: 'PDF', size: '360 KB' },
  { id: 'mem-3', title: 'แบบขอเปลี่ยนแปลงการส่งเงินค่าหุ้นรายเดือน (เพิ่ม / ลดหุ้น)', category: 'สมาชิกและหุ้น', slug: 'membership', format: 'PDF', size: '220 KB' },
  { id: 'mem-4', title: 'หนังสือตั้งผู้รับโอนประโยชน์แห่งค่าหุ้นและเงินฝาก', category: 'สมาชิกและหุ้น', slug: 'membership', format: 'PDF', size: '270 KB' },

  // 4. Welfare
  { id: 'wel-1', title: 'แบบคำขอรับเงินสวัสดิการสมาชิก (มงคลสมรส / คลอดบุตร / บำนาญ)', category: 'สวัสดิการสมาชิก', slug: 'welfare', format: 'PDF', size: '410 KB' },
  { id: 'wel-2', title: 'แบบขอรับเงินสงเคราะห์สมาชิกโสด และสวัสดิการสงเคราะห์ครอบครัว', category: 'สวัสดิการสมาชิก', slug: 'welfare', format: 'PDF', size: '330 KB' },

  // 5. Mutual Aid Associations
  { id: 'asc-1', title: 'ใบสมัครสมาชิกสมาคมฌาปนกิจสงเคราะห์ สสธท.', category: 'สมาคมฌาปนกิจ (สสธท./กสธท./สส.ชสอ.)', slug: 'associations', format: 'PDF', size: '510 KB' },
  { id: 'asc-2', title: 'แบบฟอร์มคำขอกองทุนสวัสดิการสมาชิก กสธท.', category: 'สมาคมฌาปนกิจ (สสธท./กสธท./สส.ชสอ.)', slug: 'associations', format: 'PDF', size: '480 KB' },
  { id: 'asc-3', title: 'ใบสมัครและแบบคำขอ สส.ชสอ.', category: 'สมาคมฌาปนกิจ (สสธท./กสธท./สส.ชสอ.)', slug: 'associations', format: 'PDF', size: '450 KB' },

  // 6. PDPA
  { id: 'pdpa-1', title: 'นโยบายการคุ้มครองข้อมูลส่วนบุคคล (Privacy Policy) สหกรณ์ฯ ระยอง', category: 'การคุ้มครองข้อมูลส่วนบุคคล (PDPA)', slug: 'pdpa', format: 'PDF', size: '590 KB' },
  { id: 'pdpa-2', title: 'แบบคำขอใช้สิทธิของเจ้าของข้อมูลส่วนบุคคล (Data Subject Access Request - DSAR)', category: 'การคุ้มครองข้อมูลส่วนบุคคล (PDPA)', slug: 'pdpa', format: 'PDF', size: '310 KB' },

  // 7. Regulations & Bylaws
  { id: 'reg-1', title: 'ข้อบังคับสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด ฉบับสมบูรณ์ (แก้ไขเพิ่มเติม)', category: 'ระเบียบและข้อบังคับ', slug: 'regulations', format: 'PDF', size: '2.8 MB' },
  { id: 'reg-2', title: 'ระเบียบว่าด้วยการให้เงินกู้แก่สมาชิกและดอกเบี้ยเงินกู้ พ.ศ. 2567', category: 'ระเบียบและข้อบังคับ', slug: 'regulations', format: 'PDF', size: '890 KB' },

  // 8. Annual Reports
  { id: 'rep-2568', title: 'รายงานประจำปี 2568 สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด', category: 'รายงานประจำปี / ผลการดำเนินงาน', slug: 'annual_reports', format: 'PDF', size: '15.2 MB' },
  { id: 'rep-2567', title: 'รายงานประจำปี 2567 สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด', category: 'รายงานประจำปี / ผลการดำเนินงาน', slug: 'annual_reports', format: 'PDF', size: '14.8 MB' },
  { id: 'rep-2566', title: 'รายงานประจำปี 2566 สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด', category: 'รายงานประจำปี / ผลการดำเนินงาน', slug: 'annual_reports', format: 'PDF', size: '14.5 MB' },

  // 9. Strategic Plans
  { id: 'strat-1', title: 'แผนกลยุทธ์สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด พ.ศ. 2569 - 2573', category: 'แผนกลยุทธ์สหกรณ์', slug: 'strategic', format: 'PDF', size: '4.2 MB' },
  { id: 'strat-2', title: 'แผนกลยุทธ์สหกรณ์ พ.ศ. 2566 - 2570', category: 'แผนกลยุทธ์สหกรณ์', slug: 'strategic', format: 'PDF', size: '3.8 MB' },

  // 10. General Requests & Complaints
  { id: 'comp-1', title: 'แบบคำขอทั่วไปและแบบแสดงความคิดเห็น / ข้อเสนอแนะ', category: 'แบบฟอร์มคำขอทั่วไปและเรื่องร้องเรียน', slug: 'forms_complaints', format: 'PDF', size: '240 KB' },
  { id: 'comp-2', title: 'ระเบียบว่าด้วยการรับเรื่องร้องเรียนและข้อเสนอแนะของสมาชิก', category: 'แบบฟอร์มคำขอทั่วไปและเรื่องร้องเรียน', slug: 'forms_complaints', format: 'PDF', size: '420 KB' },

  // 11. Announcements & Misc
  { id: 'ann-1', title: 'ประกาศผลการสรรหากรรมการดำเนินการและผู้ตรวจสอบกิจการ ประจำปี 2569', category: 'ประกาศและเอกสารอื่นๆ', slug: 'announcements_misc', format: 'PDF', size: '510 KB' }
];

export default function DocumentsPage() {
  const { toast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const [searchTerm, setSearchTerm] = useState('');
  const [docsList, setDocsList] = useState([]);
  const [loading, setLoading] = useState(true);

  const queryCat = searchParams.get('category');
  const initialCategory = CATEGORY_MAP.find(c => c.slug === queryCat)?.label || 'ทั้งหมด';
  const [selectedCategory, setSelectedCategory] = useState(initialCategory);

  useEffect(() => {
    let cancelled = false;
    fetch('/api/public/documents')
      .then((res) => res.json())
      .then((data) => {
        if (!cancelled && data?.success && Array.isArray(data.data) && data.data.length > 0) {
          setDocsList(data.data);
        }
      })
      .catch(() => {})
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    if (queryCat) {
      const match = CATEGORY_MAP.find(c => c.slug === queryCat);
      if (match) setSelectedCategory(match.label);
    }
  }, [queryCat]);

  const handleCategorySelect = (label, slug) => {
    setSelectedCategory(label);
    if (slug === 'all') {
      searchParams.delete('category');
      setSearchParams(searchParams);
    } else {
      setSearchParams({ category: slug });
    }
  };

  const activeDocuments = docsList.length > 0 ? docsList : OFFICIAL_DOCUMENTS;

  const filteredDocs = activeDocuments.filter((doc) => {
    const matchCategory = selectedCategory === 'ทั้งหมด' || doc.category === selectedCategory;
    const matchSearch = doc.title.toLowerCase().includes(searchTerm.toLowerCase()) || (doc.category || '').toLowerCase().includes(searchTerm.toLowerCase());
    return matchCategory && matchSearch;
  });

  const handleDownload = (doc) => {
    if (doc.id && typeof doc.id === 'number') {
      fetch(`/api/public/documents/${doc.id}/download`).catch(() => {});
      setDocsList((prev) => prev.map((d) => d.id === doc.id ? { ...d, download_count: (Number(d.download_count) || 0) + 1 } : d));
    }
    toast(`กำลังดาวน์โหลด: ${doc.title}`, 'info');
  };

  return (
    <div style={{ background: 'var(--background-main, #F4F8FD)', minHeight: '80vh', padding: '3rem 0 4rem 0' }}>
      <div className="container">

        {/* Header */}
        <div className="section-title-wrap" style={{ textAlign: 'center', marginBottom: '2.5rem' }}>
          <span className="section-badge" style={{ background: '#EDF5FF', color: '#1769C2' }}>ศูนย์ดาวน์โหลดสหกรณ์</span>
          <h1 style={{ fontSize: 'clamp(1.8rem, 3.5vw, 2.5rem)', fontWeight: 800, color: '#17315A', margin: '0.5rem 0' }}>
            เอกสารและแบบฟอร์มดาวน์โหลด
          </h1>
          <p style={{ color: '#526783', fontSize: '1.05rem', maxWidth: '640px', margin: '0 auto' }}>
            ศูนย์รวมแบบฟอร์มคำขอสินเชื่อ เงินฝาก สวัสดิการ ระเบียบข้อบังคับ และรายงานผลการดำเนินงาน สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
          </p>
        </div>

        {/* Filter and Search Bar */}
        <div className="surface-card" style={{
          background: '#FFFFFF',
          border: '1px solid #DCE5F0',
          borderRadius: '0.875rem',
          padding: '1.5rem',
          marginBottom: '2rem',
          boxShadow: '0 4px 18px rgba(16, 47, 104, 0.055)'
        }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>

            {/* Search Input */}
            <div style={{ position: 'relative' }}>
              <Search size={18} style={{ position: 'absolute', left: '16px', top: '50%', transform: 'translateY(-50%)', color: '#718096' }} />
              <input
                type="text"
                className="form-control"
                placeholder="ค้นหาชื่อแบบฟอร์ม คำขอกู้ หรือประเภทเอกสาร..."
                style={{
                  paddingLeft: '3rem',
                  height: '46px',
                  borderRadius: '0.5rem',
                  border: '1px solid #DCE5F0',
                  fontSize: '0.95rem',
                  width: '100%'
                }}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>

            {/* Category Pills (All 11 Standardized Categories) */}
            <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
              {CATEGORY_MAP.map((cat) => {
                const isActive = selectedCategory === cat.label;
                return (
                  <button
                    key={cat.slug}
                    type="button"
                    onClick={() => handleCategorySelect(cat.label, cat.slug)}
                    style={{
                      padding: '0.45rem 0.95rem',
                      borderRadius: '9999px',
                      fontSize: '0.85rem',
                      fontWeight: 600,
                      background: isActive ? '#1769C2' : '#EDF5FF',
                      color: isActive ? '#FFFFFF' : '#17315A',
                      border: isActive ? '1px solid #1769C2' : '1px solid #DCE5F0',
                      cursor: 'pointer',
                      transition: 'all 0.2s ease'
                    }}
                  >
                    {cat.label}
                  </button>
                );
              })}
            </div>
          </div>
        </div>

        {/* Documents Results List */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
          {filteredDocs.length > 0 ? (
            filteredDocs.map((doc) => (
              <div
                key={doc.id}
                className="surface-card"
                style={{
                  background: '#FFFFFF',
                  border: '1px solid #DCE5F0',
                  borderRadius: '0.75rem',
                  padding: '1.25rem 1.5rem',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  boxShadow: '0 4px 18px rgba(16, 47, 104, 0.055)',
                  flexWrap: 'wrap',
                  gap: '1rem'
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flex: '1 1 320px' }}>
                  <div style={{
                    width: '44px',
                    height: '44px',
                    borderRadius: '0.5rem',
                    background: '#EDF5FF',
                    color: '#1769C2',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0
                  }}>
                    <FileText size={22} />
                  </div>
                  <div>
                    <h3 style={{ fontSize: '1rem', fontWeight: 700, color: '#17315A', margin: '0 0 0.25rem 0' }}>
                      {doc.title}
                    </h3>
                    <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center', flexWrap: 'wrap' }}>
                      <span style={{ fontSize: '0.78rem', color: '#1769C2', fontWeight: 600 }}>{doc.category}</span>
                      <span style={{ fontSize: '0.78rem', color: '#718096' }}>
                        • {(doc.file_type?.includes('pdf') || doc.file_path?.endsWith('.pdf')) ? 'PDF' : (doc.file_path?.endsWith('.png') ? 'PNG' : doc.format || 'PDF')}
                        {doc.file_size ? ` (${(Number(doc.file_size) / 1024).toFixed(0)} KB)` : doc.size ? ` (${doc.size})` : ''}
                      </span>
                      {(doc.download_count !== undefined || doc.downloads) && (
                        <span style={{ fontSize: '0.78rem', color: '#718096' }}>
                          • ดาวน์โหลดแล้ว {Number(doc.download_count ?? doc.downloads).toLocaleString()} ครั้ง
                        </span>
                      )}
                    </div>
                  </div>
                </div>

                <div>
                  <a
                    href={doc.file_url || doc.fileUrl || (doc.file_path ? (doc.file_path.startsWith('/') ? doc.file_path : '/' + doc.file_path) : `/assets/docs/${doc.id}.pdf`)}
                    download={doc.title}
                    onClick={() => handleDownload(doc)}
                    className="btn btn-sm"
                    style={{
                      background: '#1769C2',
                      color: '#FFFFFF',
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '0.5rem',
                      padding: '0.5rem 1.15rem',
                      borderRadius: '0.5rem',
                      fontWeight: 600,
                      textDecoration: 'none'
                    }}
                  >
                    <Download size={16} />
                    <span>ดาวน์โหลด</span>
                  </a>
                </div>
              </div>
            ))
          ) : (
            <div className="surface-card" style={{ padding: '3rem', textAlign: 'center', color: '#718096', background: '#FFFFFF', borderRadius: '0.75rem', border: '1px solid #DCE5F0' }}>
              <p style={{ fontSize: '1.05rem', margin: 0 }}>ไม่พบเอกสารตามเงื่อนไขที่ค้นหา</p>
            </div>
          )}
        </div>

      </div>
    </div>
  );
}
