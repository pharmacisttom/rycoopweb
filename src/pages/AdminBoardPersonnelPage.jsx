import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import {
  Users,
  Award,
  Briefcase,
  Plus,
  Edit2,
  Trash2,
  ArrowUp,
  ArrowDown,
  Eye,
  CheckCircle2,
  AlertCircle,
  Clock,
  Upload,
  ExternalLink,
  ShieldCheck,
  RefreshCw,
  FileText,
  ChevronRight,
  X,
  Building,
  UserCheck,
  Search,
  Filter,
  Layers,
  Image as ImageIcon
} from 'lucide-react';
import { memberAdminApi } from '../services/memberAdminApi';
import alertService from '../services/alertService';
import { usePublicContent } from '../context/PublicContentContext';
import SafeImage from '../components/common/SafeImage';
import { resolveMediaUrl } from '../utils/media';
import {
  PageHeader,
  CrudDataTable,
  CrudFormDrawer,
  CrudDetailModal,
  StatusBadge,
  EmptyState,
  LoadingSkeleton,
  PermissionGuard
} from '../components/admin/design-system';

const TAB_CONFIGS = [
  { key: 'boards', label: 'คณะกรรมการ', icon: LandmarkIcon, desc: 'รายชื่อคณะกรรมการดำเนินการ ชุดปัจจุบันและวาระต่างๆ' },
  { key: 'staff', label: 'ฝ่ายจัดการและเจ้าหน้าที่', icon: Users, desc: 'รายชื่อฝ่ายจัดการ ผู้จัดการ และเจ้าหน้าที่สหกรณ์' },
  { key: 'advisors', label: 'ที่ปรึกษาสหกรณ์', icon: Award, desc: 'ที่ปรึกษากิตติมศักดิ์และที่ปรึกษาด้านต่างๆ' },
  { key: 'auditors', label: 'ผู้ตรวจสอบกิจการ', icon: ShieldCheck, desc: 'ประธานและคณะผู้ตรวจสอบกิจการสหกรณ์' },
  { key: 'groups', label: 'คณะกรรมการย่อย', icon: Layers, desc: 'คณะกรรมการอำนวยการ เงินกู้ และศึกษาประชาสัมพันธ์' },
  { key: 'history', label: 'ประวัติและร่างรอเผยแพร่', icon: Clock, desc: 'ตรวจสอบร่างและประวัติการเผยแพร่ข้อมูลบุคลากร' }
];

function LandmarkIcon(props) {
  return <Briefcase {...props} />;
}

const todayBangkok = () =>
  new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Bangkok',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).format(new Date());

export default function AdminBoardPersonnelPage() {
  const { reload: reloadPublicContent } = usePublicContent();

  const [activeTab, setActiveTab] = useState('boards');
  const [sectionsMeta, setSectionsMeta] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);

  // Loaded section data: { [key]: { value, version, history } }
  const [sectionsData, setSectionsData] = useState({});
  // Working editable draft copy: { [key]: Array }
  const [draftCopies, setDraftCopies] = useState({});

  // Filter / Search
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedTermFilter, setSelectedTermFilter] = useState('ALL');

  // Form Drawer state
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editingIndex, setEditingIndex] = useState(null); // null = create, number = index
  const [formData, setFormData] = useState({});
  const [formErrors, setFormErrors] = useState({});
  const [uploadingImage, setUploadingImage] = useState(false);
  const fileInputRef = useRef(null);

  // Detail Modal state
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  // Draft Save Modal state
  const [saveDraftModalOpen, setSaveDraftModalOpen] = useState(false);
  const [draftNote, setDraftNote] = useState('');
  const [draftSourceUrl, setDraftSourceUrl] = useState('https://rayongcoop.com/');
  const [draftEffectiveOn, setDraftEffectiveOn] = useState(todayBangkok());
  const [draftExpiresOn, setDraftExpiresOn] = useState('');
  const [savingDraft, setSavingDraft] = useState(false);

  // Preview / Diff Modal state
  const [previewModalOpen, setPreviewModalOpen] = useState(false);
  const [publishingId, setPublishingId] = useState(null);

  // ─────────────────────────────────────────────────────────────────────────
  // 1. Initial Load & Fetch Sections Meta
  // ─────────────────────────────────────────────────────────────────────────
  useEffect(() => {
    const controller = new AbortController();
    memberAdminApi('/api/admin/public-content/sections', { signal: controller.signal })
      .then((res) => {
        if (!controller.signal.aborted && res.sections) {
          const map = {};
          res.sections.forEach((s) => {
            map[s.key] = s;
          });
          setSectionsMeta(map);
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          setError(err.message || 'ไม่สามารถโหลดข้อมูลสิทธิ์การจัดการได้');
        }
      });

    return () => controller.abort();
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // 2. Load Personnel Sections Data
  // ─────────────────────────────────────────────────────────────────────────
  const fetchAllPersonnelSections = useCallback(async (signal) => {
    setLoading(true);
    setError('');

    try {
      const keys = ['boards', 'staff', 'advisors', 'auditors', 'groups'];
      const responses = await Promise.all(
        keys.map((k) =>
          memberAdminApi(`/api/admin/public-content/sections/${k}`, { signal })
            .then((data) => ({ key: k, data }))
            .catch((err) => ({ key: k, error: err }))
        )
      );

      const newData = {};
      const newDrafts = {};

      responses.forEach(({ key, data, error: reqErr }) => {
        if (data && !reqErr) {
          newData[key] = data;
          newDrafts[key] = JSON.parse(JSON.stringify(data.value || []));
        }
      });

      setSectionsData(newData);
      setDraftCopies(newDrafts);
    } catch (err) {
      if (err.name !== 'AbortError') {
        setError(err.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูลบุคลากร');
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    fetchAllPersonnelSections(controller.signal);
    return () => controller.abort();
  }, [fetchAllPersonnelSections, refreshKey]);

  // Current tab metadata & dirty checking
  const activeSectionKey = activeTab === 'history' ? 'boards' : activeTab;
  const currentMeta = sectionsMeta[activeSectionKey] || { canEdit: true, canPublish: true };
  const currentSectionData = sectionsData[activeSectionKey] || { value: [], version: '', history: [] };
  const currentWorkingList = draftCopies[activeSectionKey] || [];

  const isTabDirty = useMemo(() => {
    if (activeTab === 'history') return false;
    const published = JSON.stringify(currentSectionData.value || []);
    const working = JSON.stringify(currentWorkingList);
    return published !== working;
  }, [activeTab, currentSectionData.value, currentWorkingList]);

  // Available Terms in Boards (e.g. ["ชุดที่ 38"])
  const availableTerms = useMemo(() => {
    if (activeSectionKey !== 'boards') return [];
    const set = new Set();
    currentWorkingList.forEach((b) => {
      if (b.term) set.add(b.term);
    });
    return Array.from(set);
  }, [activeSectionKey, currentWorkingList]);

  // Lookup board names by ID for board groups
  const boardNamesMap = useMemo(() => {
    const boards = draftCopies.boards || currentSectionData.value || [];
    const map = {};
    boards.forEach((b) => {
      if (b.id) map[b.id] = b.name;
    });
    return map;
  }, [draftCopies.boards, currentSectionData.value]);

  // Find latest pending draft in history for active section
  const latestPendingDraft = useMemo(() => {
    const history = currentSectionData.history || [];
    return history.find((h) => h.state === 'draft');
  }, [currentSectionData.history]);

  // Filtered list according to search term and board term filter
  const filteredList = useMemo(() => {
    if (activeTab === 'history') return [];
    let list = currentWorkingList;

    if (activeSectionKey === 'boards' && selectedTermFilter !== 'ALL') {
      list = list.filter((b) => b.term === selectedTermFilter);
    }

    if (searchTerm.trim() !== '') {
      const q = searchTerm.toLowerCase();
      list = list.filter((item) => {
        if (activeSectionKey === 'advisors' || activeSectionKey === 'auditors') {
          return (item[0] || '').toLowerCase().includes(q) || (item[1] || '').toLowerCase().includes(q);
        }
        if (activeSectionKey === 'groups') {
          return (item.title || '').toLowerCase().includes(q);
        }
        return (
          (item.name || '').toLowerCase().includes(q) ||
          (item.position || '').toLowerCase().includes(q) ||
          (item.workplace || '').toLowerCase().includes(q) ||
          (item.department || '').toLowerCase().includes(q)
        );
      });
    }

    return list;
  }, [activeTab, currentWorkingList, activeSectionKey, selectedTermFilter, searchTerm]);

  // ─────────────────────────────────────────────────────────────────────────
  // 3. Item Reordering (Up / Down)
  // ─────────────────────────────────────────────────────────────────────────
  const handleMove = (indexInFiltered, direction) => {
    if (!currentMeta.canEdit) return;
    const targetItem = filteredList[indexInFiltered];
    const actualIndex = currentWorkingList.indexOf(targetItem);
    if (actualIndex === -1) return;

    const newIndex = actualIndex + direction;
    if (newIndex < 0 || newIndex >= currentWorkingList.length) return;

    setDraftCopies((prev) => {
      const copy = [...prev[activeSectionKey]];
      const [moved] = copy.splice(actualIndex, 1);
      copy.splice(newIndex, 0, moved);
      return { ...prev, [activeSectionKey]: copy };
    });
  };

  // ─────────────────────────────────────────────────────────────────────────
  // 4. Form Drawer Handlers (Add / Edit)
  // ─────────────────────────────────────────────────────────────────────────
  const handleOpenCreate = () => {
    setEditingIndex(null);
    setFormErrors({});

    if (activeSectionKey === 'boards') {
      const maxId = Math.max(0, ...(currentWorkingList.map((b) => Number(b.id) || 0)));
      setFormData({
        id: maxId + 1,
        name: '',
        position: 'กรรมการ',
        term: availableTerms[0] || 'ชุดที่ 38',
        workplace: '',
        image: ''
      });
    } else if (activeSectionKey === 'staff') {
      const maxId = Math.max(0, ...(currentWorkingList.map((s) => Number(s.id) || 0)));
      setFormData({
        id: maxId + 1,
        name: '',
        position: 'เจ้าหน้าที่',
        department: 'ฝ่ายจัดการ',
        image: ''
      });
    } else if (activeSectionKey === 'advisors') {
      setFormData({ name: '', position: 'ที่ปรึกษา' });
    } else if (activeSectionKey === 'auditors') {
      setFormData({ name: '', position: 'ผู้ตรวจสอบกิจการ' });
    } else if (activeSectionKey === 'groups') {
      setFormData({ title: '', members: [] });
    }

    setDrawerOpen(true);
  };

  const handleOpenEdit = (item) => {
    const actualIndex = currentWorkingList.indexOf(item);
    setEditingIndex(actualIndex);
    setFormErrors({});

    if (activeSectionKey === 'boards') {
      setFormData({
        id: item.id,
        name: item.name || '',
        position: item.position || '',
        term: item.term || 'ชุดที่ 38',
        workplace: item.workplace || '',
        image: item.image || ''
      });
    } else if (activeSectionKey === 'staff') {
      setFormData({
        id: item.id,
        name: item.name || '',
        position: item.position || '',
        department: item.department || '',
        image: item.image || ''
      });
    } else if (activeSectionKey === 'advisors' || activeSectionKey === 'auditors') {
      setFormData({
        name: item[0] || '',
        position: item[1] || ''
      });
    } else if (activeSectionKey === 'groups') {
      setFormData({
        title: item.title || '',
        members: item.members ? JSON.parse(JSON.stringify(item.members)) : []
      });
    }

    setDrawerOpen(true);
  };

  const handleFormChange = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (formErrors[field]) {
      setFormErrors((prev) => ({ ...prev, [field]: null }));
    }
  };

  const handleSaveForm = (e) => {
    e.preventDefault();
    const errors = {};

    if (activeSectionKey === 'boards' || activeSectionKey === 'staff') {
      if (!formData.name?.trim()) errors.name = 'กรุณาระบุชื่อ-นามสกุล';
      if (!formData.position?.trim()) errors.position = 'กรุณาระบุตำแหน่ง';
    } else if (activeSectionKey === 'advisors' || activeSectionKey === 'auditors') {
      if (!formData.name?.trim()) errors.name = 'กรุณาระบุชื่อ-นามสกุล';
      if (!formData.position?.trim()) errors.position = 'กรุณาระบุตำแหน่ง';
    } else if (activeSectionKey === 'groups') {
      if (!formData.title?.trim()) errors.title = 'กรุณาระบุชื่อคณะกรรมการย่อย';
    }

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      return;
    }

    let newItem;
    if (activeSectionKey === 'boards') {
      newItem = {
        id: Number(formData.id),
        name: formData.name.trim(),
        position: formData.position.trim(),
        term: formData.term ? formData.term.trim() : null,
        workplace: formData.workplace ? formData.workplace.trim() : null,
        image: formData.image ? formData.image.trim() : null
      };
    } else if (activeSectionKey === 'staff') {
      newItem = {
        id: Number(formData.id),
        name: formData.name.trim(),
        position: formData.position.trim(),
        department: formData.department ? formData.department.trim() : null,
        image: formData.image ? formData.image.trim() : null
      };
    } else if (activeSectionKey === 'advisors' || activeSectionKey === 'auditors') {
      newItem = [formData.name.trim(), formData.position.trim()];
    } else if (activeSectionKey === 'groups') {
      newItem = {
        title: formData.title.trim(),
        members: formData.members || []
      };
    }

    setDraftCopies((prev) => {
      const copy = [...prev[activeSectionKey]];
      if (editingIndex !== null && editingIndex >= 0) {
        copy[editingIndex] = newItem;
      } else {
        copy.push(newItem);
      }
      return { ...prev, [activeSectionKey]: copy };
    });

    setDrawerOpen(false);
    alertService.success(editingIndex !== null ? 'แก้ไขข้อมูลในร่างแล้ว' : 'เพิ่มรายการใหม่ในร่างแล้ว');
  };

  const handleDeleteItem = (item) => {
    if (!currentMeta.canEdit) return;

    if (activeSectionKey === 'boards') {
      // Check if board member is in any sub-committee
      const groups = draftCopies.groups || [];
      const inGroups = groups.filter((g) => g.members && g.members.some(([id]) => Number(id) === Number(item.id)));
      if (inGroups.length > 0) {
        const groupTitles = inGroups.map((g) => g.title).join(', ');
        alertService.error(`ไม่สามารถลบ "${item.name}" ได้ เนื่องจากยังมีตำแหน่งในคณะกรรมการย่อย: ${groupTitles}`);
        return;
      }
    }

    const itemName =
      item.name || (Array.isArray(item) ? item[0] : item.title) || 'รายการนี้';

    if (window.confirm(`คุณแน่ใจหรือไม่ว่าต้องการนำ "${itemName}" ออกจากร่าง? (ข้อมูลจริงจะไม่เปลี่ยนจนกว่าจะอนุมัติเผยแพร่)`)) {
      setDraftCopies((prev) => {
        const copy = prev[activeSectionKey].filter((i) => i !== item);
        return { ...prev, [activeSectionKey]: copy };
      });
      alertService.info(`นำ "${itemName}" ออกจากร่างแล้ว`);
    }
  };

  // Image Upload Handler
  const handleUploadFile = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadingImage(true);
    try {
      const uploadForm = new FormData();
      uploadForm.append('file', file);

      const res = await memberAdminApi('/api/admin/public-content/upload', {
        method: 'POST',
        data: uploadForm,
        headers: {} // let browser set boundary
      });

      if (res.success && res.data?.url) {
        handleFormChange('image', res.data.url);
        alertService.success('อัปโหลดรูปภาพสำเร็จ');
      } else {
        throw new Error(res.message || 'อัปโหลดไม่สำเร็จ');
      }
    } catch (err) {
      alertService.error(err.message || 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ');
    } finally {
      setUploadingImage(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // 5. Draft Save Workflow (POST /api/admin/public-content/sections/{section}/drafts)
  // ─────────────────────────────────────────────────────────────────────────
  const handleOpenSaveDraftModal = () => {
    setDraftNote(`ปรับปรุงข้อมูล ${TAB_CONFIGS.find((t) => t.key === activeSectionKey)?.label || ''}`);
    setDraftSourceUrl('https://rayongcoop.com/');
    setDraftEffectiveOn(todayBangkok());
    setDraftExpiresOn('');
    setSaveDraftModalOpen(true);
  };

  const handleConfirmSaveDraft = async (e) => {
    e.preventDefault();
    if (!draftNote.trim()) {
      alertService.error('กรุณาระบุเหตุผลการแก้ไข');
      return;
    }
    if (!draftSourceUrl.trim() || !draftSourceUrl.startsWith('https://')) {
      alertService.error('กรุณาระบุแหล่งอ้างอิง HTTPS ที่ถูกต้อง');
      return;
    }

    setSavingDraft(true);
    try {
      const payload = {
        value: currentWorkingList,
        version: currentSectionData.version,
        note: draftNote.trim(),
        source_url: draftSourceUrl.trim(),
        effective_on: draftEffectiveOn,
        expires_on: draftExpiresOn ? draftExpiresOn : null
      };

      const result = await memberAdminApi(
        `/api/admin/public-content/sections/${activeSectionKey}/drafts`,
        {
          method: 'POST',
          data: payload
        }
      );

      setSaveDraftModalOpen(false);
      alertService.success(`บันทึกร่าง #${result.id} เรียบร้อยแล้ว (ยังไม่เปลี่ยนข้อมูลหน้าเว็บจนกว่าจะเผยแพร่)`);
      setRefreshKey((n) => n + 1);
    } catch (err) {
      alertService.error(err.message || 'บันทึกร่างไม่สำเร็จ');
    } finally {
      setSavingDraft(false);
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // 6. Publication Workflow (POST /api/admin/public-content/revisions/{id}/publish)
  // ─────────────────────────────────────────────────────────────────────────
  const handlePublishRevision = async (revisionId) => {
    if (!window.confirm(`ยืนยันการอนุมัติและเผยแพร่ร่าง #${revisionId}? ข้อมูลบนหน้าเว็บจริงจะอัปเดตทันที`)) {
      return;
    }

    setPublishingId(revisionId);
    try {
      await memberAdminApi(`/api/admin/public-content/revisions/${revisionId}/publish`, {
        method: 'POST',
        data: {}
      });

      alertService.success('อนุมัติและเผยแพร่ข้อมูลสำเร็จ ข้อมูลแสดงบนหน้าเว็บแล้ว');
      await reloadPublicContent();
      setRefreshKey((n) => n + 1);
    } catch (err) {
      alertService.error(err.message || 'เกิดข้อผิดพลาดในการเผยแพร่ร่าง');
    } finally {
      setPublishingId(null);
    }
  };

  const handleDiscardChanges = () => {
    if (window.confirm('คุณต้องการทิ้งการเปลี่ยนแปลงร่างที่ยังไม่ได้บันทึก และโหลดข้อมูลเผยแพร่ล่าสุดใช่หรือไม่?')) {
      setDraftCopies((prev) => ({
        ...prev,
        [activeSectionKey]: JSON.parse(JSON.stringify(currentSectionData.value || []))
      }));
      alertService.info('คืนค่าข้อมูลเผยแพร่ล่าสุดเรียบร้อยแล้ว');
    }
  };

  const handleLoadRevisionAsDraft = (revision) => {
    if (window.confirm(`คุณต้องการโหลดข้อมูลจากเวอร์ชัน #${revision.id} มาเป็นร่างที่กำลังแก้ไขหรือไม่?`)) {
      try {
        const payloadData = JSON.parse(revision.payload);
        setDraftCopies((prev) => ({
          ...prev,
          [revision.section]: payloadData
        }));
        if (activeTab !== revision.section) {
          setActiveTab(revision.section);
        }
        alertService.success(`โหลดข้อมูลจาก #${revision.id} เข้าสู่พื้นที่แก้ไขแล้ว`);
      } catch (err) {
        alertService.error('ไม่สามารถอ่านข้อมูลร่างได้: ' + err.message);
      }
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // 7. Render Columns for CrudDataTable
  // ─────────────────────────────────────────────────────────────────────────
  const tableColumns = useMemo(() => {
    if (activeSectionKey === 'boards') {
      return [
        {
          key: 'image',
          label: 'รูปภาพ',
          width: '70px',
          render: (row) => (
            <div style={{ width: '48px', height: '48px', borderRadius: '50%', overflow: 'hidden', border: '2px solid var(--primary-100)', background: 'var(--bg-subtle)' }}>
              <SafeImage
                src={row.image}
                fallback="/assets/img/board_placeholder.jpg"
                alt={row.name}
                style={{ width: '100%', height: '100%', objectFit: 'cover' }}
              />
            </div>
          )
        },
        {
          key: 'name',
          label: 'ชื่อ - นามสกุล',
          render: (row) => (
            <div>
              <div style={{ fontWeight: 600, color: 'var(--primary-900)' }}>{row.name}</div>
              {row.workplace && (
                <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                  <Building size={13} />
                  <span>{row.workplace}</span>
                </div>
              )}
            </div>
          )
        },
        {
          key: 'position',
          label: 'ตำแหน่ง',
          render: (row) => (
            <span style={{ fontWeight: 600, color: 'var(--accent-gold-dark)' }}>
              {row.position}
            </span>
          )
        },
        {
          key: 'term',
          label: 'วาระ / ชุดที่',
          render: (row) => (
            <span className="badge badge-primary">{row.term || 'ชุดที่ 38'}</span>
          )
        },
        {
          key: 'order',
          label: 'ลำดับ',
          width: '90px',
          render: (row, index) => (
            <div style={{ display: 'flex', gap: '0.25rem' }}>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                disabled={!currentMeta.canEdit || index === 0}
                onClick={() => handleMove(index, -1)}
                title="เลื่อนขึ้น"
                style={{ padding: '0.25rem 0.4rem' }}
              >
                <ArrowUp size={14} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                disabled={!currentMeta.canEdit || index === filteredList.length - 1}
                onClick={() => handleMove(index, 1)}
                title="เลื่อนลง"
                style={{ padding: '0.25rem 0.4rem' }}
              >
                <ArrowDown size={14} />
              </button>
            </div>
          )
        },
        {
          key: 'actions',
          label: 'จัดการ',
          width: '110px',
          render: (row) => (
            <div style={{ display: 'flex', gap: '0.35rem' }}>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                onClick={() => {
                  setSelectedItem(row);
                  setDetailModalOpen(true);
                }}
                title="ดูรายละเอียด"
              >
                <Eye size={15} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-primary"
                disabled={!currentMeta.canEdit}
                onClick={() => handleOpenEdit(row)}
                title="แก้ไข"
              >
                <Edit2 size={15} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-danger"
                disabled={!currentMeta.canEdit}
                onClick={() => handleDeleteItem(row)}
                title="นำออก"
              >
                <Trash2 size={15} />
              </button>
            </div>
          )
        }
      ];
    }

    if (activeSectionKey === 'staff') {
      return [
        {
          key: 'image',
          label: 'รูปภาพ',
          width: '70px',
          render: (row) => (
            <div style={{ width: '48px', height: '48px', borderRadius: '50%', overflow: 'hidden', border: '2px solid var(--accent-teal-subtle)', background: 'var(--bg-subtle)' }}>
              <SafeImage
                src={row.image}
                fallback="/assets/img/board_placeholder.jpg"
                alt={row.name}
                style={{ width: '100%', height: '100%', objectFit: 'cover' }}
              />
            </div>
          )
        },
        {
          key: 'name',
          label: 'ชื่อ - นามสกุล',
          render: (row) => (
            <div style={{ fontWeight: 600, color: 'var(--primary-900)' }}>{row.name}</div>
          )
        },
        {
          key: 'position',
          label: 'ตำแหน่ง',
          render: (row) => (
            <span style={{ fontWeight: 600, color: 'var(--accent-teal-dark)' }}>
              {row.position}
            </span>
          )
        },
        {
          key: 'department',
          label: 'ฝ่าย / หน่วยงาน',
          render: (row) => (
            <span className="badge badge-teal">{row.department || 'ฝ่ายจัดการ'}</span>
          )
        },
        {
          key: 'order',
          label: 'ลำดับ',
          width: '90px',
          render: (row, index) => (
            <div style={{ display: 'flex', gap: '0.25rem' }}>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                disabled={!currentMeta.canEdit || index === 0}
                onClick={() => handleMove(index, -1)}
                title="เลื่อนขึ้น"
                style={{ padding: '0.25rem 0.4rem' }}
              >
                <ArrowUp size={14} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                disabled={!currentMeta.canEdit || index === filteredList.length - 1}
                onClick={() => handleMove(index, 1)}
                title="เลื่อนลง"
                style={{ padding: '0.25rem 0.4rem' }}
              >
                <ArrowDown size={14} />
              </button>
            </div>
          )
        },
        {
          key: 'actions',
          label: 'จัดการ',
          width: '110px',
          render: (row) => (
            <div style={{ display: 'flex', gap: '0.35rem' }}>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                onClick={() => {
                  setSelectedItem(row);
                  setDetailModalOpen(true);
                }}
                title="ดูรายละเอียด"
              >
                <Eye size={15} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-primary"
                disabled={!currentMeta.canEdit}
                onClick={() => handleOpenEdit(row)}
                title="แก้ไข"
              >
                <Edit2 size={15} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-danger"
                disabled={!currentMeta.canEdit}
                onClick={() => handleDeleteItem(row)}
                title="นำออก"
              >
                <Trash2 size={15} />
              </button>
            </div>
          )
        }
      ];
    }

    if (activeSectionKey === 'advisors' || activeSectionKey === 'auditors') {
      return [
        {
          key: 'index',
          label: 'ลำดับ',
          width: '60px',
          render: (_, idx) => <span>{idx + 1}</span>
        },
        {
          key: 'name',
          label: 'ชื่อ - นามสกุล',
          render: (row) => <span style={{ fontWeight: 600 }}>{row[0]}</span>
        },
        {
          key: 'position',
          label: 'ตำแหน่งหน้าที่',
          render: (row) => <span className="badge badge-primary">{row[1]}</span>
        },
        {
          key: 'order',
          label: 'จัดลำดับ',
          width: '90px',
          render: (row, index) => (
            <div style={{ display: 'flex', gap: '0.25rem' }}>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                disabled={!currentMeta.canEdit || index === 0}
                onClick={() => handleMove(index, -1)}
                title="เลื่อนขึ้น"
                style={{ padding: '0.25rem 0.4rem' }}
              >
                <ArrowUp size={14} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                disabled={!currentMeta.canEdit || index === filteredList.length - 1}
                onClick={() => handleMove(index, 1)}
                title="เลื่อนลง"
                style={{ padding: '0.25rem 0.4rem' }}
              >
                <ArrowDown size={14} />
              </button>
            </div>
          )
        },
        {
          key: 'actions',
          label: 'จัดการ',
          width: '100px',
          render: (row) => (
            <div style={{ display: 'flex', gap: '0.35rem' }}>
              <button
                type="button"
                className="btn btn-sm btn-primary"
                disabled={!currentMeta.canEdit}
                onClick={() => handleOpenEdit(row)}
                title="แก้ไข"
              >
                <Edit2 size={15} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-danger"
                disabled={!currentMeta.canEdit}
                onClick={() => handleDeleteItem(row)}
                title="นำออก"
              >
                <Trash2 size={15} />
              </button>
            </div>
          )
        }
      ];
    }

    if (activeSectionKey === 'groups') {
      return [
        {
          key: 'title',
          label: 'ชื่อคณะกรรมการย่อย',
          render: (row) => (
            <div>
              <div style={{ fontWeight: 600, fontSize: '1rem', color: 'var(--primary-900)' }}>
                {row.title}
              </div>
              <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
                จำนวนกรรมการในคณะ: {row.members ? row.members.length : 0} คน
              </div>
            </div>
          )
        },
        {
          key: 'members',
          label: 'รายชื่อกรรมการในคณะ',
          render: (row) => (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.35rem' }}>
              {row.members && row.members.map(([id, pos], mIdx) => (
                <span key={mIdx} className="badge badge-teal" style={{ fontSize: '0.78rem' }}>
                  {boardNamesMap[id] || `กรรมการ #${id}`}: {pos}
                </span>
              ))}
            </div>
          )
        },
        {
          key: 'actions',
          label: 'จัดการ',
          width: '100px',
          render: (row) => (
            <div style={{ display: 'flex', gap: '0.35rem' }}>
              <button
                type="button"
                className="btn btn-sm btn-primary"
                disabled={!currentMeta.canEdit}
                onClick={() => handleOpenEdit(row)}
                title="แก้ไขคณะกรรมการย่อย"
              >
                <Edit2 size={15} />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-danger"
                disabled={!currentMeta.canEdit}
                onClick={() => handleDeleteItem(row)}
                title="นำออก"
              >
                <Trash2 size={15} />
              </button>
            </div>
          )
        }
      ];
    }

    return [];
  }, [
    activeSectionKey,
    currentMeta.canEdit,
    filteredList.length,
    boardNamesMap
  ]);

  return (
    <div className="admin-board-staff-page">
      {/* ─────────────────────────────────────────────────────────────
          Page Header
      ───────────────────────────────────────────────────────────── */}
      <PageHeader
        title="จัดการข้อมูลคณะกรรมการและเจ้าหน้าที่"
        subtitle="แหล่งข้อมูลทางการชุดเดียวกับหน้าเว็บไซต์สาธารณะ รองรับการจัดทำร่าง ตรวจสอบก่อน-หลัง และอนุมัติเผยแพร่"
        breadcrumbs={[
          { label: 'Admin Control Center', path: '/admin/dashboard' },
          { label: 'พื้นที่ทำงาน', path: '/admin/workspace' },
          { label: 'คณะกรรมการและเจ้าหน้าที่' }
        ]}
        actions={
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
            <button
              type="button"
              className="btn btn-outline"
              disabled={loading}
              onClick={() => setRefreshKey((n) => n + 1)}
              title="โหลดข้อมูลล่าสุดจากฐานข้อมูล"
            >
              <RefreshCw size={15} className={loading ? 'animate-spin' : ''} />
              <span>โหลดล่าสุด</span>
            </button>

            {isTabDirty && (
              <button
                type="button"
                className="btn btn-outline"
                onClick={handleDiscardChanges}
                title="ยกเลิกการแก้ไขและคืนค่าที่เผยแพร่"
              >
                <span>คืนค่าที่เผยแพร่</span>
              </button>
            )}

            <button
              type="button"
              className="btn btn-outline"
              onClick={() => setPreviewModalOpen(true)}
              title="ดูตัวอย่างเปรียบเทียบข้อมูลจริงกับร่าง"
            >
              <Eye size={15} />
              <span>ดูตัวอย่างร่าง</span>
            </button>

            {currentMeta.canEdit && activeTab !== 'history' && (
              <button
                type="button"
                className="btn btn-primary"
                onClick={handleOpenCreate}
              >
                <Plus size={16} />
                <span>เพิ่มรายการ</span>
              </button>
            )}

            {isTabDirty && currentMeta.canEdit && (
              <button
                type="button"
                className="btn btn-success"
                onClick={handleOpenSaveDraftModal}
              >
                <FileText size={16} />
                <span>บันทึกร่างรอเผยแพร่</span>
              </button>
            )}
          </div>
        }
      />

      {error && (
        <div className="alert alert-danger" style={{ marginBottom: '1.25rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <AlertCircle size={18} />
          <span>{error}</span>
        </div>
      )}

      {/* ─────────────────────────────────────────────────────────────
          Pending Draft Alert Banner
      ───────────────────────────────────────────────────────────── */}
      {latestPendingDraft && activeTab !== 'history' && (
        <div
          className="surface-card"
          style={{
            borderLeft: '4px solid var(--accent-gold, #f59e0b)',
            padding: '1rem 1.25rem',
            marginBottom: '1.25rem',
            background: 'var(--amber-50, #fffbeb)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            flexWrap: 'wrap',
            gap: '1rem'
          }}
        >
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: '0.75rem' }}>
            <Clock size={20} color="var(--amber-600, #d97706)" style={{ marginTop: '0.2rem' }} />
            <div>
              <div style={{ fontWeight: 700, color: 'var(--amber-900, #78350f)' }}>
                มีร่างรออนุมัติเผยแพร่: ร่าง #{latestPendingDraft.id}
              </div>
              <div style={{ fontSize: '0.85rem', color: 'var(--amber-800, #92400e)', marginTop: '0.25rem' }}>
                เหตุผล: "{latestPendingDraft.note}" | มีผลตั้งแต่วันที่ {latestPendingDraft.effective_on} | บันทึกเมื่อ {latestPendingDraft.created_at}
              </div>
            </div>
          </div>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button
              type="button"
              className="btn btn-sm btn-outline"
              onClick={() => handleLoadRevisionAsDraft(latestPendingDraft)}
            >
              โหลดมาแก้ไขต่อ
            </button>
            {currentMeta.canPublish && (
              <button
                type="button"
                className="btn btn-sm btn-primary"
                disabled={publishingId === latestPendingDraft.id}
                onClick={() => handlePublishRevision(latestPendingDraft.id)}
              >
                {publishingId === latestPendingDraft.id ? 'กำลังเผยแพร่...' : 'อนุมัติและเผยแพร่ทันที'}
              </button>
            )}
          </div>
        </div>
      )}

      {/* ─────────────────────────────────────────────────────────────
          Tab Navigation
      ───────────────────────────────────────────────────────────── */}
      <div
        className="admin-tabs"
        style={{
          display: 'flex',
          gap: '0.5rem',
          borderBottom: '2px solid var(--border-color)',
          marginBottom: '1.25rem',
          overflowX: 'auto',
          paddingBottom: '0.25rem'
        }}
      >
        {TAB_CONFIGS.map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.key;
          const count =
            tab.key === 'history'
              ? currentSectionData.history?.length || 0
              : (draftCopies[tab.key] || []).length;

          return (
            <button
              key={tab.key}
              type="button"
              className={`tab-btn ${isActive ? 'active' : ''}`}
              onClick={() => {
                if (isTabDirty && !window.confirm('คุณมีข้อมูลที่ยังไม่ได้บันทึกร่าง ต้องการเปลี่ยนหมวดหรือไม่?')) {
                  return;
                }
                setActiveTab(tab.key);
                setSearchTerm('');
              }}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '0.5rem',
                padding: '0.65rem 1rem',
                border: 'none',
                background: isActive ? 'var(--primary-50, #eff6ff)' : 'transparent',
                color: isActive ? 'var(--primary-700, #1d4ed8)' : 'var(--text-muted)',
                fontWeight: isActive ? 700 : 500,
                borderRadius: '8px 8px 0 0',
                borderBottom: isActive ? '3px solid var(--primary-600, #2563eb)' : '3px solid transparent',
                cursor: 'pointer',
                whiteSpace: 'nowrap'
              }}
            >
              <Icon size={16} />
              <span>{tab.label}</span>
              <span
                className="badge"
                style={{
                  fontSize: '0.75rem',
                  background: isActive ? 'var(--primary-600)' : 'var(--bg-subtle)',
                  color: isActive ? '#fff' : 'var(--text-muted)'
                }}
              >
                {count}
              </span>
            </button>
          );
        })}
      </div>

      {/* ─────────────────────────────────────────────────────────────
          Main Content: Table or History View
      ───────────────────────────────────────────────────────────── */}
      {loading ? (
        <LoadingSkeleton count={6} />
      ) : activeTab === 'history' ? (
        /* History & Draft View */
        <div className="surface-card" style={{ padding: '1.5rem' }}>
          <h3 style={{ fontSize: '1.15rem', color: 'var(--primary-900)', marginBottom: '1rem' }}>
            ประวัติการแก้ไขและสถานะการเผยแพร่ข้อมูลบุคลากร
          </h3>
          {(!currentSectionData.history || currentSectionData.history.length === 0) ? (
            <EmptyState
              title="ยังไม่มีประวัติการบันทึกร่างหรือเผยแพร่"
              description="เมื่อมีการบันทึกร่างหรือเผยแพร่ข้อมูลบุคลากร ประวัติและการตรวจสอบย้อนหลังจะปรากฏที่นี่"
            />
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              {currentSectionData.history.map((rev) => (
                <div
                  key={rev.id}
                  style={{
                    border: '1px solid var(--border-color)',
                    borderRadius: '8px',
                    padding: '1rem 1.25rem',
                    background: rev.state === 'draft' ? 'var(--amber-50, #fffbeb)' : 'var(--surface)'
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <span style={{ fontWeight: 700, fontSize: '1rem' }}>
                        ร่าง #{rev.id} ({rev.section})
                      </span>
                      <StatusBadge
                        status={rev.state === 'published' ? 'active' : rev.state === 'draft' ? 'pending' : 'inactive'}
                        label={rev.state === 'published' ? 'เผยแพร่แล้ว' : rev.state === 'draft' ? 'รออนุมัติ' : 'ปฏิเสธ'}
                      />
                    </div>
                    <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
                      มีผลวันที่: <strong>{rev.effective_on}</strong>
                    </div>
                  </div>

                  <p style={{ margin: '0.5rem 0', color: 'var(--text-main)' }}>
                    <strong>เหตุผล:</strong> {rev.note}
                  </p>

                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem', fontSize: '0.82rem', color: 'var(--text-muted)' }}>
                    <div>
                      ผู้บันทึก: #{rev.created_by} เมื่อ {rev.created_at}
                      {rev.published_by && ` | ผู้อนุมัติ: #${rev.published_by} เมื่อ ${rev.published_at}`}
                    </div>
                    <div style={{ display: 'flex', gap: '0.5rem' }}>
                      <a
                        href={rev.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="btn btn-sm btn-outline"
                        style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}
                      >
                        <ExternalLink size={13} />
                        <span>เอกสารอ้างอิง</span>
                      </a>
                      <button
                        type="button"
                        className="btn btn-sm btn-outline"
                        onClick={() => handleLoadRevisionAsDraft(rev)}
                      >
                        นำข้อมูลมาแก้ไข
                      </button>
                      {rev.state === 'draft' && currentMeta.canPublish && (
                        <button
                          type="button"
                          className="btn btn-sm btn-primary"
                          disabled={publishingId === rev.id}
                          onClick={() => handlePublishRevision(rev.id)}
                        >
                          {publishingId === rev.id ? 'กำลังเผยแพร่...' : 'อนุมัติเผยแพร่'}
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ) : (
        /* Personnel List Table */
        <div>
          {/* Toolbar & Filters */}
          <div
            className="surface-card"
            style={{
              padding: '1rem 1.25rem',
              marginBottom: '1rem',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              flexWrap: 'wrap',
              gap: '1rem'
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flex: 1, minWidth: '240px' }}>
              <div style={{ position: 'relative', width: '100%', maxWidth: '360px' }}>
                <Search size={16} style={{ position: 'absolute', left: '10px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                <input
                  type="text"
                  className="form-control"
                  style={{ paddingLeft: '2.2rem' }}
                  placeholder="ค้นหาชื่อ, ตำแหน่ง, หน่วยงาน..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>

              {activeSectionKey === 'boards' && availableTerms.length > 0 && (
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <Filter size={15} color="var(--text-muted)" />
                  <select
                    className="form-control"
                    value={selectedTermFilter}
                    onChange={(e) => setSelectedTermFilter(e.target.value)}
                    style={{ minWidth: '130px' }}
                  >
                    <option value="ALL">ทุกวาระ</option>
                    {availableTerms.map((term) => (
                      <option key={term} value={term}>{term}</option>
                    ))}
                  </select>
                </div>
              )}
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem' }}>
              {isTabDirty ? (
                <span className="badge badge-warning" style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                  <AlertCircle size={13} />
                  <span>มีการแก้ไขในร่างที่ยังไม่ได้บันทึก</span>
                </span>
              ) : (
                <span className="badge badge-success" style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                  <CheckCircle2 size={13} />
                  <span>ข้อมูลตรงตามที่เผยแพร่บนหน้าเว็บ</span>
                </span>
              )}
              <span style={{ color: 'var(--text-muted)' }}>
                แสดง {filteredList.length} รายการ
              </span>
            </div>
          </div>

          {/* Table */}
          {filteredList.length === 0 ? (
            <EmptyState
              title="ไม่พบข้อมูลบุคลากรในหมวดนี้"
              description={searchTerm ? 'ไม่พบข้อมูลที่ตรงกับคำค้นหา กรุณาลองค้นหาด้วยคำอื่น' : 'ยังไม่มีรายการบุคลากรในหมวดนี้ คลิก "เพิ่มรายการ" เพื่อเริ่มต้น'}
            />
          ) : (
            <CrudDataTable
              columns={tableColumns}
              data={filteredList}
              keyField={activeSectionKey === 'advisors' || activeSectionKey === 'auditors' ? undefined : 'id'}
            />
          )}
        </div>
      )}

      {/* ─────────────────────────────────────────────────────────────
          Form Drawer (Add / Edit)
      ───────────────────────────────────────────────────────────── */}
      <CrudFormDrawer
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={
          editingIndex !== null
            ? `แก้ไขข้อมูล: ${TAB_CONFIGS.find((t) => t.key === activeSectionKey)?.label}`
            : `เพิ่ม: ${TAB_CONFIGS.find((t) => t.key === activeSectionKey)?.label}`
        }
        subtitle="การแก้ไขจะถูกนำเข้าสู่ร่างของหมวดนี้ และต้องบันทึกร่าง/เผยแพร่ก่อนแสดงบนหน้าเว็บจริง"
        onSubmit={handleSaveForm}
        isEdit={editingIndex !== null}
      >
        {activeSectionKey === 'boards' && (
          <>
            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>
                ชื่อ - นามสกุล <span style={{ color: 'red' }}>*</span>
              </label>
              <input
                type="text"
                className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
                value={formData.name || ''}
                onChange={(e) => handleFormChange('name', e.target.value)}
                placeholder="เช่น นางสาวอุษา อิศรางกูร ณ อยุธยา"
                required
              />
              {formErrors.name && <div style={{ color: 'red', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.name}</div>}
            </div>

            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>
                ตำแหน่งในคณะกรรมการ <span style={{ color: 'red' }}>*</span>
              </label>
              <input
                type="text"
                className={`form-control ${formErrors.position ? 'is-invalid' : ''}`}
                value={formData.position || ''}
                onChange={(e) => handleFormChange('position', e.target.value)}
                placeholder="เช่น ประธานกรรมการ, รองประธาน, กรรมการ, เหรัญญิก, เลขานุการ"
                required
              />
              {formErrors.position && <div style={{ color: 'red', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.position}</div>}
            </div>

            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>วาระ / ชุดที่</label>
              <input
                type="text"
                className="form-control"
                value={formData.term || ''}
                onChange={(e) => handleFormChange('term', e.target.value)}
                placeholder="เช่น ชุดที่ 38 หรือ ชุดที่ 39"
              />
            </div>

            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>หน่วยงานต้นสังกัด / ที่ทำงาน</label>
              <input
                type="text"
                className="form-control"
                value={formData.workplace || ''}
                onChange={(e) => handleFormChange('workplace', e.target.value)}
                placeholder="เช่น โรงพยาบาลระยอง หรือ สำนักงานสาธารณสุขจังหวัดระยอง"
              />
            </div>

            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>รูปภาพประจำตัว</label>
              <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '0.5rem' }}>
                <input
                  type="text"
                  className="form-control"
                  value={formData.image || ''}
                  onChange={(e) => handleFormChange('image', e.target.value)}
                  placeholder="เช่น /assets/img/committee/... หรือ /storage/uploads/..."
                />
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={uploadingImage}
                  style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', whiteSpace: 'nowrap' }}
                >
                  <Upload size={15} />
                  <span>{uploadingImage ? 'อัปโหลด...' : 'เลือกไฟล์'}</span>
                </button>
                <input
                  type="file"
                  ref={fileInputRef}
                  style={{ display: 'none' }}
                  accept="image/jpeg,image/png,image/webp"
                  onChange={handleUploadFile}
                />
              </div>
              {formData.image && (
                <div style={{ marginTop: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <div style={{ width: '64px', height: '64px', borderRadius: '50%', overflow: 'hidden', border: '2px solid var(--border-color)' }}>
                    <SafeImage src={formData.image} alt="Preview" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                  </div>
                  <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>ตัวอย่างรูปภาพ</span>
                </div>
              )}
            </div>
          </>
        )}

        {activeSectionKey === 'staff' && (
          <>
            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>
                ชื่อ - นามสกุล <span style={{ color: 'red' }}>*</span>
              </label>
              <input
                type="text"
                className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
                value={formData.name || ''}
                onChange={(e) => handleFormChange('name', e.target.value)}
                placeholder="เช่น นางนริศษา แย้มกลิ่น"
                required
              />
              {formErrors.name && <div style={{ color: 'red', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.name}</div>}
            </div>

            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>
                ตำแหน่ง <span style={{ color: 'red' }}>*</span>
              </label>
              <input
                type="text"
                className={`form-control ${formErrors.position ? 'is-invalid' : ''}`}
                value={formData.position || ''}
                onChange={(e) => handleFormChange('position', e.target.value)}
                placeholder="เช่น ผู้จัดการ, เจ้าหน้าที่การเงิน, เจ้าหน้าที่สินเชื่อ"
                required
              />
              {formErrors.position && <div style={{ color: 'red', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.position}</div>}
            </div>

            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>ฝ่าย / แผนก</label>
              <input
                type="text"
                className="form-control"
                value={formData.department || ''}
                onChange={(e) => handleFormChange('department', e.target.value)}
                placeholder="เช่น ฝ่ายจัดการ หรือ ฝ่ายสินเชื่อ"
              />
            </div>

            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>รูปภาพประจำตัว</label>
              <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '0.5rem' }}>
                <input
                  type="text"
                  className="form-control"
                  value={formData.image || ''}
                  onChange={(e) => handleFormChange('image', e.target.value)}
                  placeholder="เช่น /assets/img/officer/... หรือ /storage/uploads/..."
                />
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={uploadingImage}
                  style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', whiteSpace: 'nowrap' }}
                >
                  <Upload size={15} />
                  <span>{uploadingImage ? 'อัปโหลด...' : 'เลือกไฟล์'}</span>
                </button>
                <input
                  type="file"
                  ref={fileInputRef}
                  style={{ display: 'none' }}
                  accept="image/jpeg,image/png,image/webp"
                  onChange={handleUploadFile}
                />
              </div>
              {formData.image && (
                <div style={{ marginTop: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <div style={{ width: '64px', height: '64px', borderRadius: '50%', overflow: 'hidden', border: '2px solid var(--border-color)' }}>
                    <SafeImage src={formData.image} alt="Preview" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                  </div>
                  <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>ตัวอย่างรูปภาพ</span>
                </div>
              )}
            </div>
          </>
        )}

        {(activeSectionKey === 'advisors' || activeSectionKey === 'auditors') && (
          <>
            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>
                ชื่อ - นามสกุล <span style={{ color: 'red' }}>*</span>
              </label>
              <input
                type="text"
                className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
                value={formData.name || ''}
                onChange={(e) => handleFormChange('name', e.target.value)}
                placeholder="เช่น นายแพทย์สุรวิทย์ ศักดานุภาพ"
                required
              />
              {formErrors.name && <div style={{ color: 'red', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.name}</div>}
            </div>

            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>
                ตำแหน่งหน้าที่ <span style={{ color: 'red' }}>*</span>
              </label>
              <input
                type="text"
                className={`form-control ${formErrors.position ? 'is-invalid' : ''}`}
                value={formData.position || ''}
                onChange={(e) => handleFormChange('position', e.target.value)}
                placeholder={activeSectionKey === 'advisors' ? 'เช่น ที่ปรึกษากิตติมศักดิ์, ที่ปรึกษา' : 'เช่น ประธานผู้ตรวจสอบกิจการ, ผู้ตรวจสอบกิจการ'}
                required
              />
              {formErrors.position && <div style={{ color: 'red', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.position}</div>}
            </div>
          </>
        )}

        {activeSectionKey === 'groups' && (
          <>
            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label className="form-label" style={{ fontWeight: 600 }}>
                ชื่อคณะกรรมการย่อย <span style={{ color: 'red' }}>*</span>
              </label>
              <input
                type="text"
                className={`form-control ${formErrors.title ? 'is-invalid' : ''}`}
                value={formData.title || ''}
                onChange={(e) => handleFormChange('title', e.target.value)}
                placeholder="เช่น คณะกรรมการเงินกู้, คณะกรรมการศึกษาและประชาสัมพันธ์"
                required
              />
              {formErrors.title && <div style={{ color: 'red', fontSize: '0.8rem', marginTop: '0.25rem' }}>{formErrors.title}</div>}
            </div>

            <div style={{ marginTop: '1.25rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                <label className="form-label" style={{ fontWeight: 600, margin: 0 }}>
                  กรรมการในคณะย่อย ({formData.members ? formData.members.length : 0} ท่าน)
                </label>
                <button
                  type="button"
                  className="btn btn-sm btn-outline"
                  onClick={() => {
                    const firstBoardId = draftCopies.boards?.[0]?.id || 1;
                    const members = formData.members ? [...formData.members] : [];
                    members.push([firstBoardId, 'กรรมการ']);
                    handleFormChange('members', members);
                  }}
                >
                  <Plus size={14} />
                  <span>เพิ่มกรรมการ</span>
                </button>
              </div>

              {formData.members && formData.members.length > 0 ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                  {formData.members.map(([boardId, roleInGroup], mIdx) => (
                    <div
                      key={mIdx}
                      style={{
                        display: 'flex',
                        gap: '0.5rem',
                        alignItems: 'center',
                        background: 'var(--bg-subtle)',
                        padding: '0.5rem',
                        borderRadius: '6px'
                      }}
                    >
                      <select
                        className="form-control"
                        value={boardId}
                        onChange={(e) => {
                          const members = [...formData.members];
                          members[mIdx] = [Number(e.target.value), roleInGroup];
                          handleFormChange('members', members);
                        }}
                        style={{ flex: 1 }}
                      >
                        {(draftCopies.boards || []).map((b) => (
                          <option key={b.id} value={b.id}>
                            {b.name} ({b.position})
                          </option>
                        ))}
                      </select>

                      <input
                        type="text"
                        className="form-control"
                        value={roleInGroup}
                        onChange={(e) => {
                          const members = [...formData.members];
                          members[mIdx] = [boardId, e.target.value];
                          handleFormChange('members', members);
                        }}
                        placeholder="ตำแหน่งในคณะย่อย"
                        style={{ flex: 1 }}
                      />

                      <button
                        type="button"
                        className="btn btn-sm btn-danger"
                        onClick={() => {
                          const members = formData.members.filter((_, idx) => idx !== mIdx);
                          handleFormChange('members', members);
                        }}
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  ))}
                </div>
              ) : (
                <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                  ยังไม่มีกรรมการในคณะนี้ คลิก "เพิ่มกรรมการ" ด้านบน
                </p>
              )}
            </div>
          </>
        )}
      </CrudFormDrawer>

      {/* ─────────────────────────────────────────────────────────────
          Save Draft Modal
      ───────────────────────────────────────────────────────────── */}
      {saveDraftModalOpen && (
        <div
          className="modal-overlay"
          style={{
            position: 'fixed',
            inset: 0,
            background: 'rgba(0,0,0,0.5)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1050,
            padding: '1rem'
          }}
        >
          <div
            className="surface-card"
            style={{
              width: 'min(100%, 540px)',
              padding: '1.75rem',
              borderRadius: '12px',
              background: '#fff'
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-900)', margin: 0 }}>
                บันทึกร่างข้อมูล: {TAB_CONFIGS.find((t) => t.key === activeSectionKey)?.label}
              </h3>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                onClick={() => setSaveDraftModalOpen(false)}
              >
                <X size={16} />
              </button>
            </div>

            <form onSubmit={handleConfirmSaveDraft}>
              <div className="form-group" style={{ marginBottom: '1rem' }}>
                <label className="form-label" style={{ fontWeight: 600 }}>
                  เหตุผล / บันทึกการแก้ไข <span style={{ color: 'red' }}>*</span>
                </label>
                <textarea
                  className="form-control"
                  rows={3}
                  value={draftNote}
                  onChange={(e) => setDraftNote(e.target.value)}
                  placeholder="เช่น ปรับปรุงรายชื่อคณะกรรมการชุดที่ 38 ตามมติที่ประชุม..."
                  required
                />
              </div>

              <div className="form-group" style={{ marginBottom: '1rem' }}>
                <label className="form-label" style={{ fontWeight: 600 }}>
                  แหล่งอ้างอิงเอกสาร (HTTPS) <span style={{ color: 'red' }}>*</span>
                </label>
                <input
                  type="url"
                  className="form-control"
                  value={draftSourceUrl}
                  onChange={(e) => setDraftSourceUrl(e.target.value)}
                  placeholder="https://..."
                  required
                />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', marginBottom: '1.25rem' }}>
                <div className="form-group">
                  <label className="form-label" style={{ fontWeight: 600 }}>
                    วันที่มีผล (ค.ศ.) <span style={{ color: 'red' }}>*</span>
                  </label>
                  <input
                    type="date"
                    className="form-control"
                    value={draftEffectiveOn}
                    onChange={(e) => setDraftEffectiveOn(e.target.value)}
                    required
                  />
                </div>
                <div className="form-group">
                  <label className="form-label" style={{ fontWeight: 600 }}>
                    วันสิ้นสุด (ไม่บังคับ)
                  </label>
                  <input
                    type="date"
                    className="form-control"
                    value={draftExpiresOn}
                    min={draftEffectiveOn}
                    onChange={(e) => setDraftExpiresOn(e.target.value)}
                  />
                </div>
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
                <button
                  type="button"
                  className="btn btn-outline"
                  disabled={savingDraft}
                  onClick={() => setSaveDraftModalOpen(false)}
                >
                  ยกเลิก
                </button>
                <button
                  type="submit"
                  className="btn btn-primary"
                  disabled={savingDraft}
                >
                  {savingDraft ? 'กำลังบันทึกร่าง...' : 'ยืนยันบันทึกร่าง'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ─────────────────────────────────────────────────────────────
          Preview / Diff Modal (Draft vs Published)
      ───────────────────────────────────────────────────────────── */}
      {previewModalOpen && (
        <div
          className="modal-overlay"
          style={{
            position: 'fixed',
            inset: 0,
            background: 'rgba(0,0,0,0.5)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1050,
            padding: '1rem'
          }}
        >
          <div
            className="surface-card"
            style={{
              width: 'min(100%, 960px)',
              maxHeight: '90vh',
              overflowY: 'auto',
              padding: '1.75rem',
              borderRadius: '12px',
              background: '#fff'
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-900)', margin: 0 }}>
                ตัวอย่างเปรียบเทียบ: ข้อมูลที่เผยแพร่อยู่ vs ร่างที่กำลังแก้ไข
              </h3>
              <button
                type="button"
                className="btn btn-sm btn-outline"
                onClick={() => setPreviewModalOpen(false)}
              >
                <X size={16} />
              </button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem', marginBottom: '1.5rem' }}>
              {/* Published Column */}
              <div style={{ border: '1px solid var(--border-color)', borderRadius: '8px', padding: '1rem' }}>
                <h4 style={{ color: 'var(--success-700, #15803d)', borderBottom: '1px solid var(--border-color)', paddingBottom: '0.5rem', marginTop: 0 }}>
                  ข้อมูลปัจจุบันที่เผยแพร่อยู่บนหน้าเว็บ ({currentSectionData.value?.length || 0} รายการ)
                </h4>
                <div style={{ maxHeight: '420px', overflowY: 'auto' }}>
                  {(currentSectionData.value || []).map((item, idx) => (
                    <div key={idx} style={{ padding: '0.4rem 0', borderBottom: '1px solid var(--bg-subtle)', fontSize: '0.85rem' }}>
                      <strong>{idx + 1}. {item.name || (Array.isArray(item) ? item[0] : item.title)}</strong>
                      <span style={{ color: 'var(--text-muted)', marginLeft: '0.5rem' }}>
                        ({item.position || (Array.isArray(item) ? item[1] : `กรรมการ ${item.members?.length || 0} คน`)})
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Working Draft Column */}
              <div style={{ border: '1px solid var(--primary-200, #bfdbfe)', borderRadius: '8px', padding: '1rem', background: 'var(--primary-50, #f8fafc)' }}>
                <h4 style={{ color: 'var(--primary-700, #1d4ed8)', borderBottom: '1px solid var(--primary-200)', paddingBottom: '0.5rem', marginTop: 0 }}>
                  ร่างที่กำลังแก้ไข ({currentWorkingList.length} รายการ)
                </h4>
                <div style={{ maxHeight: '420px', overflowY: 'auto' }}>
                  {currentWorkingList.map((item, idx) => (
                    <div key={idx} style={{ padding: '0.4rem 0', borderBottom: '1px solid var(--border-color)', fontSize: '0.85rem' }}>
                      <strong>{idx + 1}. {item.name || (Array.isArray(item) ? item[0] : item.title)}</strong>
                      <span style={{ color: 'var(--text-muted)', marginLeft: '0.5rem' }}>
                        ({item.position || (Array.isArray(item) ? item[1] : `กรรมการ ${item.members?.length || 0} คน`)})
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
              <button
                type="button"
                className="btn btn-outline"
                onClick={() => setPreviewModalOpen(false)}
              >
                ปิดหน้าต่าง
              </button>
              {isTabDirty && currentMeta.canEdit && (
                <button
                  type="button"
                  className="btn btn-primary"
                  onClick={() => {
                    setPreviewModalOpen(false);
                    handleOpenSaveDraftModal();
                  }}
                >
                  บันทึกร่างชุดนี้
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ─────────────────────────────────────────────────────────────
          Detail Modal
      ───────────────────────────────────────────────────────────── */}
      <CrudDetailModal
        isOpen={detailModalOpen}
        onClose={() => setDetailModalOpen(false)}
        title="รายละเอียดบุคลากร"
        subtitle={selectedItem?.name || ''}
      >
        {selectedItem && (
          <div style={{ display: 'flex', gap: '1.5rem', alignItems: 'center' }}>
            <div style={{ width: '100px', height: '100px', borderRadius: '50%', overflow: 'hidden', border: '3px solid var(--primary-200)', flexShrink: 0 }}>
              <SafeImage
                src={selectedItem.image}
                fallback="/assets/img/board_placeholder.jpg"
                alt={selectedItem.name}
                style={{ width: '100%', height: '100%', objectFit: 'cover' }}
              />
            </div>
            <div>
              <h3 style={{ margin: '0 0 0.25rem 0', color: 'var(--primary-900)' }}>{selectedItem.name}</h3>
              <p style={{ margin: '0 0 0.5rem 0', fontWeight: 600, color: 'var(--accent-gold-dark)' }}>{selectedItem.position}</p>
              {selectedItem.term && (
                <p style={{ margin: '0 0 0.25rem 0', fontSize: '0.85rem' }}>
                  <strong>วาระ:</strong> {selectedItem.term}
                </p>
              )}
              {selectedItem.workplace && (
                <p style={{ margin: '0 0 0.25rem 0', fontSize: '0.85rem' }}>
                  <strong>หน่วยงาน:</strong> {selectedItem.workplace}
                </p>
              )}
              {selectedItem.department && (
                <p style={{ margin: '0 0 0.25rem 0', fontSize: '0.85rem' }}>
                  <strong>ฝ่าย:</strong> {selectedItem.department}
                </p>
              )}
            </div>
          </div>
        )}
      </CrudDetailModal>
    </div>
  );
}
