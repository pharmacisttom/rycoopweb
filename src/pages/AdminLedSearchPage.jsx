import { maskIdCard, maskName } from '../utils/pdpa';
import React, { useState } from 'react';
import {
  Search, User, CheckCircle2, AlertTriangle, ShieldCheck,
  Loader2, ArrowRight, RefreshCw, Eye
} from 'lucide-react';
import LedSubNav from '../components/led/LedSubNav';
import LedWarningNotice from '../components/led/LedWarningNotice';
import LedResultCard from '../components/led/LedResultCard';
import LedStatusBadge from '../components/led/LedStatusBadge';
import LedReviewModal from '../components/led/LedReviewModal';
import { searchLedMembers, checkLedMember } from '../services/ledApi';
import { useToast } from '../context/ToastContext';

export default function AdminLedSearchPage() {
  const { toast } = useToast();
  const [query, setQuery] = useState('');
  const [searching, setSearching] = useState(false);
  const [members, setMembers] = useState([]);
  const [selectedMember, setSelectedMember] = useState(null);
  const [checking, setChecking] = useState(false);
  const [checkResult, setCheckResult] = useState(null);
  const [reviewModalOpen, setReviewModalOpen] = useState(false);
  const [selectedReviewId, setSelectedReviewId] = useState(null);

  const handleSearch = async (e) => {
    if (e) e.preventDefault();
    const q = query.trim();
    if (q.length < 2) {
      toast.warning('กรุณากรอกคำค้นหาอย่างน้อย 2 ตัวอักษร (เลขสมาชิก, ชื่อ หรือนามสกุล)');
      return;
    }

    setSearching(true);
    setSelectedMember(null);
    setCheckResult(null);

    try {
      const res = await searchLedMembers(q);
      if (res.success && res.data) {
        setMembers(res.data);
        if (res.data.length === 0) {
          toast.info('ไม่พบข้อมูลสมาชิกที่ตรงกับคำค้นหา');
        }
      } else {
        toast.error(res.message || 'เกิดข้อผิดพลาดในการค้นหา');
      }
    } catch (err) {
      toast.error('ไม่สามารถติดต่อเซิร์ฟเวอร์ได้');
    } finally {
      setSearching(false);
    }
  };

  const handleSelectMember = (member) => {
    setSelectedMember(member);
    setCheckResult(null);
  };

  const handleRunCheck = async () => {
    if (!selectedMember) return;
    setChecking(true);
    setCheckResult(null);

    try {
      const res = await checkLedMember(selectedMember.id);
      if (res.success && res.data) {
        setCheckResult(res.data);
        toast.success('ตรวจสอบข้อมูลกับกรมบังคับคดีเรียบร้อยแล้ว');
      } else {
        toast.error(res.message || 'ไม่สามารถตรวจสอบข้อมูลได้');
      }
    } catch (err) {
      toast.error('เกิดข้อผิดพลาดในการเรียกตรวจ LED');
    } finally {
      setChecking(false);
    }
  };

  const handleOpenReview = (resultId) => {
    setSelectedReviewId(resultId);
    setReviewModalOpen(true);
  };

  return (
    <div className="admin-led-page animate-fade-in space-y-8">
      {/* Sub Navigation */}
      <LedSubNav />

      {/* Warning Notice */}
      <LedWarningNotice />

      {/* Search Header Form */}
      <div className="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 lg:p-8 shadow-sm space-y-4">
        <div>
          <h2 className="text-lg lg:text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Search className="w-5 h-5 text-[#073B74] dark:text-blue-400" />
            ค้นหาสมาชิกสหกรณ์เพื่อตรวจสอบข้อมูลกรมบังคับคดี
          </h2>
          <p className="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mt-1">
            ค้นหาด้วยเลขทะเบียนสมาชิก, ชื่อ, นามสกุล หรือชื่อ-นามสกุล เพื่อส่งคำค้นไปยังฐานข้อมูลเปิดกรมบังคับคดี
          </p>
        </div>

        <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
            <input
              type="text"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="ระบุเลขทะเบียนสมาชิก เช่น 00123 หรือ ชื่อ เช่น สมชาย, นามสกุล เช่น ใจดี..."
              className="w-full pl-12 pr-4 py-3.5 rounded-2xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#073B74]"
            />
          </div>
          <button
            type="submit"
            disabled={searching}
            className="px-6 py-3.5 rounded-2xl bg-[#073B74] hover:bg-[#0B5ED7] text-white text-sm font-semibold shadow-sm transition-all flex items-center justify-center gap-2 disabled:opacity-50 shrink-0"
          >
            {searching ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                กำลังค้นหา...
              </>
            ) : (
              <>
                <Search className="w-4 h-4" />
                ค้นหาสมาชิก
              </>
            )}
          </button>
        </form>
      </div>

      {/* Search Results & Action View */}
      {members.length > 0 && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Members List */}
          <div className="lg:col-span-1 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-3">
            <h3 className="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              ผลการค้นหา ({members.length} รายการ)
            </h3>

            <div className="space-y-2 max-h-[500px] overflow-y-auto pr-1">
              {members.map((m) => {
                const isSelected = selectedMember?.id === m.id;
                return (
                  <div
                    key={m.id}
                    onClick={() => handleSelectMember(m)}
                    className={`p-3.5 rounded-2xl border transition-all cursor-pointer ${
                      isSelected
                        ? 'border-[#0B5ED7] bg-blue-50/60 dark:bg-blue-950/40 shadow-sm ring-1 ring-[#0B5ED7]'
                        : 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/60'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <p className="font-bold text-xs lg:text-sm text-slate-900 dark:text-white">
                          {m.full_name}
                        </p>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                          เลขสมาชิก: <strong>{m.member_no}</strong> • {m.department}
                        </p>
                      </div>
                      {m.last_status && (
                        <LedStatusBadge status={m.last_status} size="sm" />
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Member Inspection & Execution Pane */}
          <div className="lg:col-span-2 space-y-6">
            {selectedMember ? (
              <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-4">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-950/60 text-[#073B74] dark:text-blue-300 flex items-center justify-center font-bold">
                      <User className="w-6 h-6" />
                    </div>
                    <div>
                      <h3 className="text-base lg:text-lg font-bold text-slate-900 dark:text-white">
                        {selectedMember.full_name}
                      </h3>
                      <p className="text-xs text-slate-500 dark:text-slate-400">
                        เลขทะเบียน: {selectedMember.member_no} • สังกัด: {selectedMember.department} • บัตร ปชช. (Masked): {selectedMember.id_card_pdpa || maskIdCard(selectedMember.id_card_masked || selectedMember.id_card)}
                      </p>
                    </div>
                  </div>

                  <button
                    onClick={handleRunCheck}
                    disabled={checking}
                    className="px-5 py-2.5 rounded-xl bg-[#073B74] hover:bg-[#0B5ED7] text-white text-xs lg:text-sm font-semibold shadow-sm transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                  >
                    {checking ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin" />
                        กำลังตรวจสอบกรมบังคับคดี...
                      </>
                    ) : (
                      <>
                        <ShieldCheck className="w-4 h-4" />
                        ตรวจสอบกรมบังคับคดี
                      </>
                    )}
                  </button>
                </div>

                {/* Last Check Summary if no new result */}
                {!checkResult && !checking && (
                  <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/60 text-xs space-y-2">
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-slate-700 dark:text-slate-300">
                        ประวัติการตรวจครั้งล่าสุดของสมาชิกรายนี้:
                      </span>
                      {selectedMember.last_status ? (
                        <LedStatusBadge status={selectedMember.last_status} size="sm" showSublabel />
                      ) : (
                        <span className="text-slate-400">ยังไม่เคยมีบันทึกการตรวจสอบ</span>
                      )}
                    </div>
                    {selectedMember.last_checked_at && (
                      <p className="text-slate-500 dark:text-slate-400">
                        ตรวจสอบเมื่อ: {selectedMember.last_checked_at}
                      </p>
                    )}
                    <p className="text-slate-500 italic">
                      กดปุ่ม "ตรวจสอบกรมบังคับคดี" ด้านบนเพื่อส่งคำค้นชื่อ-นามสกุลไปยัง CKAN API แบบสด
                    </p>
                  </div>
                )}

                {/* Check Result Display */}
                {checkResult && (
                  <div className="space-y-4 animate-fade-in">
                    <h4 className="text-sm font-bold text-slate-900 dark:text-white">
                      ผลการตรวจสอบล่าสุด (Live API Response)
                    </h4>
                    <LedResultCard
                      result={checkResult}
                      onOpenReview={handleOpenReview}
                    />
                  </div>
                )}
              </div>
            ) : (
              <div className="py-24 text-center rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 p-8">
                <User className="w-12 h-12 mx-auto text-slate-400 mb-3" />
                <h4 className="font-bold text-slate-700 dark:text-slate-300 text-sm">
                  เลือกสมาชิกจากรายการฝั่งซ้าย
                </h4>
                <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                  คลิกที่ชื่อสมาชิกในผลการค้นหาเพื่อดูข้อมูลและกดตรวจสอบกับฐานข้อมูลกรมบังคับคดี
                </p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Review Modal */}
      <LedReviewModal
        resultId={selectedReviewId}
        isOpen={reviewModalOpen}
        onClose={() => {
          setReviewModalOpen(false);
          setSelectedReviewId(null);
        }}
        onSuccess={() => {
          if (selectedMember) {
            handleSearch();
          }
        }}
      />
    </div>
  );
}
