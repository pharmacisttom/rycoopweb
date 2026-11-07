import React, { useEffect, useState } from 'react';
import { Plus, Eye, Edit2, Trash2, CheckCircle, XCircle, Image as ImageIcon, ExternalLink } from 'lucide-react';
import { memberAdminApi } from '../services/memberAdminApi';
import alertService from '../services/alertService';
import {
  PageHeader,
  CrudDataTable,
  CrudFormDrawer,
  CrudDetailModal,
  StatusBadge,
  PermissionGuard,
} from '../components/admin/design-system';

export default function AdminHeroSlidesPage() {
  const [slides, setSlides] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);

  // Form Drawer state
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [formData, setFormData] = useState({
    id: null,
    title: '',
    subtitle: '',
    description: '',
    desktop_image: '',
    button_text: '',
    button_url: '',
    button_target: '_self',
    text_alignment: 'left',
    overlay_opacity: 0.8,
    priority: 10,
    status: 'active',
  });
  const [formErrors, setFormErrors] = useState({});
  const [formDirty, setFormDirty] = useState(false);
  const [formBusy, setFormBusy] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);

  // Detail Modal state
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedSlide, setSelectedSlide] = useState(null);

  // Load slides from real API
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');

    memberAdminApi('/api/admin/hero-slides', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setSlides(res.data || []);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          setError(err.message || 'ไม่สามารถโหลดข้อมูล Hero Slides ได้');
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      });

    return () => controller.abort();
  }, [refreshKey]);

  // Open Create Drawer
  const handleOpenCreate = () => {
    setFormData({
      id: null,
      title: '',
      subtitle: '',
      description: '',
      desktop_image: 'hero_bg_default.jpg',
      button_text: '',
      button_url: '',
      button_target: '_self',
      text_alignment: 'left',
      overlay_opacity: 0.8,
      priority: 10,
      status: 'active',
    });
    setSelectedFile(null);
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(false);
    setDrawerOpen(true);
  };

  // Open Edit Drawer
  const handleOpenEdit = (slide) => {
    setFormData({
      id: slide.id,
      title: slide.title || '',
      subtitle: slide.subtitle || '',
      description: slide.description || '',
      desktop_image: slide.desktop_image || '',
      button_text: slide.button_text || '',
      button_url: slide.button_url || '',
      button_target: slide.button_target || '_self',
      text_alignment: slide.text_alignment || 'left',
      overlay_opacity: Number(slide.overlay_opacity || 0.8),
      priority: Number(slide.priority || 10),
      status: slide.status || 'active',
    });
    setSelectedFile(null);
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(true);
    setDrawerOpen(true);
  };

  // Open Detail Modal
  const handleOpenDetail = (slide) => {
    setSelectedSlide(slide);
    setDetailModalOpen(true);
  };

  // Form field change
  const handleChange = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setFormDirty(true);
    if (formErrors[field]) {
      setFormErrors((prev) => ({ ...prev, [field]: null }));
    }
  };

  // Submit Save
  const handleFormSubmit = async (e) => {
    e.preventDefault();

    // Client-side validation
    const errors = {};
    if (!formData.title.trim()) errors.title = 'กรุณากรอกหัวข้อสไลด์';
    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      alertService.validationError(errors);
      return;
    }

    setFormBusy(true);
    alertService.showLoading('กำลังบันทึกข้อมูลสไลด์...');

    try {
      const url = isEdit
        ? `/api/admin/hero-slides/${formData.id}/update`
        : '/api/admin/hero-slides';

      let payload;
      if (selectedFile) {
        payload = new FormData();
        Object.entries(formData).forEach(([k, v]) => {
          if (v !== null && v !== undefined) payload.append(k, String(v));
        });
        payload.append('file', selectedFile);
      } else {
        payload = formData;
      }

      await memberAdminApi(url, { data: payload });
      alertService.closeLoading();
      alertService.success(isEdit ? 'แก้ไขสไลด์เรียบร้อยแล้ว' : 'เพิ่มสไลด์ใหม่สำเร็จ');
      setDrawerOpen(false);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error('บันทึกสไลด์ไม่สำเร็จ', err.message);
    } finally {
      setFormBusy(false);
    }
  };

  // Delete slide
  const handleDelete = async (slide) => {
    const confirmed = await alertService.confirmDelete(`สไลด์ "${slide.title}"`);
    if (!confirmed) return;

    alertService.showLoading('กำลังลบสไลด์...');
    try {
      await memberAdminApi(`/api/admin/hero-slides/${slide.id}/delete`, { data: {} });
      alertService.closeLoading();
      alertService.success('ลบสไลด์เรียบร้อยแล้ว');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error('ลบสไลด์ไม่สำเร็จ', err.message);
    }
  };

  // Toggle status
  const handleToggleStatus = async (slide) => {
    const newStatus = slide.status === 'active' ? 'inactive' : 'active';
    const confirmed = await alertService.confirmStatusToggle(slide.title, newStatus);
    if (!confirmed) return;

    alertService.showLoading('กำลังปรับปรุงสถานะ...');
    try {
      await memberAdminApi(`/api/admin/hero-slides/${slide.id}/update`, {
        data: { ...slide, status: newStatus },
      });
      alertService.closeLoading();
      alertService.toast({ icon: 'success', title: `ปรับสถานะเป็น ${newStatus === 'active' ? 'ใช้งาน' : 'ไม่ใช้งาน'} แล้ว` });
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error('ปรับปรุงสถานะไม่สำเร็จ', err.message);
    }
  };

  const columns = [
    {
      key: 'desktop_image',
      label: 'ภาพสไลด์',
      width: '90px',
      sortable: false,
      render: (row) => (
        <div
          style={{
            width: '64px',
            height: '40px',
            borderRadius: '6px',
            backgroundColor: '#e2e8f0',
            overflow: 'hidden',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
        >
          {row.desktop_image ? (
            <img
              src={row.desktop_image}
              alt=""
              style={{ width: '100%', height: '100%', objectFit: 'cover' }}
              onError={(e) => {
                e.target.style.display = 'none';
              }}
            />
          ) : (
            <ImageIcon size={20} color="#94a3b8" />
          )}
        </div>
      ),
    },
    {
      key: 'title',
      label: 'หัวข้อสไลด์',
      render: (row) => (
        <div>
          <strong style={{ color: 'var(--acc-text, #0f172a)' }}>{row.title}</strong>
          {row.subtitle && (
            <small style={{ display: 'block', color: 'var(--acc-text-secondary, #64748b)' }}>
              {row.subtitle}
            </small>
          )}
        </div>
      ),
    },
    {
      key: 'priority',
      label: 'ลำดับความสำคัญ',
      numeric: true,
      width: '120px',
    },
    {
      key: 'status',
      label: 'สถานะ',
      width: '110px',
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'actions',
      label: 'การจัดการ',
      width: '160px',
      sortable: false,
      render: (row) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
          <button
            type="button"
            className="acc-btn"
            style={{ padding: '6px 8px' }}
            onClick={() => handleOpenDetail(row)}
            title="ดูรายละเอียด"
          >
            <Eye size={15} />
          </button>

          <button
            type="button"
            className="acc-btn"
            style={{ padding: '6px 8px' }}
            onClick={() => handleOpenEdit(row)}
            title="แก้ไข"
          >
            <Edit2 size={15} />
          </button>

          <button
            type="button"
            className={`acc-btn ${row.status === 'active' ? '' : 'primary'}`}
            style={{ padding: '6px 8px' }}
            onClick={() => handleToggleStatus(row)}
            title={row.status === 'active' ? 'ระงับการแสดง' : 'เปิดการแสดง'}
          >
            {row.status === 'active' ? <XCircle size={15} /> : <CheckCircle size={15} />}
          </button>

          <button
            type="button"
            className="acc-btn danger"
            style={{ padding: '6px 8px' }}
            onClick={() => handleDelete(row)}
            title="ลบสไลด์"
          >
            <Trash2 size={15} />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="admin-hero-slides-page">
      <PageHeader
        title="จัดการแบนเนอร์ Hero Slider"
        subtitle="เพิ่ม แก้ไข และจัดลำดับภาพสไลด์โชว์หลักบนหน้าแรกของเว็บไซต์สหกรณ์"
        breadcrumbs={[
          { label: 'จัดการเนื้อหา', to: '/admin/content' },
          { label: 'Hero Slides' },
        ]}
        actions={
          <button
            type="button"
            className="acc-btn primary"
            onClick={handleOpenCreate}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}
          >
            <Plus size={16} />
            เพิ่มสไลด์ใหม่
          </button>
        }
      />

      {/* Main Table Card */}
      <div className="admin-card">
        <CrudDataTable
          columns={columns}
          rows={slides}
          loading={loading}
          error={error}
          searchPlaceholder="ค้นหาหัวข้อ คำโปรย..."
          searchKeys={['title', 'subtitle', 'description']}
          emptyTitle="ยังไม่มีภาพสไลด์"
          emptyDescription="คุณสามารถเพิ่มแบนเนอร์ภาพสไลด์ใหม่เพื่อประชาสัมพันธ์บนหน้าแรกได้"
          emptyActionLabel="เพิ่มสไลด์แรก"
          onEmptyAction={handleOpenCreate}
        />
      </div>

      {/* Form Drawer (Create / Edit) */}
      <CrudFormDrawer
        open={drawerOpen}
        title={isEdit ? 'แก้ไขแบนเนอร์ Hero Slide' : 'เพิ่มแบนเนอร์ Hero Slide ใหม่'}
        subtitle="ข้อมูลจะแสดงผลบนหน้าแรกตามลำดับความสำคัญและสถานะเปิดใช้งาน"
        isEdit={isEdit}
        dirty={formDirty}
        busy={formBusy}
        errors={formErrors}
        onClose={() => setDrawerOpen(false)}
        onSubmit={handleFormSubmit}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <label className="admin-field">
            <span>หัวข้อสไลด์ *</span>
            <input
              type="text"
              required
              maxLength={255}
              value={formData.title}
              onChange={(e) => handleChange('title', e.target.value)}
              placeholder="เช่น อัตราดอกเบี้ยเงินฝากสหกรณ์ปี 2569"
            />
          </label>

          <label className="admin-field">
            <span>คำโปรย (Subtitle)</span>
            <input
              type="text"
              maxLength={255}
              value={formData.subtitle}
              onChange={(e) => handleChange('subtitle', e.target.value)}
              placeholder="คำโปรยสั้นๆ รองรับข้อความเน้น"
            />
          </label>

          <label className="admin-field">
            <span>รายละเอียดเพิ่มเติม</span>
            <textarea
              rows={3}
              value={formData.description}
              onChange={(e) => handleChange('description', e.target.value)}
              placeholder="ข้อความบรรยายสไลด์"
            />
          </label>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
            <label className="admin-field">
              <span>ภาพแบนเนอร์ (ไฟล์ภาพ)</span>
              <input
                type="file"
                accept="image/*"
                onChange={(e) => {
                  if (e.target.files && e.target.files[0]) {
                    setSelectedFile(e.target.files[0]);
                    setFormDirty(true);
                  }
                }}
              />
              <small>รองรับไฟล์ JPG, PNG, WebP ขนาดไม่เกิน 5MB</small>
            </label>

            <label className="admin-field">
              <span>หรือเลือกภาพ Preset / URL</span>
              <input
                type="text"
                value={formData.desktop_image}
                onChange={(e) => handleChange('desktop_image', e.target.value)}
                placeholder="hero_bg_default.jpg หรือ https://..."
              />
            </label>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '16px' }}>
            <label className="admin-field">
              <span>ข้อความบนปุ่มกด (Call to Action)</span>
              <input
                type="text"
                value={formData.button_text}
                onChange={(e) => handleChange('button_text', e.target.value)}
                placeholder="เช่น ดูรายละเอียดเพิ่มเติม"
              />
            </label>

            <label className="admin-field">
              <span>ลิงก์ปลายทาง (Button URL)</span>
              <input
                type="text"
                value={formData.button_url}
                onChange={(e) => handleChange('button_url', e.target.value)}
                placeholder="/rates หรือ https://..."
              />
            </label>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: '16px' }}>
            <label className="admin-field">
              <span>ลำดับความสำคัญ (Priority)</span>
              <input
                type="number"
                min={0}
                max={999}
                value={formData.priority}
                onChange={(e) => handleChange('priority', Number(e.target.value))}
              />
              <small>ตัวเลขมากแสดงก่อน</small>
            </label>

            <label className="admin-field">
              <span>การจัดวางข้อความ</span>
              <select
                value={formData.text_alignment}
                onChange={(e) => handleChange('text_alignment', e.target.value)}
              >
                <option value="left">ชิดซ้าย (Left)</option>
                <option value="center">กึ่งกลาง (Center)</option>
                <option value="right">ชิดขวา (Right)</option>
              </select>
            </label>

            <label className="admin-field">
              <span>สถานะการแสดงผล</span>
              <select
                value={formData.status}
                onChange={(e) => handleChange('status', e.target.value)}
              >
                <option value="active">เปิดใช้งาน (Active)</option>
                <option value="inactive">ปิดใช้งาน (Inactive)</option>
              </select>
            </label>
          </div>
        </div>
      </CrudFormDrawer>

      {/* Detail Modal */}
      <CrudDetailModal
        open={detailModalOpen}
        title={selectedSlide?.title || 'รายละเอียดสไลด์'}
        badge={<StatusBadge status={selectedSlide?.status} />}
        onClose={() => setDetailModalOpen(false)}
        actions={
          <button
            type="button"
            className="acc-btn"
            onClick={() => setDetailModalOpen(false)}
          >
            ปิด
          </button>
        }
      >
        {selectedSlide && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            {selectedSlide.desktop_image && (
              <div
                style={{
                  width: '100%',
                  height: '240px',
                  borderRadius: '12px',
                  overflow: 'hidden',
                  backgroundColor: '#0f172a',
                }}
              >
                <img
                  src={selectedSlide.desktop_image}
                  alt={selectedSlide.title}
                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                />
              </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '16px' }}>
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>หัวข้อ:</span>
                <p style={{ margin: '2px 0', fontWeight: 600 }}>{selectedSlide.title}</p>
              </div>
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>คำโปรย:</span>
                <p style={{ margin: '2px 0' }}>{selectedSlide.subtitle || '—'}</p>
              </div>
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>ปุ่มเชื่อมโยง:</span>
                <p style={{ margin: '2px 0' }}>
                  {selectedSlide.button_text ? (
                    <a href={selectedSlide.button_url} target="_blank" rel="noreferrer" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
                      {selectedSlide.button_text} <ExternalLink size={14} />
                    </a>
                  ) : '—'}
                </p>
              </div>
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>ลำดับความสำคัญ:</span>
                <p style={{ margin: '2px 0' }}>{selectedSlide.priority}</p>
              </div>
            </div>

            {selectedSlide.description && (
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>รายละเอียด:</span>
                <p style={{ margin: '4px 0', lineHeight: 1.6 }}>{selectedSlide.description}</p>
              </div>
            )}
          </div>
        )}
      </CrudDetailModal>
    </div>
  );
}
