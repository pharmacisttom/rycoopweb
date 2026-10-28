import React, { useState, useEffect } from 'react';
import {
  CheckSquare, Filter, RefreshCw, Eye, AlertTriangle,
  User, CheckCircle2, ShieldAlert, Loader2, Search
} from 'lucide-react';
import LedSubNav from '../components/led/LedSubNav';
import LedStatusBadge from '../components/led/LedStatusBadge';
import LedWarningNotice from '../components/led/LedWarningNotice';
import LedReviewModal from '../components/led/LedReviewModal';
import { fetchLedReviewQueue } from '../services/ledApi';
import { useToast } from '../context/ToastContext';

export default function AdminLedReviewPage() {
  const { toast } = useToast();
  const [loading, setLoading] = useState(true);
  const [reviews, setReviews] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, total_pages: 1 });
  const [statusFilter, setStatusFilter] = useState('PENDING');
  const [searchFilter, setSearchFilter] = useState('');
  const [selectedReviewId, setSelectedReviewId] = useState(null);
  const [reviewModalOpen, setReviewModalOpen] = useState(false);

  useEffect(() => {
    loadQueue(1, statusFilter);
  }, [statusFilter]);

  const loadQueue = async (page = 1, status = statusFilter) => {
    setLoading(true);
    try {
      const res = await fetchLedReviewQueue({ page, limit: 20, status });
      if (res.success && res.data) {
        setReviews(res.data);
        if (res.pagination) {
          setPagination(res.pagination);
        }
      } else {
        toast.error(res.message || 'ไม่สามารถโหลดรายการ Review Queue ได้');
      }
    } catch (e) {
      toast.error('เกิดข้อผิดพลาดในการโหลดคิวตรวจสอบ');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenReview = (id) => {
    setSelectedReviewId(id);
    setReviewModalOpen(true);
  };

  const filteredReviews = reviews.filter((r) => {
    if (!searchFilter.trim()) return true;
    const term = searchFilter.toLowerCase();
    return (
      r.member_name?.toLowerCase().includes(term) ||
      r.member_no?.toLowerCase().includes(term) ||
      r.department?.toLowerCase().includes(term)
    );
  });

  return (
    <div className="admin-led-page animate-fade-in space-y-8">
      {/* Sub Navigation */}
      <LedSubNav pendingReviewCount={statusFilter === 'PENDING' ? pagination.total : 0} />

      {/* Warning Notice */}
      <LedWarningNotice />

      {/* Main Review Card Container */}
      <div className="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 lg:p-8 shadow-sm space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h2 className="text-lg lg:text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <CheckSquare className="w-5 h-5 text-[#073B74] dark:text-blue-400" />
              คิวตรวจสอบและยืนยันผลโดยเจ้าหน้าที่ (Human Review Queue)
            </h2>
            <p className="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
              รายการที่ระบบคัดกรองเบื้องต้นพบความใกล้เคียง และต้องผ่านการตรวจสอบข้อเท็จจริงโดยเจ้าหน้าที่ผู้มีอำนาจ
            </p>
          </div>

          <button
            onClick={() => loadQueue(pagination.page, statusFilter)}
            disabled={loading}
            className="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2 self-start sm:self-auto transition-colors"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
            รีเฟรชคิว
          </button>
        </div>

        {/* Filter Controls */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
          <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            {[
              { id: 'PENDING', label: 'รอเจ้าหน้าที่ตรวจสอบ (Pending)' },
              { id: 'POSSIBLE_MATCH', label: 'Possible Match' },
              { id: 'REVIEW_REQUIRED', label: 'Review Required' },
              { id: 'VERIFIED_MATCH', label: 'ยืนยันแล้ว (Verified)' },
              { id: 'FALSE_MATCH', label: 'ไม่ตรงกัน (False)' },
              { id: 'ALL', label: 'ทั้งหมด' },
            ].map((f) => (
              <button
                key={f.id}
                onClick={() => setStatusFilter(f.id)}
                className={`px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all ${
                  statusFilter === f.id
                    ? 'bg-[#073B74] text-white shadow-sm'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                }`}
              >
                {f.label}
              </button>
            ))}
          </div>

          <div className="relative w-full md:w-64 shrink-0">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              value={searchFilter}
              onChange={(e) => setSearchFilter(e.target.value)}
              placeholder="กรองด้วยชื่อ หรือเลขสมาชิก..."
              className="w-full pl-10 pr-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#073B74]"
            />
          </div>
        </div>

        {/* Table View */}
        {loading ? (
          <div className="py-24 text-center space-y-3">
            <Loader2 className="w-10 h-10 animate-spin text-[#0B5ED7] mx-auto" />
            <p className="text-sm text-slate-500">กำลังโหลดรายการตรวจสอบ...</p>
          </div>
        ) : filteredReviews.length === 0 ? (
          <div className="py-20 text-center rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20 p-8 space-y-2">
            <CheckCircle2 className="w-12 h-12 text-emerald-500 mx-auto" />
            <h4 className="font-bold text-slate-800 dark:text-slate-200 text-base">
              ไม่มีรายการที่รอการตรวจสอบ
            </h4>
            <p className="text-xs text-slate-500 max-w-sm mx-auto">
              ทุกรายการได้รับการตรวจสอบยืนยันผลครบถ้วนแล้ว หรือไม่มีข้อมูลที่ตรงตามตัวกรองที่เลือก
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
            <table className="w-full text-left text-xs lg:text-sm">
              <thead className="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold">
                <tr>
                  <th className="py-3.5 px-4">เลขสมาชิก</th>
                  <th className="py-3.5 px-4">ชื่อ - นามสกุล</th>
                  <th className="py-3.5 px-4">สังกัด</th>
                  <th className="py-3.5 px-4">จำนวนที่พบ</th>
                  <th className="py-3.5 px-4">คะแนนสูงสุด</th>
                  <th className="py-3.5 px-4">วันที่ตรวจสอบ</th>
                  <th className="py-3.5 px-4">สถานะ</th>
                  <th className="py-3.5 px-4 text-right">การตัดสิน</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {filteredReviews.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                    <td className="py-3.5 px-4 font-mono font-bold text-[#073B74] dark:text-blue-400">
                      {item.member_no}
                    </td>
                    <td className="py-3.5 px-4 font-medium text-slate-900 dark:text-white">
                      {item.member_name}
                    </td>
                    <td className="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400">
                      {item.department || '-'}
                    </td>
                    <td className="py-3.5 px-4">
                      <span className="font-semibold text-slate-700 dark:text-slate-300">
                        {item.api_total}
                      </span>
                    </td>
                    <td className="py-3.5 px-4">
                      <span className="font-bold text-amber-600 dark:text-amber-400">
                        {item.match_score ? Number(item.match_score).toFixed(0) : 0}%
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                      {item.checked_at}
                    </td>
                    <td className="py-3.5 px-4 whitespace-nowrap">
                      <LedStatusBadge status={item.status} size="sm" />
                    </td>
                    <td className="py-3.5 px-4 text-right">
                      <button
                        onClick={() => handleOpenReview(item.id)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#073B74] hover:bg-[#0B5ED7] text-white text-xs font-semibold shadow-sm transition-all"
                      >
                        <Eye className="w-3.5 h-3.5" />
                        ดูรายละเอียด & รีวิว
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Footer */}
        {pagination.total_pages > 1 && (
          <div className="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500">
            <span>
              แสดงหน้า {pagination.page} จาก {pagination.total_pages} (รวม {pagination.total} รายการ)
            </span>
            <div className="flex items-center gap-2">
              <button
                disabled={pagination.page <= 1}
                onClick={() => loadQueue(pagination.page - 1, statusFilter)}
                className="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 disabled:opacity-40"
              >
                ก่อนหน้า
              </button>
              <button
                disabled={pagination.page >= pagination.total_pages}
                onClick={() => loadQueue(pagination.page + 1, statusFilter)}
                className="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 disabled:opacity-40"
              >
                ถัดไป
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Review Decision Modal */}
      <LedReviewModal
        resultId={selectedReviewId}
        isOpen={reviewModalOpen}
        onClose={() => {
          setReviewModalOpen(false);
          setSelectedReviewId(null);
        }}
        onSuccess={() => loadQueue(pagination.page, statusFilter)}
      />
    </div>
  );
}
