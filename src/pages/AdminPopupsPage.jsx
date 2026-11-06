import React, { useEffect, useState } from 'react';
import { Plus, Eye, Edit2, Trash2, CheckCircle, XCircle, Image as ImageIcon, Sparkles, AlertCircle, Smartphone, Monitor } from 'lucide-react';
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

const MAX_POPUPS = 3;

const INITIAL_FORM = {
  id: null,
  title: '',
  subtitle: '',
  banner_image: 'popup_dividend.jpg',
  mascot_image: '',
  layout_mode: 'banner_only',
  button_text: 'ดูรายละเอียด',
  button_url: '/dividend-estimator',
  button_target: '_self',
  priority_order: 1,
  start_at: '',
  end_at: '',
  status: 'active',
};

const LAYOUT_LABELS = {
  banner_only: 'แบนเนอร์อย่างเดียว (Banner Only)',
  mascot_only: 'มาสคอตน้องออมสุขอย่างเดียว (Mascot Only)',
  banner_and_mascot: 'แบนเนอร์ร่วมกับมาสคอต (Banner + Mascot)',
};

export default function AdminPopupsPage() {
  const [popups, setPopups] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);

  // Form Drawer state
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [formData, setFormData] = useState(INITIAL_FORM);
  const [formErrors, setFormErrors] = useState({});
  const [formDirty, setFormDirty] = useState(false);
  const [formBusy, setFormBusy] = useState(false);
  const [previewDevice, setPreviewDevice] = useState('desktop'); // 'desktop' | 'mobile'

  // Detail Modal state
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedPopup, setSelectedPopup] = useState(null);

  // Load popups from Backend API
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');

    memberAdminApi('/api/admin/popups', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setPopups(res.data?.items || []);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          setError(err.message || 'ไม่สามารถโหลดข้อมูลป็อปอัปได้');
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
    if (popups.length >= MAX_POPUPS) {
      alertService.warning('ระบบอนุญาตให้มีป็อปอัปได้สูงสุดไม่เกิน 3 รายการ หากต้องการสร้างใหม่ กรุณาลบหรือแก้ไขรายการเดิม', 'โควต้าป็อปอัปเต็ม');
      return;
    }
    setFormData({
      ...INITIAL_FORM,
      priority_order: popups.length + 1,
    });
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(false);
    setDrawerOpen(true);
  };

  // Open Edit Drawer
  const handleOpenEdit = (item) => {
    setFormData({
      id: item.id,
      title: item.title || '',
      subtitle: item.subtitle || '',
      banner_image: item.desktop_image || item.banner_image || '',
      mascot_image: item.mascot_image || '',
      layout_mode: item.layout_mode || 'banner_only',
      button_text: item.button_text || 'ดูรายละเอียด',
      button_url: item.button_url || '/',
      button_target: item.button_target || '_self',
      priority_order: Number(item.priority_order || 1),
      start_at: item.start_at ? item.start_at.replace(' ', 'T').slice(0, 16) : '',
      end_at: item.end_at ? item.end_at.replace(' ', 'T').slice(0, 16) : '',
      status: item.status || 'active',
      version: item.version,
    });
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(true);
    setDrawerOpen(true);
  };

  // Open Detail Modal
  const handleOpenDetail = (item) => {
    setSelectedPopup(item);
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

  // File upload handler
  const handleFileUpload = async (e, type) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      alertService.error('กรุณาเลือกไฟล์รูปภาพเท่านั้น (JPG, PNG, WEBP, SVG)', 'ไฟล์ไม่ถูกต้อง');
      return;
    }

    const uploadFormData = new FormData();
    uploadFormData.append('file', file);

    try {
      const res = await memberAdminApi('/api/admin/popups/upload', {
        method: 'POST',
        body: uploadFormData,
      });

      if (res.success && res.data?.path) {
        if (type === 'banner') {
          handleChange('banner_image', res.data.path);
        } else if (type === 'mascot') {
          handleChange('mascot_image', res.data.path);
        }
        alertService.success('อัปโหลดรูปภาพเรียบร้อยแล้ว');
      }
    } catch (err) {
      alertService.error(err.message || 'อัปโหลดรูปภาพล้มเหลว', 'เกิดข้อผิดพลาด');
    }
  };

  // Form Validation
  const validateForm = () => {
    const errors = {};
    if (!formData.title.trim()) errors.title = 'กรุณากรอกหัวข้อป็อปอัป';
    if (!formData.button_text.trim()) errors.button_text = 'กรุณากรอกข้อความบนปุ่ม';
    if (!formData.button_url.trim()) errors.button_url = 'กรุณากรอกลิงก์ปลายทาง';
    if (Number(formData.priority_order) < 1 || Number(formData.priority_order) > 3) {
      errors.priority_order = 'ลำดับความสำคัญต้องอยู่ระหว่าง 1 ถึง 3';
    }
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  // Save Form (Create or Update)
  const handleSave = async () => {
    if (!validateForm()) return;

    setFormBusy(true);
    try {
      const endpoint = isEdit
        ? `/api/admin/popups/${formData.id}/update`
        : '/api/admin/popups';

      const res = await memberAdminApi(endpoint, {
        method: 'POST',
        body: JSON.stringify(formData),
      });

      if (res.success) {
        alertService.success(res.message || 'บันทึกข้อมูลเรียบร้อยแล้ว');
        setDrawerOpen(false);
        setRefreshKey((k) => k + 1);
      }
    } catch (err) {
      alertService.error(err.message || 'ไม่สามารถบันทึกข้อมูลได้', 'เกิดข้อผิดพลาด');
    } finally {
      setFormBusy(false);
    }
  };

  // Delete Popup
  const handleDelete = async (item) => {
    const confirmed = await alertService.confirm(
      `คุณต้องการลบป็อปอัป "${item.title}" ใช่หรือไม่? เมื่อลบแล้วจะเพิ่มโควต้าว่างสำหรับสร้างรายการใหม่`,
      'ยืนยันการลบแคมเปญ'
    );
    if (!confirmed) return;

    try {
      const res = await memberAdminApi(`/api/admin/popups/${item.id}/delete`, {
        method: 'POST',
      });
      if (res.success) {
        alertService.success('ลบป็อปอัปเรียบร้อยแล้ว');
        setRefreshKey((k) => k + 1);
      }
    } catch (err) {
      alertService.error(err.message || 'ไม่สามารถลบป็อปอัปได้', 'เกิดข้อผิดพลาด');
    }
  };

  const columns = [
    {
      key: 'priority_order',
      title: 'ลำดับ',
      render: (item) => (
        <span
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: '28px',
            height: '28px',
            borderRadius: '50%',
            background: item.priority_order === 1 ? '#0284c7' : '#64748b',
            color: '#fff',
            fontWeight: 700,
            fontSize: '0.85rem',
          }}
        >
          {item.priority_order || 1}
        </span>
      ),
    },
    {
      key: 'preview',
      title: 'รูปภาพ / มาสคอต',
      render: (item) => {
        const isMascot = item.layout_mode === 'mascot_only' || item.layout_mode === 'banner_and_mascot';
        return (
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <div
              style={{
                width: '64px',
                height: '42px',
                borderRadius: '6px',
                background: '#f1f5f9',
                overflow: 'hidden',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                border: '1px solid #e2e8f0',
              }}
            >
              {item.banner_image ? (
                <img
                  src={item.banner_image}
                  alt=""
                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                />
              ) : (
                <ImageIcon size={18} color="#94a3b8" />
              )}
            </div>
            {isMascot && (
              <img
                src={item.mascot_image || '/assets/img/mascot-aomsuk.svg'}
                alt="น้องออมสุข"
                title="มาสคอตน้องออมสุข"
                style={{ width: '28px', height: '28px', objectFit: 'contain' }}
              />
            )}
          </div>
        );
      },
    },
    {
      key: 'title',
      title: 'หัวข้อ & รายละเอียด',
      render: (item) => (
        <div>
          <div style={{ fontWeight: 600, color: 'var(--primary-900)', fontSize: '0.95rem' }}>
            {item.title}
          </div>
          <div style={{ fontSize: '0.8rem', color: '#64748b', maxWidth: '300px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
            {item.subtitle || '-'}
          </div>
        </div>
      ),
    },
    {
      key: 'layout_mode',
      title: 'โหมดภาพ',
      render: (item) => (
        <span style={{ fontSize: '0.82rem', color: '#334155', background: '#f8fafc', padding: '0.2rem 0.5rem', borderRadius: '4px', border: '1px solid #e2e8f0' }}>
          {item.layout_mode === 'mascot_only'
            ? 'มาสคอตล้วน'
            : item.layout_mode === 'banner_and_mascot'
            ? 'แบนเนอร์+มาสคอต'
            : 'แบนเนอร์ล้วน'}
        </span>
      ),
    },
    {
      key: 'button',
      title: 'ปุ่ม CTA',
      render: (item) => (
        <span style={{ fontSize: '0.82rem', color: '#0284c7', fontWeight: 600 }}>
          {item.button_text} ({item.button_url})
        </span>
      ),
    },
    {
      key: 'status',
      title: 'สถานะ',
      render: (item) => (
        <StatusBadge
          status={item.status === 'active' ? 'published' : 'draft'}
          label={item.status === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'}
        />
      ),
    },
    {
      key: 'actions',
      title: 'จัดการ',
      align: 'right',
      render: (item) => (
        <div style={{ display: 'flex', gap: '0.4rem', justifyContent: 'flex-end' }}>
          <button
            type="button"
            onClick={() => handleOpenDetail(item)}
            className="btn btn-sm btn-subtle"
            title="ดูรายละเอียดและพรีวิว"
          >
            <Eye size={15} />
          </button>
          <button
            type="button"
            onClick={() => handleOpenEdit(item)}
            className="btn btn-sm btn-subtle"
            title="แก้ไข"
          >
            <Edit2 size={15} />
          </button>
          <button
            type="button"
            onClick={() => handleDelete(item)}
            className="btn btn-sm btn-subtle"
            style={{ color: '#ef4444' }}
            title="ลบ"
          >
            <Trash2 size={15} />
          </button>
        </div>
      ),
    },
  ];

  return (
    <PermissionGuard permission="content" action="view">
      <div className="admin-page-container">
        <PageHeader
          title="ระบบจัดการแคมเปญป็อปอัป (Popup Campaign Manager V2)"
          subtitle="จัดการหน้าต่างประชาสัมพันธ์สาธารณะ รองรับสูงสุดไม่เกิน 3 รายการ แสดงผลแบบเรียงลำดับพร้อมรองรับมาสคอตน้องออมสุข"
          breadcrumbs={[
            { label: 'แดชบอร์ด', to: '/admin/workspace' },
            { label: 'จัดการเนื้อหา', to: '/admin/content' },
            { label: 'แคมเปญป็อปอัป' },
          ]}
          actions={
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <span
                style={{
                  fontSize: '0.88rem',
                  fontWeight: 600,
                  padding: '0.4rem 0.85rem',
                  borderRadius: '9999px',
                  background: popups.length >= MAX_POPUPS ? '#fee2e2' : '#e0f2fe',
                  color: popups.length >= MAX_POPUPS ? '#b91c1c' : '#0369a1',
                  border: `1px solid ${popups.length >= MAX_POPUPS ? '#fca5a5' : '#bae6fd'}`,
                }}
              >
                โควต้า: {popups.length} / {MAX_POPUPS} รายการ
              </span>
              <button
                type="button"
                onClick={handleOpenCreate}
                disabled={popups.length >= MAX_POPUPS}
                className="btn btn-primary"
                style={{
                  opacity: popups.length >= MAX_POPUPS ? 0.6 : 1,
                  cursor: popups.length >= MAX_POPUPS ? 'not-allowed' : 'pointer',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '0.4rem',
                }}
              >
                <Plus size={16} />
                <span>สร้างป็อปอัปใหม่</span>
              </button>
            </div>
          }
        />

        {/* Notice alert when limit reached */}
        {popups.length >= MAX_POPUPS && (
          <div
            style={{
              background: '#fef2f2',
              border: '1px solid #fecaca',
              borderRadius: '0.5rem',
              padding: '0.75rem 1rem',
              marginBottom: '1rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem',
              color: '#991b1b',
              fontSize: '0.88rem',
            }}
          >
            <AlertCircle size={18} />
            <span>
              จำนวนป็อปอัปครบตามขีดจำกัดสูงสุด 3 รายการแล้ว ระบบถูกออกแบบมาให้แสดงผลเรียงตามลำดับความสำคัญ (Priority 1–3) เพื่อประสบการณ์ที่ดีของผู้ใช้งาน
            </span>
          </div>
        )}

        {/* Data Table */}
        <CrudDataTable
          columns={columns}
          data={popups}
          loading={loading}
          error={error}
          onRetry={() => setRefreshKey((k) => k + 1)}
          emptyMessage="ยังไม่มีรายการป็อปอัปประชาสัมพันธ์ในระบบ"
        />

        {/* Form Drawer (Create & Edit) */}
        <CrudFormDrawer
          open={drawerOpen}
          title={isEdit ? 'แก้ไขแคมเปญป็อปอัป' : 'สร้างแคมเปญป็อปอัปใหม่ (สูงสุด 3 รายการ)'}
          onClose={() => setDrawerOpen(false)}
          onSave={handleSave}
          dirty={formDirty}
          saving={formBusy}
          width="min(720px, 100vw)"
        >
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            {/* Title */}
            <div>
              <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                หัวข้อป็อปอัป <span style={{ color: '#ef4444' }}>*</span>
              </label>
              <input
                type="text"
                className="form-control"
                value={formData.title}
                onChange={(e) => handleChange('title', e.target.value)}
                placeholder="เช่น โครงการประมาณการเงินปันผลและเฉลี่ยคืน ประจำปี 2568"
                style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
              />
              {formErrors.title && <div style={{ color: '#ef4444', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.title}</div>}
            </div>

            {/* Subtitle / Description */}
            <div>
              <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                คำบรรยาย / รายละเอียดประชาสัมพันธ์
              </label>
              <textarea
                className="form-control"
                rows={3}
                value={formData.subtitle}
                onChange={(e) => handleChange('subtitle', e.target.value)}
                placeholder="รายละเอียดสิทธิประโยชน์ วันที่เริ่มต้น และเงื่อนไขสำคัญ..."
                style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
              />
            </div>

            {/* Layout Mode */}
            <div>
              <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                รูปแบบการแสดงภาพ (Layout Mode)
              </label>
              <select
                className="form-control"
                value={formData.layout_mode}
                onChange={(e) => handleChange('layout_mode', e.target.value)}
                style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
              >
                <option value="banner_only">{LAYOUT_LABELS.banner_only}</option>
                <option value="mascot_only">{LAYOUT_LABELS.mascot_only}</option>
                <option value="banner_and_mascot">{LAYOUT_LABELS.banner_and_mascot}</option>
              </select>
            </div>

            {/* Banner Image Input & Upload */}
            {(formData.layout_mode === 'banner_only' || formData.layout_mode === 'banner_and_mascot') && (
              <div style={{ background: '#f8fafc', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #e2e8f0' }}>
                <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                  ภาพแบนเนอร์ (Banner Image)
                </label>
                <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginBottom: '0.5rem' }}>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.banner_image}
                    onChange={(e) => handleChange('banner_image', e.target.value)}
                    placeholder="/assets/img/popup_dividend.jpg หรือ uploads/popups/..."
                    style={{ flex: 1, padding: '0.55rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
                  />
                  <label className="btn btn-outline" style={{ cursor: 'pointer', padding: '0.55rem 0.85rem' }}>
                    <span>อัปโหลดภาพ</span>
                    <input type="file" accept="image/*" onChange={(e) => handleFileUpload(e, 'banner')} style={{ display: 'none' }} />
                  </label>
                </div>
                <div style={{ fontSize: '0.78rem', color: '#64748b' }}>
                  แนะนำขนาด 1200 x 600 พิกเซล (อัตราส่วน 2:1) ไฟล์ JPG, PNG หรือ WEBP
                </div>
              </div>
            )}

            {/* Mascot Image Input & Upload */}
            {(formData.layout_mode === 'mascot_only' || formData.layout_mode === 'banner_and_mascot') && (
              <div style={{ background: '#f0f9ff', padding: '1rem', borderRadius: '0.5rem', border: '1px solid #bae6fd' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.35rem' }}>
                  <label className="form-label" style={{ fontWeight: 600, color: '#0369a1' }}>
                    มาสคอต "น้องออมสุข" (Mascot Artwork)
                  </label>
                  <button
                    type="button"
                    onClick={() => handleChange('mascot_image', '/assets/img/mascot-aomsuk.svg')}
                    className="btn btn-sm btn-subtle"
                    style={{ fontSize: '0.78rem', color: '#0284c7' }}
                  >
                    ใช้น้องออมสุขมาตรฐาน
                  </button>
                </div>
                <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginBottom: '0.5rem' }}>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.mascot_image}
                    onChange={(e) => handleChange('mascot_image', e.target.value)}
                    placeholder="เว้นว่างเพื่อใช้น้องออมสุขทางการ หรือระบุพาธภาพ..."
                    style={{ flex: 1, padding: '0.55rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
                  />
                  <label className="btn btn-outline" style={{ cursor: 'pointer', padding: '0.55rem 0.85rem' }}>
                    <span>เปลี่ยนมาสคอต</span>
                    <input type="file" accept="image/*" onChange={(e) => handleFileUpload(e, 'mascot')} style={{ display: 'none' }} />
                  </label>
                </div>
                <div style={{ fontSize: '0.78rem', color: '#0284c7' }}>
                  ภาพเวกเตอร์ทางการ: <code>/assets/img/mascot-aomsuk.svg</code>
                </div>
              </div>
            )}

            {/* Button Text & URL */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
              <div>
                <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                  ข้อความบนปุ่ม (CTA) <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.button_text}
                  onChange={(e) => handleChange('button_text', e.target.value)}
                  placeholder="เช่น ดูรายละเอียด, ประมาณการทันที"
                  style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
                />
                {formErrors.button_text && <div style={{ color: '#ef4444', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.button_text}</div>}
              </div>

              <div>
                <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                  ลิงก์ปลายทาง (URL) <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.button_url}
                  onChange={(e) => handleChange('button_url', e.target.value)}
                  placeholder="เช่น /dividend-estimator หรือ https://..."
                  style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
                />
                {formErrors.button_url && <div style={{ color: '#ef4444', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.button_url}</div>}
              </div>
            </div>

            {/* Priority Order & Status */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
              <div>
                <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                  ลำดับความสำคัญ (1–3)
                </label>
                <select
                  className="form-control"
                  value={formData.priority_order}
                  onChange={(e) => handleChange('priority_order', Number(e.target.value))}
                  style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
                >
                  <option value={1}>ลำดับที่ 1 (ความสำคัญสูงสุด - แสดงก่อน)</option>
                  <option value={2}>ลำดับที่ 2 (ความสำคัญปานกลาง)</option>
                  <option value={3}>ลำดับที่ 3 (ความสำคัญทั่วไป)</option>
                </select>
              </div>

              <div>
                <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                  สถานะการเผยแพร่
                </label>
                <select
                  className="form-control"
                  value={formData.status}
                  onChange={(e) => handleChange('status', e.target.value)}
                  style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
                >
                  <option value="active">เปิดใช้งาน (Active - เผยแพร่สาธารณะ)</option>
                  <option value="inactive">ปิดใช้งานชั่วคราว (Inactive)</option>
                </select>
              </div>
            </div>

            {/* Scheduling Datetimes */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
              <div>
                <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                  เริ่มเผยแพร่ (ไม่ระบุ = ทันที)
                </label>
                <input
                  type="datetime-local"
                  className="form-control"
                  value={formData.start_at}
                  onChange={(e) => handleChange('start_at', e.target.value)}
                  style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
                />
              </div>

              <div>
                <label className="form-label" style={{ fontWeight: 600, display: 'block', marginBottom: '0.35rem' }}>
                  สิ้นสุดเผยแพร่ (ไม่ระบุ = ตลอดไป)
                </label>
                <input
                  type="datetime-local"
                  className="form-control"
                  value={formData.end_at}
                  onChange={(e) => handleChange('end_at', e.target.value)}
                  style={{ width: '100%', padding: '0.65rem', borderRadius: '0.375rem', border: '1px solid #cbd5e1' }}
                />
              </div>
            </div>

            {/* Live Interactive Preview Box */}
            <div style={{ marginTop: '1rem', borderTop: '1px solid #e2e8f0', paddingTop: '1.25rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
                <span style={{ fontWeight: 700, color: 'var(--primary-900)', fontSize: '0.95rem' }}>
                  พรีวิวแบบเรียลไทม์ (Live Interactive Preview)
                </span>
                <div style={{ display: 'flex', gap: '0.25rem' }}>
                  <button
                    type="button"
                    onClick={() => setPreviewDevice('desktop')}
                    className={`btn btn-sm ${previewDevice === 'desktop' ? 'btn-primary' : 'btn-subtle'}`}
                  >
                    <Monitor size={14} /> Desktop
                  </button>
                  <button
                    type="button"
                    onClick={() => setPreviewDevice('mobile')}
                    className={`btn btn-sm ${previewDevice === 'mobile' ? 'btn-primary' : 'btn-subtle'}`}
                  >
                    <Smartphone size={14} /> Mobile
                  </button>
                </div>
              </div>

              {/* Preview Container */}
              <div
                style={{
                  background: 'rgba(15, 23, 42, 0.75)',
                  padding: '1.5rem',
                  borderRadius: '0.75rem',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                <div
                  style={{
                    width: previewDevice === 'desktop' ? '100%' : '320px',
                    maxWidth: '100%',
                    background: '#ffffff',
                    borderRadius: '1rem',
                    overflow: 'hidden',
                    boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.2)',
                  }}
                >
                  {/* Visual Area */}
                  {formData.layout_mode === 'banner_only' && (
                    <div style={{ height: '140px', background: '#0284c7', overflow: 'hidden' }}>
                      <img
                        src={formData.banner_image || '/assets/img/popup_dividend.jpg'}
                        alt=""
                        style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                      />
                    </div>
                  )}

                  {formData.layout_mode === 'mascot_only' && (
                    <div style={{ padding: '1.5rem', background: '#e0f2fe', textAlign: 'center' }}>
                      <img
                        src={formData.mascot_image || '/assets/img/mascot-aomsuk.svg'}
                        alt="น้องออมสุข"
                        style={{ height: '100px', objectFit: 'contain' }}
                      />
                    </div>
                  )}

                  {formData.layout_mode === 'banner_and_mascot' && (
                    <div style={{ height: '140px', background: '#0369a1', position: 'relative', overflow: 'hidden', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      {formData.banner_image && (
                        <img src={formData.banner_image} alt="" style={{ position: 'absolute', width: '100%', height: '100%', objectFit: 'cover', opacity: 0.2 }} />
                      )}
                      <img src={formData.mascot_image || '/assets/img/mascot-aomsuk.svg'} alt="" style={{ height: '100px', objectFit: 'contain', zIndex: 2 }} />
                    </div>
                  )}

                  {/* Content Area */}
                  <div style={{ padding: '1rem' }}>
                    <div style={{ fontSize: '0.72rem', color: '#b45309', fontWeight: 700, marginBottom: '0.25rem' }}>
                      ✨ สิทธิประโยชน์สมาชิก
                    </div>
                    <div style={{ fontSize: '1rem', fontWeight: 800, color: '#0f172a', marginBottom: '0.4rem', lineHeight: 1.3 }}>
                      {formData.title || 'ชื่อหัวข้อป็อปอัป'}
                    </div>
                    <div style={{ fontSize: '0.82rem', color: '#64748b', lineHeight: 1.5, marginBottom: '0.85rem' }}>
                      {formData.subtitle || 'รายละเอียดคำบรรยายประชาสัมพันธ์...'}
                    </div>
                    <div style={{ display: 'flex', gap: '0.5rem' }}>
                      <div className="btn btn-outline btn-sm" style={{ flex: 1, textAlign: 'center' }}>
                        ปิดหน้าต่าง
                      </div>
                      <div className="btn btn-primary btn-sm" style={{ flex: 1.5, textAlign: 'center' }}>
                        {formData.button_text || 'ดูรายละเอียด'}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </CrudFormDrawer>

        {/* Detail Modal */}
        <CrudDetailModal
          open={detailModalOpen}
          title={`รายละเอียดป็อปอัป: ${selectedPopup?.title || ''}`}
          onClose={() => setDetailModalOpen(false)}
        >
          {selectedPopup && (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              <div>
                <span style={{ fontWeight: 600, color: '#64748b' }}>หัวข้อ:</span>
                <div style={{ fontSize: '1.1rem', fontWeight: 700, color: '#0f172a' }}>{selectedPopup.title}</div>
              </div>
              <div>
                <span style={{ fontWeight: 600, color: '#64748b' }}>คำบรรยาย:</span>
                <div style={{ color: '#334155', whiteSpace: 'pre-line' }}>{selectedPopup.subtitle || '-'}</div>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
                <div>
                  <span style={{ fontWeight: 600, color: '#64748b' }}>ลำดับความสำคัญ:</span>
                  <div>ลำดับที่ {selectedPopup.priority_order || 1}</div>
                </div>
                <div>
                  <span style={{ fontWeight: 600, color: '#64748b' }}>โหมดภาพ:</span>
                  <div>{LAYOUT_LABELS[selectedPopup.layout_mode] || selectedPopup.layout_mode}</div>
                </div>
                <div>
                  <span style={{ fontWeight: 600, color: '#64748b' }}>ข้อความบนปุ่ม:</span>
                  <div>{selectedPopup.button_text}</div>
                </div>
                <div>
                  <span style={{ fontWeight: 600, color: '#64748b' }}>ลิงก์ปลายทาง:</span>
                  <div><code>{selectedPopup.button_url}</code></div>
                </div>
                <div>
                  <span style={{ fontWeight: 600, color: '#64748b' }}>สถานะ:</span>
                  <div>{selectedPopup.status === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'}</div>
                </div>
                <div>
                  <span style={{ fontWeight: 600, color: '#64748b' }}>สถิติการแสดงผล:</span>
                  <div>แสดงผล {selectedPopup.impressions_count || 0} ครั้ง / คลิก {selectedPopup.clicks_count || 0} ครั้ง</div>
                </div>
              </div>
            </div>
          )}
        </CrudDetailModal>
      </div>
    </PermissionGuard>
  );
}

