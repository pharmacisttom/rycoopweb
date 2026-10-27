import { maskIdCard, maskName } from '../utils/pdpa';
import React, { useState, useEffect, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { 
  User, Phone, Camera, UploadCloud, CheckCircle2, 
  Lock, Trash2, ArrowLeft, Save, ShieldCheck, AlertCircle 
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';

export default function ProfilePage() {
  const { user, isLoggedIn, updateProfile, setShowAuthModal } = useAuth();
  const { toast } = useToast();
  const navigate = useNavigate();

  const [avatar, setAvatar] = useState('');
  const [phone, setPhone] = useState('');
  const [isDragging, setIsDragging] = useState(false);
  const [savedSuccess, setSavedSuccess] = useState(false);
  const fileInputRef = useRef(null);

  useEffect(() => {
    if (user) {
      setAvatar(user.avatar || '');
      setPhone(user.phone || '');
    }
  }, [user]);

  if (!isLoggedIn || !user) {
    return (
      <div className="section" style={{ minHeight: '60vh', display: 'flex', alignItems: 'center' }}>
        <div className="container" style={{ textAlign: 'center' }}>
          <div className="glass-card" style={{ maxWidth: '480px', margin: '0 auto', padding: '3rem 2rem', borderRadius: 'var(--radius-xl)' }}>
            <User size={48} style={{ color: 'var(--primary-600)', margin: '0 auto 1rem auto' }} />
            <h2 style={{ fontSize: '1.4rem', marginBottom: '0.5rem' }}>กรุณาเข้าสู่ระบบ</h2>
            <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '1.5rem' }}>
              เพื่อเข้าถึงหน้าแก้ไขข้อมูลส่วนตัวของคุณ
            </p>
            <button onClick={() => setShowAuthModal(true)} className="btn btn-primary" style={{ width: '100%' }}>
              <span>เข้าสู่ระบบ</span>
            </button>
          </div>
        </div>
      </div>
    );
  }

  const handleFileUpload = (file) => {
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      toast.error('กรุณาเลือกไฟล์รูปภาพเท่านั้น (เช่น PNG, JPG, JPEG, WEBP)');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error('ไฟล์รูปภาพมีขนาดใหญ่เกิน 5MB กรุณาเลือกไฟล์ขนาดเล็กลง');
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      setAvatar(e.target.result);
    };
    reader.readAsDataURL(file);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileUpload(e.dataTransfer.files[0]);
    }
  };

  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!phone.trim()) {
      toast.error('กรุณาระบุเบอร์โทรศัพท์ติดต่อ');
      return;
    }

    updateProfile({ avatar, phone: phone.trim() });
    toast.success('บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว');
    setSavedSuccess(true);
    setTimeout(() => {
      setSavedSuccess(false);
    }, 2500);
  };

  return (
    <div className="section" style={{ background: 'var(--bg-main)', minHeight: '85vh' }}>
      <div className="container" style={{ maxWidth: '720px' }}>
        
        {/* Navigation Breadcrumb */}
        <div style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <button onClick={() => navigate(-1)} className="btn btn-sm btn-subtle" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
            <ArrowLeft size={16} />
            <span>ย้อนกลับ</span>
          </button>
          <span style={{ color: 'var(--text-muted)' }}>/</span>
          <span style={{ fontSize: '0.88rem', fontWeight: 600, color: 'var(--text-main)' }}>แก้ไขข้อมูลส่วนตัว</span>
        </div>

        <div className="surface-card" style={{ padding: '2.5rem 2rem', borderRadius: 'var(--radius-xl)' }}>
          
          <div style={{ borderBottom: '1px solid var(--border-subtle)', paddingBottom: '1.25rem', marginBottom: '1.75rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
              <div style={{ padding: '0.6rem', borderRadius: '12px', background: 'var(--primary-50)', color: 'var(--primary-600)' }}>
                <User size={26} />
              </div>
              <div>
                <h1 style={{ fontSize: '1.4rem', color: 'var(--primary-900)', fontWeight: 800, margin: 0 }}>
                  แก้ไขข้อมูลส่วนตัว (Edit Profile)
                </h1>
                <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', margin: '0.2rem 0 0 0' }}>
                  สิทธิ์การใช้งาน: <strong style={{ color: 'var(--primary-700)' }}>{user.roleName || user.roleBadge}</strong>
                </p>
              </div>
            </div>
          </div>

          {savedSuccess && (
            <div className="animate-fade-in" style={{
              background: 'var(--accent-emerald-light)',
              color: 'var(--accent-emerald-dark)',
              padding: '1rem 1.25rem',
              borderRadius: '12px',
              marginBottom: '1.5rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem',
              fontWeight: 700,
              fontSize: '0.95rem'
            }}>
              <CheckCircle2 size={22} />
              <span>บันทึกการเปลี่ยนแปลงรูปภาพโปรไฟล์และเบอร์โทรศัพท์เรียบร้อยแล้ว!</span>
            </div>
          )}

          <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1.75rem' }}>
            
            {/* 1. Profile Picture */}
            <div style={{
              background: 'var(--bg-subtle)',
              padding: '1.5rem',
              borderRadius: '16px',
              border: '1px solid var(--border-subtle)'
            }}>
              <label className="form-label" style={{ fontWeight: 700, fontSize: '0.95rem', marginBottom: '0.85rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span>1. รูปภาพโปรไฟล์ (Profile Picture)</span>
                <span className="badge badge-emerald" style={{ fontSize: '0.72rem' }}>อนุญาตแก้ไขได้</span>
              </label>

              <div style={{ display: 'flex', alignItems: 'center', gap: '1.75rem', flexWrap: 'wrap' }}>
                <div 
                  onClick={() => fileInputRef.current && fileInputRef.current.click()}
                  style={{
                    width: '100px',
                    height: '100px',
                    borderRadius: '50%',
                    background: 'var(--gradient-primary)',
                    color: '#ffffff',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '2.5rem',
                    fontWeight: 800,
                    boxShadow: 'var(--shadow-md)',
                    position: 'relative',
                    overflow: 'hidden',
                    cursor: 'pointer',
                    border: '3px solid #ffffff',
                    flexShrink: 0
                  }}
                  title="คลิกเพื่อเปลี่ยนรูปภาพ"
                >
                  {avatar ? (
                    <img src={avatar} alt="Profile" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                  ) : (
                    user.name?.charAt(0) || 'U'
                  )}

                  <div style={{
                    position: 'absolute',
                    bottom: 0,
                    left: 0,
                    right: 0,
                    background: 'rgba(0, 0, 0, 0.55)',
                    padding: '4px 0',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    color: '#ffffff'
                  }}>
                    <Camera size={16} />
                  </div>
                </div>

                <div style={{ flex: 1, minWidth: '240px' }}>
                  <input
                    type="file"
                    ref={fileInputRef}
                    accept="image/*"
                    style={{ display: 'none' }}
                    onChange={(e) => {
                      if (e.target.files && e.target.files[0]) {
                        handleFileUpload(e.target.files[0]);
                      }
                    }}
                  />

                  <div
                    onDrop={handleDrop}
                    onDragOver={handleDragOver}
                    onDragLeave={handleDragLeave}
                    onClick={() => fileInputRef.current && fileInputRef.current.click()}
                    style={{
                      border: isDragging ? '2px dashed var(--primary-600)' : '2px dashed var(--border-subtle)',
                      background: isDragging ? 'rgba(2, 132, 199, 0.08)' : 'var(--bg-surface)',
                      borderRadius: '12px',
                      padding: '1.25rem 1rem',
                      textAlign: 'center',
                      cursor: 'pointer',
                      transition: 'all 0.2s ease'
                    }}
                  >
                    <UploadCloud size={26} style={{ color: 'var(--primary-600)', margin: '0 auto 0.35rem auto' }} />
                    <div style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-main)' }}>
                      {isDragging ? 'ปล่อยรูปภาพที่นี่' : 'คลิกเลือกไฟล์ หรือลากรูปภาพมาวางที่นี่'}
                    </div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.2rem' }}>
                      PNG, JPG, WEBP (ขนาดไฟล์สูงสุด 5MB)
                    </div>
                  </div>

                  {avatar && (
                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '0.5rem' }}>
                      <button
                        type="button"
                        onClick={() => setAvatar('')}
                        className="btn btn-sm btn-subtle"
                        style={{ fontSize: '0.75rem', color: 'var(--accent-rose)', padding: '0.2rem 0.6rem' }}
                      >
                        <Trash2 size={13} style={{ marginRight: '0.25rem' }} />
                        <span>ลบรูปภาพ (ใช้ตัวอักษรย่อ)</span>
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* 2. Phone Number */}
            <div>
              <label className="form-label" style={{ fontWeight: 700, fontSize: '0.95rem', marginBottom: '0.4rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span>2. เบอร์โทรศัพท์มือถือ (Phone Number) <span style={{ color: 'var(--accent-rose)' }}>*</span></span>
                <span className="badge badge-emerald" style={{ fontSize: '0.72rem' }}>อนุญาตแก้ไขได้</span>
              </label>
              <div style={{ position: 'relative' }}>
                <Phone size={18} style={{ position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)', color: 'var(--primary-600)' }} />
                <input
                  type="tel"
                  className="form-control"
                  required
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="เช่น 081-234-5678"
                  style={{ paddingLeft: '2.75rem', fontSize: '1rem', fontWeight: 600 }}
                />
              </div>
              <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '0.4rem', margin: '0.4rem 0 0 0' }}>
                💡 ใช้สำหรับติดต่อแจ้งผลการอนุมัติสินเชื่อ, รับรหัส OTP, และการสื่อสารทางการจากสหกรณ์
              </p>
            </div>

            {/* 3. Locked Official Information */}
            <div style={{
              background: 'var(--bg-subtle)',
              padding: '1.5rem',
              borderRadius: '16px',
              border: '1px solid var(--border-subtle)',
              fontSize: '0.88rem'
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', fontWeight: 700, color: 'var(--text-muted)', marginBottom: '1rem' }}>
                <Lock size={16} style={{ color: 'var(--accent-gold-dark)' }} />
                <span>ข้อมูลทางการประจำตัว (ล็อคความปลอดภัย - ไม่สามารถแก้ไขเองได้)</span>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1rem' }}>
                <div>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>ชื่อ - นามสกุล:</span>
                  <div style={{ fontWeight: 700, color: 'var(--text-main)', marginTop: '0.1rem', fontSize: '0.95rem' }}>{maskName(user.name)}</div>
                </div>

                <div>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>รหัสประจำตัว / สมาชิก:</span>
                  <div style={{ fontWeight: 700, color: 'var(--primary-700)', marginTop: '0.1rem', fontSize: '0.95rem' }}>{user.username && user.username.length === 13 ? maskIdCard(user.username) : (user.username || user.memberId)}</div>
                </div>

                <div>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>บทบาท / สิทธิ์ในระบบ:</span>
                  <div style={{ fontWeight: 700, color: 'var(--text-main)', marginTop: '0.1rem', fontSize: '0.95rem' }}>{user.roleBadge || user.roleName}</div>
                </div>

                <div>
                  <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>สังกัด / หน่วยงาน:</span>
                  <div style={{ fontWeight: 700, color: 'var(--text-main)', marginTop: '0.1rem', fontSize: '0.95rem' }}>{user.department || '-'}</div>
                </div>
              </div>

              <div style={{
                marginTop: '1.25rem',
                paddingTop: '1rem',
                borderTop: '1px dashed var(--border-subtle)',
                fontSize: '0.78rem',
                color: 'var(--text-muted)',
                display: 'flex',
                alignItems: 'center',
                gap: '0.4rem'
              }}>
                <AlertCircle size={15} style={{ color: 'var(--accent-gold)' }} />
                <span>หากต้องการแก้ไขชื่อ-สกุล หรือเปลี่ยนสังกัด กรุณาติดต่อฝ่ายทะเบียนสมาชิก สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด</span>
              </div>
            </div>

            {/* Buttons */}
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem', marginTop: '0.5rem' }}>
              <button
                type="button"
                onClick={() => navigate(-1)}
                className="btn btn-subtle"
              >
                ยกเลิก
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem', padding: '0.75rem 2rem', fontSize: '0.95rem' }}
              >
                <Save size={18} />
                <span>บันทึกข้อมูลส่วนตัว</span>
              </button>
            </div>

          </form>

        </div>
      </div>
    </div>
  );
}
