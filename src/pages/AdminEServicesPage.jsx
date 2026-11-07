import React, { useEffect, useState } from 'react';
import { Plus, Eye, Edit2, Trash2, CheckCircle, XCircle, Globe, ExternalLink, AlertTriangle, ShieldCheck } from 'lucide-react';
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

const INITIAL_FORM = {
  id: null,
  name: '',
  description: '',
  url: '',
  icon: 'globe',
  category: 'general',
  is_internal: 0,
  open_new_tab: 1,
  confirm_before_redirect: 0,
  is_maintenance: 0,
  sort_order: 10,
  status: 'active',
};

const CATEGORY_LABELS = {
  general: 'บริการทั่วไป (General)',
  member: 'สมาชิกสหกรณ์ (Member Services)',
  government: 'หน่วยงานราชการ / พันธมิตร (Government & Partners)',
  finance: 'บริการทางการเงิน (Financial Services)',
  system: 'ระบบงานภายใน (Internal Systems)',
};

export default function AdminEServicesPage() {
  const [links, setLinks] = useState([]);
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

  // Detail Modal state
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedLink, setSelectedLink] = useState(null);

  // Load from real API
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');

    memberAdminApi('/api/admin/eservices', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setLinks(res.data || []);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          setError(err.message || 'ไม่สามารถโหลดข้อมูลลิงก์ E-Service ได้');
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
    setFormData(INITIAL_FORM);
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(false);
    setDrawerOpen(true);
  };

  // Open Edit Drawer
  const handleOpenEdit = (item) => {
    setFormData({
      id: item.id,
      name: item.name || '',
      description: item.description || '',
      url: item.url || '',
      icon: item.icon || 'globe',
      category: item.category || 'general',
      is_internal: item.is_internal ? 1 : 0,
      open_new_tab: item.open_new_tab ? 1 : 0,
      confirm_before_redirect: item.confirm_before_redirect ? 1 : 0,
      is_maintenance: item.is_maintenance ? 1 : 0,
      sort_order: item.sort_order ?? 10,
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
    setSelectedLink(item);
    setDetailModalOpen(true);
  };

  // Field change
  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? (checked ? 1 : 0) : value,
    }));
    setFormDirty(true);
    if (formErrors[name]) {
      setFormErrors((prev) => ({ ...prev, [name]: null }));
    }
  };

  // Validation
  const validateForm = () => {
    const errors = {};
    if (!formData.name.trim()) errors.name = 'กรุณาระบุชื่อบริการหรือชื่อลิงก์';
    if (!formData.url.trim()) {
      errors.url = 'กรุณาระบุ URL ปลายทาง';
    } else if (!formData.url.startsWith('http://') && !formData.url.startsWith('https://') && !formData.url.startsWith('/')) {
      errors.url = 'URL ต้องขึ้นต้นด้วย https://, http:// หรือ / สำหรับลิงก์ภายใน';
    }
    return errors;
  };

  // Submit create/update
  const handleSubmitForm = async (e) => {
    e.preventDefault();
    const errors = validateForm();
    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      alertService.error('กรุณาตรวจสอบข้อมูลที่จำเป็น', 'ข้อมูลไม่ครบถ้วน');
      return;
    }

    setFormBusy(true);
    alertService.loading(isEdit ? 'กำลังบันทึกการแก้ไขบริการ...' : 'กำลังเพิ่มลิงก์บริการใหม่...');

    try {
      const endpoint = isEdit
        ? `/api/admin/eservices/${formData.id}`
        : '/api/admin/eservices';
      const method = isEdit ? 'PUT' : 'POST';

      const payload = {
        name: formData.name.trim(),
        description: formData.description.trim(),
        url: formData.url.trim(),
        icon: formData.icon,
        category: formData.category,
        is_internal: formData.is_internal,
        open_new_tab: formData.open_new_tab,
        confirm_before_redirect: formData.confirm_before_redirect,
        is_maintenance: formData.is_maintenance,
        sort_order: parseInt(formData.sort_order, 10) || 0,
        status: formData.status,
      };

      await memberAdminApi(endpoint, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      alertService.close();
      await alertService.success(
        isEdit ? 'บันทึกการแก้ไขบริการเรียบร้อยแล้ว' : 'เพิ่มลิงก์บริการเรียบร้อยแล้ว',
        'ดำเนินการสำเร็จ'
      );

      setDrawerOpen(false);
      setFormDirty(false);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.close();
      alertService.error(err.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'บันทึกไม่สำเร็จ');
    } finally {
      setFormBusy(false);
    }
  };

  // Toggle Status
  const handleToggleStatus = async (item) => {
    const newStatus = item.status === 'active' ? 'inactive' : 'active';
    const confirmed = await alertService.confirmStatusToggle(
      item.name,
      item.status,
      newStatus
    );
    if (!confirmed) return;

    try {
      alertService.loading('กำลังเปลี่ยนสถานะบริการ...');
      await memberAdminApi(`/api/admin/eservices/${item.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: item.name,
          url: item.url,
          status: newStatus,
        }),
      });
      alertService.close();
      alertService.toastSuccess(`เปลี่ยนสถานะเป็น ${newStatus === 'active' ? 'เปิดใช้งาน' : 'ปิดการใช้งาน'} เรียบร้อยแล้ว`);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.close();
      alertService.error(err.message || 'ไม่สามารถเปลี่ยนสถานะได้');
    }
  };

  // Delete
  const handleDelete = async (item) => {
    const confirmed = await alertService.confirmDelete(item.name, 'ลิงก์ E-Service นี้');
    if (!confirmed) return;

    try {
      alertService.loading('กำลังลบลิงก์บริการ...');
      await memberAdminApi(`/api/admin/eservices/${item.id}`, {
        method: 'DELETE',
      });
      alertService.close();
      await alertService.success('ลบลิงก์บริการเรียบร้อยแล้ว', 'ลบสำเร็จ');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.close();
      alertService.error(err.message || 'ไม่สามารถลบลิงก์บริการได้');
    }
  };

  // Columns definition
  const columns = [
    {
      key: 'name',
      header: 'ชื่อบริการ / URL',
      sortable: true,
      render: (item) => (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem', maxWidth: '320px' }}>
          <div style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Globe size={16} color="var(--primary-600, #2563eb)" />
            {item.name}
          </div>
          <a
            href={item.url}
            target="_blank"
            rel="noopener noreferrer"
            style={{
              fontSize: '0.8rem',
              color: 'var(--primary-600, #2563eb)',
              display: 'inline-flex',
              alignItems: 'center',
              gap: '0.25rem',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap',
              textDecoration: 'none',
            }}
          >
            {item.url}
            <ExternalLink size={12} />
          </a>
        </div>
      ),
    },
    {
      key: 'category',
      header: 'หมวดหมู่',
      sortable: true,
      render: (item) => (
        <span style={{ fontSize: '0.85rem', color: 'var(--navy-700, #334155)' }}>
          {CATEGORY_LABELS[item.category] || item.category || 'ทั่วไป'}
        </span>
      ),
    },
    {
      key: 'flags',
      header: 'คุณลักษณะ',
      render: (item) => (
        <div style={{ display: 'flex', gap: '0.35rem', flexWrap: 'wrap' }}>
          {item.is_maintenance ? (
            <span style={{ fontSize: '0.75rem', padding: '0.15rem 0.4rem', borderRadius: '4px', background: '#fef3c7', color: '#92400e', display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
              <AlertTriangle size={12} /> ปิดปรับปรุง
            </span>
          ) : null}
          {item.is_internal ? (
            <span style={{ fontSize: '0.75rem', padding: '0.15rem 0.4rem', borderRadius: '4px', background: '#e0e7ff', color: '#3730a3', display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
              <ShieldCheck size={12} /> ภายใน
            </span>
          ) : (
            <span style={{ fontSize: '0.75rem', padding: '0.15rem 0.4rem', borderRadius: '4px', background: '#f1f5f9', color: '#475569' }}>
              ภายนอก
            </span>
          )}
          {item.open_new_tab ? (
            <span style={{ fontSize: '0.75rem', padding: '0.15rem 0.4rem', borderRadius: '4px', background: '#f1f5f9', color: '#475569' }}>
              แท็บใหม่
            </span>
          ) : null}
        </div>
      ),
    },
    {
      key: 'sort_order',
      header: 'ลำดับ',
      sortable: true,
      render: (item) => (
        <span style={{ fontWeight: 600, color: 'var(--navy-700, #334155)' }}>
          {item.sort_order ?? 0}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'สถานะ',
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
  ];

  // Row Actions
  const renderRowActions = (item) => (
    <div style={{ display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
      <button
        type="button"
        className="action-btn"
        title="ดูรายละเอียด"
        onClick={() => handleOpenDetail(item)}
      >
        <Eye size={16} />
      </button>

      <PermissionGuard permission="content.edit" fallback={null}>
        <button
          type="button"
          className="action-btn"
          title="แก้ไขข้อมูล"
          onClick={() => handleOpenEdit(item)}
        >
          <Edit2 size={16} />
        </button>

        <button
          type="button"
          className="action-btn"
          title={item.status === 'active' ? 'คลิกเพื่อปิดใช้งาน' : 'คลิกเพื่อเปิดใช้งาน'}
          onClick={() => handleToggleStatus(item)}
        >
          {item.status === 'active' ? (
            <XCircle size={16} color="var(--warning-600, #d97706)" />
          ) : (
            <CheckCircle size={16} color="var(--success-600, #16a34a)" />
          )}
        </button>
      </PermissionGuard>

      <PermissionGuard permission="content.delete" fallback={null}>
        <button
          type="button"
          className="action-btn delete"
          title="ลบลิงก์"
          onClick={() => handleDelete(item)}
        >
          <Trash2 size={16} />
        </button>
      </PermissionGuard>
    </div>
  );

  return (
    <div className="admin-page-container">
      <PageHeader
        title="จัดการ E-Services & ลิงก์บริการภายนอก"
        subtitle="ศูนย์รวมลิงก์บริการออนไลน์ ประตูสู่บริการราชการ สหกรณ์ และพันธมิตร"
        breadcrumbs={[
          { label: 'Admin Workspace', to: '/admin/workspace' },
          { label: 'ระบบจัดการเนื้อหา (CMS)', to: '/admin/public-content' },
          { label: 'E-Services' },
        ]}
        actions={
          <PermissionGuard permission="content.create">
            <button
              type="button"
              className="admin-btn-primary"
              onClick={handleOpenCreate}
            >
              <Plus size={18} />
              เพิ่มลิงก์บริการใหม่
            </button>
          </PermissionGuard>
        }
      />

      {error && (
        <div style={{ padding: '1rem', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#991b1b', marginBottom: '1.5rem' }}>
          {error}
        </div>
      )}

      <CrudDataTable
        columns={columns}
        data={links}
        loading={loading}
        renderActions={renderRowActions}
        searchPlaceholder="ค้นหาชื่อบริการ, URL, หรือหมวดหมู่..."
        filterOptions={[
          {
            key: 'category',
            label: 'ทุกหมวดหมู่',
            options: Object.entries(CATEGORY_LABELS).map(([value, label]) => ({ value, label })),
          },
          {
            key: 'status',
            label: 'ทุกสถานะ',
            options: [
              { value: 'active', label: 'ใช้งาน (Active)' },
              { value: 'inactive', label: 'ระงับการใช้งาน (Inactive)' },
            ],
          },
        ]}
        emptyTitle="ยังไม่มีรายการลิงก์ E-Service"
        emptyDescription="เริ่มสร้างลิงก์บริการออนไลน์เพื่อให้สมาชิกเข้าถึงได้สะดวกรวดเร็ว"
        onCreateNew={handleOpenCreate}
      />

      {/* Form Drawer (Create / Edit) */}
      <CrudFormDrawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        onSubmit={handleSubmitForm}
        title={isEdit ? 'แก้ไขลิงก์ E-Service' : 'เพิ่มลิงก์ E-Service ใหม่'}
        subtitle="กำหนดข้อมูลปลายทาง ลักษณะการเปิดหน้าเว็บ และการยืนยันก่อนส่งต่อ"
        isDirty={formDirty}
        busy={formBusy}
        errors={formErrors}
      >
        <div className="admin-form-group">
          <label className="admin-form-label required">ชื่อบริการ / ลิงก์</label>
          <input
            type="text"
            name="name"
            className={`admin-form-input ${formErrors.name ? 'has-error' : ''}`}
            placeholder="เช่น ระบบตรวจสอบสิทธิ์ สปสช., กรมส่งเสริมสหกรณ์"
            value={formData.name}
            onChange={handleChange}
            required
          />
          {formErrors.name && <span className="admin-form-error">{formErrors.name}</span>}
        </div>

        <div className="admin-form-group">
          <label className="admin-form-label required">URL ปลายทาง</label>
          <input
            type="text"
            name="url"
            className={`admin-form-input ${formErrors.url ? 'has-error' : ''}`}
            placeholder="https://example.com/service หรือ /member/portal"
            value={formData.url}
            onChange={handleChange}
            required
          />
          {formErrors.url && <span className="admin-form-error">{formErrors.url}</span>}
        </div>

        <div className="admin-form-group">
          <label className="admin-form-label">คำอธิบายบริการโดยย่อ</label>
          <textarea
            name="description"
            className="admin-form-textarea"
            rows={3}
            placeholder="อธิบายรายละเอียดการใช้งาน หรือเอกสารที่ต้องเตรียม..."
            value={formData.description}
            onChange={handleChange}
          />
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
          <div className="admin-form-group">
            <label className="admin-form-label">หมวดหมู่บริการ</label>
            <select
              name="category"
              className="admin-form-select"
              value={formData.category}
              onChange={handleChange}
            >
              {Object.entries(CATEGORY_LABELS).map(([val, label]) => (
                <option key={val} value={val}>{label}</option>
              ))}
            </select>
          </div>

          <div className="admin-form-group">
            <label className="admin-form-label">ไอคอนสัญลักษณ์</label>
            <select
              name="icon"
              className="admin-form-select"
              value={formData.icon}
              onChange={handleChange}
            >
              <option value="globe">ลูกโลก (Globe)</option>
              <option value="file-text">เอกสาร (Document)</option>
              <option value="credit-card">การเงิน/บัตร (Finance)</option>
              <option value="shield">ความปลอดภัย (Security)</option>
              <option value="external-link">ลิงก์ภายนอก (External Link)</option>
            </select>
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
          <div className="admin-form-group">
            <label className="admin-form-label">ลำดับการแสดงผล (Sort Order)</label>
            <input
              type="number"
              name="sort_order"
              className="admin-form-input"
              value={formData.sort_order}
              onChange={handleChange}
              min={0}
            />
            <span style={{ fontSize: '0.8rem', color: 'var(--navy-600, #475569)' }}>ค่าน้อยแสดงผลก่อน</span>
          </div>

          <div className="admin-form-group">
            <label className="admin-form-label">สถานะการแสดงผล</label>
            <select
              name="status"
              className="admin-form-select"
              value={formData.status}
              onChange={handleChange}
            >
              <option value="active">เปิดใช้งาน (Active)</option>
              <option value="inactive">ปิดใช้งานชั่วคราว (Inactive)</option>
            </select>
          </div>
        </div>

        <div style={{ borderTop: '1px solid var(--navy-100, #e2e8f0)', paddingTop: '1.25rem', marginTop: '1rem', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', fontSize: '0.9rem', color: 'var(--navy-800, #1e293b)' }}>
            <input
              type="checkbox"
              name="open_new_tab"
              checked={formData.open_new_tab === 1}
              onChange={handleChange}
            />
            เปิดในแท็บใหม่ (Open in new window / tab)
          </label>

          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', fontSize: '0.9rem', color: 'var(--navy-800, #1e293b)' }}>
            <input
              type="checkbox"
              name="is_internal"
              checked={formData.is_internal === 1}
              onChange={handleChange}
            />
            เป็นระบบภายในสหกรณ์ (Internal System / Intranet)
          </label>

          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', fontSize: '0.9rem', color: 'var(--navy-800, #1e293b)' }}>
            <input
              type="checkbox"
              name="confirm_before_redirect"
              checked={formData.confirm_before_redirect === 1}
              onChange={handleChange}
            />
            แสดงกล่องข้อความยืนยันก่อนพาไปยังเว็บไซต์ภายนอก (Redirect Confirmation)
          </label>

          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', fontSize: '0.9rem', color: 'var(--danger-700, #b91c1c)' }}>
            <input
              type="checkbox"
              name="is_maintenance"
              checked={formData.is_maintenance === 1}
              onChange={handleChange}
            />
            ทำเครื่องหมายว่ากำลังปิดปรับปรุง (Maintenance Mode)
          </label>
        </div>
      </CrudFormDrawer>

      {/* Detail Modal */}
      <CrudDetailModal
        isOpen={detailModalOpen}
        onClose={() => setDetailModalOpen(false)}
        title={selectedLink?.name || 'รายละเอียดลิงก์ E-Service'}
        badge={<StatusBadge status={selectedLink?.status} />}
        actions={
          <PermissionGuard permission="content.edit" fallback={null}>
            <button
              type="button"
              className="admin-btn-primary"
              onClick={() => {
                setDetailModalOpen(false);
                handleOpenEdit(selectedLink);
              }}
            >
              <Edit2 size={16} />
              แก้ไขลิงก์นี้
            </button>
          </PermissionGuard>
        }
      >
        {selectedLink && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            <div style={{ padding: '1rem', background: 'var(--navy-50, #f8fafc)', borderRadius: '8px', border: '1px solid var(--navy-100, #e2e8f0)' }}>
              <div style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)', marginBottom: '0.25rem' }}>URL ปลายทาง</div>
              <a
                href={selectedLink.url}
                target="_blank"
                rel="noopener noreferrer"
                style={{ fontSize: '1rem', fontWeight: 600, color: 'var(--primary-600, #2563eb)', wordBreak: 'break-all', display: 'flex', alignItems: 'center', gap: '0.35rem' }}
              >
                {selectedLink.url}
                <ExternalLink size={16} />
              </a>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>หมวดหมู่:</span>
                <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                  {CATEGORY_LABELS[selectedLink.category] || selectedLink.category}
                </p>
              </div>

              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ลำดับการแสดงผล:</span>
                <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                  {selectedLink.sort_order ?? 0}
                </p>
              </div>

              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ระบบภายใน / ภายนอก:</span>
                <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                  {selectedLink.is_internal ? 'ระบบภายในสหกรณ์' : 'บริการภายนอก'}
                </p>
              </div>

              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>สถานะปรับปรุง:</span>
                <p style={{ fontWeight: 600, color: selectedLink.is_maintenance ? 'var(--warning-600, #d97706)' : 'var(--success-600, #16a34a)' }}>
                  {selectedLink.is_maintenance ? 'ปิดปรับปรุงชั่วคราว' : 'พร้อมให้บริการปกติ'}
                </p>
              </div>
            </div>

            {selectedLink.description && (
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>คำอธิบาย:</span>
                <p style={{ marginTop: '0.25rem', color: 'var(--navy-700, #334155)', lineHeight: 1.6, whiteSpace: 'pre-line' }}>
                  {selectedLink.description}
                </p>
              </div>
            )}
          </div>
        )}
      </CrudDetailModal>
    </div>
  );
}
