import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
  Layers, Play, Clock, CheckCircle2, AlertTriangle,
  Loader2, RefreshCw, Filter, ShieldCheck, Database,
  ArrowRight, Users
} from 'lucide-react';
import LedSubNav from '../components/led/LedSubNav';
import LedStatusBadge from '../components/led/LedStatusBadge';
import LedWarningNotice from '../components/led/LedWarningNotice';
import LedRunHistoryTable from '../components/led/LedRunHistoryTable';
import { startLedBatchRun, fetchLedRuns, fetchLedRunDetail } from '../services/ledApi';
import { useToast } from '../context/ToastContext';

export default function AdminLedBatchPage() {
  const { toast, confirmDialog } = useToast();
  const [searchParams] = useSearchParams();
  const [loading, setLoading] = useState(true);
  const [starting, setStarting] = useState(false);
  const [runs, setRuns] = useState([]);
  const [activeRunDetail, setActiveRunDetail] = useState(null);

  // Filter Form State
  const [department, setDepartment] = useState('');
  const [startNo, setStartNo] = useState('');
  const [endNo, setEndNo] = useState('');
  const [maxMembers, setMaxMembers] = useState(50);

  useEffect(() => {
    loadRuns();
    const runIdParam = searchParams.get('runId');
    if (runIdParam) {
      loadRunDetail(runIdParam);
    }
  }, [searchParams]);

  const loadRuns = async () => {
    setLoading(true);
    try {
      const res = await fetchLedRuns();
      if (res.success && res.data) {
        setRuns(res.data);
      } else {
        toast.error(res.message || 'ไม่สามารถโหลดประวัติการตรวจสอบได้');
      }
    } catch (e) {
      toast.error('เกิดข้อผิดพลาดในการโหลดข้อมูล');
    } finally {
      setLoading(false);
    }
  };

  const loadRunDetail = async (runId) => {
    try {
      const res = await fetchLedRunDetail(runId);
      if (res.success && res.data) {
        setActiveRunDetail(res.data);
      }
    } catch (e) {
      // Ignore
    }
  };

  const handleStartBatch = async (e) => {
    e.preventDefault();

    const confirmed = await confirmDialog({
      title: 'เริ่มการตรวจสอบสมาชิกแบบกลุ่ม (Batch Run)',
      message: 'ระบบจะเริ่มประมวลผลสมาชิกทีละรายแบบ Sequential โดยเว้นระยะห่างตามกำหนด (Rate Delay) เพื่อไม่ให้กระทบการทำงานของเซิร์ฟเวอร์กรมบังคับคดี ต้องการเริ่มดำเนินการหรือไม่?',
      confirmText: 'เริ่มตรวจสอบ',
      cancelText: 'ยกเลิก',
    });

    if (!confirmed) return;

    setStarting(true);
    try {
      const payload = {
        department: department.trim(),
        member_no_start: startNo.trim(),
        member_no_end: endNo.trim(),
        max_members: Number(maxMembers) || 50,
      };

      const res = await startLedBatchRun(payload);
      if (res.success) {
        toast.success(res.message || 'เริ่มรอบการตรวจสอบเรียบร้อยแล้ว');
        await loadRuns();
        if (res.data?.run_id) {
          await loadRunDetail(res.data.run_id);
        }
      } else {
        toast.error(res.message || 'ไม่สามารถเริ่มการตรวจสอบได้');
      }
    } catch (err) {
      toast.error('เกิดข้อผิดพลาดในการส่งคำขอ');
    } finally {
      setStarting(false);
    }
  };

  return (
    <div className="admin-led-page animate-fade-in space-y-8">
      {/* Sub Navigation */}
      <LedSubNav />

      {/* Warning Notice */}
      <LedWarningNotice />

      {/* Batch Setup Form Card */}
      <div className="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 lg:p-8 shadow-sm space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-2">
          <div>
            <h2 className="text-lg lg:text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <Layers className="w-5 h-5 text-[#073B74] dark:text-blue-400" />
              กำหนดเงื่อนไขและเริ่มตรวจสอบแบบกลุ่ม (Batch Processing)
            </h2>
            <p className="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
              ประมวลผลสมาชิก Active ทั้งหมด หรือกรองตามสังกัด/ช่วงเลขสมาชิกอย่างเป็นระบบและปลอดภัย
            </p>
          </div>

          <span className="text-xs px-3 py-1 rounded-full font-semibold bg-blue-50 dark:bg-blue-950/60 text-[#073B74] dark:text-blue-300 border border-blue-200 dark:border-blue-900 self-start sm:self-auto">
            Sequential Controlled Rate
          </span>
        </div>

        <form onSubmit={handleStartBatch} className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                สังกัด / หน่วยงาน (ไม่ระบุ = ทั้งหมด)
              </label>
              <input
                type="text"
                value={department}
                onChange={(e) => setDepartment(e.target.value)}
                placeholder="เช่น รพ.ระยอง, สสจ.ระยอง..."
                className="w-full text-xs lg:text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#073B74]"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                ช่วงเลขทะเบียนสมาชิกเริ่มต้น
              </label>
              <input
                type="text"
                value={startNo}
                onChange={(e) => setStartNo(e.target.value)}
                placeholder="เช่น 00001"
                className="w-full text-xs lg:text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#073B74]"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                ถึงเลขทะเบียนสมาชิกสิ้นสุด
              </label>
              <input
                type="text"
                value={endNo}
                onChange={(e) => setEndNo(e.target.value)}
                placeholder="เช่น 09999"
                className="w-full text-xs lg:text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#073B74]"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                จำนวนสมาชิกในรอบนี้ (Batch Size)
              </label>
              <select
                value={maxMembers}
                onChange={(e) => setMaxMembers(e.target.value)}
                className="w-full text-xs lg:text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#073B74]"
              >
                <option value={10}>10 สมาชิก (ทดสอบด่วน)</option>
                <option value={50}>50 สมาชิก (ค่าแนะนำ)</option>
                <option value={100}>100 สมาชิก</option>
              </select>
            </div>
          </div>

          <div className="flex items-center justify-between pt-2">
            <p className="text-xs text-slate-500 dark:text-slate-400">
              * ระบบจะหน่วงเวลาอัตโนมัติ 500ms ระหว่างแต่ละรายการ เพื่อปฏิบัติตามนโยบายการใช้งาน API ภายนอก
            </p>

            <button
              type="submit"
              disabled={starting}
              className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-[#073B74] hover:bg-[#0B5ED7] text-white text-xs lg:text-sm font-semibold shadow-sm transition-all disabled:opacity-50"
            >
              {starting ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  กำลังเริ่ม Batch Run...
                </>
              ) : (
                <>
                  <Play className="w-4 h-4" />
                  เริ่มการตรวจสอบแบบกลุ่ม
                </>
              )}
            </button>
          </div>
        </form>
      </div>

      {/* Active Run Detail Panel if selected */}
      {activeRunDetail && (
        <div className="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 lg:p-8 shadow-sm space-y-4 animate-fade-in">
          <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
              <h3 className="font-bold text-sm lg:text-base text-slate-900 dark:text-white">
                รายละเอียดรอบการตรวจสอบ #{activeRunDetail.run?.id} ({activeRunDetail.run?.run_type})
              </h3>
              <p className="text-xs text-slate-500">
                เริ่มเมื่อ: {activeRunDetail.run?.started_at || activeRunDetail.run?.created_at} |
                สถานะ: <LedStatusBadge status={activeRunDetail.run?.status} size="sm" />
              </p>
            </div>
            <button
              onClick={() => loadRunDetail(activeRunDetail.run?.id)}
              className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              title="รีเฟรชสถานะรอบนี้"
            >
              <RefreshCw className="w-4 h-4" />
            </button>
          </div>

          <div className="overflow-x-auto max-h-64 overflow-y-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 sticky top-0 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-2.5 px-3">เลขสมาชิก</th>
                  <th className="py-2.5 px-3">ชื่อ - นามสกุล</th>
                  <th className="py-2.5 px-3">คำค้น</th>
                  <th className="py-2.5 px-3">คะแนน</th>
                  <th className="py-2.5 px-3">สถานะ</th>
                  <th className="py-2.5 px-3">เวลาที่ตรวจ</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {(activeRunDetail.results || []).map((r) => (
                  <tr key={r.id}>
                    <td className="py-2.5 px-3 font-mono font-bold text-[#073B74] dark:text-blue-400">{r.member_no}</td>
                    <td className="py-2.5 px-3 font-medium">{r.prefix} {r.first_name} {r.last_name}</td>
                    <td className="py-2.5 px-3 text-slate-500">{r.query_value}</td>
                    <td className="py-2.5 px-3 font-bold text-amber-600">{r.match_score ? `${Number(r.match_score).toFixed(0)}%` : '-'}</td>
                    <td className="py-2.5 px-3"><LedStatusBadge status={r.status} size="sm" /></td>
                    <td className="py-2.5 px-3 text-slate-400">{r.checked_at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Runs History Table */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
            <Clock className="w-4 h-4 text-slate-500" />
            ประวัติการรันทั้งหมด (Run History)
          </h3>
          <button
            onClick={loadRuns}
            disabled={loading}
            className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>

        <LedRunHistoryTable
          runs={runs}
          onViewRun={(runId) => loadRunDetail(runId)}
        />
      </div>
    </div>
  );
}
