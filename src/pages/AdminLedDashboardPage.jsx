import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Users, CheckCircle2, AlertTriangle, ShieldAlert,
  HelpCircle, Activity, Clock, RefreshCw, Layers,
  Search, ShieldCheck, ArrowRight, Eye
} from 'lucide-react';
import LedSubNav from '../components/led/LedSubNav';
import LedKpiCard from '../components/led/LedKpiCard';
import LedStatusBadge from '../components/led/LedStatusBadge';
import LedWarningNotice from '../components/led/LedWarningNotice';
import LedRunHistoryTable from '../components/led/LedRunHistoryTable';
import LedReviewModal from '../components/led/LedReviewModal';
import { fetchLedDashboard } from '../services/ledApi';
import { useToast } from '../context/ToastContext';

export default function AdminLedDashboardPage() {
  const { toast } = useToast();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState(null);
  const [selectedReviewId, setSelectedReviewId] = useState(null);
  const [reviewModalOpen, setReviewModalOpen] = useState(false);

  useEffect(() => {
    loadDashboard();
  }, []);

  const loadDashboard = async () => {
    setLoading(true);
    try {
      const res = await fetchLedDashboard();
      if (res.success && res.data) {
        setData(res.data);
      } else {
        toast.error(res.message || 'ไม่สามารถโหลดข้อมูลแดชบอร์ดได้');
      }
    } catch (e) {
      toast.error('เกิดข้อผิดพลาดในการติดต่อเซิร์ฟเวอร์');
    } finally {
      setLoading(false);
    }
  };

  const kpis = data?.kpis || {};
  const apiHealth = data?.api_health || {};
  const recentRuns = data?.recent_runs || [];
  const recentReviews = data?.recent_reviews || [];

  const handleOpenReview = (id) => {
    setSelectedReviewId(id);
    setReviewModalOpen(true);
  };

  return (
    <div className="admin-led-page animate-fade-in space-y-8">
      {/* Sub Nav Bar */}
      <LedSubNav pendingReviewCount={kpis.pending_reviews_total || 0} />

      {/* Warning Notice */}
      <LedWarningNotice />

      {/* Header Actions & API Status Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        <div className="flex items-center gap-3">
          <div className={`p-2.5 rounded-xl ${
            apiHealth.status === 'UP' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-red-50 text-red-600'
          }`}>
            <Activity className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                สถานะการเชื่อมต่อ CKAN Open Data กรมบังคับคดี
              </span>
              <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${
                apiHealth.status === 'UP' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-red-100 text-red-700'
              }`}>
                {apiHealth.status === 'UP' ? 'เชื่อมต่อปกติ (ONLINE)' : 'ไม่สามารถเชื่อมต่อได้'}
              </span>
            </div>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              เวลาตอบสนอง API เฉลี่ย: <strong>{apiHealth.response_time_ms ? `${apiHealth.response_time_ms} ms` : '-'}</strong> |
              ความแม่นยำการเชื่อมต่อ: <strong>{kpis.api_success_rate || 100}%</strong>
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => navigate('/admin/led/search')}
            className="px-4 py-2.5 rounded-xl bg-[#073B74] hover:bg-[#0B5ED7] text-white text-xs font-semibold transition-colors flex items-center gap-2 shadow-sm"
          >
            <Search className="w-4 h-4" />
            ตรวจสมาชิกรายคน
          </button>
          <button
            onClick={() => navigate('/admin/led/batch')}
            className="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-semibold transition-colors flex items-center gap-2"
          >
            <Layers className="w-4 h-4" />
            เริ่มตรวจแบบกลุ่ม (Batch)
          </button>
          <button
            onClick={loadDashboard}
            disabled={loading}
            className="p-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            title="รีเฟรชข้อมูล"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <LedKpiCard
          title="สมาชิกทั้งหมดในระบบ"
          value={kpis.total_members || 0}
          subvalue="สถานะปกติ (Active)"
          icon={Users}
          variant="navy"
        />
        <LedKpiCard
          title="ตรวจสอบแล้ววันนี้"
          value={kpis.checked_today || 0}
          subvalue={`สะสมทั้งหมด ${Number(kpis.total_checked_all_time || 0).toLocaleString()} ครั้ง`}
          icon={Clock}
          variant="blue"
        />
        <LedKpiCard
          title="ไม่พบข้อมูล (ปลอดภัย)"
          value={kpis.not_found_today || 0}
          subvalue="ไม่พบในฐานข้อมูล LED"
          icon={CheckCircle2}
          variant="emerald"
        />
        <LedKpiCard
          title="คิวรอเจ้าหน้าที่ตรวจสอบ"
          value={kpis.pending_reviews_total || 0}
          subvalue="Possible Match / Review"
          icon={AlertTriangle}
          variant="amber"
          badgeText={kpis.pending_reviews_total > 0 ? 'รอตรวจ' : 'เรียบร้อย'}
          onClick={() => navigate('/admin/led/review')}
        />
      </div>

      {/* Status Breakdown Bar */}
      <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
        <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
          สรุปการคัดกรองสมาชิกวันนี้ (Daily Breakdown)
        </h3>

        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-center">
          <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
            <span className="text-xs text-slate-500 dark:text-slate-400">ไม่พบข้อมูล</span>
            <p className="text-xl font-bold text-slate-700 dark:text-slate-300 mt-1">
              {kpis.not_found_today || 0}
            </p>
          </div>
          <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
            <span className="text-xs text-amber-700 dark:text-amber-300 font-medium">อาจตรงกัน (Possible)</span>
            <p className="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">
              {kpis.possible_match_today || 0}
            </p>
          </div>
          <div className="p-3 rounded-xl bg-orange-50 dark:bg-orange-950/40 border border-orange-200 dark:border-orange-800">
            <span className="text-xs text-orange-700 dark:text-orange-300 font-medium">ต้องตรวจสอบ (Review)</span>
            <p className="text-xl font-bold text-orange-600 dark:text-orange-400 mt-1">
              {kpis.review_required_today || 0}
            </p>
          </div>
          <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800">
            <span className="text-xs text-rose-700 dark:text-rose-300 font-medium">ยืนยันแล้ว (Verified)</span>
            <p className="text-xl font-bold text-rose-600 dark:text-rose-400 mt-1">
              {kpis.verified_match_today || 0}
            </p>
          </div>
          <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
            <span className="text-xs text-emerald-700 dark:text-emerald-300 font-medium">ไม่ตรงกัน (False Match)</span>
            <p className="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
              {kpis.false_match_today || 0}
            </p>
          </div>
          <div className="p-3 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800">
            <span className="text-xs text-red-700 dark:text-red-300 font-medium">API Error</span>
            <p className="text-xl font-bold text-red-600 dark:text-red-400 mt-1">
              {kpis.api_error_today || 0}
            </p>
          </div>
        </div>
      </div>

      {/* Review Queue Preview & Recent Runs */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left: Pending Review Queue */}
        <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div className="flex items-center gap-2">
              <AlertTriangle className="w-4 h-4 text-amber-500" />
              <h3 className="font-bold text-sm text-slate-900 dark:text-white">
                รายการรอเจ้าหน้าที่ตรวจสอบ (Review Queue ล่าสุด)
              </h3>
            </div>
            <button
              onClick={() => navigate('/admin/led/review')}
              className="text-xs font-semibold text-[#0B5ED7] hover:text-[#073B74] flex items-center gap-1 transition-colors"
            >
              ดูทั้งหมด ({kpis.pending_reviews_total || 0})
              <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>

          <div className="space-y-2.5">
            {recentReviews.length === 0 ? (
              <div className="py-10 text-center text-xs text-slate-500 bg-slate-50 dark:bg-slate-800/40 rounded-xl">
                ไม่มีรายการที่ต้องตรวจสอบในขณะนี้
              </div>
            ) : (
              recentReviews.map((item) => (
                <div
                  key={item.id}
                  className="flex items-center justify-between p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors"
                >
                  <div className="space-y-0.5">
                    <p className="font-semibold text-xs text-slate-900 dark:text-white">
                      {item.prefix} {item.first_name} {item.last_name}
                    </p>
                    <p className="text-[11px] text-slate-500 dark:text-slate-400">
                      เลขสมาชิก: {item.member_no} • {item.department || '-'}
                    </p>
                  </div>

                  <div className="flex items-center gap-3">
                    <div className="text-right">
                      <span className="text-xs font-bold text-amber-600 dark:text-amber-400">
                        {item.match_score ? Number(item.match_score).toFixed(0) : 0}%
                      </span>
                      <LedStatusBadge status={item.status} size="sm" />
                    </div>
                    <button
                      onClick={() => handleOpenReview(item.id)}
                      className="p-1.5 rounded-lg bg-blue-50 text-[#073B74] hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300 transition-colors"
                      title="ตรวจสอบและรีวิวผล"
                    >
                      <Eye className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Right: Recent Runs Summary */}
        <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div className="flex items-center gap-2">
              <Layers className="w-4 h-4 text-[#073B74] dark:text-blue-400" />
              <h3 className="font-bold text-sm text-slate-900 dark:text-white">
                รอบการตรวจสอบล่าสุด (Recent Runs)
              </h3>
            </div>
            <button
              onClick={() => navigate('/admin/led/batch')}
              className="text-xs font-semibold text-[#0B5ED7] hover:text-[#073B74] flex items-center gap-1 transition-colors"
            >
              จัดการ Batch
              <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>

          <LedRunHistoryTable
            runs={recentRuns}
            onViewRun={(runId) => navigate(`/admin/led/batch?runId=${runId}`)}
          />
        </div>
      </div>

      {/* Review Modal */}
      <LedReviewModal
        resultId={selectedReviewId}
        isOpen={reviewModalOpen}
        onClose={() => {
          setReviewModalOpen(false);
          setSelectedReviewId(null);
        }}
        onSuccess={loadDashboard}
      />
    </div>
  );
}
