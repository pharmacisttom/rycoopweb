import React, { useEffect, useState, useMemo } from 'react';
import {
  Plus, Eye, Edit2, Trash2, CheckCircle, XCircle, Users, Award,
  Briefcase, Mail, Phone, Image as ImageIcon, Copy, Calendar, Globe,
  ArrowUp, ArrowDown, ShieldCheck, Clock, RefreshCw
} from 'lucide-react';
import { memberAdminApi } from '../services/memberAdminApi';
import alertService from '../services/alertService';
import { resolveMediaUrl } from '../utils/media';
import {
  PageHeader,
  CrudDataTable,
  CrudFormDrawer,
  CrudDetailModal,
  StatusBadge,
  PermissionGuard,
} from '../components/admin/design-system';

const INITIAL_MEMBER_FORM = {
  id: null,
  name: '',
  position: '',
  role_type: 'director',
  workplace: '',
  department: '',
  image_url: '',
  bio: '',
  sort_order: 0,
  is_chairperson: false,
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
  // Main tabs: 'terms' (Board Terms & Governance) | 'staff' (Staff Management)
  const [mainTab, setMainTab] = useState('terms');
  const [boardCategory, setBoardCategory] = useState('director'); // 'director' | 'advisor' | 'auditor' | 'groups' | 'metadata'

  // Term state
  const [terms, setTerms] = useState([]);
  const [selectedTermId, setSelectedTermId] = useState(null);
  const [termDetails, setTermDetails] = useState(null);
  const [termsLoading, setTermsLoading] = useState(true);

  // Staff state
  const [staff, setStaff] = useState([]);
  const [staffLoading, setStaffLoading] = useState(false);

  // Refresh triggers
  const [refreshKey, setRefreshKey] = useState(0);

  // Modals & Drawers
  const [memberDrawerOpen, setMemberDrawerOpen] = useState(false);
  const [staffDrawerOpen, setStaffDrawerOpen] = useState(false);
  const [termModalOpen, setTermModalOpen] = useState(false);
  const [copyModalOpen, setCopyModalOpen] = useState(false);
  const [scheduleModalOpen, setScheduleModalOpen] = useState(false);
  const [previewModalOpen, setPreviewModalOpen] = useState(false);
  const [groupModalOpen, setGroupModalOpen] = useState(false);
  const [assignMemberModalOpen, setAssignMemberModalOpen] = useState(false);

  // Form states
  const [memberForm, setMemberForm] = useState(INITIAL_MEMBER_FORM);
  const [staffForm, setStaffForm] = useState(INITIAL_STAFF_FORM);
  const [termForm, setTermForm] = useState({ term_number: '', term_name: '', year_start_be: '', year_end_be: '', source_url: '', note: '' });
  const [copyForm, setCopyForm] = useState({ term_number: '', term_name: '' });
  const [scheduleForm, setScheduleForm] = useState({ publish_at: '' });
  const [groupForm, setGroupForm] = useState({ id: null, title: '', description: '', sort_order: 0 });
  const [assignForm, setAssignForm] = useState({ group_id: null, member_id: '', role_in_group: 'กรรมการ' });

  const [formErrors, setFormErrors] = useState({});
  const [formBusy, setFormBusy] = useState(false);
  const [uploadingPhoto, setUploadingPhoto] = useState(false);

  // 1. Fetch Terms List
  useEffect(() => {
    const controller = new AbortController();
    setTermsLoading(true);

    memberAdminApi('/api/admin/board-terms', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          const list = res.data || [];
          setTerms(list);
          if (list.length > 0) {
            // Select current published term or first term if none selected
            setSelectedTermId((prev) => {
              if (prev && list.some((t) => t.id === prev)) return prev;
              const current = list.find((t) => t.is_current) || list[0];
              return current ? current.id : null;
            });
          } else {
            setSelectedTermId(null);
            setTermDetails(null);
          }
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          alertService.toast({ icon: 'error', title: err.message || 'ไม่สามารถโหลดข้อมูลวาระได้' });
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setTermsLoading(false);
      });

    return () => controller.abort();
  }, [refreshKey]);

  // 2. Fetch Selected Term Details
  useEffect(() => {
    if (!selectedTermId) {
      setTermDetails(null);
      return;
    }

    const controller = new AbortController();
    memberAdminApi(`/api/admin/board-terms/${selectedTermId}`, { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setTermDetails(res.data || null);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          alertService.toast({ icon: 'error', title: err.message || 'ไม่สามารถโหลดรายละเอียดวาระได้' });
        }
      });

    return () => controller.abort();
  }, [selectedTermId, refreshKey]);

  // 3. Fetch Staff when on staff tab
  useEffect(() => {
    if (mainTab !== 'staff') return;
    const controller = new AbortController();
    setStaffLoading(true);

    memberAdminApi('/api/admin/board-staff', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted) {
          setStaff(res.data?.staff || []);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          alertService.toast({ icon: 'error', title: err.message || 'ไม่สามารถโหลดข้อมูลเจ้าหน้าที่ได้' });
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setStaffLoading(false);
      });

    return () => controller.abort();
  }, [mainTab, refreshKey]);

  // Selected Term Object
  const currentTerm = useMemo(() => {
    return terms.find((t) => t.id === selectedTermId) || termDetails?.term || null;
  }, [terms, selectedTermId, termDetails]);

  // --- ACTIONS: Term Level ---

  // Reconcile Term 38
  const handleReconcileTerm38 = async (force = false) => {
    const confirm = await alertService.confirm(
      'เทียบข้อมูลวาระที่ 38 จากเว็บไซต์เดิม',
      'ระบบจะนำเข้าข้อมูลคณะกรรมการชุดที่ 38, ที่ปรึกษา, ผู้ตรวจสอบกิจการ และคณะย่อยจากฐานข้อมูลเดิมอย่างปลอดภัย',
      'ยืนยันนำเข้า',
      'ยกเลิก'
    );
    if (!confirm) return;

    try {
      alertService.showLoading('กำลังเทียบข้อมูลวาระที่ 38...');
      const res = await memberAdminApi('/api/admin/board-terms/reconcile-38', {
        method: 'POST',
        data: { force },
      });
      alertService.closeLoading();
      await alertService.success(res.message || 'เทียบข้อมูลวาระที่ 38 เรียบร้อยแล้ว');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ไม่สามารถเทียบข้อมูลได้');
    }
  };

  // Open Create Term Modal
  const handleOpenCreateTerm = () => {
    const maxTerm = terms.reduce((max, t) => Math.max(max, t.term_number || 0), 38);
    const nextTerm = maxTerm + 1;
    setTermForm({
      term_number: nextTerm,
      term_name: `ชุดที่ ${nextTerm}`,
      year_start_be: 2568,
      year_end_be: 2569,
      source_url: '',
      note: '',
    });
    setFormErrors({});
    setTermModalOpen(true);
  };

  // Submit Create Term
  const handleSubmitCreateTerm = async (e) => {
    e.preventDefault();
    if (!termForm.term_number || Number(termForm.term_number) <= 0) {
      setFormErrors({ term_number: 'กรุณาระบุหมายเลขวาระที่ถูกต้อง' });
      return;
    }

    setFormBusy(true);
    alertService.showLoading('กำลังสร้างวาระใหม่...');

    try {
      const res = await memberAdminApi('/api/admin/board-terms', {
        method: 'POST',
        data: termForm,
      });
      alertService.closeLoading();
      await alertService.success(res.message || 'สร้างวาระใหม่สำเร็จ');
      setTermModalOpen(false);
      setRefreshKey((k) => k + 1);
      if (res.data?.term?.id) {
        setSelectedTermId(res.data.term.id);
      }
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'สร้างวาระไม่สำเร็จ');
    } finally {
      setFormBusy(false);
    }
  };

  // Open Copy Term Modal
  const handleOpenCopyTerm = () => {
    if (!currentTerm) return;
    const maxTerm = terms.reduce((max, t) => Math.max(max, t.term_number || 0), 38);
    const nextTerm = maxTerm + 1;
    setCopyForm({
      term_number: nextTerm,
      term_name: `ชุดที่ ${nextTerm}`,
    });
    setFormErrors({});
    setCopyModalOpen(true);
  };

  // Submit Copy Term
  const handleSubmitCopyTerm = async (e) => {
    e.preventDefault();
    if (!copyForm.term_number || Number(copyForm.term_number) <= 0) {
      setFormErrors({ term_number: 'กรุณาระบุหมายเลขวาระใหม่' });
      return;
    }

    setFormBusy(true);
    alertService.showLoading(`กำลังคัดลอกข้อมูลจาก ${currentTerm.term_name} ไปยังชุดที่ ${copyForm.term_number}...`);

    try {
      const res = await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/copy`, {
        method: 'POST',
        data: copyForm,
      });
      alertService.closeLoading();
      await alertService.success(res.message || 'คัดลอกวาระสำเร็จ');
      setCopyModalOpen(false);
      setRefreshKey((k) => k + 1);
      if (res.data?.term?.id) {
        setSelectedTermId(res.data.term.id);
      }
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'คัดลอกวาระไม่สำเร็จ');
    } finally {
      setFormBusy(false);
    }
  };

  // Publish Term
  const handlePublishTerm = async () => {
    if (!currentTerm) return;
    const confirm = await alertService.confirm(
      `ยืนยันเผยแพร่ ${currentTerm.term_name}`,
      `การเผยแพร่นี้จะทำให้ ${currentTerm.term_name} แสดงเป็นคณะกรรมการปัจจุบันบนหน้าเว็บไซต์สาธารณะทันที และปรับวาระเดิมเข้าสู่สถานะเก็บถาวร (ประวัติ)`,
      'อนุมัติและเผยแพร่ทันที',
      'ยกเลิก'
    );
    if (!confirm) return;

    try {
      alertService.showLoading('กำลังเผยแพร่วาระ...');
      const res = await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/publish`, {
        method: 'POST',
      });
      alertService.closeLoading();
      await alertService.success(res.message || 'เผยแพร่วาระสำเร็จ');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ไม่สามารถเผยแพร่วาระได้');
    }
  };

  // Submit Schedule Term
  const handleSubmitSchedule = async (e) => {
    e.preventDefault();
    if (!scheduleForm.publish_at) {
      setFormErrors({ publish_at: 'กรุณาระบุวันและเวลา' });
      return;
    }

    setFormBusy(true);
    alertService.showLoading('กำลังตั้งเวลาเผยแพร่...');

    try {
      const res = await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/schedule`, {
        method: 'POST',
        data: scheduleForm,
      });
      alertService.closeLoading();
      await alertService.success(res.message || 'ตั้งเวลาเผยแพร่สำเร็จ');
      setScheduleModalOpen(false);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ไม่สามารถตั้งเวลาเผยแพร่ได้');
    } finally {
      setFormBusy(false);
    }
  };

  // --- ACTIONS: Member Level (Directors, Advisors, Auditors) ---

  const handleOpenAddMember = () => {
    setMemberForm({
      ...INITIAL_MEMBER_FORM,
      sort_order: (termDetails?.[boardCategory === 'director' ? 'directors' : boardCategory === 'advisor' ? 'advisors' : 'auditors']?.length || 0) + 1,
    });
    setFormErrors({});
    setMemberDrawerOpen(true);
  };

  const handleOpenEditMember = (member) => {
    setMemberForm({
      id: member.id,
      name: member.name || '',
      position: member.position || '',
      role_type: member.role_type || 'director',
      workplace: member.workplace || '',
      department: member.department || '',
      image_url: member.image_url || '',
      bio: member.bio || '',
      sort_order: member.sort_order ?? 0,
      is_chairperson: Boolean(member.is_chairperson),
    });
    setFormErrors({});
    setMemberDrawerOpen(true);
  };

  // Handle Photo Upload
  const handlePhotoUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('photo', file);
    formData.append('name', memberForm.name || 'personnel');

    setUploadingPhoto(true);
    try {
      const res = await memberAdminApi('/api/admin/board-terms/upload-photo', {
        method: 'POST',
        data: formData,
      });
      if (res.data?.url) {
        setMemberForm((prev) => ({ ...prev, image_url: res.data.url }));
        alertService.toast({ icon: 'success', title: 'อัปโหลดรูปภาพสำเร็จ' });
      }
    } catch (err) {
      alertService.error(err.message || 'อัปโหลดรูปถ่ายไม่สำเร็จ');
    } finally {
      setUploadingPhoto(false);
    }
  };

  // Submit Member Form
  const handleSubmitMember = async (e) => {
    e.preventDefault();
    if (!memberForm.name.trim()) {
      setFormErrors({ name: 'กรุณากรอกชื่อ-นามสกุล' });
      return;
    }
    if (!memberForm.position.trim()) {
      setFormErrors({ position: 'กรุณากรอกตำแหน่ง' });
      return;
    }

    const categoryKey = boardCategory === 'director' ? 'directors' : boardCategory === 'advisor' ? 'advisors' : 'auditors';
    const currentList = [...(termDetails?.[categoryKey] || [])];

    if (memberForm.id) {
      const idx = currentList.findIndex((m) => m.id === memberForm.id);
      if (idx !== -1) currentList[idx] = { ...currentList[idx], ...memberForm };
    } else {
      currentList.push({ ...memberForm, id: null });
    }

    setFormBusy(true);
    alertService.showLoading('กำลังบันทึกข้อมูล...');

    try {
      const res = await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/members`, {
        method: 'PUT',
        data: {
          category: boardCategory,
          members: currentList,
        },
      });
      alertService.closeLoading();
      await alertService.success(res.message || 'บันทึกรายชื่อเรียบร้อยแล้ว');
      setMemberDrawerOpen(false);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'บันทึกข้อมูลไม่สำเร็จ');
    } finally {
      setFormBusy(false);
    }
  };

  // Move member up/down
  const handleMoveMember = async (member, direction) => {
    const categoryKey = boardCategory === 'director' ? 'directors' : boardCategory === 'advisor' ? 'advisors' : 'auditors';
    const list = [...(termDetails?.[categoryKey] || [])];
    const index = list.findIndex((m) => m.id === member.id);
    if (index === -1) return;

    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= list.length) return;

    // Swap
    const temp = list[index];
    list[index] = list[targetIndex];
    list[targetIndex] = temp;

    // Re-index sort_order
    const updated = list.map((m, idx) => ({ ...m, sort_order: idx }));

    try {
      alertService.showLoading('กำลังปรับลำดับ...');
      await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/members`, {
        method: 'PUT',
        data: { category: boardCategory, members: updated },
      });
      alertService.closeLoading();
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ปรับลำดับไม่สำเร็จ');
    }
  };

  // Delete Member
  const handleDeleteMember = async (member) => {
    const confirmed = await alertService.confirmDelete(member.name, 'สมาชิกท่านนี้');
    if (!confirmed) return;

    const categoryKey = boardCategory === 'director' ? 'directors' : boardCategory === 'advisor' ? 'advisors' : 'auditors';
    const updated = (termDetails?.[categoryKey] || []).filter((m) => m.id !== member.id);

    try {
      alertService.showLoading('กำลังลบข้อมูล...');
      const res = await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/members`, {
        method: 'PUT',
        data: { category: boardCategory, members: updated },
      });
      alertService.closeLoading();
      await alertService.success(res.message || 'ลบรายชื่อเรียบร้อยแล้ว');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ลบไม่สำเร็จ');
    }
  };

  // --- ACTIONS: Subgroups (คณะกรรมการย่อย) ---

  const handleOpenAddGroup = () => {
    setGroupForm({ id: null, title: '', description: '', sort_order: (termDetails?.groups?.length || 0) + 1 });
    setGroupModalOpen(true);
  };

  const handleSaveGroup = async (e) => {
    e.preventDefault();
    if (!groupForm.title.trim()) {
      alertService.error('กรุณาระบุชื่อคณะกรรมการย่อย');
      return;
    }

    const currentGroups = [...(termDetails?.groups || [])];
    if (groupForm.id) {
      const idx = currentGroups.findIndex((g) => g.id === groupForm.id);
      if (idx !== -1) currentGroups[idx] = { ...currentGroups[idx], ...groupForm };
    } else {
      currentGroups.push({ ...groupForm, members: [] });
    }

    // Prepare payload
    const payload = currentGroups.map((g, idx) => ({
      title: g.title,
      description: g.description,
      sort_order: idx,
      members: (g.members || []).map((m) => [m.member_id || m.id, m.role_in_group || 'กรรมการ']),
    }));

    try {
      alertService.showLoading('กำลังบันทึกคณะย่อย...');
      await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/groups`, {
        method: 'PUT',
        data: { groups: payload },
      });
      alertService.closeLoading();
      await alertService.success('บันทึกคณะกรรมการย่อยสำเร็จ');
      setGroupModalOpen(false);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'บันทึกคณะย่อยไม่สำเร็จ');
    }
  };

  const handleDeleteGroup = async (group) => {
    const confirm = await alertService.confirmDelete(group.title, 'คณะกรรมการย่อยนี้');
    if (!confirm) return;

    const remaining = (termDetails?.groups || []).filter((g) => g.id !== group.id);
    const payload = remaining.map((g, idx) => ({
      title: g.title,
      description: g.description,
      sort_order: idx,
      members: (g.members || []).map((m) => [m.member_id || m.id, m.role_in_group || 'กรรมการ']),
    }));

    try {
      alertService.showLoading('กำลังลบคณะย่อย...');
      await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/groups`, {
        method: 'PUT',
        data: { groups: payload },
      });
      alertService.closeLoading();
      await alertService.success('ลบคณะกรรมการย่อยเรียบร้อยแล้ว');
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ลบไม่สำเร็จ');
    }
  };

  // Open Assign Member to Group
  const handleOpenAssignMember = (group) => {
    setAssignForm({ group_id: group.id, member_id: '', role_in_group: 'กรรมการ' });
    setAssignMemberModalOpen(true);
  };

  // Submit Assign Member to Group
  const handleSaveAssignMember = async (e) => {
    e.preventDefault();
    if (!assignForm.member_id) {
      alertService.error('กรุณาเลือกกรรมการ');
      return;
    }

    const currentGroups = [...(termDetails?.groups || [])];
    const group = currentGroups.find((g) => g.id === assignForm.group_id);
    if (!group) return;

    const memberIdNum = Number(assignForm.member_id);
    if (group.members?.some((m) => (m.member_id || m.id) === memberIdNum)) {
      alertService.error('กรรมการท่านนี้อยู่ในคณะย่อยนี้แล้ว');
      return;
    }

    const director = termDetails?.directors?.find((d) => d.id === memberIdNum);
    const newMembers = [...(group.members || []), {
      member_id: memberIdNum,
      name: director?.name || '',
      position: director?.position || '',
      role_in_group: assignForm.role_in_group,
    }];
    group.members = newMembers;

    const payload = currentGroups.map((g, idx) => ({
      title: g.title,
      description: g.description,
      sort_order: idx,
      members: (g.members || []).map((m) => [m.member_id || m.id, m.role_in_group || 'กรรมการ']),
    }));

    try {
      alertService.showLoading('กำลังเพิ่มกรรมการในคณะย่อย...');
      await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/groups`, {
        method: 'PUT',
        data: { groups: payload },
      });
      alertService.closeLoading();
      await alertService.success('เพิ่มกรรมการในคณะย่อยสำเร็จ');
      setAssignMemberModalOpen(false);
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'เพิ่มกรรมการในคณะย่อยไม่สำเร็จ');
    }
  };

  // Remove member from group
  const handleRemoveMemberFromGroup = async (group, memberId) => {
    const currentGroups = [...(termDetails?.groups || [])];
    const g = currentGroups.find((item) => item.id === group.id);
    if (!g) return;

    g.members = (g.members || []).filter((m) => (m.member_id || m.id) !== memberId);

    const payload = currentGroups.map((grp, idx) => ({
      title: grp.title,
      description: grp.description,
      sort_order: idx,
      members: (grp.members || []).map((m) => [m.member_id || m.id, m.role_in_group || 'กรรมการ']),
    }));

    try {
      alertService.showLoading('กำลังนำออกจากคณะย่อย...');
      await memberAdminApi(`/api/admin/board-terms/${currentTerm.id}/groups`, {
        method: 'PUT',
        data: { groups: payload },
      });
      alertService.closeLoading();
      setRefreshKey((k) => k + 1);
    } catch (err) {
      alertService.closeLoading();
      alertService.error(err.message || 'ดำเนินการไม่สำเร็จ');
    }
  };

  // Render Status Badge for a Term
  const renderTermStatusBadge = (term) => {
    if (!term) return null;
    if (term.is_current || term.status === 'published') {
      return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem', padding: '0.2rem 0.6rem', borderRadius: '9999px', fontSize: '0.75rem', fontWeight: 600, background: '#dcfce7', color: '#15803d', border: '1px solid #86efac' }}>
          <CheckCircle size={12} /> เผยแพร่แล้ว (สาธารณะ)
        </span>
      );
    }
    if (term.status === 'scheduled') {
      return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem', padding: '0.2rem 0.6rem', borderRadius: '9999px', fontSize: '0.75rem', fontWeight: 600, background: '#e0f2fe', color: '#0369a1', border: '1px solid #7dd3fc' }}>
          <Clock size={12} /> ตั้งเวลา {term.publish_at ? new Date(term.publish_at).toLocaleDateString('th-TH') : ''}
        </span>
      );
    }
    if (term.status === 'archived') {
      return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem', padding: '0.2rem 0.6rem', borderRadius: '9999px', fontSize: '0.75rem', fontWeight: 600, background: '#fef3c7', color: '#b45309', border: '1px solid #fcd34d' }}>
          <Clock size={12} /> เก็บถาวร (ประวัติ)
        </span>
      );
    }
    return (
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem', padding: '0.2rem 0.6rem', borderRadius: '9999px', fontSize: '0.75rem', fontWeight: 600, background: '#f1f5f9', color: '#475569', border: '1px solid #cbd5e1' }}>
        ฉบับร่าง
      </span>
    );
  };

  return (
    <div className="admin-page-container" style={{ padding: '1.5rem', maxWidth: '1440px', margin: '0 auto' }}>
      <PageHeader
        title="จัดการคณะกรรมการและเจ้าหน้าที่ (Personnel Management)"
        subtitle="ระบบบริหารจัดการคณะกรรมการดำเนินงานหลายวาระ (Multi-Term Governance) และบุคลากรสหกรณ์"
        icon={<Users size={24} />}
        actions={
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
            {terms.length === 0 && (
              <button
                type="button"
                className="admin-btn-primary"
                style={{ background: '#0284c7' }}
                onClick={() => handleReconcileTerm38(false)}
              >
                <RefreshCw size={16} /> เทียบข้อมูลวาระที่ 38 จากเว็บเดิม
              </button>
            )}
            <PermissionGuard permission="people.create">
              <button type="button" className="admin-btn-primary" onClick={handleOpenCreateTerm}>
                <Plus size={16} /> สร้างวาระใหม่
              </button>
            </PermissionGuard>
          </div>
        }
      />

      {/* Main Tabs (Board Terms vs Staff) */}
      <div style={{ display: 'flex', gap: '0.5rem', borderBottom: '2px solid #e2e8f0', marginBottom: '1.5rem' }}>
        <button
          type="button"
          onClick={() => setMainTab('terms')}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            padding: '0.75rem 1.25rem',
            fontWeight: 600,
            fontSize: '0.95rem',
            border: 'none',
            background: 'none',
            cursor: 'pointer',
            borderBottom: mainTab === 'terms' ? '3px solid #2563eb' : '3px solid transparent',
            color: mainTab === 'terms' ? '#2563eb' : '#64748b',
          }}
        >
          <Award size={18} /> คณะกรรมการและวาระการทำงาน ({terms.length} วาระ)
        </button>
        <button
          type="button"
          onClick={() => setMainTab('staff')}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            padding: '0.75rem 1.25rem',
            fontWeight: 600,
            fontSize: '0.95rem',
            border: 'none',
            background: 'none',
            cursor: 'pointer',
            borderBottom: mainTab === 'staff' ? '3px solid #2563eb' : '3px solid transparent',
            color: mainTab === 'staff' ? '#2563eb' : '#64748b',
          }}
        >
          <Briefcase size={18} /> ฝ่ายจัดการและเจ้าหน้าที่
        </button>
      </div>

      {mainTab === 'terms' ? (
        <div>
          {/* Term Selector & Controls Card */}
          <div
            className="surface-card"
            style={{
              padding: '1.25rem',
              marginBottom: '1.5rem',
              background: '#ffffff',
              borderRadius: '12px',
              border: '1px solid #e2e8f0',
              boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
                <span style={{ fontWeight: 600, color: '#334155', fontSize: '0.9rem' }}>เลือกวาระการทำงาน:</span>
                <select
                  className="admin-form-select"
                  style={{ minWidth: '220px', padding: '0.5rem 0.75rem', fontWeight: 600 }}
                  value={selectedTermId || ''}
                  onChange={(e) => setSelectedTermId(Number(e.target.value))}
                >
                  {terms.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.term_name} ({t.is_current ? 'ปัจจุบัน' : t.status === 'draft' ? 'ร่าง' : t.status === 'scheduled' ? 'ตั้งเวลา' : 'เก็บถาวร'})
                    </option>
                  ))}
                </select>

                {currentTerm && renderTermStatusBadge(currentTerm)}
              </div>

              {/* Action Buttons for Selected Term */}
              {currentTerm && (
                <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                  <button
                    type="button"
                    className="admin-btn-secondary"
                    style={{ fontSize: '0.85rem', display: 'flex', alignItems: 'center', gap: '0.35rem' }}
                    onClick={() => setPreviewModalOpen(true)}
                  >
                    <Eye size={15} /> ดูตัวอย่างหน้าเว็บจริง
                  </button>

                  <PermissionGuard permission="people.create">
                    <button
                      type="button"
                      className="admin-btn-secondary"
                      style={{ fontSize: '0.85rem', display: 'flex', alignItems: 'center', gap: '0.35rem' }}
                      onClick={handleOpenCopyTerm}
                    >
                      <Copy size={15} /> คัดลอกสร้างร่างใหม่
                    </button>
                  </PermissionGuard>

                  {!currentTerm.is_current && (
                    <PermissionGuard permission="people.publish">
                      <button
                        type="button"
                        className="admin-btn-secondary"
                        style={{ fontSize: '0.85rem', display: 'flex', alignItems: 'center', gap: '0.35rem' }}
                        onClick={() => {
                          setScheduleForm({ publish_at: '' });
                          setScheduleModalOpen(true);
                        }}
                      >
                        <Clock size={15} /> ตั้งเวลาเผยแพร่
                      </button>

                      <button
                        type="button"
                        className="admin-btn-primary"
                        style={{ fontSize: '0.85rem', background: '#16a34a', display: 'flex', alignItems: 'center', gap: '0.35rem' }}
                        onClick={handlePublishTerm}
                      >
                        <ShieldCheck size={15} /> อนุมัติและเผยแพร่ทันที
                      </button>
                    </PermissionGuard>
                  )}
                </div>
              )}
            </div>

            {/* Term Summary info */}
            {currentTerm && (
              <div style={{ marginTop: '1rem', paddingTop: '0.75rem', borderTop: '1px solid #f1f5f9', display: 'flex', gap: '1.5rem', fontSize: '0.85rem', color: '#64748b', flexWrap: 'wrap' }}>
                <div><strong>ปีที่ดำรงตำแหน่ง:</strong> พ.ศ. {currentTerm.year_start_be || '-'} – {currentTerm.year_end_be || '-'}</div>
                <div><strong>กรรมการ:</strong> {termDetails?.directors?.length || 0} ท่าน</div>
                <div><strong>ที่ปรึกษา:</strong> {termDetails?.advisors?.length || 0} ท่าน</div>
                <div><strong>ผู้ตรวจสอบ:</strong> {termDetails?.auditors?.length || 0} ท่าน</div>
                <div><strong>คณะกรรมการย่อย:</strong> {termDetails?.groups?.length || 0} คณะ</div>
                {currentTerm.note && <div><strong>หมายเหตุ:</strong> {currentTerm.note}</div>}
              </div>
            )}
          </div>

          {/* Sub-tabs: Category Navigation */}
          {currentTerm && (
            <div>
              <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1rem', borderBottom: '1px solid #cbd5e1' }}>
                {[
                  { key: 'director', label: 'คณะกรรมการดำเนินงาน', count: termDetails?.directors?.length },
                  { key: 'advisor', label: 'ที่ปรึกษาสหกรณ์', count: termDetails?.advisors?.length },
                  { key: 'auditor', label: 'ผู้ตรวจสอบกิจการ', count: termDetails?.auditors?.length },
                  { key: 'groups', label: 'คณะกรรมการย่อย', count: termDetails?.groups?.length },
                ].map((tab) => (
                  <button
                    key={tab.key}
                    type="button"
                    onClick={() => setBoardCategory(tab.key)}
                    style={{
                      padding: '0.6rem 1rem',
                      fontSize: '0.9rem',
                      fontWeight: 600,
                      border: 'none',
                      background: 'none',
                      cursor: 'pointer',
                      borderBottom: boardCategory === tab.key ? '2px solid #2563eb' : '2px solid transparent',
                      color: boardCategory === tab.key ? '#2563eb' : '#475569',
                    }}
                  >
                    {tab.label} ({tab.count ?? 0})
                  </button>
                ))}
              </div>

              {/* Subgroups Tab */}
              {boardCategory === 'groups' ? (
                <div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
                    <h3 style={{ margin: 0, fontSize: '1.1rem', color: '#1e293b' }}>คณะกรรมการย่อยใน{currentTerm.term_name}</h3>
                    <PermissionGuard permission="people.create">
                      <button type="button" className="admin-btn-primary" onClick={handleOpenAddGroup}>
                        <Plus size={16} /> เพิ่มคณะกรรมการย่อย
                      </button>
                    </PermissionGuard>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1rem' }}>
                    {(termDetails?.groups || []).map((grp) => (
                      <div
                        key={grp.id}
                        className="surface-card"
                        style={{
                          padding: '1.25rem',
                          background: '#ffffff',
                          borderRadius: '10px',
                          border: '1px solid #e2e8f0',
                        }}
                      >
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem' }}>
                          <div>
                            <h4 style={{ margin: '0 0 0.25rem 0', color: '#0f172a', fontSize: '1.05rem' }}>{grp.title}</h4>
                            {grp.description && <p style={{ margin: 0, fontSize: '0.8rem', color: '#64748b' }}>{grp.description}</p>}
                          </div>
                          <div style={{ display: 'flex', gap: '0.25rem' }}>
                            <button
                              type="button"
                              className="admin-btn-icon"
                              title="ลบคณะย่อย"
                              onClick={() => handleDeleteGroup(grp)}
                              style={{ color: '#ef4444' }}
                            >
                              <Trash2 size={16} />
                            </button>
                          </div>
                        </div>

                        <div style={{ borderTop: '1px solid #f1f5f9', paddingTop: '0.75rem' }}>
                          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                            <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#475569' }}>
                              รายชื่อกรรมการ ({grp.members?.length || 0})
                            </span>
                            <PermissionGuard permission="people.edit">
                              <button
                                type="button"
                                style={{ background: 'none', border: 'none', color: '#2563eb', fontSize: '0.8rem', fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '0.25rem' }}
                                onClick={() => handleOpenAssignMember(grp)}
                              >
                                <Plus size={14} /> มอบหมายกรรมการ
                              </button>
                            </PermissionGuard>
                          </div>

                          <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
                            {(grp.members || []).map((m) => (
                              <li
                                key={m.link_id || m.member_id || m.id}
                                style={{
                                  display: 'flex',
                                  justifyContent: 'space-between',
                                  alignItems: 'center',
                                  padding: '0.4rem 0',
                                  borderBottom: '1px dashed #f1f5f9',
                                  fontSize: '0.85rem',
                                }}
                              >
                                <div>
                                  <span style={{ fontWeight: 600, color: '#1e293b' }}>{m.name}</span>
                                  <span style={{ color: '#64748b', marginLeft: '0.5rem' }}>— {m.role_in_group}</span>
                                </div>
                                <button
                                  type="button"
                                  onClick={() => handleRemoveMemberFromGroup(grp, m.member_id || m.id)}
                                  style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', padding: '0.2rem' }}
                                  title="นำออกจากคณะย่อย"
                                >
                                  <XCircle size={14} />
                                </button>
                              </li>
                            ))}
                            {(!grp.members || grp.members.length === 0) && (
                              <li style={{ padding: '0.5rem 0', color: '#94a3b8', fontSize: '0.85rem', textAlign: 'center' }}>
                                ยังไม่มีการมอบหมายกรรมการ
                              </li>
                            )}
                          </ul>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                /* Member Lists: Directors, Advisors, Auditors */
                <div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
                    <h3 style={{ margin: 0, fontSize: '1.1rem', color: '#1e293b' }}>
                      {boardCategory === 'director' ? 'รายชื่อคณะกรรมการดำเนินงาน' : boardCategory === 'advisor' ? 'รายชื่อที่ปรึกษา' : 'รายชื่อผู้ตรวจสอบกิจการ'} ({termDetails?.[boardCategory === 'director' ? 'directors' : boardCategory === 'advisor' ? 'advisors' : 'auditors']?.length || 0})
                    </h3>
                    <PermissionGuard permission="people.create">
                      <button type="button" className="admin-btn-primary" onClick={handleOpenAddMember}>
                        <Plus size={16} /> เพิ่ม{boardCategory === 'director' ? 'กรรมการ' : boardCategory === 'advisor' ? 'ที่ปรึกษา' : 'ผู้ตรวจสอบ'}
                      </button>
                    </PermissionGuard>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '1rem' }}>
                    {(termDetails?.[boardCategory === 'director' ? 'directors' : boardCategory === 'advisor' ? 'advisors' : 'auditors'] || []).map((m, index) => (
                      <div
                        key={m.id}
                        className="surface-card"
                        style={{
                          padding: '1rem',
                          background: '#ffffff',
                          borderRadius: '10px',
                          border: m.is_chairperson ? '2px solid #2563eb' : '1px solid #e2e8f0',
                          display: 'flex',
                          flexDirection: 'column',
                          justifyContent: 'space-between',
                          position: 'relative',
                        }}
                      >
                        {m.is_chairperson && (
                          <span
                            style={{
                              position: 'absolute',
                              top: '0.5rem',
                              right: '0.5rem',
                              background: '#eff6ff',
                              color: '#1d4ed8',
                              padding: '0.2rem 0.5rem',
                              borderRadius: '9999px',
                              fontSize: '0.7rem',
                              fontWeight: 700,
                              border: '1px solid #bfdbfe',
                            }}
                          >
                            ประธาน
                          </span>
                        )}

                        <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center', marginBottom: '0.75rem' }}>
                          <img
                            src={resolveMediaUrl(m.image_url, '/assets/img/logo.webp')}
                            alt={m.name}
                            style={{
                              width: '56px',
                              height: '56px',
                              borderRadius: '50%',
                              objectFit: 'cover',
                              border: '2px solid #e2e8f0',
                              background: '#f8fafc',
                            }}
                            onError={(e) => { e.currentTarget.src = '/assets/img/logo.webp'; }}
                          />
                          <div style={{ overflow: 'hidden' }}>
                            <div style={{ fontSize: '0.75rem', color: '#64748b' }}>ลำดับที่ {index + 1}</div>
                            <h4 style={{ margin: 0, fontSize: '0.95rem', color: '#0f172a', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                              {m.name}
                            </h4>
                            <div style={{ fontSize: '0.85rem', color: '#2563eb', fontWeight: 600 }}>{m.position}</div>
                          </div>
                        </div>

                        {m.workplace && (
                          <div style={{ fontSize: '0.78rem', color: '#64748b', marginBottom: '0.5rem' }}>
                            หน่วยงาน: {m.workplace}
                          </div>
                        )}

                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid #f1f5f9', paddingTop: '0.5rem', marginTop: 'auto' }}>
                          <div style={{ display: 'flex', gap: '0.25rem' }}>
                            <button
                              type="button"
                              className="admin-btn-icon"
                              title="ย้ายขึ้น"
                              disabled={index === 0}
                              onClick={() => handleMoveMember(m, 'up')}
                              style={{ opacity: index === 0 ? 0.3 : 1 }}
                            >
                              <ArrowUp size={15} />
                            </button>
                            <button
                              type="button"
                              className="admin-btn-icon"
                              title="ย้ายลง"
                              disabled={index === (termDetails?.[boardCategory === 'director' ? 'directors' : boardCategory === 'advisor' ? 'advisors' : 'auditors']?.length || 0) - 1}
                              onClick={() => handleMoveMember(m, 'down')}
                              style={{ opacity: index === (termDetails?.[boardCategory === 'director' ? 'directors' : boardCategory === 'advisor' ? 'advisors' : 'auditors']?.length || 0) - 1 ? 0.3 : 1 }}
                            >
                              <ArrowDown size={15} />
                            </button>
                          </div>

                          <div style={{ display: 'flex', gap: '0.25rem' }}>
                            <PermissionGuard permission="people.edit">
                              <button
                                type="button"
                                className="admin-btn-icon"
                                title="แก้ไข"
                                onClick={() => handleOpenEditMember(m)}
                              >
                                <Edit2 size={15} />
                              </button>
                            </PermissionGuard>
                            <PermissionGuard permission="people.delete">
                              <button
                                type="button"
                                className="admin-btn-icon"
                                title="ลบ"
                                style={{ color: '#ef4444' }}
                                onClick={() => handleDeleteMember(m)}
                              >
                                <Trash2 size={15} />
                              </button>
                            </PermissionGuard>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      ) : (
        /* Staff Management Tab */
        <div>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
            <h3 style={{ margin: 0, fontSize: '1.1rem', color: '#1e293b' }}>รายชื่อฝ่ายจัดการและเจ้าหน้าที่สหกรณ์ ({staff.length})</h3>
            <PermissionGuard permission="people.create">
              <button
                type="button"
                className="admin-btn-primary"
                onClick={() => {
                  setStaffForm(INITIAL_STAFF_FORM);
                  setStaffDrawerOpen(true);
                }}
              >
                <Plus size={16} /> เพิ่มเจ้าหน้าที่
              </button>
            </PermissionGuard>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '1rem' }}>
            {staff.map((s) => (
              <div
                key={s.id}
                className="surface-card"
                style={{
                  padding: '1rem',
                  background: '#ffffff',
                  borderRadius: '10px',
                  border: '1px solid #e2e8f0',
                }}
              >
                <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center', marginBottom: '0.5rem' }}>
                  <img
                    src={resolveMediaUrl(s.photo, '/assets/img/logo.webp')}
                    alt={s.name}
                    style={{
                      width: '52px',
                      height: '52px',
                      borderRadius: '50%',
                      objectFit: 'cover',
                      border: '2px solid #e2e8f0',
                      background: '#f8fafc',
                    }}
                    onError={(e) => { e.currentTarget.src = '/assets/img/logo.webp'; }}
                  />
                  <div>
                    <h4 style={{ margin: 0, fontSize: '0.95rem', color: '#0f172a' }}>{s.name}</h4>
                    <div style={{ fontSize: '0.85rem', color: '#2563eb', fontWeight: 600 }}>{s.position}</div>
                    <div style={{ fontSize: '0.75rem', color: '#64748b' }}>{s.department || '-'}</div>
                  </div>
                </div>

                <div style={{ fontSize: '0.8rem', color: '#64748b', display: 'flex', flexDirection: 'column', gap: '0.2rem', marginBottom: '0.75rem' }}>
                  {s.phone && <div><Phone size={12} style={{ display: 'inline', marginRight: '0.25rem' }} />{s.phone}</div>}
                  {s.email && <div><Mail size={12} style={{ display: 'inline', marginRight: '0.25rem' }} />{s.email}</div>}
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', borderTop: '1px solid #f1f5f9', paddingTop: '0.5rem' }}>
                  <PermissionGuard permission="people.edit">
                    <button
                      type="button"
                      className="admin-btn-icon"
                      onClick={() => {
                        setStaffForm(s);
                        setStaffDrawerOpen(true);
                      }}
                    >
                      <Edit2 size={15} />
                    </button>
                  </PermissionGuard>
                  <PermissionGuard permission="people.delete">
                    <button
                      type="button"
                      className="admin-btn-icon"
                      style={{ color: '#ef4444' }}
                      onClick={async () => {
                        const confirm = await alertService.confirmDelete(s.name, 'เจ้าหน้าที่ท่านนี้');
                        if (!confirm) return;
                        try {
                          await memberAdminApi(`/api/admin/board-staff/staff/${s.id}`, { method: 'DELETE' });
                          alertService.toast({ icon: 'success', title: 'ลบข้อมูลสำเร็จ' });
                          setRefreshKey((k) => k + 1);
                        } catch (err) {
                          alertService.error(err.message || 'ลบไม่สำเร็จ');
                        }
                      }}
                    >
                      <Trash2 size={15} />
                    </button>
                  </PermissionGuard>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* --- DRAWERS & MODALS --- */}

      {/* Member Form Drawer (Directors, Advisors, Auditors) */}
      <CrudFormDrawer
        isOpen={memberDrawerOpen}
        onClose={() => setMemberDrawerOpen(false)}
        title={memberForm.id ? `แก้ไข${boardCategory === 'director' ? 'กรรมการ' : boardCategory === 'advisor' ? 'ที่ปรึกษา' : 'ผู้ตรวจสอบ'}` : `เพิ่ม${boardCategory === 'director' ? 'กรรมการ' : boardCategory === 'advisor' ? 'ที่ปรึกษา' : 'ผู้ตรวจสอบ'}`}
        onSubmit={handleSubmitMember}
        isBusy={formBusy}
      >
        <div className="admin-form-group">
          <label className="admin-form-label">ชื่อ-นามสกุล *</label>
          <input
            type="text"
            className="admin-form-input"
            value={memberForm.name}
            onChange={(e) => setMemberForm({ ...memberForm, name: e.target.value })}
            placeholder="เช่น นางสาวสมใจ รักษ์ดี"
            required
          />
          {formErrors.name && <span className="admin-form-error">{formErrors.name}</span>}
        </div>

        <div className="admin-form-group">
          <label className="admin-form-label">ตำแหน่ง *</label>
          <input
            type="text"
            className="admin-form-input"
            value={memberForm.position}
            onChange={(e) => setMemberForm({ ...memberForm, position: e.target.value })}
            placeholder="เช่น ประธานกรรมการ, รองประธาน, กรรมการ"
            required
          />
          {formErrors.position && <span className="admin-form-error">{formErrors.position}</span>}
        </div>

        {boardCategory === 'director' && (
          <div className="admin-form-group">
            <label className="admin-form-label">บทบาทหลัก</label>
            <select
              className="admin-form-select"
              value={memberForm.role_type}
              onChange={(e) => setMemberForm({ ...memberForm, role_type: e.target.value })}
            >
              <option value="president">ประธานกรรมการ (President)</option>
              <option value="vice_president">รองประธานกรรมการ (Vice President)</option>
              <option value="treasurer">เหรัญญิก (Treasurer)</option>
              <option value="secretary">เลขานุการ (Secretary)</option>
              <option value="director">กรรมการ (Director)</option>
            </select>
          </div>
        )}

        <div className="admin-form-group">
          <label className="admin-form-label">หน่วยงาน / ที่ทำงาน (Workplace)</label>
          <input
            type="text"
            className="admin-form-input"
            value={memberForm.workplace || ''}
            onChange={(e) => setMemberForm({ ...memberForm, workplace: e.target.value })}
            placeholder="เช่น โรงพยาบาลระยอง, สสจ.ระยอง"
          />
        </div>

        <div className="admin-form-group">
          <label className="admin-form-label">รูปถ่ายบุคลากร</label>
          <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginBottom: '0.5rem' }}>
            <input
              type="file"
              accept="image/*"
              onChange={handlePhotoUpload}
              disabled={uploadingPhoto}
              style={{ fontSize: '0.85rem' }}
            />
            {uploadingPhoto && <span style={{ fontSize: '0.8rem', color: '#2563eb' }}>กำลังอัปโหลด...</span>}
          </div>
          <input
            type="text"
            className="admin-form-input"
            value={memberForm.image_url || ''}
            onChange={(e) => setMemberForm({ ...memberForm, image_url: e.target.value })}
            placeholder="URL รูปภาพ เช่น /assets/img/committee/... หรือ /storage/uploads/..."
          />
          {memberForm.image_url && (
            <div style={{ marginTop: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <img
                src={resolveMediaUrl(memberForm.image_url)}
                alt="Preview"
                style={{ width: '48px', height: '48px', borderRadius: '50%', objectFit: 'cover', border: '1px solid #cbd5e1' }}
              />
              <span style={{ fontSize: '0.8rem', color: '#64748b' }}>ตัวอย่างรูปถ่าย</span>
            </div>
          )}
        </div>

        {boardCategory === 'director' && (
          <div className="admin-form-group" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <input
              type="checkbox"
              id="is_chairperson"
              checked={memberForm.is_chairperson}
              onChange={(e) => setMemberForm({ ...memberForm, is_chairperson: e.target.checked })}
            />
            <label htmlFor="is_chairperson" style={{ fontWeight: 600, color: '#1e293b', cursor: 'pointer' }}>
              เป็นประธานกรรมการ (Chairperson)
            </label>
          </div>
        )}
      </CrudFormDrawer>

      {/* Staff Form Drawer */}
      <CrudFormDrawer
        isOpen={staffDrawerOpen}
        onClose={() => setStaffDrawerOpen(false)}
        title={staffForm.id ? 'แก้ไขเจ้าหน้าที่' : 'เพิ่มเจ้าหน้าที่ใหม่'}
        onSubmit={async (e) => {
          e.preventDefault();
          setFormBusy(true);
          try {
            const endpoint = staffForm.id ? `/api/admin/board-staff/staff/${staffForm.id}` : '/api/admin/board-staff/staff';
            const method = staffForm.id ? 'PUT' : 'POST';
            await memberAdminApi(endpoint, { method, data: staffForm });
            alertService.toast({ icon: 'success', title: 'บันทึกข้อมูลเจ้าหน้าที่สำเร็จ' });
            setStaffDrawerOpen(false);
            setRefreshKey((k) => k + 1);
          } catch (err) {
            alertService.error(err.message || 'บันทึกไม่สำเร็จ');
          } finally {
            setFormBusy(false);
          }
        }}
        isBusy={formBusy}
      >
        <div className="admin-form-group">
          <label className="admin-form-label">ชื่อ-นามสกุล *</label>
          <input
            type="text"
            className="admin-form-input"
            value={staffForm.name}
            onChange={(e) => setStaffForm({ ...staffForm, name: e.target.value })}
            required
          />
        </div>
        <div className="admin-form-group">
          <label className="admin-form-label">ตำแหน่ง *</label>
          <input
            type="text"
            className="admin-form-input"
            value={staffForm.position}
            onChange={(e) => setStaffForm({ ...staffForm, position: e.target.value })}
            required
          />
        </div>
        <div className="admin-form-group">
          <label className="admin-form-label">ฝ่าย / กลุ่มงาน</label>
          <input
            type="text"
            className="admin-form-input"
            value={staffForm.department || ''}
            onChange={(e) => setStaffForm({ ...staffForm, department: e.target.value })}
          />
        </div>
        <div className="admin-form-group">
          <label className="admin-form-label">เบอร์โทรศัพท์</label>
          <input
            type="text"
            className="admin-form-input"
            value={staffForm.phone || ''}
            onChange={(e) => setStaffForm({ ...staffForm, phone: e.target.value })}
          />
        </div>
        <div className="admin-form-group">
          <label className="admin-form-label">อีเมล</label>
          <input
            type="email"
            className="admin-form-input"
            value={staffForm.email || ''}
            onChange={(e) => setStaffForm({ ...staffForm, email: e.target.value })}
          />
        </div>
        <div className="admin-form-group">
          <label className="admin-form-label">URL รูปถ่าย</label>
          <input
            type="text"
            className="admin-form-input"
            value={staffForm.photo || ''}
            onChange={(e) => setStaffForm({ ...staffForm, photo: e.target.value })}
          />
        </div>
      </CrudFormDrawer>

      {/* Create Term Modal */}
      {termModalOpen && (
        <div className="admin-modal-overlay" style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div className="surface-card" style={{ background: '#ffffff', borderRadius: '12px', padding: '1.5rem', width: '90%', maxWidth: '500px' }}>
            <h3 style={{ margin: '0 0 1rem 0', color: '#0f172a' }}>สร้างวาระคณะกรรมการใหม่</h3>
            <form onSubmit={handleSubmitCreateTerm}>
              <div className="admin-form-group">
                <label className="admin-form-label">หมายเลขวาระ (ตัวเลข) *</label>
                <input
                  type="number"
                  className="admin-form-input"
                  value={termForm.term_number}
                  onChange={(e) => setTermForm({ ...termForm, term_number: e.target.value, term_name: `ชุดที่ ${e.target.value}` })}
                  min={1}
                  required
                />
              </div>
              <div className="admin-form-group">
                <label className="admin-form-label">ชื่อวาระ *</label>
                <input
                  type="text"
                  className="admin-form-input"
                  value={termForm.term_name}
                  onChange={(e) => setTermForm({ ...termForm, term_name: e.target.value })}
                  required
                />
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
                <div className="admin-form-group">
                  <label className="admin-form-label">ปีเริ่มต้น (พ.ศ.)</label>
                  <input
                    type="number"
                    className="admin-form-input"
                    value={termForm.year_start_be}
                    onChange={(e) => setTermForm({ ...termForm, year_start_be: e.target.value })}
                  />
                </div>
                <div className="admin-form-group">
                  <label className="admin-form-label">ปีสิ้นสุด (พ.ศ.)</label>
                  <input
                    type="number"
                    className="admin-form-input"
                    value={termForm.year_end_be}
                    onChange={(e) => setTermForm({ ...termForm, year_end_be: e.target.value })}
                  />
                </div>
              </div>
              <div className="admin-form-group">
                <label className="admin-form-label">หมายเหตุ</label>
                <input
                  type="text"
                  className="admin-form-input"
                  value={termForm.note}
                  onChange={(e) => setTermForm({ ...termForm, note: e.target.value })}
                  placeholder="เช่น เตรียมการสำหรับการเลือกตั้งประจำปี"
                />
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1.25rem' }}>
                <button type="button" className="admin-btn-secondary" onClick={() => setTermModalOpen(false)}>
                  ยกเลิก
                </button>
                <button type="submit" className="admin-btn-primary" disabled={formBusy}>
                  สร้างวาระ (ฉบับร่าง)
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Copy Term Modal */}
      {copyModalOpen && (
        <div className="admin-modal-overlay" style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div className="surface-card" style={{ background: '#ffffff', borderRadius: '12px', padding: '1.5rem', width: '90%', maxWidth: '500px' }}>
            <h3 style={{ margin: '0 0 0.5rem 0', color: '#0f172a' }}>คัดลอกวาระไปยังฉบับร่างใหม่</h3>
            <p style={{ margin: '0 0 1rem 0', fontSize: '0.85rem', color: '#64748b' }}>
              ระบบจะคัดลอกรายชื่อกรรมการ, ที่ปรึกษา, ผู้ตรวจสอบ และคณะย่อยจาก <strong>{currentTerm?.term_name}</strong> ไปยังวาระใหม่โดยสมบูรณ์
            </p>
            <form onSubmit={handleSubmitCopyTerm}>
              <div className="admin-form-group">
                <label className="admin-form-label">หมายเลขวาระใหม่ (ตัวเลข) *</label>
                <input
                  type="number"
                  className="admin-form-input"
                  value={copyForm.term_number}
                  onChange={(e) => setCopyForm({ ...copyForm, term_number: e.target.value, term_name: `ชุดที่ ${e.target.value}` })}
                  min={1}
                  required
                />
              </div>
              <div className="admin-form-group">
                <label className="admin-form-label">ชื่อวาระใหม่ *</label>
                <input
                  type="text"
                  className="admin-form-input"
                  value={copyForm.term_name}
                  onChange={(e) => setCopyForm({ ...copyForm, term_name: e.target.value })}
                  required
                />
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1.25rem' }}>
                <button type="button" className="admin-btn-secondary" onClick={() => setCopyModalOpen(false)}>
                  ยกเลิก
                </button>
                <button type="submit" className="admin-btn-primary" disabled={formBusy}>
                  ยืนยันการคัดลอก
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Schedule Modal */}
      {scheduleModalOpen && (
        <div className="admin-modal-overlay" style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div className="surface-card" style={{ background: '#ffffff', borderRadius: '12px', padding: '1.5rem', width: '90%', maxWidth: '450px' }}>
            <h3 style={{ margin: '0 0 0.5rem 0', color: '#0f172a' }}>ตั้งเวลาเผยแพร่ {currentTerm?.term_name}</h3>
            <p style={{ margin: '0 0 1rem 0', fontSize: '0.85rem', color: '#64748b' }}>
              เมื่อถึงเวลาที่กำหนด ระบบจะปรับวาระนี้ให้เป็นวาระที่เผยแพร่อัตโนมัติ
            </p>
            <form onSubmit={handleSubmitSchedule}>
              <div className="admin-form-group">
                <label className="admin-form-label">วันและเวลาที่ต้องการเผยแพร่ *</label>
                <input
                  type="datetime-local"
                  className="admin-form-input"
                  value={scheduleForm.publish_at}
                  onChange={(e) => setScheduleForm({ publish_at: e.target.value })}
                  required
                />
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1.25rem' }}>
                <button type="button" className="admin-btn-secondary" onClick={() => setScheduleModalOpen(false)}>
                  ยกเลิก
                </button>
                <button type="submit" className="admin-btn-primary" disabled={formBusy}>
                  บันทึกการตั้งเวลา
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Group Modal */}
      {groupModalOpen && (
        <div className="admin-modal-overlay" style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div className="surface-card" style={{ background: '#ffffff', borderRadius: '12px', padding: '1.5rem', width: '90%', maxWidth: '480px' }}>
            <h3 style={{ margin: '0 0 1rem 0', color: '#0f172a' }}>เพิ่มคณะกรรมการย่อย</h3>
            <form onSubmit={handleSaveGroup}>
              <div className="admin-form-group">
                <label className="admin-form-label">ชื่อคณะกรรมการย่อย *</label>
                <input
                  type="text"
                  className="admin-form-input"
                  value={groupForm.title}
                  onChange={(e) => setGroupForm({ ...groupForm, title: e.target.value })}
                  placeholder="เช่น คณะกรรมการเงินกู้"
                  required
                />
              </div>
              <div className="admin-form-group">
                <label className="admin-form-label">คำอธิบาย</label>
                <input
                  type="text"
                  className="admin-form-input"
                  value={groupForm.description}
                  onChange={(e) => setGroupForm({ ...groupForm, description: e.target.value })}
                  placeholder="รายละเอียดหน้าที่ความรับผิดชอบ"
                />
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1.25rem' }}>
                <button type="button" className="admin-btn-secondary" onClick={() => setGroupModalOpen(false)}>
                  ยกเลิก
                </button>
                <button type="submit" className="admin-btn-primary">
                  บันทึก
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Assign Member to Group Modal */}
      {assignMemberModalOpen && (
        <div className="admin-modal-overlay" style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div className="surface-card" style={{ background: '#ffffff', borderRadius: '12px', padding: '1.5rem', width: '90%', maxWidth: '450px' }}>
            <h3 style={{ margin: '0 0 1rem 0', color: '#0f172a' }}>มอบหมายกรรมการในคณะย่อย</h3>
            <form onSubmit={handleSaveAssignMember}>
              <div className="admin-form-group">
                <label className="admin-form-label">เลือกกรรมการ *</label>
                <select
                  className="admin-form-select"
                  value={assignForm.member_id}
                  onChange={(e) => setAssignForm({ ...assignForm, member_id: e.target.value })}
                  required
                >
                  <option value="">-- กรุณาเลือกกรรมการ --</option>
                  {(termDetails?.directors || []).map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name} ({d.position})
                    </option>
                  ))}
                </select>
              </div>
              <div className="admin-form-group">
                <label className="admin-form-label">บทบาทในคณะย่อย *</label>
                <input
                  type="text"
                  className="admin-form-input"
                  value={assignForm.role_in_group}
                  onChange={(e) => setAssignForm({ ...assignForm, role_in_group: e.target.value })}
                  placeholder="เช่น ประธานคณะกรรมการ, กรรมการ, เลขานุการ"
                  required
                />
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1.25rem' }}>
                <button type="button" className="admin-btn-secondary" onClick={() => setAssignMemberModalOpen(false)}>
                  ยกเลิก
                </button>
                <button type="submit" className="admin-btn-primary">
                  บันทึก
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Public Preview Modal */}
      {previewModalOpen && currentTerm && (
        <div className="admin-modal-overlay" style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.7)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1100, padding: '1rem' }}>
          <div className="surface-card" style={{ background: '#ffffff', borderRadius: '16px', width: '100%', maxWidth: '960px', maxHeight: '90vh', overflowY: 'auto', padding: '2rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #e2e8f0', paddingBottom: '1rem', marginBottom: '1.5rem' }}>
              <div>
                <span style={{ fontSize: '0.8rem', color: '#2563eb', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                  ตัวอย่างการแสดงผลหน้าสาธารณะ (/board_committee)
                </span>
                <h2 style={{ margin: '0.25rem 0 0 0', color: '#0f172a' }}>
                  ทำเนียบคณะกรรมการดำเนินการ {currentTerm.term_name}
                </h2>
              </div>
              <button
                type="button"
                className="admin-btn-secondary"
                onClick={() => setPreviewModalOpen(false)}
              >
                ปิดหน้าต่าง
              </button>
            </div>

            {/* Simulated Public Layout */}
            <div style={{ background: '#f8fafc', padding: '1.5rem', borderRadius: '12px' }}>
              <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
                <h3 style={{ margin: 0, color: '#1e3a8a', fontSize: '1.35rem' }}>
                  คณะกรรมการดำเนินงาน {currentTerm.term_name}
                </h3>
                <p style={{ margin: '0.5rem 0 0 0', color: '#64748b', fontSize: '0.9rem' }}>
                  สหกรณ์ออมทรัพย์สาธารณสุขจังหวัดระยอง จำกัด
                </p>
              </div>

              {/* Directors Grid */}
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: '1rem', marginBottom: '2rem' }}>
                {(termDetails?.directors || []).map((d) => (
                  <div
                    key={d.id}
                    style={{
                      background: '#ffffff',
                      borderRadius: '10px',
                      padding: '1rem',
                      textAlign: 'center',
                      boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
                    }}
                  >
                    <img
                      src={resolveMediaUrl(d.image_url, '/assets/img/logo.webp')}
                      alt={d.name}
                      style={{
                        width: '80px',
                        height: '80px',
                        borderRadius: '50%',
                        objectFit: 'cover',
                        margin: '0 auto 0.75rem auto',
                        display: 'block',
                        border: '2px solid #e2e8f0',
                      }}
                      onError={(e) => { e.currentTarget.src = '/assets/img/logo.webp'; }}
                    />
                    <div style={{ fontWeight: 600, fontSize: '0.9rem', color: '#0f172a' }}>{d.name}</div>
                    <div style={{ fontSize: '0.8rem', color: '#2563eb', marginTop: '0.2rem' }}>{d.position}</div>
                    {d.workplace && <div style={{ fontSize: '0.75rem', color: '#64748b', marginTop: '0.2rem' }}>{d.workplace}</div>}
                  </div>
                ))}
              </div>

              {/* Subgroups */}
              {(termDetails?.groups || []).map((grp) => (
                <div key={grp.id} style={{ background: '#ffffff', borderRadius: '10px', padding: '1.25rem', marginBottom: '1rem' }}>
                  <h4 style={{ margin: '0 0 0.5rem 0', color: '#1e293b' }}>{grp.title}</h4>
                  <ul style={{ margin: 0, paddingLeft: '1.25rem', color: '#475569', fontSize: '0.9rem' }}>
                    {(grp.members || []).map((m) => (
                      <li key={m.link_id || m.member_id || m.id} style={{ padding: '0.2rem 0' }}>
                        {m.name} — {m.role_in_group}
                      </li>
                    ))}
                  </ul>
                </div>
              ))}

              {/* Advisors & Auditors */}
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
                <div style={{ background: '#ffffff', borderRadius: '10px', padding: '1.25rem' }}>
                  <h4 style={{ margin: '0 0 0.5rem 0', color: '#1e293b' }}>ที่ปรึกษาสหกรณ์</h4>
                  <ul style={{ margin: 0, paddingLeft: '1.25rem', color: '#475569', fontSize: '0.9rem' }}>
                    {(termDetails?.advisors || []).map((adv) => (
                      <li key={adv.id} style={{ padding: '0.2rem 0' }}>
                        {adv.name} — {adv.position}
                      </li>
                    ))}
                  </ul>
                </div>
                <div style={{ background: '#ffffff', borderRadius: '10px', padding: '1.25rem' }}>
                  <h4 style={{ margin: '0 0 0.5rem 0', color: '#1e293b' }}>คณะผู้ตรวจสอบกิจการสหกรณ์</h4>
                  <ul style={{ margin: 0, paddingLeft: '1.25rem', color: '#475569', fontSize: '0.9rem' }}>
                    {(termDetails?.auditors || []).map((aud) => (
                      <li key={aud.id} style={{ padding: '0.2rem 0' }}>
                        {aud.name} — {aud.position}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
