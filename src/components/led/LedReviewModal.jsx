import { maskIdCard, maskName } from '../../utils/pdpa';
import React, { useState, useEffect } from 'react';
import {
  X, CheckCircle2, AlertTriangle, ShieldAlert,
  HelpCircle, User, Scale, ArrowRight, Loader2, Save
} from 'lucide-react';
import LedStatusBadge from './LedStatusBadge';
import LedWarningNotice from './LedWarningNotice';
import { fetchLedReviewDetail, submitLedReview } from '../../services/ledApi';
import { useToast } from '../../context/ToastContext';

export default function LedReviewModal({ resultId, isOpen, onClose, onSuccess }) {
  const { toast, confirmDialog } = useToast();
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [data, setData] = useState(null);
  const [decision, setDecision] = useState('REVIEW_REQUIRED');
  const [note, setNote] = useState('');

  useEffect(() => {
    if (isOpen && resultId) {
      loadDetail(resultId);
    } else {
      setData(null);
      setNote('');
    }
  }, [isOpen, resultId]);

  const loadDetail = async (id) => {
    setLoading(true);
    try {
      const res = await fetchLedReviewDetail(id);
      if (res.success && res.data) {
        setData(res.data);
        setDecision(res.data.result?.status === 'VERIFIED_MATCH' || res.data.result?.status === 'FALSE_MATCH'
          ? res.data.result.status
          : 'VERIFIED_MATCH'
        );
        setNote(res.data.result?.review_note || '');
      } else {
        toast.error(res.message || 'ไม่สามารถโหลดรายละเอียดผลการตรวจได้');
        onClose();
      }
    } catch (e) {
      toast.error('เกิดข้อผิดพลาดในการโหลดข้อมูล');
      onClose();
    } finally {
      setLoading(false);
    }
  };

  const handleDecisionSubmit = async () => {
    if (!note.trim()) {
      toast.warning('กรุณาระบุหมายเหตุหรือเหตุผลประกอบการตัดสินใจ');
      return;
    }

    const decisionLabels = {
      VERIFIED_MATCH: 'ยืนยันว่าตรงกัน (Verified Match)',
      FALSE_MATCH: 'ยืนยันว่าไม่ตรงกัน / ข้อมูลคลาดเคลื่อน (False Match)',
      REVIEW_REQUIRED: 'ยังต้องตรวจสอบเพิ่มเติม (Review Required)',
    };

    const isConfirmed = await confirmDialog({
      title: 'ยืนยันผลการตัดสินโดยเจ้าหน้าที่',
      message: `คุณต้องการบันทึกผลการตรวจสอบเป็น "${decisionLabels[decision]}" หรือไม่? การกระทำนี้จะถูกบันทึกใน Audit Trail พร้อมระบุผู้ตรวจสอบ`,
      confirmText: 'ยืนยันการบันทึก',
      cancelText: 'ยกเลิก',
      isDestructive: decision === 'VERIFIED_MATCH',
    });

    if (!isConfirmed) return;

    setSubmitting(true);
    try {
      const res = await submitLedReview(resultId, decision, note);
      if (res.success) {
        toast.success('บันทึกผลการตรวจสอบโดยเจ้าหน้าที่เรียบร้อยแล้ว');
        if (onSuccess) onSuccess();
        onClose();
      } else {
        toast.error(res.message || 'ไม่สามารถบันทึกผลได้');
      }
    } catch (e) {
      toast.error('เกิดข้อผิดพลาดในการบันทึกผล');
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen) return null;

  const member = data?.member || {};
  const result = data?.result || {};
  const candidates = data?.candidates || [];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 md:p-6 overflow-y-auto bg-slate-900/60 backdrop-blur-sm animate-fade-in">
      <div className="relative w-full max-w-5xl rounded-3xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col max-h-[92vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/50">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-2xl bg-[#073B74] text-white">
              <Scale className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                ตรวจสอบและยืนยันผล (Human-in-the-Loop Review)
              </h2>
              <p className="text-xs text-slate-500 dark:text-slate-400">
                เปรียบเทียบข้อมูลสมาชิกสหกรณ์กับข้อมูลเปิดกรมบังคับคดี เพื่อการตัดสินอย่างถูกต้องตามกฎหมาย
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {loading ? (
            <div className="py-20 text-center space-y-3">
              <Loader2 className="w-10 h-10 animate-spin text-[#0B5ED7] mx-auto" />
              <p className="text-sm text-slate-500">กำลังโหลดรายละเอียดข้อมูลการตรวจสอบ...</p>
            </div>
          ) : (
            <>
              {/* Disclaimer */}
              <LedWarningNotice />

              {/* Side-by-Side Comparison */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Left: Cooperative Member Details */}
                <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-850/40 p-5 space-y-4">
                  <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-700/60 pb-3">
                    <div className="flex items-center gap-2">
                      <User className="w-4 h-4 text-[#073B74] dark:text-blue-400" />
                      <h3 className="font-bold text-slate-900 dark:text-white text-sm">
                        1. ข้อมูลสมาชิกสหกรณ์ (RayongCoop)
                      </h3>
                    </div>
                    <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/60 text-[#073B74] dark:text-blue-300">
                      สมาชิกสหกรณ์
                    </span>
                  </div>

                  <div className="space-y-3 text-xs lg:text-sm">
                    <div>
                      <span className="text-slate-500 text-xs">ชื่อ-นามสกุล:</span>
                      <p className="font-bold text-slate-900 dark:text-white text-base">
                        {member.full_name || '-'}
                      </p>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <span className="text-slate-500 text-xs">เลขทะเบียนสมาชิก:</span>
                        <p className="font-semibold text-slate-800 dark:text-slate-200">{member.member_no || '-'}</p>
                      </div>
                      <div>
                        <span className="text-slate-500 text-xs">เลขบัตร ปชช. (Masked):</span>
                        <p className="font-mono font-semibold text-slate-800 dark:text-slate-200">{member.id_card_pdpa || maskIdCard(member.id_card_masked || member.id_card) || '-'}</p>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <span className="text-slate-500 text-xs">สังกัด / หน่วยงาน:</span>
                        <p className="font-medium text-slate-700 dark:text-slate-300">{member.department || '-'}</p>
                      </div>
                      <div>
                        <span className="text-slate-500 text-xs">ตำแหน่ง:</span>
                        <p className="font-medium text-slate-700 dark:text-slate-300">{member.position || '-'}</p>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <span className="text-slate-500 text-xs">วันที่เข้าเป็นสมาชิก:</span>
                        <p className="text-slate-700 dark:text-slate-300">{member.join_date || '-'}</p>
                      </div>
                      <div>
                        <span className="text-slate-500 text-xs">เบอร์โทรศัพท์ (Masked):</span>
                        <p className="font-mono text-slate-700 dark:text-slate-300">{member.phone_masked || '-'}</p>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Right: LED Candidates & Match Data */}
                <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-4">
                  <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div className="flex items-center gap-2">
                      <Scale className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                      <h3 className="font-bold text-slate-900 dark:text-white text-sm">
                        2. ข้อมูลกรมบังคับคดี (LED Open Data)
                      </h3>
                    </div>
                    <LedStatusBadge status={result.status} size="sm" />
                  </div>

                  <div className="space-y-3">
                    <div className="flex items-center justify-between bg-amber-50/60 dark:bg-amber-950/20 p-3 rounded-xl border border-amber-200/60 dark:border-amber-800/40 text-xs">
                      <div>
                        <span className="text-slate-500">คำค้นที่ใช้ตรวจสอบ:</span>
                        <p className="font-bold text-slate-900 dark:text-white mt-0.5">{result.query_value || '-'}</p>
                      </div>
                      <div className="text-right">
                        <span className="text-slate-500">คะแนนสูงสุด:</span>
                        <p className="text-base font-bold text-amber-600 dark:text-amber-400">
                          {result.match_score ? Number(result.match_score).toFixed(1) : '0.0'}%
                        </p>
                      </div>
                    </div>

                    {/* Candidate Records List */}
                    <div className="space-y-2.5 max-h-64 overflow-y-auto pr-1">
                      {candidates.length === 0 ? (
                        <div className="py-6 text-center text-xs text-slate-500 bg-slate-50 dark:bg-slate-800/40 rounded-xl">
                          ไม่พบรายการข้อมูลในฐานข้อมูลกรมบังคับคดี
                        </div>
                      ) : (
                        candidates.map((c, i) => (
                          <div
                            key={c.id || i}
                            className="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 text-xs space-y-1.5"
                          >
                            <div className="flex items-center justify-between">
                              <span className="font-bold text-slate-800 dark:text-slate-200 text-sm">
                                {c.court_name || c.normalized_name || `รายการที่ #${i + 1}`}
                              </span>
                              <span className="font-bold text-amber-600 dark:text-amber-400">
                                Match {c.match_score ? Number(c.match_score).toFixed(0) : 0}%
                              </span>
                            </div>
                            <p className="text-[11px] text-slate-600 dark:text-slate-400">
                              เหตุผล: {c.match_reason}
                            </p>
                            <div className="grid grid-cols-2 gap-2 pt-1 border-t border-slate-200/60 dark:border-slate-700/40 text-[11px] text-slate-500 dark:text-slate-400">
                              <div>คดีดำ: <strong className="text-slate-700 dark:text-slate-300">{c.black_case || '-'}</strong></div>
                              <div>คดีแดง: <strong className="text-slate-700 dark:text-slate-300">{c.red_case || '-'}</strong></div>
                              <div>สำนักงาน: <span className="text-slate-700 dark:text-slate-300">{c.dept_name || '-'}</span></div>
                              <div>ทุนทรัพย์: <span className="text-slate-700 dark:text-slate-300 font-semibold">{c.capital_amount || '-'}</span></div>
                            </div>
                          </div>
                        ))
                      )}
                    </div>
                  </div>
                </div>
              </div>

              {/* Review History if already reviewed */}
              {result.reviewed_at && (
                <div className="p-4 rounded-xl bg-blue-50/60 dark:bg-blue-950/20 border border-blue-200/60 dark:border-blue-900/40 text-xs space-y-1">
                  <div className="flex items-center justify-between font-semibold text-blue-900 dark:text-blue-300">
                    <span>เคยผ่านการตรวจสอบแล้วโดย: {result.reviewer_name || 'เจ้าหน้าที่'}</span>
                    <span>เมื่อ: {result.reviewed_at}</span>
                  </div>
                  {result.review_note && (
                    <p className="text-slate-700 dark:text-slate-300">
                      หมายเหตุเดิม: {result.review_note}
                    </p>
                  )}
                </div>
              )}

              {/* Decision Section */}
              <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-4">
                <h4 className="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                  <span>3. บันทึกผลการตัดสินใจของเจ้าหน้าที่</span>
                  <span className="text-xs text-rose-500 font-normal">* บังคับตรวจสอบและยืนยันโดยเจ้าหน้าที่</span>
                </h4>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <button
                    type="button"
                    onClick={() => setDecision('VERIFIED_MATCH')}
                    className={`p-3.5 rounded-2xl border text-left transition-all ${
                      decision === 'VERIFIED_MATCH'
                        ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/40 text-rose-900 dark:text-rose-100 shadow-sm ring-2 ring-rose-500/20'
                        : 'border-slate-200 dark:border-slate-800 hover:border-rose-300 text-slate-700 dark:text-slate-300'
                    }`}
                  >
                    <div className="flex items-center gap-2 font-bold text-sm text-rose-700 dark:text-rose-400">
                      <ShieldAlert className="w-4 h-4" />
                      VERIFIED_MATCH
                    </div>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                      ยืนยันว่าตรงกัน (เป็นบุคคลเดียวกัน)
                    </p>
                  </button>

                  <button
                    type="button"
                    onClick={() => setDecision('FALSE_MATCH')}
                    className={`p-3.5 rounded-2xl border text-left transition-all ${
                      decision === 'FALSE_MATCH'
                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-100 shadow-sm ring-2 ring-emerald-500/20'
                        : 'border-slate-200 dark:border-slate-800 hover:border-emerald-300 text-slate-700 dark:text-slate-300'
                    }`}
                  >
                    <div className="flex items-center gap-2 font-bold text-sm text-emerald-700 dark:text-emerald-400">
                      <CheckCircle2 className="w-4 h-4" />
                      FALSE_MATCH
                    </div>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                      ยืนยันว่าไม่ตรงกัน (คนละบุคคล)
                    </p>
                  </button>

                  <button
                    type="button"
                    onClick={() => setDecision('REVIEW_REQUIRED')}
                    className={`p-3.5 rounded-2xl border text-left transition-all ${
                      decision === 'REVIEW_REQUIRED'
                        ? 'border-orange-500 bg-orange-50 dark:bg-orange-950/40 text-orange-900 dark:text-orange-100 shadow-sm ring-2 ring-orange-500/20'
                        : 'border-slate-200 dark:border-slate-800 hover:border-orange-300 text-slate-700 dark:text-slate-300'
                    }`}
                  >
                    <div className="flex items-center gap-2 font-bold text-sm text-orange-700 dark:text-orange-400">
                      <HelpCircle className="w-4 h-4" />
                      REVIEW_REQUIRED
                    </div>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                      ยังคงต้องตรวจสอบเพิ่มเติม
                    </p>
                  </button>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                    หมายเหตุการตรวจสอบ (Audit Note) <span className="text-rose-500">*</span>
                  </label>
                  <textarea
                    rows={3}
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder="ระบุข้อเท็จจริง แหล่งข้อมูลอ้างอิง หรือเหตุผลในการตัดสิน เช่น 'ตรวจสอบเทียบเคียงกับหมายศาลฉบับจริงแล้ว ยืนยันเป็นบุคคลละท่าน'..."
                    className="w-full text-xs lg:text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#073B74]"
                  />
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
          <button
            onClick={onClose}
            className="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs lg:text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            ปิดหน้าต่าง
          </button>

          <button
            onClick={handleDecisionSubmit}
            disabled={submitting || loading}
            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#073B74] hover:bg-[#0B5ED7] text-white text-xs lg:text-sm font-semibold shadow-sm transition-all disabled:opacity-50"
          >
            {submitting ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                กำลังบันทึก...
              </>
            ) : (
              <>
                <Save className="w-4 h-4" />
                บันทึกผลการตรวจสอบ
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}
