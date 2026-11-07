import React from 'react';
import { Link } from 'react-router-dom';
import {
  Sparkles,
  Layers,
  Newspaper,
  Megaphone,
  HelpCircle,
  FileText,
  Building2,
  Users,
  Percent,
  FolderKanban,
  Settings,
  ArrowRight,
  ShieldCheck,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { PageHeader } from '../components/admin/design-system';

const CMS_MODULES = [
  {
    permission: 'news.view',
    path: '/admin/popups',
    title: 'แคมเปญป๊อปอัป (Popup Manager V2)',
    desc: 'จัดการหน้าต่างแจ้งเตือนและแคมเปญหน้าแรก รองรับแบนเนอร์และมาสคอต (สูงสุด 3 รายการ)',
    icon: Sparkles,
    tag: 'V2 Stable',
    tagColor: '#0284c7',
  },
  {
    permission: 'news.view',
    path: '/admin/hero-slides',
    title: 'ภาพสไลด์แบนเนอร์ (Hero Slides)',
    desc: 'จัดการภาพแบนเนอร์ประชาสัมพันธ์ส่วนบนของหน้าแรก พร้อมปุ่ม Call to Action',
    icon: Layers,
    tag: 'Hero Banner',
    tagColor: '#0284c7',
  },
  {
    permission: 'news.view',
    path: '/admin/content?tab=news',
    title: 'ข่าวสารและกิจกรรม',
    desc: 'ประชาสัมพันธ์ข่าวสาร อัลบั้มภาพกิจกรรม และจดหมายข่าวสมาชิกสหกรณ์',
    icon: Newspaper,
    tag: 'News & PR',
    tagColor: '#059669',
  },
  {
    permission: 'announcements.view',
    path: '/admin/workspace',
    title: 'ประกาศสำคัญสำหรับสมาชิก',
    desc: 'เผยแพร่ประกาศเร่งด่วน การประชุมใหญ่สามัญ และข้อบังคับสหกรณ์',
    icon: Megaphone,
    tag: 'Announcements',
    tagColor: '#d97706',
  },
  {
    permission: 'faqs.view',
    path: '/admin/content?tab=faqs',
    title: 'คำถามที่พบบ่อย (FAQ)',
    desc: 'จัดการชุดคำถาม-คำตอบเพื่ออำนวยความสะดวกแก่สมาชิกและผู้รับบริการ',
    icon: HelpCircle,
    tag: 'Knowledge',
    tagColor: '#6366f1',
  },
  {
    permission: 'documents.view',
    path: '/admin/documents',
    title: 'ศูนย์เอกสารและแบบฟอร์ม',
    desc: 'จัดการไฟล์เอกสารคำขอกู้ คำขอสมาชิก และรายงานประจำปีสำหรับดาวน์โหลด',
    icon: FileText,
    tag: 'Downloads',
    tagColor: '#0284c7',
  },
  {
    permission: 'coop_info.view',
    path: '/admin/public-content',
    title: 'ข้อมูลสหกรณ์และวิสัยทัศน์',
    desc: 'ปรับปรุงประวัติความเป็นมา ค่านิยม นโยบายคุณภาพ และข้อมูลติดต่อสหกรณ์',
    icon: Building2,
    tag: 'Coop Info',
    tagColor: '#0f2942',
  },
  {
    permission: 'people.view',
    path: '/admin/board-staff',
    title: 'คณะกรรมการและเจ้าหน้าที่',
    desc: 'จัดการทำเนียบคณะกรรมการดำเนินการ ผู้ตรวจสอบกิจการ และเจ้าหน้าที่สหกรณ์',
    icon: Users,
    tag: 'Personnel',
    tagColor: '#0f2942',
  },
  {
    permission: 'rates.view',
    path: '/admin/public-content',
    title: 'อัตราดอกเบี้ยเงินฝากและเงินกู้',
    desc: 'ปรับปรุงตารางอัตราดอกเบี้ยและเงื่อนไขการให้บริการทางการเงิน',
    icon: Percent,
    tag: 'Financial Rates',
    tagColor: '#059669',
  },
  {
    permission: 'products.view',
    path: '/admin/products',
    title: 'ผลิตภัณฑ์เงินกู้และเงินฝาก',
    desc: 'กำหนดรายละเอียดผลิตภัณฑ์ วงเงินกู้ อัตราดอกเบี้ย และหลักเกณฑ์การกู้ยืม',
    icon: FolderKanban,
    tag: 'Products',
    tagColor: '#0284c7',
  },
  {
    permission: 'settings.view',
    path: '/admin/settings/content',
    title: 'ข้อมูลเว็บไซต์ที่อนุญาตให้แก้ไข',
    desc: 'จัดการข้อมูลการติดต่อ หมายเลขโทรศัพท์ และการตั้งค่าพื้นฐานของเว็บไซต์',
    icon: Settings,
    tag: 'Site Settings',
    tagColor: '#64748b',
  },
];

/**
 * Legacy prototype stub retained strictly for backwards compatibility.
 * @deprecated All production administrative features use live database-backed APIs.
 */
export function LegacyAdminDashboardPage() {
  return (
    <div style={{ padding: '2rem', textAlign: 'center' }}>
      <p>ระบบเดิมแบบเบราว์เซอร์ Prototype ถูกยกเลิกและทดแทนด้วยระบบ API กลางเรียบร้อยแล้ว</p>
      <Link to="/admin/dashboard" className="btn btn-primary">ไปยัง Admin Control Center</Link>
    </div>
  );
}

/**
 * Design System 3.1 Content & CMS Hub
 */
export default function AdminDashboardPage() {
  const { user, can } = useAuth();

  const permittedModules = CMS_MODULES.filter(
    (item) => !item.permission || can(item.permission)
  );

  return (
    <section className="admin-module" style={{ maxWidth: '1400px', margin: '0 auto', paddingBottom: '3rem' }}>
      <PageHeader
        title="ระบบบริหารจัดการเนื้อหาส่วนกลาง (CMS Hub)"
        subtitle="ศูนย์รวมเครื่องมือปรับปรุงข้อมูลเว็บไซต์ เชื่อมต่อฐานข้อมูลและ API กลางอย่างสมบูรณ์"
        breadcrumbs={[
          { label: 'Admin Control Center', path: '/admin/dashboard' },
          { label: 'จัดการเนื้อหาเว็บไซต์' },
        ]}
      />

      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: '12px',
          background: '#f0fdf4',
          border: '1px solid #bbf7d0',
          borderRadius: '10px',
          padding: '12px 18px',
          margin: '1.25rem 0 2rem 0',
          color: '#166534',
          fontSize: '0.9rem',
        }}
      >
        <ShieldCheck size={20} color="#16a34a" style={{ flexShrink: 0 }} />
        <span>
          <strong>ระบบเชื่อมต่อฐานข้อมูลจริง:</strong> ข้อมูลทุกโมดูลถูกบันทึกลงในระบบฐานข้อมูลกลาง มีการตรวจสอบสิทธิ์ RBAC, ตรวจสอบ CSRF Token และบันทึก Audit Trail ทุกการเปลี่ยนแปลง
        </span>
      </div>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
          gap: '1.25rem',
        }}
      >
        {permittedModules.map((item) => {
          const Icon = item.icon;
          return (
            <Link
              key={item.path + item.title}
              to={item.path}
              style={{
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'space-between',
                padding: '1.5rem',
                backgroundColor: '#ffffff',
                border: '1px solid #e2e8f0',
                borderRadius: '12px',
                textDecoration: 'none',
                color: '#0f172a',
                boxShadow: '0 1px 3px 0 rgba(15, 23, 42, 0.04)',
                transition: 'all 0.2s ease',
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.borderColor = '#0284c7';
                e.currentTarget.style.boxShadow = '0 6px 12px -2px rgba(2, 132, 199, 0.12)';
                e.currentTarget.style.transform = 'translateY(-2px)';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.borderColor = '#e2e8f0';
                e.currentTarget.style.boxShadow = '0 1px 3px 0 rgba(15, 23, 42, 0.04)';
                e.currentTarget.style.transform = 'translateY(0)';
              }}
            >
              <div>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '1rem' }}>
                  <div
                    style={{
                      width: '42px',
                      height: '42px',
                      borderRadius: '10px',
                      backgroundColor: '#f0f9ff',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      color: '#0284c7',
                    }}
                  >
                    <Icon size={22} />
                  </div>
                  <span
                    style={{
                      fontSize: '0.75rem',
                      fontWeight: 600,
                      padding: '3px 10px',
                      borderRadius: '9999px',
                      backgroundColor: '#f1f5f9',
                      color: item.tagColor || '#475569',
                      border: `1px solid ${item.tagColor ? `${item.tagColor}33` : '#cbd5e1'}`,
                    }}
                  >
                    {item.tag}
                  </span>
                </div>
                <h3 style={{ fontSize: '1.05rem', fontWeight: 600, margin: '0 0 0.5rem 0', color: '#0f2942' }}>
                  {item.title}
                </h3>
                <p style={{ fontSize: '0.875rem', color: '#64748b', lineHeight: 1.5, margin: 0 }}>
                  {item.desc}
                </p>
              </div>

              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '6px',
                  marginTop: '1.25rem',
                  fontSize: '0.85rem',
                  fontWeight: 600,
                  color: '#0284c7',
                }}
              >
                <span>เข้าสู่โมดูล</span>
                <ArrowRight size={15} />
              </div>
            </Link>
          );
        })}
      </div>

      <div style={{ marginTop: '2.5rem', textAlign: 'center' }}>
        <Link to="/admin/dashboard" className="btn btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
          <span>กลับสู่ภาพรวมแดชบอร์ดหลัก (Executive Dashboard)</span>
        </Link>
      </div>
    </section>
  );
}
