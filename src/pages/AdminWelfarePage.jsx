import React, { useEffect, useState } from 'react';
import { Plus, Eye, Edit2, Trash2, CheckCircle, XCircle, HeartHandshake } from 'lucide-react';
import { memberAdminApi } from '../services/memberAdminApi';
import alertService from '../services/alertService';
import {
  PageHeader,
  CrudDataTable,
  CrudFormDrawer,
  CrudDetailModal,
  StatusBadge,
} from '../components/admin/design-system';

export default function AdminWelfarePage() {
  const [welfares, setWelfares] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);

  // Form Drawer state
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [formData, setFormData] = useState({
    id: null,
    category: 'สวัสดิการทั่วไป',
    title: '',
    benefit_amount: '',
    short_description: '',
    full_description: '',
    eligibility: '',
    contact_info: '',
    sort_order: 0,
    status: 'active',
  });
  const [formErrors, setFormErrors] = useState({});
  const [formDirty, setFormDirty] = useState(false);
  const [formBusy, setFormBusy] = useState(false);

  // Detail Modal state
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedWelfare, setSelectedWelfare] = useState(null);

  // Load welfares from real API
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');

    memberAdminApi('/api/admin/welfare', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setWelfares(res.data || []);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          setError(err.message || 'ไม่สามารถโหลดข้อมูลสวัสดิการได้');
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
      category: 'สวัสดิการทั่วไป',
      title: '',
      benefit_amount: '',
      short_description: '',
      full_description: '',
      eligibility: '',
      contact_info: '',
      sort_order: 0,
      status: 'active',
    });
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(false);
    setDrawerOpen(true);
  };

  // Open Edit Drawer
  const handleOpenEdit = (welfare) => {
    setFormData({
      id: welfare.id,
      category: welfare.category || 'สวัสดิการทั่วไป',
      title: welfare.title || '',
      benefit_amount: welfare.benefit_amount || '',
      short_description: welfare.short_description || '',
      full_description: welfare.full_description || '',
      eligibility: welfare.eligibility || '',
      contact_info: welfare.contact_info || '',
      sort_order: Number(welfare.sort_order || 0),
      status: welfare.status || 'active',
    });
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(true);
    setDrawerOpen(true);
  };

  // Open Detail Modal
  const handleOpenDetail = (welfare) => {
    setSelectedWelfare(welfare);
    setDetailModalOpen(true);
  };

  // Field change
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

    const errors = {};
    if (!formData.title.trim()) errors.title = 'กรุณากรอกชื่อสวัสดิการ';
    if (!formData.benefit_amount.trim()) errors.benefit_amount = 'กรุณากรอกวงเงินช่วยเหลือ';

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      alertService.validationError(errors);
      return;
    }

    setFormBusy(true);
    alertService.showLoading('กำลังบันทึกข้อมูลสวัสดิการ...');

    try {
      const url = isEdit
        ? `/api/admin/welfare/${formData.id}/update`
        : '/api/admin/welfare';

      await memberAdminApi(url, { data: formData });
      alertService.closeLoading();
      alertService.success(isEdit ? 'แก้ไขสวัสดิการเรียบร้อยแล้ว' : 'เพิ่มสวัสดิการใหม่สำเร็จ');
      setDrawerOpen(false);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error('บันทึกสวัสดิการไม่สำเร็จ', err.message);
    } finally {
      setFormBusy(false);
    }
  };

  // Delete
  const handleDelete = async (welfare) => {
    const confirmed = await alertService.confirmDelete(`สวัสดิการ "${welfare.title}"`);
    if (!confirmed) return;

    alertService.showLoading('กำลังลบสวัสดิการ...');
    try {
      await memberAdminApi(`/api/admin/welfare/${welfare.id}/delete`, { data: {} });
      alertService.closeLoading();
      alertService.success('ลบสวัสดิการเรียบร้อยแล้ว');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error('ลบสวัสดิการไม่สำเร็จ', err.message);
    }
  };

  // Toggle status
  const handleToggleStatus = async (welfare) => {
    const newStatus = welfare.status === 'active' ? 'inactive' : 'active';
    const confirmed = await alertService.confirmStatusToggle(welfare.title, newStatus);
    if (!confirmed) return;

    alertService.showLoading('กำลังปรับปรุงสถานะ...');
    try {
      await memberAdminApi(`/api/admin/welfare/${welfare.id}/update`, {
        data: { ...welfare, status: newStatus },
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
      key: 'category',
      label: 'หมวดหมู่',
      width: '140px',
      render: (row) => (
        <span
          style={{
            padding: '3px 8px',
            borderRadius: '6px',
            backgroundColor: '#e0f2fe',
            color: '#0284c7',
            fontWeight: 600,
            fontSize: '0.8rem',
          }}
        >
          {row.category || 'ทั่วไป'}
        </span>
      ),
    },
    {
      key: 'title',
      label: 'ชื่อสวัสดิการสมาชิก',
      render: (row) => (
        <div>
          <strong style={{ color: 'var(--acc-text, #0f172a)' }}>{row.title}</strong>
          {row.short_description && (
            <small style={{ display: 'block', color: 'var(--acc-text-secondary, #64748b)' }}>
              {row.short_description}
            </small>
          )}
        </div>
      ),
    },
    {
      key: 'benefit_amount',
      label: 'วงเงินช่วยเหลือ / ประโยชน์',
      width: '180px',
      render: (row) => (
        <span style={{ fontWeight: 600, color: '#047857' }}>
          {row.benefit_amount || '—'}
        </span>
      ),
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
            title={row.status === 'active' ? 'ระงับ' : 'เปิดใช้งาน'}
          >
            {row.status === 'active' ? <XCircle size={15} /> : <CheckCircle size={15} />}
          </button>

          <button
            type="button"
            className="acc-btn danger"
            style={{ padding: '6px 8px' }}
            onClick={() => handleDelete(row)}
            title="ลบสวัสดิการ"
          >
            <Trash2 size={15} />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="admin-welfare-page">
      <PageHeader
        title="จัดการสวัสดิการสมาชิก"
        subtitle="บันทึกและปรับปรุงข้อมูลสวัสดิการสงเคราะห์ วงเงิน และเกณฑ์การรับสิทธิ์สำหรับสมาชิกสหกรณ์"
        breadcrumbs={[
          { label: 'การเงิน & สวัสดิการ', to: '/admin/finance' },
          { label: 'สวัสดิการสมาชิก' },
        ]}
        actions={
          <button
            type="button"
            className="acc-btn primary"
            onClick={handleOpenCreate}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}
          >
            <Plus size={16} />
            เพิ่มสวัสดิการใหม่
          </button>
        }
      />

      <div className="admin-card">
        <CrudDataTable
          columns={columns}
          rows={welfares}
          loading={loading}
          error={error}
          searchPlaceholder="ค้นหาชื่อสวัสดิการ หมวด หรือวงเงิน..."
          searchKeys={['title', 'category', 'benefit_amount', 'eligibility']}
          emptyTitle="ยังไม่มีข้อมูลสวัสดิการ"
          emptyDescription="คุณสามารถเพิ่มรายการสวัสดิการสมาชิกเพื่อให้สมาชิกตรวจสอบสิทธิ์ได้"
          emptyActionLabel="เพิ่มสวัสดิการแรก"
          onEmptyAction={handleOpenCreate}
        />
      </div>

      {/* Form Drawer */}
      <CrudFormDrawer
        open={drawerOpen}
        title={isEdit ? 'แก้ไขข้อมูลสวัสดิการ' : 'เพิ่มสวัสดิการสมาชิกใหม่'}
        subtitle="ข้อมูลจะแสดงผลในหน้าสวัสดิการของเว็บไซต์และพอร์ทัลสมาชิก"
        isEdit={isEdit}
        dirty={formDirty}
        busy={formBusy}
        errors={formErrors}
        onClose={() => setDrawerOpen(false)}
        onSubmit={handleFormSubmit}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '16px' }}>
            <label className="admin-field">
              <span>หมวดหมู่สวัสดิการ *</span>
              <select
                value={formData.category}
                onChange={(e) => handleChange('category', e.target.value)}
              >
                <option value="สวัสดิการทั่วไป">สวัสดิการทั่วไป</option>
                <option value="สวัสดิการครอบครัว">สวัสดิการครอบครัว</option>
                <option value="สวัสดิการรักษาพยาบาล">สวัสดิการรักษาพยาบาล</option>
                <option value="สวัสดิการมรณกรรม">สวัสดิการมรณกรรม / เสียชีวิต</option>
                <option value="สวัสดิการการศึกษา">สวัสดิการทุนการศึกษา</option>
                <option value="สวัสดิการเกษียณอายุ">สวัสดิการสมาชิกเกษียณ</option>
              </select>
            </label>

            <label className="admin-field">
              <span>วงเงิน / ผลประโยชน์ช่วยเหลือ *</span>
              <input
                type="text"
                required
                maxLength={100}
                value={formData.benefit_amount}
                onChange={(e) => handleChange('benefit_amount', e.target.value)}
                placeholder="เช่น 10,000 บาท หรือ ตามระยะเวลาเป็นสมาชิก"
              />
            </label>
          </div>

          <label className="admin-field">
            <span>ชื่อสวัสดิการสมาชิก *</span>
            <input
              type="text"
              required
              maxLength={255}
              value={formData.title}
              onChange={(e) => handleChange('title', e.target.value)}
              placeholder="เช่น เงินช่วยเหลือกรณีสมาชิกเสียชีวิต"
            />
          </label>

          <label className="admin-field">
            <span>คำอธิบายสั้นๆ (Short Description)</span>
            <textarea
              rows={2}
              value={formData.short_description}
              onChange={(e) => handleChange('short_description', e.target.value)}
              placeholder="คำสรุปสั้นๆ สำหรับแสดงในรายการสวัสดิการ"
            />
          </label>

          <label className="admin-field">
            <span>เกณฑ์และเงื่อนไขการรับสิทธิ์ (Eligibility)</span>
            <textarea
              rows={3}
              value={formData.eligibility}
              onChange={(e) => handleChange('eligibility', e.target.value)}
              placeholder="เช่น เป็นสมาชิกติดต่อกันไม่น้อยกว่า 1 ปี และส่งค่าหุ้นสม่ำเสมอ"
            />
          </label>

          <label className="admin-field">
            <span>รายละเอียดเงื่อนไขทั้งหมด</span>
            <textarea
              rows={4}
              value={formData.full_description}
              onChange={(e) => handleChange('full_description', e.target.value)}
              placeholder="เอกสารประกอบการยื่นขอ และขั้นตอนการรับเงินช่วยเหลือ"
            />
          </label>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: '16px' }}>
            <label className="admin-field">
              <span>ช่องทางติดต่อสอบถาม</span>
              <input
                type="text"
                value={formData.contact_info}
                onChange={(e) => handleChange('contact_info', e.target.value)}
                placeholder="เช่น งานสวัสดิการ โทร. 038-611111 ต่อ 104"
              />
            </label>

            <label className="admin-field">
              <span>ลำดับการแสดงผล</span>
              <input
                type="number"
                min={0}
                value={formData.sort_order}
                onChange={(e) => handleChange('sort_order', Number(e.target.value))}
              />
            </label>

            <label className="admin-field">
              <span>สถานะการใช้งาน</span>
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
        title={selectedWelfare?.title || 'รายละเอียดสวัสดิการ'}
        badge={<StatusBadge status={selectedWelfare?.status} />}
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
        {selectedWelfare && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '16px' }}>
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>หมวดหมู่:</span>
                <p style={{ margin: '2px 0', fontWeight: 600 }}>{selectedWelfare.category}</p>
              </div>
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>วงเงินช่วยเหลือ:</span>
                <p style={{ margin: '2px 0', fontWeight: 600, color: '#047857' }}>
                  {selectedWelfare.benefit_amount}
                </p>
              </div>
            </div>

            {selectedWelfare.eligibility && (
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>เกณฑ์การรับสิทธิ์:</span>
                <p style={{ margin: '4px 0', lineHeight: 1.6, backgroundColor: '#f8fafc', padding: '12px', borderRadius: '8px' }}>
                  {selectedWelfare.eligibility}
                </p>
              </div>
            )}

            {selectedWelfare.full_description && (
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>รายละเอียดและเอกสาร:</span>
                <p style={{ margin: '4px 0', lineHeight: 1.6 }}>{selectedWelfare.full_description}</p>
              </div>
            )}

            {selectedWelfare.contact_info && (
              <div>
                <span style={{ fontSize: '0.8rem', color: '#64748b' }}>ช่องทางติดต่อ:</span>
                <p style={{ margin: '2px 0' }}>{selectedWelfare.contact_info}</p>
              </div>
            )}
          </div>
        )}
      </CrudDetailModal>
    </div>
  );
}
