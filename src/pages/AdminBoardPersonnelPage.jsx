import React, { useEffect, useState } from 'react';
import { Plus, Eye, Edit2, Trash2, CheckCircle, XCircle, Users, Award, Briefcase, Mail, Phone, Image as ImageIcon } from 'lucide-react';
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

const INITIAL_BOARD_FORM = {
  id: null,
  name: '',
  position: '',
  role_type: 'director',
  term_years: '2568-2569',
  photo: '',
  bio: '',
  sort_order: 10,
  status: 'active',
};

const INITIAL_STAFF_FORM = {
  id: null,
  name: '',
  position: '',
  department: 'ฝ่ายบริหารทั่วไป',
  email: '',
  phone: '',
  photo: '',
  sort_order: 10,
  status: 'active',
};

const BOARD_ROLE_LABELS = {
  president: 'ประธานกรรมการ (President)',
  vice_president: 'รองประธานกรรมการ (Vice President)',
  treasurer: 'เหรัญญิก (Treasurer)',
  secretary: 'เลขานุการ (Secretary)',
  director: 'กรรมการ (Director)',
  advisor: 'ที่ปรึกษา (Advisor)',
  auditor: 'ผู้ตรวจสอบกิจการ (Auditor)',
};

export default function AdminBoardPersonnelPage() {
  const [activeTab, setActiveTab] = useState('board'); // 'board' | 'staff'
  const [boards, setBoards] = useState([]);
  const [staff, setStaff] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);

  // Form Drawer state
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [formData, setFormData] = useState(INITIAL_BOARD_FORM);
  const [formErrors, setFormErrors] = useState({});
  const [formDirty, setFormDirty] = useState(false);
  const [formBusy, setFormBusy] = useState(false);

  // Detail Modal state
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  // Load from API
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');

    memberAdminApi('/api/admin/board-staff', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setBoards(res.data?.boards || []);
          setStaff(res.data?.staff || []);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          setError(err.message || 'ไม่สามารถโหลดข้อมูลบุคลากรได้');
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
    setFormData(activeTab === 'board' ? INITIAL_BOARD_FORM : INITIAL_STAFF_FORM);
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(false);
    setDrawerOpen(true);
  };

  // Open Edit Drawer
  const handleOpenEdit = (item) => {
    if (activeTab === 'board') {
      setFormData({
        id: item.id,
        name: item.name || '',
        position: item.position || '',
        role_type: item.role_type || 'director',
        term_years: item.term_years || '2568-2569',
        photo: item.photo || '',
        bio: item.bio || '',
        sort_order: item.sort_order ?? 10,
        status: item.status || 'active',
        version: item.version,
      });
    } else {
      setFormData({
        id: item.id,
        name: item.name || '',
        position: item.position || '',
        department: item.department || '',
        email: item.email || '',
        phone: item.phone || '',
        photo: item.photo || '',
        sort_order: item.sort_order ?? 10,
        status: item.status || 'active',
        version: item.version,
      });
    }
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(true);
    setDrawerOpen(true);
  };

  // Open Detail Modal
  const handleOpenDetail = (item) => {
    setSelectedItem(item);
    setDetailModalOpen(true);
  };

  // Form input change
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
    if (!formData.name?.trim()) errors.name = 'กรุณาระบุชื่อ-นามสกุล';
    if (!formData.position?.trim()) errors.position = 'กรุณาระบุตำแหน่ง';
    return errors;
  };

  // Submit form
  const handleSubmitForm = async (e) => {
    e.preventDefault();
    const errors = validateForm();
    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      alertService.error('กรุณาตรวจสอบข้อมูลที่จำเป็น', 'ข้อมูลไม่ครบถ้วน');
      return;
    }

    setFormBusy(true);
    const targetTypeLabel = activeTab === 'board' ? 'คณะกรรมการ' : 'เจ้าหน้าที่';
    alertService.showLoading(isEdit ? `กำลังบันทึกข้อมูล${targetTypeLabel}...` : `กำลังเพิ่ม${targetTypeLabel}ใหม่...`);

    try {
      let endpoint = '';
      let method = isEdit ? 'PUT' : 'POST';

      if (activeTab === 'board') {
        endpoint = isEdit ? `/api/admin/board-staff/board/${formData.id}` : '/api/admin/board-staff/board';
      } else {
        endpoint = isEdit ? `/api/admin/board-staff/staff/${formData.id}` : '/api/admin/board-staff/staff';
      }

      await memberAdminApi(endpoint, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });

      alertService.closeLoading();
      await alertService.success(
        isEdit ? `บันทึกข้อมูล${targetTypeLabel}เรียบร้อยแล้ว` : `เพิ่ม${targetTypeLabel}เรียบร้อยแล้ว`,
        'ดำเนินการสำเร็จ'
      );

      setDrawerOpen(false);
      setFormDirty(false);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
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
      alertService.showLoading('กำลังเปลี่ยนสถานะ...');
      const endpoint = activeTab === 'board'
        ? `/api/admin/board-staff/board/${item.id}`
        : `/api/admin/board-staff/staff/${item.id}`;

      await memberAdminApi(endpoint, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...item,
          status: newStatus,
        }),
      });

      alertService.closeLoading();
      alertService.toast({ icon: 'success', title: `เปลี่ยนสถานะเป็น ${newStatus === 'active' ? 'เปิดใช้งาน' : 'ปิดการใช้งาน'} เรียบร้อยแล้ว` });
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ไม่สามารถเปลี่ยนสถานะได้');
    }
  };

  // Delete
  const handleDelete = async (item) => {
    const label = activeTab === 'board' ? 'คณะกรรมการท่านนี้' : 'เจ้าหน้าที่ท่านนี้';
    const confirmed = await alertService.confirmDelete(item.name, label);
    if (!confirmed) return;

    try {
      alertService.showLoading('กำลังลบข้อมูล...');
      const endpoint = activeTab === 'board'
        ? `/api/admin/board-staff/board/${item.id}`
        : `/api/admin/board-staff/staff/${item.id}`;

      await memberAdminApi(endpoint, { method: 'DELETE' });
      alertService.closeLoading();
      await alertService.success('ลบข้อมูลเรียบร้อยแล้ว', 'ลบสำเร็จ');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ไม่สามารถลบข้อมูลได้');
    }
  };

  // Columns for Board
  const boardColumns = [
    {
      key: 'name',
      header: 'ชื่อ-นามสกุล / ตำแหน่ง',
      sortable: true,
      render: (item) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          {item.photo ? (
            <img
              src={item.photo}
              alt={item.name}
              style={{ width: '42px', height: '42px', borderRadius: '50%', objectFit: 'cover', border: '1.5px solid var(--primary-200, #bfdbfe)' }}
            />
          ) : (
            <div style={{ width: '42px', height: '42px', borderRadius: '50%', background: 'var(--navy-100, #e2e8f0)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--navy-600, #475569)' }}>
              <Users size={20} />
            </div>
          )}
          <div>
            <div style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>{item.name}</div>
            <div style={{ fontSize: '0.85rem', color: 'var(--primary-600, #2563eb)' }}>{item.position}</div>
          </div>
        </div>
      ),
    },
    {
      key: 'role_type',
      header: 'บทบาท',
      sortable: true,
      render: (item) => (
        <span style={{ fontSize: '0.85rem', color: 'var(--navy-700, #334155)' }}>
          {BOARD_ROLE_LABELS[item.role_type] || item.role_type}
        </span>
      ),
    },
    {
      key: 'term_years',
      header: 'วาระดำรงตำแหน่ง',
      sortable: true,
      render: (item) => (
        <span style={{ fontSize: '0.85rem', color: 'var(--navy-600, #475569)' }}>
          {item.term_years || '-'}
        </span>
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

  // Columns for Staff
  const staffColumns = [
    {
      key: 'name',
      header: 'ชื่อ-นามสกุล / ตำแหน่ง',
      sortable: true,
      render: (item) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          {item.photo ? (
            <img
              src={item.photo}
              alt={item.name}
              style={{ width: '42px', height: '42px', borderRadius: '50%', objectFit: 'cover', border: '1.5px solid var(--primary-200, #bfdbfe)' }}
            />
          ) : (
            <div style={{ width: '42px', height: '42px', borderRadius: '50%', background: 'var(--navy-100, #e2e8f0)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--navy-600, #475569)' }}>
              <Briefcase size={20} />
            </div>
          )}
          <div>
            <div style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>{item.name}</div>
            <div style={{ fontSize: '0.85rem', color: 'var(--primary-600, #2563eb)' }}>{item.position}</div>
          </div>
        </div>
      ),
    },
    {
      key: 'department',
      header: 'ฝ่าย / กลุ่มงาน',
      sortable: true,
      render: (item) => (
        <span style={{ fontSize: '0.85rem', color: 'var(--navy-700, #334155)' }}>
          {item.department || '-'}
        </span>
      ),
    },
    {
      key: 'contact',
      header: 'ข้อมูลติดต่อ',
      render: (item) => (
        <div style={{ fontSize: '0.8rem', color: 'var(--navy-600, #475569)', display: 'flex', flexDirection: 'column', gap: '0.2rem' }}>
          {item.phone && (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
              <Phone size={12} /> {item.phone}
            </span>
          )}
          {item.email && (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
              <Mail size={12} /> {item.email}
            </span>
          )}
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

      <PermissionGuard permission="people.edit" fallback={null}>
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

      <PermissionGuard permission="people.delete" fallback={null}>
        <button
          type="button"
          className="action-btn delete"
          title="ลบข้อมูล"
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
        title="จัดการคณะกรรมการและเจ้าหน้าที่สหกรณ์"
        subtitle="บันทึกข้อมูลโครงสร้างองค์กร คณะกรรมการดำเนินการ และฝ่ายจัดการสหกรณ์"
        breadcrumbs={[
          { label: 'Admin Workspace', to: '/admin/workspace' },
          { label: 'ข้อมูลองค์กรและบุคลากร', to: '/admin/public-content' },
          { label: 'คณะกรรมการและเจ้าหน้าที่' },
        ]}
        actions={
          <PermissionGuard permission="people.create">
            <button
              type="button"
              className="admin-btn-primary"
              onClick={handleOpenCreate}
            >
              <Plus size={18} />
              {activeTab === 'board' ? 'เพิ่มคณะกรรมการ' : 'เพิ่มเจ้าหน้าที่'}
            </button>
          </PermissionGuard>
        }
      />

      {/* Tabs */}
      <div style={{ display: 'flex', gap: '0.75rem', borderBottom: '2px solid var(--navy-100, #e2e8f0)', marginBottom: '1.5rem' }}>
        <button
          type="button"
          style={{
            padding: '0.75rem 1.25rem',
            background: 'none',
            border: 'none',
            borderBottom: activeTab === 'board' ? '3px solid var(--primary-600, #2563eb)' : '3px solid transparent',
            color: activeTab === 'board' ? 'var(--primary-700, #1d4ed8)' : 'var(--navy-600, #475569)',
            fontWeight: activeTab === 'board' ? 700 : 500,
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            marginBottom: '-2px',
            fontSize: '0.95rem',
          }}
          onClick={() => setActiveTab('board')}
        >
          <Award size={18} />
          คณะกรรมการดำเนินการ ({boards.length})
        </button>

        <button
          type="button"
          style={{
            padding: '0.75rem 1.25rem',
            background: 'none',
            border: 'none',
            borderBottom: activeTab === 'staff' ? '3px solid var(--primary-600, #2563eb)' : '3px solid transparent',
            color: activeTab === 'staff' ? 'var(--primary-700, #1d4ed8)' : 'var(--navy-600, #475569)',
            fontWeight: activeTab === 'staff' ? 700 : 500,
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            marginBottom: '-2px',
            fontSize: '0.95rem',
          }}
          onClick={() => setActiveTab('staff')}
        >
          <Briefcase size={18} />
          เจ้าหน้าที่และฝ่ายจัดการ ({staff.length})
        </button>
      </div>

      {error && (
        <div style={{ padding: '1rem', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#991b1b', marginBottom: '1.5rem' }}>
          {error}
        </div>
      )}

      {activeTab === 'board' ? (
        <CrudDataTable
          columns={boardColumns}
          data={boards}
          loading={loading}
          renderActions={renderRowActions}
          searchPlaceholder="ค้นหาชื่อคณะกรรมการ, ตำแหน่ง, หรือบทบาท..."
          filterOptions={[
            {
              key: 'role_type',
              label: 'ทุกบทบาท',
              options: Object.entries(BOARD_ROLE_LABELS).map(([value, label]) => ({ value, label })),
            },
            {
              key: 'status',
              label: 'ทุกสถานะ',
              options: [
                { value: 'active', label: 'กำลังดำรงตำแหน่ง (Active)' },
                { value: 'inactive', label: 'พ้นจากตำแหน่ง (Inactive)' },
              ],
            },
          ]}
          emptyTitle="ยังไม่มีข้อมูลคณะกรรมการ"
          emptyDescription="กดปุ่ม 'เพิ่มคณะกรรมการ' ด้านบนเพื่อบันทึกทำเนียบกรรมการชุดปัจจุบัน"
          onCreateNew={handleOpenCreate}
        />
      ) : (
        <CrudDataTable
          columns={staffColumns}
          data={staff}
          loading={loading}
          renderActions={renderRowActions}
          searchPlaceholder="ค้นหาชื่อเจ้าหน้าที่, ตำแหน่ง, หรือฝ่ายงาน..."
          filterOptions={[
            {
              key: 'status',
              label: 'ทุกสถานะ',
              options: [
                { value: 'active', label: 'ปฏิบัติงานปกติ (Active)' },
                { value: 'inactive', label: 'พ้นสภาพ / ลาออก (Inactive)' },
              ],
            },
          ]}
          emptyTitle="ยังไม่มีข้อมูลเจ้าหน้าที่"
          emptyDescription="กดปุ่ม 'เพิ่มเจ้าหน้าที่' ด้านบนเพื่อบันทึกข้อมูลบุคลากรสหกรณ์"
          onCreateNew={handleOpenCreate}
        />
      )}

      {/* Form Drawer (Board / Staff) */}
      <CrudFormDrawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        onSubmit={handleSubmitForm}
        title={
          isEdit
            ? (activeTab === 'board' ? 'แก้ไขข้อมูลคณะกรรมการ' : 'แก้ไขข้อมูลเจ้าหน้าที่')
            : (activeTab === 'board' ? 'เพิ่มคณะกรรมการชุดใหม่' : 'เพิ่มข้อมูลเจ้าหน้าที่ใหม่')
        }
        subtitle="บันทึกข้อมูลประวัติ ภาพถ่าย และตำแหน่งในโครงสร้างองค์กร"
        isDirty={formDirty}
        busy={formBusy}
        errors={formErrors}
      >
        <div className="admin-form-group">
          <label className="admin-form-label required">ชื่อ-นามสกุล</label>
          <input
            type="text"
            name="name"
            className={`admin-form-input ${formErrors.name ? 'has-error' : ''}`}
            placeholder="เช่น นายสมคิด สหกรณ์ก้าวหน้า"
            value={formData.name}
            onChange={handleChange}
            required
          />
          {formErrors.name && <span className="admin-form-error">{formErrors.name}</span>}
        </div>

        <div className="admin-form-group">
          <label className="admin-form-label required">ตำแหน่ง</label>
          <input
            type="text"
            name="position"
            className={`admin-form-input ${formErrors.position ? 'has-error' : ''}`}
            placeholder={activeTab === 'board' ? 'เช่น ประธานกรรมการ, กรรมการและเหรัญญิก' : 'เช่น ผู้จัดการใหญ่, หัวหน้าฝ่ายสินเชื่อ'}
            value={formData.position}
            onChange={handleChange}
            required
          />
          {formErrors.position && <span className="admin-form-error">{formErrors.position}</span>}
        </div>

        {activeTab === 'board' ? (
          <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              <div className="admin-form-group">
                <label className="admin-form-label">บทบาทหลัก</label>
                <select
                  name="role_type"
                  className="admin-form-select"
                  value={formData.role_type}
                  onChange={handleChange}
                >
                  {Object.entries(BOARD_ROLE_LABELS).map(([val, label]) => (
                    <option key={val} value={val}>{label}</option>
                  ))}
                </select>
              </div>

              <div className="admin-form-group">
                <label className="admin-form-label">วาระดำรงตำแหน่ง</label>
                <input
                  type="text"
                  name="term_years"
                  className="admin-form-input"
                  placeholder="เช่น 2568-2569"
                  value={formData.term_years}
                  onChange={handleChange}
                />
              </div>
            </div>

            <div className="admin-form-group">
              <label className="admin-form-label">ประวัติโดยย่อ / คำแถลง</label>
              <textarea
                name="bio"
                className="admin-form-textarea"
                rows={3}
                placeholder="วุฒิการศึกษา ประสบการณ์ หรือประวัติการทำงาน..."
                value={formData.bio}
                onChange={handleChange}
              />
            </div>
          </>
        ) : (
          <>
            <div className="admin-form-group">
              <label className="admin-form-label">ฝ่าย / กลุ่มงาน</label>
              <input
                type="text"
                name="department"
                className="admin-form-input"
                placeholder="เช่น ฝ่ายสินเชื่อ, ฝ่ายบัญชีและการเงิน, ฝ่ายธุรการ"
                value={formData.department}
                onChange={handleChange}
              />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              <div className="admin-form-group">
                <label className="admin-form-label">เบอร์โทรศัพท์ติดต่อ</label>
                <input
                  type="text"
                  name="phone"
                  className="admin-form-input"
                  placeholder="038-xxxxxx ต่อ xx"
                  value={formData.phone}
                  onChange={handleChange}
                />
              </div>

              <div className="admin-form-group">
                <label className="admin-form-label">อีเมลติดต่อ</label>
                <input
                  type="email"
                  name="email"
                  className="admin-form-input"
                  placeholder="officer@rayongcoop.org"
                  value={formData.email}
                  onChange={handleChange}
                />
              </div>
            </div>
          </>
        )}

        <div className="admin-form-group">
          <label className="admin-form-label">URL รูปถ่ายบุคลากร</label>
          <input
            type="text"
            name="photo"
            className="admin-form-input"
            placeholder="/uploads/board/photo1.jpg หรือ https://..."
            value={formData.photo}
            onChange={handleChange}
          />
          {formData.photo && (
            <div style={{ marginTop: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <img
                src={formData.photo}
                alt="Preview"
                style={{ width: '48px', height: '48px', borderRadius: '50%', objectFit: 'cover', border: '1px solid #cbd5e1' }}
                onError={(e) => { e.currentTarget.style.display = 'none'; }}
              />
              <span style={{ fontSize: '0.8rem', color: 'var(--navy-600, #475569)' }}>ตัวอย่างรูปถ่าย</span>
            </div>
          )}
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
        title={selectedItem?.name || 'รายละเอียดบุคลากร'}
        badge={<StatusBadge status={selectedItem?.status} />}
        actions={
          <PermissionGuard permission="people.edit" fallback={null}>
            <button
              type="button"
              className="admin-btn-primary"
              onClick={() => {
                setDetailModalOpen(false);
                handleOpenEdit(selectedItem);
              }}
            >
              <Edit2 size={16} />
              แก้ไขข้อมูล
            </button>
          </PermissionGuard>
        }
      >
        {selectedItem && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', padding: '1rem', background: 'var(--navy-50, #f8fafc)', borderRadius: '8px' }}>
              {selectedItem.photo ? (
                <img
                  src={selectedItem.photo}
                  alt={selectedItem.name}
                  style={{ width: '64px', height: '64px', borderRadius: '50%', objectFit: 'cover', border: '2px solid var(--primary-300, #93c5fd)' }}
                />
              ) : (
                <div style={{ width: '64px', height: '64px', borderRadius: '50%', background: 'var(--navy-200, #cbd5e1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <Users size={32} color="var(--navy-600, #475569)" />
                </div>
              )}
              <div>
                <h4 style={{ margin: 0, color: 'var(--navy-900, #0f172a)', fontSize: '1.1rem' }}>{selectedItem.name}</h4>
                <div style={{ color: 'var(--primary-600, #2563eb)', fontWeight: 600 }}>{selectedItem.position}</div>
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              {activeTab === 'board' ? (
                <>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>บทบาท:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {BOARD_ROLE_LABELS[selectedItem.role_type] || selectedItem.role_type}
                    </p>
                  </div>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>วาระดำรงตำแหน่ง:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {selectedItem.term_years || '-'}
                    </p>
                  </div>
                </>
              ) : (
                <>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ฝ่าย / กลุ่มงาน:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {selectedItem.department || '-'}
                    </p>
                  </div>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>เบอร์โทรศัพท์:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {selectedItem.phone || '-'}
                    </p>
                  </div>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>อีเมล:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {selectedItem.email || '-'}
                    </p>
                  </div>
                </>
              )}

              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ลำดับการแสดงผล:</span>
                <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                  {selectedItem.sort_order ?? 0}
                </p>
              </div>
            </div>

            {selectedItem.bio && (
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ประวัติโดยย่อ:</span>
                <p style={{ marginTop: '0.25rem', color: 'var(--navy-700, #334155)', lineHeight: 1.6, whiteSpace: 'pre-line' }}>
                  {selectedItem.bio}
                </p>
              </div>
            )}
          </div>
        )}
      </CrudDetailModal>
    </div>
  );
}
