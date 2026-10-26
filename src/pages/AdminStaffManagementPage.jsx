import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { 
  Users, UserPlus, Shield, ShieldAlert, ShieldCheck, Mail, 
  RotateCw, RefreshCw, KeyRound, Ban, CheckCircle, Copy, Check, 
  Lock, AlertTriangle, ExternalLink, Sparkles, Clock, Globe, ArrowLeft
} from 'lucide-react';
import { useToast } from '../context/ToastContext';

export default function AdminStaffManagementPage() {
  const { toast } = useToast();
  const [activeTab, setActiveTab] = useState('staff'); // 'staff', 'invitations', 'security'
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  // Data states
  const [staffList, setStaffList] = useState([]);
  const [invitations, setInvitations] = useState([]);
  const [roles, setRoles] = useState([]);
  const [securityData, setSecurityData] = useState(null);

  // Invite Modal State
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [inviteForm, setInviteForm] = useState({ name: '', email: '', role_id: '', department: '' });

  // Edit Role Modal State
  const [showEditModal, setShowEditModal] = useState(false);
  const [editForm, setEditForm] = useState({ id: 0, name: '', role_id: '', department: '' });

  // Newly created invitation result
  const [createdInvite, setCreatedInvite] = useState(null);
  const [copiedToken, setCopiedToken] = useState(false);

  const fetchStaffData = async () => {
    setLoading(true);
    setError('');
    try {
      const [staffRes, secRes] = await Promise.all([
        fetch('/api/admin/staff', { credentials: 'include', headers: { Accept: 'application/json' } }),
        fetch('/api/admin/security/dashboard', { credentials: 'include', headers: { Accept: 'application/json' } })
      ]);

      if (staffRes.ok) {
        const staffData = await staffRes.json();
        if (staffData.success) {
          setStaffList(staffData.staff || []);
          setInvitations(staffData.invitations || []);
          setRoles(staffData.roles || []);
        }
      }

      if (secRes.ok) {
        const secData = await secRes.json();
        if (secData.success) {
          setSecurityData(secData);
        }
      }
    } catch (err) {
      setError('ไม่สามารถเชื่อมต่อข้อมูลเจ้าหน้าที่และระบบความปลอดภัยได้');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStaffData();
  }, []);

  const getCsrfToken = async () => {
    const res = await fetch('/csrf-token', { credentials: 'include', headers: { Accept: 'application/json' } });
    const data = res.ok ? await res.json() : {};
    return data.token || '';
  };

  const handleInviteSubmit = async (e) => {
    e.preventDefault();
    if (!inviteForm.name || !inviteForm.email || !inviteForm.role_id) {
      toast.error('กรุณากรอกข้อมูลให้ครบถ้วน');
      return;
    }

    setBusy(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch('/api/admin/staff/invite', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(inviteForm),
      });

      const data = await res.json();
      if (data.success) {
        toast.success(data.message || 'ส่งคำเชิญเรียบร้อยแล้ว');
        setCreatedInvite(data.invitation);
        setInviteForm({ name: '', email: '', role_id: '', department: '' });
        fetchStaffData();
      } else {
        toast.error(data.message || 'เกิดข้อผิดพลาดในการสร้างคำเชิญ');
      }
    } catch (err) {
      toast.error('ไม่สามารถส่งคำเชิญได้');
    } finally {
      setBusy(false);
    }
  };

  const handleRevokeInvitation = async (id) => {
    if (!window.confirm('ยืนยันยกเลิกคำเชิญนี้?')) return;
    setBusy(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(`/api/admin/staff/invitations/${id}/revoke`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      });
      const data = await res.json();
      if (data.success) {
        toast.success('ยกเลิกคำเชิญเรียบร้อยแล้ว');
        fetchStaffData();
      } else {
        toast.error(data.message || 'เกิดข้อผิดพลาด');
      }
    } catch {
      toast.error('ไม่สามารถดำเนินการได้');
    } finally {
      setBusy(false);
    }
  };

  const handleResendInvitation = async (id) => {
    setBusy(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(`/api/admin/staff/invitations/${id}/resend`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      });
      const data = await res.json();
      if (data.success) {
        toast.success('ต่ออายุคำเชิญและสร้างลิงก์ใหม่เรียบร้อยแล้ว');
        setCreatedInvite(data.invitation);
        fetchStaffData();
      } else {
        toast.error(data.message || 'เกิดข้อผิดพลาด');
      }
    } catch {
      toast.error('ไม่สามารถดำเนินการได้');
    } finally {
      setBusy(false);
    }
  };

  const handleToggleSuspend = async (staff) => {
    const isSuspended = staff.status === 'suspended';
    const action = isSuspended ? 'activate' : 'suspend';
    const label = isSuspended ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';

    if (!window.confirm(`ยืนยัน${label}บัญชี ${staff.name}?`)) return;

    setBusy(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(`/api/admin/staff/${staff.id}/${action}`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      });
      const data = await res.json();
      if (data.success) {
        toast.success(data.message || `${label}สำเร็จ`);
        fetchStaffData();
      } else {
        toast.error(data.message || `ไม่สามารถ${label}ได้`);
      }
    } catch {
      toast.error('เกิดข้อผิดพลาดในการส่งคำขอ');
    } finally {
      setBusy(false);
    }
  };

  const handleRevokeSessions = async (staff) => {
    if (!window.confirm(`ยืนยันยกเลิกเซสชันที่ใช้งานอยู่ทั้งหมดของ ${staff.name}? ผู้ใช้จะต้องเข้าสู่ระบบใหม่ทันที`)) return;

    setBusy(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(`/api/admin/staff/${staff.id}/revoke-sessions`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      });
      const data = await res.json();
      if (data.success) {
        toast.success(data.message || 'ยกเลิกเซสชันเรียบร้อยแล้ว');
        fetchStaffData();
      } else {
        toast.error(data.message || 'เกิดข้อผิดพลาด');
      }
    } catch {
      toast.error('เกิดข้อผิดพลาด');
    } finally {
      setBusy(false);
    }
  };

  const handleResetGoogleLink = async (staff) => {
    if (!window.confirm(`ยืนยันรีเซ็ตการเชื่อมต่อ Google ของ ${staff.name}? ผู้ใช้จะต้องทำการผูกบัญชี Google ใหม่อีกครั้ง`)) return;

    setBusy(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(`/api/admin/staff/${staff.id}/relink`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      });
      const data = await res.json();
      if (data.success) {
        toast.success(data.message || 'รีเซ็ตสำเร็จ');
        fetchStaffData();
      } else {
        toast.error(data.message || 'เกิดข้อผิดพลาด');
      }
    } catch {
      toast.error('เกิดข้อผิดพลาด');
    } finally {
      setBusy(false);
    }
  };

  const handleSaveRole = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(`/api/admin/staff/${editForm.id}/role`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ role_id: editForm.role_id, department: editForm.department }),
      });
      const data = await res.json();
      if (data.success) {
        toast.success(data.message || 'อัปเดตบทบาทเรียบร้อยแล้ว');
        setShowEditModal(false);
        fetchStaffData();
      } else {
        toast.error(data.message || 'เกิดข้อผิดพลาด');
      }
    } catch {
      toast.error('ไม่สามารถอัปเดตบทบาทได้');
    } finally {
      setBusy(false);
    }
  };

  const copyInviteLink = (url) => {
    navigator.clipboard.writeText(url);
    setCopiedToken(true);
    toast.success('คัดลอกลิงก์คำเชิญเรียบร้อยแล้ว');
    setTimeout(() => setCopiedToken(false), 3000);
  };

  return (
    <div className="container section" style={{ maxWidth: '1200px', margin: '30px auto' }}>
      
      {/* Top Breadcrumb & Actions */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <Link to="/admin/workspace" className="btn btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
          <ArrowLeft size={16} /> กลับสู่พื้นที่ทำงานผู้ดูแลระบบ
        </Link>
        <button 
          className="btn btn-outline" 
          disabled={loading || busy} 
          onClick={fetchStaffData}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
        >
          <RefreshCw size={15} className={loading ? 'member-entry-spin' : ''} /> โหลดข้อมูลล่าสุด
        </button>
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '16px', marginBottom: '28px' }}>
        <div>
          <h1 style={{ fontSize: '1.85rem', fontWeight: 800, color: 'var(--primary-800)', margin: '0 0 6px' }}>
            การจัดการเจ้าหน้าที่ & ระบบความปลอดภัย (Staff & Security)
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem', margin: 0 }}>
            ควบคุมการเข้าถึงด้วย Google OAuth 2.0 / OpenID Connect, นโยบาย Mandatory MFA, คำเชิญเจ้าหน้าที่ และการเพิกถอนเซสชัน
          </p>
        </div>

        <button 
          className="btn btn-primary"
          onClick={() => { setShowInviteModal(true); setCreatedInvite(null); }}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '10px 22px', borderRadius: '12px', fontWeight: 700 }}
        >
          <UserPlus size={18} /> เชิญเจ้าหน้าที่ใหม่ (Google Account)
        </button>
      </div>

      {error && <div role="alert" className="member-entry-error" style={{ marginBottom: '20px' }}>{error}</div>}

      {/* Security Metrics Overview Cards */}
      {securityData?.metrics && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '16px', marginBottom: '28px' }}>
          
          <div className="surface-card" style={{ padding: '20px', borderRadius: '16px', display: 'flex', alignItems: 'center', gap: '16px' }}>
            <div style={{ width: '48px', height: '48px', borderRadius: '12px', background: 'var(--primary-50)', color: 'var(--primary-600)', display: 'grid', placeItems: 'center' }}>
              <Users size={24} />
            </div>
            <div>
              <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>เจ้าหน้าที่เปิดใช้งาน</div>
              <div style={{ fontSize: '1.6rem', fontWeight: 800, color: '#1e293b' }}>{securityData.metrics.active_staff}</div>
              <div style={{ fontSize: '0.75rem', color: '#64748b' }}>ทั้งหมด {securityData.metrics.total_staff} บัญชี</div>
            </div>
          </div>

          <div className="surface-card" style={{ padding: '20px', borderRadius: '16px', display: 'flex', alignItems: 'center', gap: '16px' }}>
            <div style={{ width: '48px', height: '48px', borderRadius: '12px', background: '#ecfdf5', color: '#059669', display: 'grid', placeItems: 'center' }}>
              <ShieldCheck size={24} />
            </div>
            <div>
              <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>MFA Adoption Rate</div>
              <div style={{ fontSize: '1.6rem', fontWeight: 800, color: '#059669' }}>{securityData.metrics.mfa_adoption_rate}%</div>
              <div style={{ fontSize: '0.75rem', color: '#64748b' }}>เปิดใช้แล้ว {securityData.metrics.mfa_enrolled_count} คน</div>
            </div>
          </div>

          <div className="surface-card" style={{ padding: '20px', borderRadius: '16px', display: 'flex', alignItems: 'center', gap: '16px' }}>
            <div style={{ width: '48px', height: '48px', borderRadius: '12px', background: '#eff6ff', color: '#2563eb', display: 'grid', placeItems: 'center' }}>
              <Globe size={24} />
            </div>
            <div>
              <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>ผูกบัญชี Google แล้ว</div>
              <div style={{ fontSize: '1.6rem', fontWeight: 800, color: '#2563eb' }}>{securityData.metrics.google_linked_count}</div>
              <div style={{ fontSize: '0.75rem', color: '#64748b' }}>จาก {securityData.metrics.total_staff} บัญชี</div>
            </div>
          </div>

          <div className="surface-card" style={{ padding: '20px', borderRadius: '16px', display: 'flex', alignItems: 'center', gap: '16px' }}>
            <div style={{ width: '48px', height: '48px', borderRadius: '12px', background: '#fff7ed', color: '#d97706', display: 'grid', placeItems: 'center' }}>
              <Mail size={24} />
            </div>
            <div>
              <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>คำเชิญรอยืนยัน</div>
              <div style={{ fontSize: '1.6rem', fontWeight: 800, color: '#d97706' }}>{securityData.metrics.pending_invitations}</div>
              <div style={{ fontSize: '0.75rem', color: '#64748b' }}>รอยืนยันตัวตนผ่าน Google</div>
            </div>
          </div>

        </div>
      )}

      {/* Tabs Navigation */}
      <div style={{ display: 'flex', gap: '10px', borderBottom: '2px solid #e2e8f0', marginBottom: '24px' }}>
        <button
          type="button"
          onClick={() => setActiveTab('staff')}
          style={{
            padding: '12px 20px', background: 'transparent', border: 0,
            borderBottom: activeTab === 'staff' ? '3px solid var(--primary-600)' : '3px solid transparent',
            color: activeTab === 'staff' ? 'var(--primary-700)' : 'var(--text-muted)',
            fontWeight: 700, fontSize: '0.98rem', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '8px'
          }}
        >
          <Users size={18} /> รายชื่อเจ้าหน้าที่ ({staffList.length})
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('invitations')}
          style={{
            padding: '12px 20px', background: 'transparent', border: 0,
            borderBottom: activeTab === 'invitations' ? '3px solid var(--primary-600)' : '3px solid transparent',
            color: activeTab === 'invitations' ? 'var(--primary-700)' : 'var(--text-muted)',
            fontWeight: 700, fontSize: '0.98rem', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '8px'
          }}
        >
          <Mail size={18} /> คำเชิญเจ้าหน้าที่ ({invitations.filter(i => i.status === 'pending').length} รอดำเนินการ)
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('security')}
          style={{
            padding: '12px 20px', background: 'transparent', border: 0,
            borderBottom: activeTab === 'security' ? '3px solid var(--primary-600)' : '3px solid transparent',
            color: activeTab === 'security' ? 'var(--primary-700)' : 'var(--text-muted)',
            fontWeight: 700, fontSize: '0.98rem', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '8px'
          }}
        >
          <ShieldAlert size={18} /> แดชบอร์ดความปลอดภัย & Audit Logs
        </button>
      </div>

      {/* Tab 1: Staff Directory */}
      {activeTab === 'staff' && (
        <section className="surface-card" style={{ padding: '24px', borderRadius: '18px' }}>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
              <thead>
                <tr style={{ borderBottom: '2px solid #e2e8f0', color: '#64748b', fontSize: '0.85rem' }}>
                  <th style={{ padding: '12px 14px' }}>ชื่อ - นามสกุล</th>
                  <th style={{ padding: '12px 14px' }}>อีเมล / แผนก</th>
                  <th style={{ padding: '12px 14px' }}>บทบาท (Role)</th>
                  <th style={{ padding: '12px 14px' }}>การผูกบัญชี Google</th>
                  <th style={{ padding: '12px 14px' }}>สถานะ 2FA</th>
                  <th style={{ padding: '12px 14px' }}>สถานะบัญชี</th>
                  <th style={{ padding: '12px 14px', textAlign: 'right' }}>จัดการ</th>
                </tr>
              </thead>
              <tbody>
                {staffList.map((item) => (
                  <tr key={item.id} style={{ borderBottom: '1px solid #f1f5f9', fontSize: '0.9rem' }}>
                    
                    <td style={{ padding: '14px' }}>
                      <div style={{ fontWeight: 700, color: '#1e293b' }}>{item.name}</div>
                      <div style={{ fontSize: '0.78rem', color: '#64748b' }}>@{item.username}</div>
                    </td>

                    <td style={{ padding: '14px' }}>
                      <div style={{ color: '#334155' }}>{item.email}</div>
                      {item.department && <div style={{ fontSize: '0.78rem', color: '#64748b' }}>{item.department}</div>}
                    </td>

                    <td style={{ padding: '14px' }}>
                      <span style={{ 
                        display: 'inline-block', padding: '4px 10px', borderRadius: '6px', fontSize: '0.78rem', fontWeight: 700,
                        background: item.role_slug === 'super_admin' ? '#fef3c7' : '#eff6ff',
                        color: item.role_slug === 'super_admin' ? '#92400e' : '#1d4ed8'
                      }}>
                        {item.role_name}
                      </span>
                    </td>

                    <td style={{ padding: '14px' }}>
                      {item.google.linked ? (
                        <div>
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#059669', fontSize: '0.8rem', fontWeight: 600 }}>
                            <CheckCircle size={14} /> ผูกแล้ว ({item.google.google_email})
                          </span>
                          <div style={{ fontSize: '0.72rem', color: '#94a3b8', fontFamily: 'monospace' }}>
                            sub: {item.google.google_sub ? `${item.google.google_sub.slice(0, 10)}...` : '-'}
                          </div>
                        </div>
                      ) : (
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#d97706', fontSize: '0.8rem' }}>
                          <Clock size={14} /> ยังไม่ผูก Google
                        </span>
                      )}
                    </td>

                    <td style={{ padding: '14px' }}>
                      {item.mfa.enrolled ? (
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#059669', fontSize: '0.82rem', fontWeight: 600 }}>
                          <ShieldCheck size={16} /> 2FA Active ({item.mfa.remaining_recovery_codes} codes)
                        </span>
                      ) : (
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#e11d48', fontSize: '0.82rem', fontWeight: 600 }}>
                          <AlertTriangle size={16} /> บังคับลงทะเบียน 2FA
                        </span>
                      )}
                    </td>

                    <td style={{ padding: '14px' }}>
                      <span style={{
                        display: 'inline-block', padding: '3px 8px', borderRadius: '6px', fontSize: '0.75rem', fontWeight: 700,
                        background: item.status === 'active' ? '#ecfdf5' : '#fff1f2',
                        color: item.status === 'active' ? '#065f46' : '#9f1239'
                      }}>
                        {item.status === 'active' ? 'ใช้งานปกติ' : item.status === 'suspended' ? 'ระงับการใช้งาน' : 'ปิดใช้งาน'}
                      </span>
                    </td>

                    <td style={{ padding: '14px', textAlign: 'right' }}>
                      <div style={{ display: 'inline-flex', gap: '6px' }}>
                        <button
                          type="button"
                          className="btn btn-outline"
                          style={{ padding: '4px 10px', fontSize: '0.78rem' }}
                          onClick={() => {
                            setEditForm({ id: item.id, name: item.name, role_id: item.role_id, department: item.department || '' });
                            setShowEditModal(true);
                          }}
                        >
                          แก้ไขสิทธิ์
                        </button>

                        <button
                          type="button"
                          className="btn btn-outline"
                          style={{ padding: '4px 8px', fontSize: '0.78rem', color: '#b91c1c' }}
                          title="ยกเลิกเซสชันทั้งหมดที่ค้างอยู่"
                          onClick={() => handleRevokeSessions(item)}
                        >
                          <RotateCw size={13} /> เพิกถอนเซสชัน
                        </button>

                        {item.google.linked && (
                          <button
                            type="button"
                            className="btn btn-outline"
                            style={{ padding: '4px 8px', fontSize: '0.78rem', color: '#d97706' }}
                            title="รีเซ็ตการเชื่อมต่อ Google สำหรับกู้คืนบัญชี"
                            onClick={() => handleResetGoogleLink(item)}
                          >
                            รีเซ็ต Google
                          </button>
                        )}

                        <button
                          type="button"
                          className="btn btn-outline"
                          style={{ 
                            padding: '4px 8px', fontSize: '0.78rem', 
                            color: item.status === 'suspended' ? '#059669' : '#dc2626' 
                          }}
                          onClick={() => handleToggleSuspend(item)}
                        >
                          {item.status === 'suspended' ? 'ปลดระงับ' : 'ระงับ'}
                        </button>
                      </div>
                    </td>

                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {/* Tab 2: Staff Invitations */}
      {activeTab === 'invitations' && (
        <section className="surface-card" style={{ padding: '24px', borderRadius: '18px' }}>
          
          {createdInvite && (
            <div style={{ background: '#ecfdf5', border: '1px solid #a7f3d0', borderRadius: '14px', padding: '18px', marginBottom: '24px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#065f46', fontWeight: 700, marginBottom: '6px' }}>
                <Sparkles size={18} /> สร้างคำเชิญสำหรับ {createdInvite.name} ({createdInvite.email}) สำเร็จ!
              </div>
              <p style={{ margin: '0 0 12px', fontSize: '0.88rem', color: '#047857' }}>
                ส่งลิงก์ด้านล่างนี้ให้เจ้าหน้าที่เพื่อกดเข้าสู่ระบบผ่าน Google Account:
              </p>
              <div style={{ display: 'flex', gap: '10px' }}>
                <input 
                  readOnly 
                  value={createdInvite.invitation_url} 
                  style={{ flex: 1, padding: '10px 14px', background: '#fff', border: '1px solid #6ee7b7', borderRadius: '8px', fontSize: '0.88rem', fontFamily: 'monospace' }} 
                />
                <button
                  type="button"
                  onClick={() => copyInviteLink(createdInvite.invitation_url)}
                  className="btn btn-primary"
                  style={{ display: 'flex', alignItems: 'center', gap: '6px', whiteSpace: 'nowrap' }}
                >
                  {copiedToken ? <><Check size={16} /> คัดลอกแล้ว</> : <><Copy size={16} /> คัดลอกลิงก์</>}
                </button>
              </div>
            </div>
          )}

          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
              <thead>
                <tr style={{ borderBottom: '2px solid #e2e8f0', color: '#64748b', fontSize: '0.85rem' }}>
                  <th style={{ padding: '12px 14px' }}>ชื่อ - นามสกุล</th>
                  <th style={{ padding: '12px 14px' }}>อีเมล Google ที่ได้รับอนุมัติ</th>
                  <th style={{ padding: '12px 14px' }}>บทบาทที่จะได้รับ</th>
                  <th style={{ padding: '12px 14px' }}>ผู้เชิญ</th>
                  <th style={{ padding: '12px 14px' }}>วันหมดอายุ</th>
                  <th style={{ padding: '12px 14px' }}>สถานะคำเชิญ</th>
                  <th style={{ padding: '12px 14px', textAlign: 'right' }}>จัดการ</th>
                </tr>
              </thead>
              <tbody>
                {invitations.map((inv) => (
                  <tr key={inv.id} style={{ borderBottom: '1px solid #f1f5f9', fontSize: '0.9rem' }}>
                    <td style={{ padding: '14px', fontWeight: 700 }}>{inv.name}</td>
                    <td style={{ padding: '14px', color: '#1e293b' }}>{inv.email}</td>
                    <td style={{ padding: '14px' }}>
                      <span style={{ padding: '4px 8px', borderRadius: '6px', background: '#eff6ff', color: '#1d4ed8', fontSize: '0.78rem', fontWeight: 600 }}>
                        {inv.role_name}
                      </span>
                    </td>
                    <td style={{ padding: '14px', color: '#64748b', fontSize: '0.85rem' }}>{inv.inviter_name}</td>
                    <td style={{ padding: '14px', color: '#64748b', fontSize: '0.85rem' }}>{new Date(inv.expires_at).toLocaleDateString('th-TH')}</td>
                    <td style={{ padding: '14px' }}>
                      <span style={{
                        display: 'inline-block', padding: '3px 8px', borderRadius: '6px', fontSize: '0.75rem', fontWeight: 700,
                        background: inv.status === 'accepted' ? '#ecfdf5' : inv.status === 'pending' ? '#fff7ed' : '#f1f5f9',
                        color: inv.status === 'accepted' ? '#065f46' : inv.status === 'pending' ? '#c2410c' : '#64748b'
                      }}>
                        {inv.status === 'accepted' ? 'ยืนยันและเปิดใช้งานแล้ว' : inv.status === 'pending' ? 'รอยืนยันด้วย Google' : inv.status === 'expired' ? 'หมดอายุ' : 'ยกเลิกแล้ว'}
                      </span>
                    </td>
                    <td style={{ padding: '14px', textAlign: 'right' }}>
                      {inv.status === 'pending' ? (
                        <div style={{ display: 'inline-flex', gap: '8px' }}>
                          <button
                            type="button"
                            className="btn btn-outline"
                            style={{ padding: '4px 10px', fontSize: '0.78rem' }}
                            onClick={() => handleResendInvitation(inv.id)}
                            disabled={busy}
                          >
                            ต่ออายุ / สร้างลิงก์
                          </button>
                          <button
                            type="button"
                            className="btn btn-outline"
                            style={{ padding: '4px 10px', fontSize: '0.78rem', color: '#b91c1c' }}
                            onClick={() => handleRevokeInvitation(inv.id)}
                            disabled={busy}
                          >
                            ยกเลิก
                          </button>
                        </div>
                      ) : (
                        <span style={{ fontSize: '0.8rem', color: '#94a3b8' }}>-</span>
                      )}
                    </td>
                  </tr>
                ))}
                {invitations.length === 0 && (
                  <tr>
                    <td colSpan={7} style={{ textAlign: 'center', padding: '36px', color: '#94a3b8' }}>
                      ยังไม่มีรายการคำเชิญเจ้าหน้าที่
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {/* Tab 3: Security Dashboard & Audit Events */}
      {activeTab === 'security' && (
        <section style={{ display: 'grid', gap: '24px' }}>
          
          {/* Failed Logins Summary */}
          <div className="surface-card" style={{ padding: '24px', borderRadius: '18px' }}>
            <h3 style={{ fontSize: '1.1rem', fontWeight: 700, color: '#1e293b', margin: '0 0 16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <ShieldAlert size={20} style={{ color: '#e11d48' }} /> สรุปการเข้าสู่ระบบที่ไม่สำเร็จ (Failed Authentication)
            </h3>
            
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '24px', alignItems: 'center' }}>
              <div style={{ background: '#fff1f2', borderRadius: '14px', padding: '20px', border: '1px solid #fecdd3' }}>
                <div style={{ fontSize: '0.85rem', color: '#9f1239', fontWeight: 600 }}>พยายามเข้าสู่ระบบไม่สำเร็จ (7 วันล่าสุด)</div>
                <div style={{ fontSize: '2.2rem', fontWeight: 800, color: '#9f1239', margin: '6px 0' }}>
                  {securityData?.metrics?.failed_logins_7d || 0} ครั้ง
                </div>
                <div style={{ fontSize: '0.78rem', color: '#be123c' }}>
                  ใน 24 ชั่วโมงล่าสุด: {securityData?.metrics?.failed_logins_24h || 0} ครั้ง
                </div>
              </div>

              <div>
                <h4 style={{ fontSize: '0.9rem', color: '#475569', margin: '0 0 10px' }}>สาเหตุข้อผิดพลาด 5 อันดับแรก:</h4>
                <div style={{ display: 'grid', gap: '8px' }}>
                  {(securityData?.top_failure_reasons || []).map((r, idx) => (
                    <div key={idx} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 12px', background: '#f8fafc', borderRadius: '8px', fontSize: '0.85rem' }}>
                      <span style={{ color: '#334155' }}>{r.failure_reason}</span>
                      <strong style={{ color: '#e11d48' }}>{r.count} ครั้ง</strong>
                    </div>
                  ))}
                  {(!securityData?.top_failure_reasons || securityData.top_failure_reasons.length === 0) && (
                    <p style={{ color: '#94a3b8', fontSize: '0.85rem' }}>ไม่มีรายการผิดพลาดในรอบ 7 วันที่ผ่านมา</p>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Recent Audit Events Feed */}
          <div className="surface-card" style={{ padding: '24px', borderRadius: '18px' }}>
            <h3 style={{ fontSize: '1.1rem', fontWeight: 700, color: '#1e293b', margin: '0 0 16px' }}>
              ประวัติการเข้าสู่ระบบล่าสุด (Authentication Audit Logs)
            </h3>
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.85rem' }}>
                <thead>
                  <tr style={{ borderBottom: '2px solid #e2e8f0', color: '#64748b' }}>
                    <th style={{ padding: '10px 12px' }}>เวลา</th>
                    <th style={{ padding: '10px 12px' }}>อีเมล / บัญชี</th>
                    <th style={{ padding: '10px 12px' }}>ผู้ให้บริการ</th>
                    <th style={{ padding: '10px 12px' }}>ผลลัพธ์</th>
                    <th style={{ padding: '10px 12px' }}>IP Address</th>
                    <th style={{ padding: '10px 12px' }}>รายละเอียด / ข้อความ</th>
                  </tr>
                </thead>
                <tbody>
                  {(securityData?.recent_events || []).map((ev) => (
                    <tr key={ev.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                      <td style={{ padding: '10px 12px', whiteSpace: 'nowrap', color: '#64748b' }}>
                        {new Date(ev.created_at).toLocaleString('th-TH')}
                      </td>
                      <td style={{ padding: '10px 12px', fontWeight: 600 }}>
                        {ev.email} {ev.user_name && <span style={{ color: '#64748b', fontWeight: 400 }}>({ev.user_name})</span>}
                      </td>
                      <td style={{ padding: '10px 12px' }}>
                        <span style={{ 
                          padding: '2px 8px', borderRadius: '4px', fontSize: '0.72rem', fontWeight: 700,
                          background: ev.auth_provider === 'google' ? '#eff6ff' : '#f1f5f9',
                          color: ev.auth_provider === 'google' ? '#2563eb' : '#475569'
                        }}>
                          {ev.auth_provider === 'google' ? 'Google OIDC' : 'Local'}
                        </span>
                      </td>
                      <td style={{ padding: '10px 12px' }}>
                        <span style={{ 
                          color: ev.status === 'success' ? '#059669' : '#dc2626', 
                          fontWeight: 700, display: 'inline-flex', alignItems: 'center', gap: '4px' 
                        }}>
                          {ev.status === 'success' ? <CheckCircle size={14} /> : <AlertTriangle size={14} />}
                          {ev.status === 'success' ? 'สำเร็จ' : ev.status === '2fa_failed' ? '2FA ไม่ผ่าน' : ev.status === 'locked_out' ? 'ถูกระงับ' : 'ล้มเหลว'}
                        </span>
                      </td>
                      <td style={{ padding: '10px 12px', fontFamily: 'monospace', color: '#475569' }}>
                        {ev.ip_address}
                      </td>
                      <td style={{ padding: '10px 12px', color: '#64748b' }}>
                        {ev.failure_reason || '-'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

        </section>
      )}

      {/* Invite Modal */}
      {showInviteModal && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(15, 23, 42, 0.65)', display: 'grid', placeItems: 'center', zIndex: 9999, padding: '20px' }}>
          <div className="surface-card" style={{ width: '100%', maxWidth: '520px', borderRadius: '20px', padding: '30px', boxShadow: '0 25px 70px rgba(0,0,0,0.2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
              <h2 style={{ fontSize: '1.25rem', fontWeight: 800, margin: 0, color: 'var(--primary-800)' }}>
                เชิญเจ้าหน้าที่เข้าร่วมงาน (Google Account)
              </h2>
              <button 
                type="button" 
                onClick={() => setShowInviteModal(false)}
                style={{ background: 'transparent', border: 0, fontSize: '1.2rem', cursor: 'pointer', color: '#94a3b8' }}
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleInviteSubmit}>
              <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', fontWeight: 700, fontSize: '0.88rem', marginBottom: '6px' }}>
                  ชื่อ - นามสกุล เจ้าหน้าที่:
                </label>
                <input
                  required
                  type="text"
                  placeholder="เช่น นางสาวสมใจ สดใส"
                  value={inviteForm.name}
                  onChange={(e) => setInviteForm({ ...inviteForm, name: e.target.value })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: '10px', border: '1px solid #cbd5e1' }}
                />
              </div>

              <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', fontWeight: 700, fontSize: '0.88rem', marginBottom: '6px' }}>
                  อีเมล Google Account ที่ได้รับอนุมัติ:
                </label>
                <input
                  required
                  type="email"
                  placeholder="เช่น user@gmail.com หรือ user@workspace.org"
                  value={inviteForm.email}
                  onChange={(e) => setInviteForm({ ...inviteForm, email: e.target.value })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: '10px', border: '1px solid #cbd5e1' }}
                />
              </div>

              <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', fontWeight: 700, fontSize: '0.88rem', marginBottom: '6px' }}>
                  แผนก / ส่วนงาน:
                </label>
                <input
                  type="text"
                  placeholder="เช่น แผนกสินเชื่อ, การเงิน, ธุรการ"
                  value={inviteForm.department}
                  onChange={(e) => setInviteForm({ ...inviteForm, department: e.target.value })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: '10px', border: '1px solid #cbd5e1' }}
                />
              </div>

              <div style={{ marginBottom: '24px' }}>
                <label style={{ display: 'block', fontWeight: 700, fontSize: '0.88rem', marginBottom: '6px' }}>
                  บทบาทและสิทธิ์การใช้งาน (Role):
                </label>
                <select
                  required
                  value={inviteForm.role_id}
                  onChange={(e) => setInviteForm({ ...inviteForm, role_id: e.target.value })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: '10px', border: '1px solid #cbd5e1' }}
                >
                  <option value="">-- เลือกบทบาท --</option>
                  {roles.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.name} ({r.slug})
                    </option>
                  ))}
                </select>
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => setShowInviteModal(false)}
                  disabled={busy}
                >
                  ยกเลิก
                </button>
                <button
                  type="submit"
                  className="btn btn-primary"
                  disabled={busy}
                  style={{ minWidth: '120px' }}
                >
                  {busy ? 'กำลังสร้างคำเชิญ...' : 'สร้างคำเชิญ'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Role Modal */}
      {showEditModal && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(15, 23, 42, 0.65)', display: 'grid', placeItems: 'center', zIndex: 9999, padding: '20px' }}>
          <div className="surface-card" style={{ width: '100%', maxWidth: '480px', borderRadius: '20px', padding: '30px' }}>
            <h2 style={{ fontSize: '1.25rem', fontWeight: 800, margin: '0 0 16px', color: 'var(--primary-800)' }}>
              แก้ไขบทบาทและแผนก: {editForm.name}
            </h2>

            <form onSubmit={handleSaveRole}>
              <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', fontWeight: 700, fontSize: '0.88rem', marginBottom: '6px' }}>แผนก:</label>
                <input
                  type="text"
                  value={editForm.department}
                  onChange={(e) => setEditForm({ ...editForm, department: e.target.value })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: '10px', border: '1px solid #cbd5e1' }}
                />
              </div>

              <div style={{ marginBottom: '24px' }}>
                <label style={{ display: 'block', fontWeight: 700, fontSize: '0.88rem', marginBottom: '6px' }}>บทบาท (Role):</label>
                <select
                  required
                  value={editForm.role_id}
                  onChange={(e) => setEditForm({ ...editForm, role_id: e.target.value })}
                  style={{ width: '100%', padding: '10px 14px', borderRadius: '10px', border: '1px solid #cbd5e1' }}
                >
                  {roles.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.name}
                    </option>
                  ))}
                </select>
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                <button type="button" className="btn btn-outline" onClick={() => setShowEditModal(false)}>
                  ยกเลิก
                </button>
                <button type="submit" className="btn btn-primary" disabled={busy}>
                  {busy ? 'กำลังบันทึก...' : 'บันทึกการเปลี่ยนแปลง'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
