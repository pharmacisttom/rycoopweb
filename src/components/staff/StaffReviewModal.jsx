import { maskName, maskPhone } from '../../utils/pdpa';
import React, { useState } from 'react';
import { 
  X, CheckCircle2, AlertTriangle, FileText, User, 
  DollarSign, Check, RotateCcw, MessageSquare, ShieldCheck, 
  Send, Eye, ArrowRight, Clock, Building, Phone
} from 'lucide-react';

export default function StaffReviewModal({ isOpen, onClose, request, onApprove, onReject, onAddNote }) {
  if (!isOpen || !request) return null;

  const [activeAction, setActiveAction] = useState('approve'); // 'approve', 'reject', 'note'
  const [remarks, setRemarks] = useState(
    activeAction === 'approve' 
      ? 'ตรวจสอบเอกสารและคุณสมบัติครบถ้วน อนุมัติการดำเนินการตามระเบียบ'
      : ''
  );

  const handleActionSubmit = (e) => {
    e.preventDefault();
    if (activeAction === 'approve') {
      onApprove(request.id, remarks);
    } else if (activeAction === 'reject') {
      onReject(request.id, remarks || 'เอกสารไม่สมบูรณ์ กรุณาแนบหลักฐานเพิ่มเติม');
    } else {
      onAddNote(request.id, remarks);
    }
    onClose();
  };

  return (
    <div style={{
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      background: 'rgba(15, 23, 42, 0.75)',
      backdropFilter: 'blur(8px)',
      zIndex: 2500,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '1rem',
      overflowY: 'auto'
    }}>
      <div className="surface-card animate-fade-in" style={{
        width: '100%',
        maxWidth: '680px',
        maxHeight: '92vh',
        borderRadius: 'var(--radius-xl)',
        padding: '2rem',
        position: 'relative',
        display: 'flex',
        flexDirection: 'column',
        gap: '1.25rem',
        overflowY: 'auto',
        border: '1px solid var(--border-subtle)'
      }}>
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', borderBottom: '1px solid var(--border-subtle)', paddingBottom: '1rem' }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
              <span className="badge badge-primary">{request.id}</span>
              <span className="badge badge-gold">{request.status}</span>
            </div>
            <h3 style={{ fontSize: '1.25rem', color: 'var(--primary-900)', fontWeight: 800 }}>
              ตรวจสอบและพิจารณา: {request.type || request.name}
            </h3>
            <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)', marginTop: '0.2rem' }}>
              วันที่ยื่นคำขอ: <strong>{request.date || request.submitDate || '11 มี.ค. 2567'}</strong>
            </div>
          </div>

          <button 
            onClick={onClose}
            style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer', padding: '0.35rem' }}
          >
            <X size={20} />
          </button>
        </div>

        {/* Member Financial & Profile Snapshot */}
        <div style={{
          background: 'var(--bg-subtle)',
          borderRadius: '12px',
          padding: '1.25rem',
          display: 'grid',
          gridTemplateColumns: 'repeat(2, 1fr)',
          gap: '0.75rem 1.25rem',
          fontSize: '0.85rem'
        }}>
          <div>
            <span style={{ color: 'var(--text-muted)' }}>ชื่อสมาชิก:</span>{' '}
            <strong style={{ color: 'var(--text-main)' }}>{maskName(request.memberName)}</strong>
          </div>
          <div>
            <span style={{ color: 'var(--text-muted)' }}>รหัสสมาชิก:</span>{' '}
            <strong style={{ color: 'var(--primary-600)' }}>{request.memberId || '04892'}</strong>
          </div>
          <div>
            <span style={{ color: 'var(--text-muted)' }}>สังกัด / หน่วยงาน:</span>{' '}
            <strong>{request.department || 'โรงพยาบาลระยอง'}</strong>
          </div>
          <div>
            <span style={{ color: 'var(--text-muted)' }}>เบอร์โทรศัพท์:</span>{' '}
            <strong>{maskPhone(request.phone || '081-234-5678')}</strong>
          </div>
          <div>
            <span style={{ color: 'var(--text-muted)' }}>วงเงินที่ขอทำรายการ:</span>{' '}
            <strong style={{ color: 'var(--primary-700)', fontSize: '1.05rem' }}>{request.amount}</strong>
          </div>
          <div>
            <span style={{ color: 'var(--text-muted)' }}>สถานะเครดิตบูโร/วินัยการชำระ:</span>{' '}
            <span className="badge badge-emerald" style={{ fontSize: '0.7rem' }}>ปกติ (ชั้นดี)</span>
          </div>
        </div>

        {/* Qualification Check List */}
        <div style={{ background: 'rgba(16, 185, 129, 0.08)', border: '1px solid rgba(16, 185, 129, 0.25)', borderRadius: '12px', padding: '1rem' }}>
          <div style={{ fontWeight: 700, fontSize: '0.88rem', color: 'var(--accent-emerald-dark)', marginBottom: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
            <ShieldCheck size={16} />
            <span>ผลการตรวจสอบคุณสมบัติอัตโนมัติ (Pre-qualification Checklist):</span>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.4rem', fontSize: '0.8rem', color: 'var(--text-main)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
              <Check size={14} style={{ color: 'var(--accent-emerald)' }} />
              <span>อายุการเป็นสมาชิกเกิน 6 เดือน</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
              <Check size={14} style={{ color: 'var(--accent-emerald)' }} />
              <span>เงินได้รายเดือนคงเหลือเกิน 30%</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
              <Check size={14} style={{ color: 'var(--accent-emerald)' }} />
              <span>ไม่มีประวัติผิดนัดชำระหนี้</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
              <Check size={14} style={{ color: 'var(--accent-emerald)' }} />
              <span>เอกสารแนบถูกต้องครบถ้วน</span>
            </div>
          </div>
        </div>

        {/* Action Selector Buttons */}
        <div>
          <label className="form-label" style={{ fontSize: '0.9rem', marginBottom: '0.5rem' }}>
            เลือกการดำเนินการของเจ้าหน้าที่ (Officer Decision):
          </label>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '0.5rem' }}>
            <button 
              type="button"
              onClick={() => {
                setActiveAction('approve');
                setRemarks('ตรวจสอบเอกสารและคุณสมบัติครบถ้วน อนุมัติการดำเนินการตามระเบียบ');
              }}
              style={{
                padding: '0.65rem',
                borderRadius: '8px',
                border: activeAction === 'approve' ? '2px solid var(--accent-emerald)' : '1px solid var(--border-subtle)',
                background: activeAction === 'approve' ? 'rgba(16, 185, 129, 0.15)' : 'var(--bg-subtle)',
                color: activeAction === 'approve' ? 'var(--accent-emerald-dark)' : 'var(--text-main)',
                fontWeight: 700,
                fontSize: '0.85rem',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '0.35rem'
              }}
            >
              <CheckCircle2 size={16} />
              <span>อนุมัติคำขอ</span>
            </button>

            <button 
              type="button"
              onClick={() => {
                setActiveAction('reject');
                setRemarks('เอกสารแนบไม่สมบูรณ์ กรุณาอัปโหลดสำเนาสลิปเงินเดือนเดือนล่าสุดเพิ่มเติม');
              }}
              style={{
                padding: '0.65rem',
                borderRadius: '8px',
                border: activeAction === 'reject' ? '2px solid var(--accent-rose)' : '1px solid var(--border-subtle)',
                background: activeAction === 'reject' ? 'rgba(244, 63, 94, 0.15)' : 'var(--bg-subtle)',
                color: activeAction === 'reject' ? 'var(--accent-rose)' : 'var(--text-main)',
                fontWeight: 700,
                fontSize: '0.85rem',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '0.35rem'
              }}
            >
              <RotateCcw size={16} />
              <span>ส่งกลับแก้ไข</span>
            </button>

            <button 
              type="button"
              onClick={() => {
                setActiveAction('note');
                setRemarks('กำลังประสานงานฝ่ายการเงินเพื่อตรวจสอบยอดเงินฝากเพิ่มเติม');
              }}
              style={{
                padding: '0.65rem',
                borderRadius: '8px',
                border: activeAction === 'note' ? '2px solid var(--primary-600)' : '1px solid var(--border-subtle)',
                background: activeAction === 'note' ? 'rgba(37, 99, 235, 0.15)' : 'var(--bg-subtle)',
                color: activeAction === 'note' ? 'var(--primary-600)' : 'var(--text-main)',
                fontWeight: 700,
                fontSize: '0.85rem',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '0.35rem'
              }}
            >
              <MessageSquare size={16} />
              <span>บันทึกหมายเหตุ</span>
            </button>
          </div>
        </div>

        {/* Remarks Form */}
        <form onSubmit={handleActionSubmit}>
          <div className="form-group" style={{ marginBottom: '1.25rem' }}>
            <label className="form-label" style={{ fontSize: '0.85rem' }}>
              {activeAction === 'approve' ? 'บันทึกข้อความการอนุมัติ (จะแจ้งให้สมาชิกทราบใน e-Tracking):' : activeAction === 'reject' ? 'เหตุผลที่ส่งกลับแก้ไข (ระบุสิ่งที่ต้องแก้ไข):' : 'บันทึกหมายเหตุการดำเนินงาน:'}
            </label>
            <textarea 
              className="form-control"
              rows={3}
              value={remarks}
              onChange={(e) => setRemarks(e.target.value)}
              required
            />
          </div>

          <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end' }}>
            <button type="button" onClick={onClose} className="btn btn-subtle btn-sm">
              ยกเลิก
            </button>
            <button 
              type="submit" 
              className={`btn btn-sm ${activeAction === 'approve' ? 'btn-teal' : activeAction === 'reject' ? 'btn-rose' : 'btn-primary'}`}
              style={{ padding: '0.5rem 1.5rem', fontWeight: 700 }}
            >
              {activeAction === 'approve' ? '✓ ยืนยันการอนุมัติคำขอ' : activeAction === 'reject' ? '↩ ส่งกลับให้สมาชิกแก้ไข' : 'บันทึกข้อความ'}
            </button>
          </div>
        </form>

      </div>
    </div>
  );
}
