import React, { useEffect, useState } from 'react';
import { Plus, Eye, Edit2, Trash2, CheckCircle, XCircle, Landmark, PiggyBank, Percent, Calculator, ShieldCheck } from 'lucide-react';
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

const INITIAL_LOAN_FORM = {
  id: null,
  name: '',
  category: 'เงินกู้สามัญ',
  interest_rate: 4.5,
  min_loan_amount: 10000,
  max_loan_limit: 1500000,
  max_term_months: 120,
  calculation_type: 'effective',
  short_description: '',
  full_description: '',
  eligibility: '',
  guarantor_requirement: '',
  is_featured: 0,
  is_calculator_enabled: 1,
  sort_order: 10,
  status: 'active',
};

const INITIAL_DEPOSIT_FORM = {
  id: null,
  name: '',
  interest_rate: 2.25,
  min_deposit: 500,
  max_deposit: 5000000,
  withdrawal_condition: '',
  eligibility: '',
  required_documents: '',
  short_description: '',
  full_description: '',
  is_featured: 0,
  sort_order: 10,
  status: 'active',
};

const LOAN_CATEGORIES = [
  'เงินกู้เพื่อเหตุฉุกเฉิน',
  'เงินกู้สามัญ',
  'เงินกู้พิเศษเพื่อการเคหะ',
  'เงินกู้พิเศษเพื่อการศึกษา',
  'เงินกู้พิเศษอื่นๆ',
];

export default function AdminProductsPage() {
  const [activeTab, setActiveTab] = useState('loan'); // 'loan' | 'deposit'
  const [loans, setLoans] = useState([]);
  const [deposits, setDeposits] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);

  // Form Drawer state
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [formData, setFormData] = useState(INITIAL_LOAN_FORM);
  const [formErrors, setFormErrors] = useState({});
  const [formDirty, setFormDirty] = useState(false);
  const [formBusy, setFormBusy] = useState(false);

  // Detail Modal state
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState(null);

  // Load from API
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');

    memberAdminApi('/api/admin/loans', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setLoans(res.data?.loans || []);
          setDeposits(res.data?.deposits || []);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          setError(err.message || 'ไม่สามารถโหลดข้อมูลผลิตภัณฑ์ทางการเงินได้');
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
    setFormData(activeTab === 'loan' ? INITIAL_LOAN_FORM : INITIAL_DEPOSIT_FORM);
    setFormErrors({});
    setFormDirty(false);
    setIsEdit(false);
    setDrawerOpen(true);
  };

  // Open Edit Drawer
  const handleOpenEdit = (item) => {
    if (activeTab === 'loan') {
      setFormData({
        id: item.id,
        name: item.name || '',
        category: item.category || 'เงินกู้สามัญ',
        interest_rate: item.interest_rate ?? 0,
        min_loan_amount: item.min_loan_amount ?? 0,
        max_loan_limit: item.max_loan_limit ?? 0,
        max_term_months: item.max_term_months ?? 120,
        calculation_type: item.calculation_type || 'effective',
        short_description: item.short_description || '',
        full_description: item.full_description || '',
        eligibility: item.eligibility || '',
        guarantor_requirement: item.guarantor_requirement || '',
        is_featured: item.is_featured ? 1 : 0,
        is_calculator_enabled: item.is_calculator_enabled ? 1 : 0,
        sort_order: item.sort_order ?? 10,
        status: item.status || 'active',
        version: item.version,
      });
    } else {
      setFormData({
        id: item.id,
        name: item.name || '',
        interest_rate: item.interest_rate ?? 0,
        min_deposit: item.min_deposit ?? 0,
        max_deposit: item.max_deposit ?? 0,
        withdrawal_condition: item.withdrawal_condition || '',
        eligibility: item.eligibility || '',
        required_documents: item.required_documents || '',
        short_description: item.short_description || '',
        full_description: item.full_description || '',
        is_featured: item.is_featured ? 1 : 0,
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
    setSelectedProduct(item);
    setDetailModalOpen(true);
  };

  // Handle Input Changes
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
    if (!formData.name?.trim()) errors.name = 'กรุณาระบุชื่อผลิตภัณฑ์';
    if (formData.interest_rate === '' || isNaN(formData.interest_rate)) {
      errors.interest_rate = 'กรุณาระบุอัตราดอกเบี้ยเป็นตัวเลข';
    }
    return errors;
  };

  // Submit Form
  const handleSubmitForm = async (e) => {
    e.preventDefault();
    const errors = validateForm();
    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      alertService.error('กรุณาตรวจสอบข้อมูลที่จำเป็น', 'ข้อมูลไม่ครบถ้วน');
      return;
    }

    setFormBusy(true);
    const label = activeTab === 'loan' ? 'ผลิตภัณฑ์สินเชื่อ' : 'ผลิตภัณฑ์เงินฝาก';
    alertService.showLoading(isEdit ? `กำลังบันทึกการแก้ไข${label}...` : `กำลังเพิ่ม${label}ใหม่...`);

    try {
      let endpoint = '';
      let method = isEdit ? 'PUT' : 'POST';

      if (activeTab === 'loan') {
        endpoint = isEdit ? `/api/admin/loans/${formData.id}` : '/api/admin/loans';
      } else {
        endpoint = isEdit ? `/api/admin/deposits/${formData.id}` : '/api/admin/deposits';
      }

      await memberAdminApi(endpoint, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });

      alertService.closeLoading();
      await alertService.success(
        isEdit ? `บันทึกการแก้ไข${label}เรียบร้อยแล้ว` : `เพิ่ม${label}ใหม่เรียบร้อยแล้ว`,
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
      const endpoint = activeTab === 'loan'
        ? `/api/admin/loans/${item.id}`
        : `/api/admin/deposits/${item.id}`;

      await memberAdminApi(endpoint, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...item,
          status: newStatus,
        }),
      });

      alertService.closeLoading();
      alertService.toast({ icon: 'success', title: `เปลี่ยนสถานะเป็น ${newStatus === 'active' ? 'เปิดบริการ' : 'ปิดบริการ'} เรียบร้อยแล้ว` });
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ไม่สามารถเปลี่ยนสถานะได้');
    }
  };

  // Delete
  const handleDelete = async (item) => {
    const label = activeTab === 'loan' ? 'ผลิตภัณฑ์สินเชื่อนี้' : 'ผลิตภัณฑ์เงินฝากนี้';
    const confirmed = await alertService.confirmDelete(item.name, label);
    if (!confirmed) return;

    try {
      alertService.showLoading('กำลังลบผลิตภัณฑ์...');
      const endpoint = activeTab === 'loan'
        ? `/api/admin/loans/${item.id}`
        : `/api/admin/deposits/${item.id}`;

      await memberAdminApi(endpoint, { method: 'DELETE' });
      alertService.closeLoading();
      await alertService.success('ลบผลิตภัณฑ์เรียบร้อยแล้ว', 'ลบสำเร็จ');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ไม่สามารถลบผลิตภัณฑ์ได้');
    }
  };

  // Format currency
  const formatBaht = (num) => {
    if (num === null || num === undefined || num === '') return '-';
    return Number(num).toLocaleString('th-TH') + ' บาท';
  };

  // Columns for Loan Products
  const loanColumns = [
    {
      key: 'name',
      header: 'ชื่อผลิตภัณฑ์สินเชื่อ',
      sortable: true,
      render: (item) => (
        <div>
          <div style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
            <Landmark size={16} color="var(--primary-600, #2563eb)" />
            {item.name}
          </div>
          <div style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>{item.category}</div>
        </div>
      ),
    },
    {
      key: 'interest_rate',
      header: 'อัตราดอกเบี้ย',
      sortable: true,
      render: (item) => (
        <span style={{ fontWeight: 700, color: 'var(--success-700, #15803d)', display: 'inline-flex', alignItems: 'center', gap: '0.2rem' }}>
          <Percent size={14} /> {Number(item.interest_rate).toFixed(2)}% ต่อปี
        </span>
      ),
    },
    {
      key: 'max_loan_limit',
      header: 'วงเงินกู้สูงสุด',
      sortable: true,
      render: (item) => (
        <span style={{ fontSize: '0.85rem', color: 'var(--navy-700, #334155)' }}>
          {formatBaht(item.max_loan_limit)}
        </span>
      ),
    },
    {
      key: 'max_term_months',
      header: 'ผ่อนชำระสูงสุด',
      sortable: true,
      render: (item) => (
        <span style={{ fontSize: '0.85rem', color: 'var(--navy-600, #475569)' }}>
          {item.max_term_months ? `${item.max_term_months} งวด` : '-'}
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

  // Columns for Deposit Products
  const depositColumns = [
    {
      key: 'name',
      header: 'ชื่อผลิตภัณฑ์เงินฝาก',
      sortable: true,
      render: (item) => (
        <div>
          <div style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
            <PiggyBank size={16} color="var(--primary-600, #2563eb)" />
            {item.name}
          </div>
          {item.short_description && (
            <div style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)', maxWidth: '280px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              {item.short_description}
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'interest_rate',
      header: 'อัตราดอกเบี้ย',
      sortable: true,
      render: (item) => (
        <span style={{ fontWeight: 700, color: 'var(--primary-700, #1d4ed8)', display: 'inline-flex', alignItems: 'center', gap: '0.2rem' }}>
          <Percent size={14} /> {Number(item.interest_rate).toFixed(2)}% ต่อปี
        </span>
      ),
    },
    {
      key: 'min_deposit',
      header: 'เงินฝากขั้นต่ำ',
      sortable: true,
      render: (item) => (
        <span style={{ fontSize: '0.85rem', color: 'var(--navy-700, #334155)' }}>
          {formatBaht(item.min_deposit)}
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

      <PermissionGuard permission="products.edit" fallback={null}>
        <button
          type="button"
          className="action-btn"
          title="แก้ไขผลิตภัณฑ์"
          onClick={() => handleOpenEdit(item)}
        >
          <Edit2 size={16} />
        </button>

        <button
          type="button"
          className="action-btn"
          title={item.status === 'active' ? 'คลิกเพื่อปิดบริการ' : 'คลิกเพื่อเปิดบริการ'}
          onClick={() => handleToggleStatus(item)}
        >
          {item.status === 'active' ? (
            <XCircle size={16} color="var(--warning-600, #d97706)" />
          ) : (
            <CheckCircle size={16} color="var(--success-600, #16a34a)" />
          )}
        </button>
      </PermissionGuard>

      <PermissionGuard permission="products.delete" fallback={null}>
        <button
          type="button"
          className="action-btn delete"
          title="ลบผลิตภัณฑ์"
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
        title="จัดการผลิตภัณฑ์ทางการเงิน (เงินกู้และเงินฝาก)"
        subtitle="กำหนดอัตราดอกเบี้ย วงเงินกู้ เงื่อนไขการฝากถอน และเงื่อนไขการคำนวณ"
        breadcrumbs={[
          { label: 'Admin Workspace', to: '/admin/workspace' },
          { label: 'ผลิตภัณฑ์และอัตราดอกเบี้ย', to: '/admin/public-content' },
          { label: 'ผลิตภัณฑ์ทางการเงิน' },
        ]}
        actions={
          <PermissionGuard permission="products.create">
            <button
              type="button"
              className="admin-btn-primary"
              onClick={handleOpenCreate}
            >
              <Plus size={18} />
              {activeTab === 'loan' ? 'เพิ่มผลิตภัณฑ์เงินกู้' : 'เพิ่มผลิตภัณฑ์เงินฝาก'}
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
            borderBottom: activeTab === 'loan' ? '3px solid var(--primary-600, #2563eb)' : '3px solid transparent',
            color: activeTab === 'loan' ? 'var(--primary-700, #1d4ed8)' : 'var(--navy-600, #475569)',
            fontWeight: activeTab === 'loan' ? 700 : 500,
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            marginBottom: '-2px',
            fontSize: '0.95rem',
          }}
          onClick={() => setActiveTab('loan')}
        >
          <Landmark size={18} />
          ผลิตภัณฑ์เงินกู้ / สินเชื่อ ({loans.length})
        </button>

        <button
          type="button"
          style={{
            padding: '0.75rem 1.25rem',
            background: 'none',
            border: 'none',
            borderBottom: activeTab === 'deposit' ? '3px solid var(--primary-600, #2563eb)' : '3px solid transparent',
            color: activeTab === 'deposit' ? 'var(--primary-700, #1d4ed8)' : 'var(--navy-600, #475569)',
            fontWeight: activeTab === 'deposit' ? 700 : 500,
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            marginBottom: '-2px',
            fontSize: '0.95rem',
          }}
          onClick={() => setActiveTab('deposit')}
        >
          <PiggyBank size={18} />
          ผลิตภัณฑ์เงินฝากออมทรัพย์ ({deposits.length})
        </button>
      </div>

      {error && (
        <div style={{ padding: '1rem', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', color: '#991b1b', marginBottom: '1.5rem' }}>
          {error}
        </div>
      )}

      {activeTab === 'loan' ? (
        <CrudDataTable
          columns={loanColumns}
          data={loans}
          loading={loading}
          renderActions={renderRowActions}
          searchPlaceholder="ค้นหาชื่อเงินกู้, หมวดหมู่, หรือรายละเอียด..."
          filterOptions={[
            {
              key: 'category',
              label: 'ทุกหมวดหมู่สินเชื่อ',
              options: LOAN_CATEGORIES.map((c) => ({ value: c, label: c })),
            },
            {
              key: 'status',
              label: 'ทุกสถานะ',
              options: [
                { value: 'active', label: 'เปิดให้บริการ (Active)' },
                { value: 'inactive', label: 'ปิดรับสมัคร (Inactive)' },
              ],
            },
          ]}
          emptyTitle="ยังไม่มีข้อมูลผลิตภัณฑ์สินเชื่อ"
          emptyDescription="กดปุ่ม 'เพิ่มผลิตภัณฑ์เงินกู้' เพื่อสร้างประเภทสินเชื่อและอัตราดอกเบี้ย"
          onCreateNew={handleOpenCreate}
        />
      ) : (
        <CrudDataTable
          columns={depositColumns}
          data={deposits}
          loading={loading}
          renderActions={renderRowActions}
          searchPlaceholder="ค้นหาชื่อบัญชีเงินฝาก, เงื่อนไข..."
          filterOptions={[
            {
              key: 'status',
              label: 'ทุกสถานะ',
              options: [
                { value: 'active', label: 'เปิดให้บริการ (Active)' },
                { value: 'inactive', label: 'ปิดรับฝาก (Inactive)' },
              ],
            },
          ]}
          emptyTitle="ยังไม่มีข้อมูลผลิตภัณฑ์เงินฝาก"
          emptyDescription="กดปุ่ม 'เพิ่มผลิตภัณฑ์เงินฝาก' เพื่อสร้างประเภทบัญชีเงินฝากสหกรณ์"
          onCreateNew={handleOpenCreate}
        />
      )}

      {/* Form Drawer (Loan / Deposit) */}
      <CrudFormDrawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        onSubmit={handleSubmitForm}
        title={
          isEdit
            ? (activeTab === 'loan' ? 'แก้ไขผลิตภัณฑ์สินเชื่อ' : 'แก้ไขผลิตภัณฑ์เงินฝาก')
            : (activeTab === 'loan' ? 'เพิ่มผลิตภัณฑ์สินเชื่อใหม่' : 'เพิ่มผลิตภัณฑ์เงินฝากใหม่')
        }
        subtitle="ระบุอัตราดอกเบี้ย วงเงิน และเงื่อนไขการให้บริการ"
        isDirty={formDirty}
        busy={formBusy}
        errors={formErrors}
      >
        <div className="admin-form-group">
          <label className="admin-form-label required">ชื่อผลิตภัณฑ์</label>
          <input
            type="text"
            name="name"
            className={`admin-form-input ${formErrors.name ? 'has-error' : ''}`}
            placeholder={activeTab === 'loan' ? 'เช่น เงินกู้สามัญเพื่อการครองชีพ' : 'เช่น เงินฝากออมทรัพย์พิเศษ'}
            value={formData.name}
            onChange={handleChange}
            required
          />
          {formErrors.name && <span className="admin-form-error">{formErrors.name}</span>}
        </div>

        {activeTab === 'loan' ? (
          <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              <div className="admin-form-group">
                <label className="admin-form-label">หมวดหมู่เงินกู้</label>
                <select
                  name="category"
                  className="admin-form-select"
                  value={formData.category}
                  onChange={handleChange}
                >
                  {LOAN_CATEGORIES.map((c) => (
                    <option key={c} value={c}>{c}</option>
                  ))}
                </select>
              </div>

              <div className="admin-form-group">
                <label className="admin-form-label required">อัตราดอกเบี้ย (% ต่อปี)</label>
                <input
                  type="number"
                  step="0.01"
                  name="interest_rate"
                  className={`admin-form-input ${formErrors.interest_rate ? 'has-error' : ''}`}
                  value={formData.interest_rate}
                  onChange={handleChange}
                  required
                />
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem' }}>
              <div className="admin-form-group">
                <label className="admin-form-label">วงเงินกู้ขั้นต่ำ (บาท)</label>
                <input
                  type="number"
                  name="min_loan_amount"
                  className="admin-form-input"
                  value={formData.min_loan_amount}
                  onChange={handleChange}
                />
              </div>

              <div className="admin-form-group">
                <label className="admin-form-label">วงเงินกู้สูงสุด (บาท)</label>
                <input
                  type="number"
                  name="max_loan_limit"
                  className="admin-form-input"
                  value={formData.max_loan_limit}
                  onChange={handleChange}
                />
              </div>

              <div className="admin-form-group">
                <label className="admin-form-label">ผ่อนชำระสูงสุด (งวด/เดือน)</label>
                <input
                  type="number"
                  name="max_term_months"
                  className="admin-form-input"
                  value={formData.max_term_months}
                  onChange={handleChange}
                />
              </div>
            </div>

            <div className="admin-form-group">
              <label className="admin-form-label">คุณสมบัติผู้กู้</label>
              <textarea
                name="eligibility"
                className="admin-form-textarea"
                rows={2}
                placeholder="เช่น เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 6 เดือน..."
                value={formData.eligibility}
                onChange={handleChange}
              />
            </div>

            <div className="admin-form-group">
              <label className="admin-form-label">หลักประกัน / ผู้ค้ำประกัน</label>
              <textarea
                name="guarantor_requirement"
                className="admin-form-textarea"
                rows={2}
                placeholder="เช่น สมาชิกค้ำประกัน 2 คน หรืออสังหาริมทรัพย์..."
                value={formData.guarantor_requirement}
                onChange={handleChange}
              />
            </div>

            <div style={{ display: 'flex', gap: '1.5rem', marginTop: '0.5rem', marginBottom: '1rem' }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', fontSize: '0.9rem' }}>
                <input
                  type="checkbox"
                  name="is_calculator_enabled"
                  checked={formData.is_calculator_enabled === 1}
                  onChange={handleChange}
                />
                เปิดใช้ในระบบคำนวณเงินกู้ออนไลน์
              </label>

              <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', fontSize: '0.9rem' }}>
                <input
                  type="checkbox"
                  name="is_featured"
                  checked={formData.is_featured === 1}
                  onChange={handleChange}
                />
                แสดงหน้าแรก (Featured)
              </label>
            </div>
          </>
        ) : (
          <>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              <div className="admin-form-group">
                <label className="admin-form-label required">อัตราดอกเบี้ย (% ต่อปี)</label>
                <input
                  type="number"
                  step="0.01"
                  name="interest_rate"
                  className={`admin-form-input ${formErrors.interest_rate ? 'has-error' : ''}`}
                  value={formData.interest_rate}
                  onChange={handleChange}
                  required
                />
              </div>

              <div className="admin-form-group">
                <label className="admin-form-label">เงินฝากขั้นต่ำ (บาท)</label>
                <input
                  type="number"
                  name="min_deposit"
                  className="admin-form-input"
                  value={formData.min_deposit}
                  onChange={handleChange}
                />
              </div>
            </div>

            <div className="admin-form-group">
              <label className="admin-form-label">เงื่อนไขการถอนเงิน</label>
              <input
                type="text"
                name="withdrawal_condition"
                className="admin-form-input"
                placeholder="เช่น ถอนได้เดือนละ 1 ครั้งโดยไม่เสียค่าธรรมเนียม"
                value={formData.withdrawal_condition}
                onChange={handleChange}
              />
            </div>

            <div className="admin-form-group">
              <label className="admin-form-label">คุณสมบัติผู้เปิดบัญชี</label>
              <textarea
                name="eligibility"
                className="admin-form-textarea"
                rows={2}
                placeholder="เช่น สมาชิกสามัญ และสมาชิกสมทบ..."
                value={formData.eligibility}
                onChange={handleChange}
              />
            </div>

            <div className="admin-form-group">
              <label className="admin-form-label">เอกสารประกอบการเปิดบัญชี</label>
              <textarea
                name="required_documents"
                className="admin-form-textarea"
                rows={2}
                placeholder="เช่น สำเนาบัตรประชาชน, สำเนาทะเบียนบ้าน..."
                value={formData.required_documents}
                onChange={handleChange}
              />
            </div>
          </>
        )}

        <div className="admin-form-group">
          <label className="admin-form-label">คำอธิบายโดยย่อ</label>
          <textarea
            name="short_description"
            className="admin-form-textarea"
            rows={2}
            placeholder="สรุปจุดเด่นของผลิตภัณฑ์สำหรับแสดงบนการ์ด..."
            value={formData.short_description}
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
          </div>

          <div className="admin-form-group">
            <label className="admin-form-label">สถานะการให้บริการ</label>
            <select
              name="status"
              className="admin-form-select"
              value={formData.status}
              onChange={handleChange}
            >
              <option value="active">เปิดให้บริการ (Active)</option>
              <option value="inactive">ปิดให้บริการ (Inactive)</option>
            </select>
          </div>
        </div>
      </CrudFormDrawer>

      {/* Detail Modal */}
      <CrudDetailModal
        isOpen={detailModalOpen}
        onClose={() => setDetailModalOpen(false)}
        title={selectedProduct?.name || 'รายละเอียดผลิตภัณฑ์'}
        badge={<StatusBadge status={selectedProduct?.status} />}
        actions={
          <PermissionGuard permission="products.edit" fallback={null}>
            <button
              type="button"
              className="admin-btn-primary"
              onClick={() => {
                setDetailModalOpen(false);
                handleOpenEdit(selectedProduct);
              }}
            >
              <Edit2 size={16} />
              แก้ไขผลิตภัณฑ์
            </button>
          </PermissionGuard>
        }
      >
        {selectedProduct && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            <div style={{ padding: '1.25rem', background: 'var(--navy-50, #f8fafc)', borderRadius: '8px', border: '1px solid var(--navy-100, #e2e8f0)', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '1rem' }}>
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>
                  {activeTab === 'loan' ? selectedProduct.category : 'ประเภทบัญชีเงินฝาก'}
                </span>
                <h3 style={{ margin: '0.25rem 0 0 0', color: 'var(--navy-900, #0f172a)' }}>
                  {selectedProduct.name}
                </h3>
              </div>
              <div style={{ textAlign: 'right' }}>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>อัตราดอกเบี้ย</span>
                <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--primary-700, #1d4ed8)' }}>
                  {Number(selectedProduct.interest_rate).toFixed(2)}% ต่อปี
                </div>
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              {activeTab === 'loan' ? (
                <>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>วงเงินกู้ขั้นต่ำ - สูงสุด:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {formatBaht(selectedProduct.min_loan_amount)} - {formatBaht(selectedProduct.max_loan_limit)}
                    </p>
                  </div>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ผ่อนชำระสูงสุด:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {selectedProduct.max_term_months ? `${selectedProduct.max_term_months} งวด` : '-'}
                    </p>
                  </div>
                </>
              ) : (
                <>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>เงินฝากขั้นต่ำ:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {formatBaht(selectedProduct.min_deposit)}
                    </p>
                  </div>
                  <div>
                    <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>เงื่อนไขการถอน:</span>
                    <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                      {selectedProduct.withdrawal_condition || '-'}
                    </p>
                  </div>
                </>
              )}

              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>ลำดับการแสดงผล:</span>
                <p style={{ fontWeight: 600, color: 'var(--navy-900, #0f172a)' }}>
                  {selectedProduct.sort_order ?? 0}
                </p>
              </div>
            </div>

            {selectedProduct.eligibility && (
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>คุณสมบัติผู้ขอรับบริการ:</span>
                <p style={{ marginTop: '0.25rem', color: 'var(--navy-700, #334155)', lineHeight: 1.6 }}>
                  {selectedProduct.eligibility}
                </p>
              </div>
            )}

            {selectedProduct.guarantor_requirement && (
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>หลักประกัน / ผู้ค้ำประกัน:</span>
                <p style={{ marginTop: '0.25rem', color: 'var(--navy-700, #334155)', lineHeight: 1.6 }}>
                  {selectedProduct.guarantor_requirement}
                </p>
              </div>
            )}

            {selectedProduct.short_description && (
              <div>
                <span style={{ fontSize: '0.8rem', color: 'var(--navy-500, #64748b)' }}>คำอธิบายโดยย่อ:</span>
                <p style={{ marginTop: '0.25rem', color: 'var(--navy-700, #334155)', lineHeight: 1.6 }}>
                  {selectedProduct.short_description}
                </p>
              </div>
            )}
          </div>
        )}
      </CrudDetailModal>
    </div>
  );
}
