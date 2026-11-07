import React, { useEffect, useState } from 'react';
import { Plus, Eye, Edit2, Trash2, CheckCircle, XCircle, Folder, FileText, ArrowLeft, ExternalLink } from 'lucide-react';
import { Link } from 'react-router-dom';
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
  icon: 'folder',
  sort_order: 10,
  status: 'active',
};

export default function AdminDocumentCategoriesPage() {
  const [categories, setCategories] = useState([]);
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
  const [selectedCategory, setSelectedCategory] = useState(null);

  // Load from API
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');

    memberAdminApi('/api/admin/document-categories', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setCategories(res.data || []);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          setError(err.message || 'ไม่สามารถโหลดข้อมูลหมวดหมู่เอกสารได้');
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
      icon: item.icon || 'folder',
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
    setSelectedCategory(item);
    setDetailModalOpen(true);
  };

  // Form input changes
  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    setFormDirty(true);
    if (formErrors[name]) {
      setFormErrors((prev) => ({ ...prev, [name]: null }));
    }
  };

  // Validation
  const validateForm = () => {
    const errors = {};
    if (!formData.name?.trim()) errors.name = 'กรุณาระบุชื่อหมวดหมู่เอกสาร';
    return errors;
  };

  // Submit form
  const handleSubmitForm = async (e) => {
    e.preventDefault();
    const errors = validateForm();
    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      alertService.error('กรุณาระบุชื่อหมวดหมู่', 'ข้อมูลไม่ครบถ้วน');
      return;
    }

    setFormBusy(true);
    alertService.loading(isEdit ? 'กำลังบันทึกการแก้ไขหมวดหมู่...' : 'กำลังเพิ่มหมวดหมู่ใหม่...');

    try {
      const endpoint = isEdit
        ? `/api/admin/document-categories/${formData.id}`
        : '/api/admin/document-categories';
      const method = isEdit ? 'PUT' : 'POST';

      await memberAdminApi(endpoint, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });

      alertService.close();
      await alertService.success(
        isEdit ? 'บันทึกการแก้ไขหมวดหมู่เรียบร้อยแล้ว' : 'เพิ่มหมวดหมู่ใหม่เรียบร้อยแล้ว',
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
    const confirmed = await alertService.confirmStatusToggle(item.name, item.status, newStatus);
    if (!confirmed) return;

    try {
      alertService.loading('กำลังเปลี่ยนสถานะหมวดหมู่...');
      await memberAdminApi(`/api/admin/document-categories/${item.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...item,
          status: newStatus,
        }),
      });

      alertService.close();
      alertService.toastSuccess(`เปลี่ยนสถานะเป็น ${newStatus === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'} เรียบร้อยแล้ว`);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.close();
      alertService.error(err.message || 'ไม่สามารถเปลี่ยนสถานะได้');
    }
  };

  // Delete
  const handleDelete = async (item) => {
    const confirmed = await alertService.confirmDelete(item.name, 'หมวดหมู่เอกสารนี้');
    if (!confirmed) return;

    try {
      alertService.loading('กำลังลบหมวดหมู่...');
      await memberAdminApi(`/api/admin/document-categories/${item.id}`, {
        method: 'DELETE',
      });

      alertService.close();
      await alertService.success('ลบหมวดหมู่เอกสารเรียบร้อยแล้ว', 'ลบสำเร็จ');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.close();
      alertService.error(err.message || 'ไม่สามารถลบหมวดหมู่นี้ได้ อาจมีเอกสารผูกอยู่');
    }
  };

  // Columns definition
  const columns = [
    {
      key: 'name',
      header: 'ชื่อหมวดหมู่เอกสาร',
      sortable: true,
      render: (item) => (
        <div>
          <div style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Folder size={18} color="var(--primary-600, #2563eb)" />
            {item.name}
          </div>
          {item.description && (
            <div style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>{item.description}</div>
          )}
        </div>
      ),
    },
    {
      key: 'slug',
      header: 'Slug / รหัสอ้างอิง',
      sortable: true,
      render: (item) => (
        <code style={{ fontSize: '0.8rem', background: 'var(--navy-100, #e2e8f0)', padding: '0.2rem 0.4rem', borderRadius: '4px' }}>
          {item.slug || '-'}
        </code>
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

  // Render Row Actions
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

      <PermissionGuard permission="documents.edit" fallback={null}>
        <button
          type="button"
          className="action-btn"
          title="แก้ไขหมวดหมู่"
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

      <PermissionGuard permission="documents.delete" fallback={null}>
        <button
          type="button"
          className="action-btn delete"
          title="ลบหมวดหมู่"
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
        title="จัดการหมวดหมู่เอกสารและแบบฟอร์ม"
        subtitle="จัดกลุ่มเอกสารดาวน์โหลด แบบฟอร์มคำขอ ระเบียบ และรายงานประจำปี"
        breadcrumbs={[
          { label: 'Admin Workspace', to: '/admin/workspace' },
          { label: 'จัดการคลังเอกสาร', to: '/admin/documents' },
          { label: 'หมวดหมู่เอกสาร' },
        ]}
        actions={
          <div style={{ display: 'flex', gap: '0.75rem' }}>
            <Link
              to="/admin/documents"
              className="admin-btn-secondary"
              style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem', textDecoration: 'none' }}
            >
              <ArrowLeft size={16} />
              กลับไปคลังเอกสาร
            </Link>

            <PermissionGuard permission="documents.create">
              <button
                type="button"
                className="admin-btn-primary"
                onClick={handleOpenCreate}
              >
                <Plus size={18} />
                เพิ่มหมวดหมู่ใหม่
              </button>
            </PermissionGuard>
          </div>
        }
      />

      {error && (
        <div style={{ padding: '1rem', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#991b1b', marginBottom: '1.5rem' }}>
          {error}
        </div>
      )}

      <CrudDataTable
        columns={columns}
        data={categories}
        loading={loading}
        renderActions={renderRowActions}
        searchPlaceholder="ค้นหาชื่อหมวดหมู่, คำอธิบาย..."
        filterOptions={[
          {
            key: 'status',
            label: 'ทุกสถานะ',
            options: [
              { value: 'active', label: 'ใช้งาน (Active)' },
              { value: 'inactive', label: 'ปิดใช้งาน (Inactive)' },
            ],
          },
        ]}
        emptyTitle="ยังไม่มีหมวดหมู่เอกสาร"
        emptyDescription="กดปุ่ม 'เพิ่มหมวดหมู่ใหม่' เพื่อสร้างโครงสร้างหมวดหมู่เอกสารสำหรับสมาชิกและประชาชน"
        onCreateNew={handleOpenCreate}
      />

      {/* Form Drawer */}
      <CrudFormDrawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        onSubmit={handleSubmitForm}
        title={isEdit ? 'แก้ไขหมวดหมู่เอกสาร' : 'เพิ่มหมวดหมู่เอกสารใหม่'}
        subtitle="กำหนดชื่อหมวดหมู่และลำดับการแสดงผลในหน้าดาวน์โหลดเอกสาร"
        isDirty={formDirty}
        busy={formBusy}
        errors={formErrors}
      >
        <div className="admin-form-group">
          <label className="admin-form-label required">ชื่อหมวดหมู่เอกสาร</label>
          <input
            type="text"
            name="name"
            className={`admin-form-input ${formErrors.name ? 'has-error' : ''}`}
            placeholder="เช่น แบบฟอร์มเงินกู้, ระเบียบข้อบังคับ, รายงานประจำปี"
            value={formData.name}
            onChange={handleChange}
            required
          />
          {formErrors.name && <span className="admin-form-error">{formErrors.name}</span>}
        </div>

        <div className="admin-form-group">
          <label className="admin-form-label">คำอธิบายหมวดหมู่</label>
          <textarea
            name="description"
            className="admin-form-textarea"
            rows={3}
            placeholder="คำอธิบายสั้นๆ สำหรับผู้ดาวน์โหลด..."
            value={formData.description}
            onChange={handleChange}
          />
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
          <div className="admin-form-group">
            <label className="admin-form-label">ลำดับการแสดงผล</label>
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
            <label className="admin-form-label">สถานะ</label>
            <select
              name="status"
              className="admin-form-select"
              value={formData.status}
              onChange={handleChange}
            >
              <option value="active">เปิดใช้งาน (Active)</option>
              <option value="inactive">ปิดใช้งาน (Inactive)</option>
            </select>
          </div>
        </div>
      </CrudFormDrawer>

      {/* Detail Modal */}
      <CrudDetailModal
        isOpen={detailModalOpen}
        onClose={() => setDetailModalOpen(false)}
        title={selectedCategory?.name || 'รายละเอียดหมวดหมู่'}
        badge={<StatusBadge status={selectedCategory?.status} />}
        actions={
          <PermissionGuard permission="documents.edit" fallback={null}>
            <button
              type="button"
              className="admin-btn-primary"
              onClick={() => {
                setDetailModalOpen(false);
                handleOpenEdit(selectedCategory);
              }}
            >
              <Edit2 size={16} />
              แก้ไขหมวดหมู่นี้
            </button>
          </PermissionGuard>
        }
      >
        {selectedCategory && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            <div style={{ padding: '1rem', background: 'var(--navy-50, #f8fafc)', borderRadius: '8px', border: '1px solid var(--navy-100, #e2e8f0)' }}>
              <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ชื่อหมวดหมู่:</span>
              <h3 style={{ margin: '0.25rem 0 0 0', color: 'var(--navy-900, #0f172a)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <Folder size={22} color="var(--primary-600, #2563eb)" />
                {selectedCategory.name}
              </h3>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>Slug:</span>
                <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                  {selectedCategory.slug || '-'}
                </p>
              </div>

              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ลำดับการแสดงผล:</span>
                <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                  {selectedCategory.sort_order ?? 0}
                </p>
              </div>
            </div>

            {selectedCategory.description && (
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>คำอธิบาย:</span>
                <p style={{ marginTop: '0.25rem', color: 'var(--navy-700, #334155)', lineHeight: 1.6 }}>
                  {selectedCategory.description}
                </p>
              </div>
            )}
          </div>
        )}
      </CrudDetailModal>
    </div>
  );
}
