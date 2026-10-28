import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Activity,
  AlertCircle,
  ArrowUpRight,
  BarChart3,
  Calendar,
  Clock,
  Eye,
  FileText,
  Globe,
  Info,
  Laptop,
  Layers,
  Monitor,
  RefreshCw,
  ShieldCheck,
  Smartphone,
  Tablet,
  TrendingUp,
  Users,
} from 'lucide-react';
import { fetchVisitorAnalytics } from '../services/api';
import VisitorCounter, { formatThaiNumber } from '../components/analytics/VisitorCounter';

const DAY_RANGES = [
  { value: 7, label: '7 วันล่าสุด' },
  { value: 14, label: '14 วันล่าสุด' },
  { value: 30, label: '30 วันล่าสุด' },
  { value: 60, label: '60 วันล่าสุด' },
  { value: 90, label: '90 วันล่าสุด' },
];

export default function VisitorStatsPage() {
  const [days, setDays] = useState(30);
  const [analytics, setAnalytics] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [lastVerifiedAt, setLastVerifiedAt] = useState(null);

  const loadAnalytics = useCallback(async (selectedDays = days, isSilent = false) => {
    if (!isSilent) {
      setLoading(true);
    }
    try {
      const res = await fetchVisitorAnalytics(selectedDays);
      if (res && res.success && res.available !== false && res.data && res.data.available !== false) {
        setAnalytics(res.data);
        setError(false);
        setLastVerifiedAt(res.data.generated_at_bangkok || new Date().toLocaleString('th-TH'));
      } else {
        setError(true);
      }
    } catch (err) {
      setError(true);
    } finally {
      setLoading(false);
    }
  }, [days]);

  useEffect(() => {
    loadAnalytics(days, false);
    const interval = setInterval(() => {
      if (typeof document !== 'undefined' && document.hidden) return;
      loadAnalytics(days, true);
    }, 60000);
    return () => clearInterval(interval);
  }, [days, loadAnalytics]);

  const summary = analytics?.summary || null;
  const timeline = Array.isArray(analytics?.timeline) ? analytics.timeline : [];
  const monthlyTrend = Array.isArray(analytics?.monthly_trend) ? analytics.monthly_trend : [];
  const hourly = Array.isArray(analytics?.hourly_distribution) ? analytics.hourly_distribution : [];
  const devices = Array.isArray(analytics?.devices) ? analytics.devices : [];
  const topPages = Array.isArray(analytics?.top_pages) ? analytics.top_pages : [];
  const definitions = analytics?.definitions || null;

  const maxDailyViews = Math.max(
    1,
    ...timeline.map((item) => Math.max(Number(item.page_views || 0), Number(item.visitors || 0)))
  );
  const maxHourlyViews = Math.max(
    1,
    ...hourly.map((item) => Number(item.page_views || 0))
  );
  const maxMonthlyViews = Math.max(
    1,
    ...monthlyTrend.map((item) => Math.max(Number(item.page_views || 0), Number(item.visitors || 0)))
  );

  return (
    <div className="min-h-screen bg-slate-50 pb-16">
      {/* Header Banner */}
      <section className="bg-gradient-to-br from-[#0b2545] via-[#13315c] to-[#0f4c5c] text-white py-12 border-b border-white/10">
        <div className="container mx-auto px-4 max-w-7xl">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
              <div className="inline-flex items-center gap-2 rounded-full bg-teal-400/15 border border-teal-300/30 px-3.5 py-1 text-xs font-bold text-teal-200 mb-3">
                <ShieldCheck className="h-4 w-4 text-teal-300" />
                <span>PDPA-Aware Server-Side Web Analytics • Asia/Bangkok (+07:00)</span>
              </div>
              <h1 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white">
                สถิติผู้เข้าชมและวิเคราะห์การใช้งานเว็บไซต์
              </h1>
              <p className="text-sm sm:text-base text-blue-100/85 mt-2 max-w-2xl">
                รายงานข้อมูลผู้เข้าชมจริงจากฐานข้อมูลเซิร์ฟเวอร์ (ไม่ใช้ตัวเลขจำลอง) คัดกรองบอทและปกป้องข้อมูลส่วนบุคคลตามมาตรฐาน PDPA
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2.5">
              <div className="inline-flex rounded-xl bg-slate-900/50 p-1 border border-white/10">
                {DAY_RANGES.map((range) => (
                  <button
                    key={range.value}
                    type="button"
                    onClick={() => setDays(range.value)}
                    className={`rounded-lg px-3 py-1.5 text-xs font-bold transition-colors ${
                      days === range.value
                        ? 'bg-teal-400 text-slate-950 shadow-sm'
                        : 'text-slate-200 hover:text-white hover:bg-white/10'
                    }`}
                  >
                    {range.label}
                  </button>
                ))}
              </div>

              <button
                type="button"
                onClick={() => loadAnalytics(days, false)}
                disabled={loading}
                className="inline-flex items-center gap-1.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 px-3.5 py-2 text-xs font-bold text-white transition-colors"
              >
                <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                รีเฟรชข้อมูล
              </button>
            </div>
          </div>

          {lastVerifiedAt && (
            <div className="mt-4 flex flex-wrap items-center gap-4 text-xs text-blue-200/80">
              <span>อัปเดตข้อมูลล่าสุด: {lastVerifiedAt}</span>
              {error && analytics && (
                <span className="inline-flex items-center gap-1 rounded-md bg-amber-500/20 border border-amber-400/30 px-2 py-0.5 text-amber-200 font-semibold">
                  <AlertCircle className="h-3.5 w-3.5" />
                  การเชื่อมต่อล่าสุดขัดข้อง กำลังแสดงข้อมูลที่ตรวจสอบแล้วก่อนหน้า
                </span>
              )}
            </div>
          )}
        </div>
      </section>

      <div className="container mx-auto px-4 max-w-7xl -mt-6 space-y-6">
        {/* Primary 4 Visitor Counter Cards */}
        <VisitorCounter
          variant="full"
          externalStats={summary}
          externalLoading={loading && !analytics}
          externalError={error}
          onRetry={() => loadAnalytics(days, false)}
        />

        {/* Secondary Session & Page-View KPI Strip */}
        {summary && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="rounded-2xl bg-white border border-slate-200/80 p-4 shadow-sm">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-500">
                <span>จำนวนการเปิดหน้าเว็บวันนี้ (Page Views)</span>
                <Eye className="h-4 w-4 text-blue-700" />
              </div>
              <div className="text-2xl font-extrabold text-slate-900 mt-1.5 tabular-nums">
                {formatThaiNumber(summary.today_page_views)}
              </div>
              <div className="text-[11px] text-slate-500 mt-1">
                สัปดาห์นี้ {formatThaiNumber(summary.week_page_views)} ครั้ง
              </div>
            </div>

            <div className="rounded-2xl bg-white border border-slate-200/80 p-4 shadow-sm">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-500">
                <span>จำนวนเซสชันวันนี้ (Sessions)</span>
                <Layers className="h-4 w-4 text-teal-700" />
              </div>
              <div className="text-2xl font-extrabold text-slate-900 mt-1.5 tabular-nums">
                {formatThaiNumber(summary.today_sessions)}
              </div>
              <div className="text-[11px] text-slate-500 mt-1">
                ตัดเซสชันใหม่เมื่อไม่มีกิจกรรมเกิน 30 นาที
              </div>
            </div>

            <div className="rounded-2xl bg-white border border-slate-200/80 p-4 shadow-sm">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-500">
                <span>ค่าเฉลี่ยหน้าต่อเซสชัน ({days} วัน)</span>
                <TrendingUp className="h-4 w-4 text-indigo-700" />
              </div>
              <div className="text-2xl font-extrabold text-slate-900 mt-1.5 tabular-nums">
                {Number(summary.avg_pages_per_session || 0).toLocaleString('th-TH', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}
              </div>
              <div className="text-[11px] text-slate-500 mt-1">
                คำนวณจาก {formatThaiNumber(summary.period_sessions)} เซสชันในช่วงที่เลือก
              </div>
            </div>

            <div className="rounded-2xl bg-white border border-slate-200/80 p-4 shadow-sm">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-500">
                <span>ผู้เข้าชมช่วง 7 วันล่าสุด</span>
                <Calendar className="h-4 w-4 text-emerald-700" />
              </div>
              <div className="text-2xl font-extrabold text-slate-900 mt-1.5 tabular-nums">
                {formatThaiNumber(summary.this_week)}
              </div>
              <div className="text-[11px] text-slate-500 mt-1">
                เปิดหน้าเว็บรวมทั้งหมด {formatThaiNumber(summary.total_page_views)} ครั้ง
              </div>
            </div>
          </div>
        )}

        {/* Daily Traffic Trend Chart */}
        {analytics && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm">
              <div className="flex flex-wrap items-center justify-between gap-2 mb-5">
                <div>
                  <h2 className="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <BarChart3 className="h-5 w-5 text-[#0b2545]" />
                    แนวโน้มผู้เข้าชมและการเปิดหน้าเว็บรายวัน ({days} วันล่าสุด)
                  </h2>
                  <p className="text-xs text-slate-500 mt-0.5">
                    เปรียบเทียบจำนวนผู้เข้าชมไม่ซ้ำ (Unique Visitors) และจำนวนการเปิดหน้าเว็บ (Page Views) ตามวันปฏิทินไทย
                  </p>
                </div>
                <div className="flex items-center gap-4 text-xs font-semibold">
                  <span className="inline-flex items-center gap-1.5 text-slate-700">
                    <span className="h-2.5 w-2.5 rounded-sm bg-[#0b2545]" />
                    ผู้เข้าชม (Unique)
                  </span>
                  <span className="inline-flex items-center gap-1.5 text-slate-700">
                    <span className="h-2.5 w-2.5 rounded-sm bg-teal-500" />
                    เปิดหน้าเว็บ (Views)
                  </span>
                </div>
              </div>

              {timeline.length === 0 ? (
                <div className="py-12 text-center text-sm text-slate-500">
                  ยังไม่มีข้อมูลในช่วงเวลาที่เลือก
                </div>
              ) : (
                <div className="space-y-2.5">
                  <div className="h-56 flex items-end gap-1 sm:gap-1.5 pt-4 pb-2 border-b border-slate-100 overflow-x-auto">
                    {timeline.map((day) => {
                      const visitorPct = Math.max(
                        day.visitors > 0 ? 6 : 1,
                        Math.round((Number(day.visitors || 0) / maxDailyViews) * 100)
                      );
                      const viewsPct = Math.max(
                        day.page_views > 0 ? 6 : 1,
                        Math.round((Number(day.page_views || 0) / maxDailyViews) * 100)
                      );
                      return (
                        <div
                          key={day.full_date}
                          className="group relative flex-1 min-w-[14px] h-full flex flex-col justify-end items-center"
                          title={`${day.date} (${day.full_date}): ผู้เข้าชม ${formatThaiNumber(day.visitors)} | เปิดหน้าเว็บ ${formatThaiNumber(day.page_views)} ครั้ง`}
                        >
                          <div className="w-full flex items-end justify-center gap-0.5 h-full">
                            <div
                              style={{ height: `${visitorPct}%` }}
                              className="w-1/2 rounded-t bg-[#0b2545] transition-all group-hover:opacity-85"
                            />
                            <div
                              style={{ height: `${viewsPct}%` }}
                              className="w-1/2 rounded-t bg-teal-500 transition-all group-hover:opacity-85"
                            />
                          </div>
                        </div>
                      );
                    })}
                  </div>
                  <div className="flex justify-between text-[11px] text-slate-400 font-medium px-1">
                    <span>{timeline[0]?.date}</span>
                    <span>{timeline[Math.floor(timeline.length / 2)]?.date}</span>
                    <span>{timeline[timeline.length - 1]?.date}</span>
                  </div>
                </div>
              )}
            </div>

            {/* Device Category Breakdown */}
            <div className="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm flex flex-col justify-between">
              <div>
                <h2 className="text-base font-extrabold text-slate-900 flex items-center gap-2">
                  <Smartphone className="h-5 w-5 text-teal-700" />
                  สัดส่วนอุปกรณ์ที่เข้าใช้งาน
                </h2>
                <p className="text-xs text-slate-500 mt-0.5 mb-5">
                  จำแนกตามประเภทอุปกรณ์จากข้อมูลจริงในช่วง {days} วันล่าสุด
                </p>

                {devices.length === 0 ? (
                  <div className="py-10 text-center text-xs text-slate-500">
                    ยังไม่มีข้อมูลอุปกรณ์ในช่วงเวลานี้
                  </div>
                ) : (
                  <div className="space-y-4">
                    {devices.map((dev) => {
                      const Icon = dev.type === 'mobile' ? Smartphone : dev.type === 'tablet' ? Tablet : Monitor;
                      return (
                        <div key={dev.type} className="space-y-1.5">
                          <div className="flex items-center justify-between text-xs">
                            <span className="inline-flex items-center gap-2 font-bold text-slate-800">
                              <Icon className="h-4 w-4 text-[#0b2545]" />
                              {dev.label}
                            </span>
                            <span className="font-bold text-slate-900 tabular-nums">
                              {formatThaiNumber(dev.count)} ครั้ง ({Number(dev.percentage || 0).toFixed(1)}%)
                            </span>
                          </div>
                          <div className="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div
                              className="h-full rounded-full bg-gradient-to-r from-[#0b2545] to-teal-500"
                              style={{ width: `${Math.min(100, Math.max(0, Number(dev.percentage || 0)))}%` }}
                            />
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>

              {/* Monthly Summary Mini-Table */}
              <div className="mt-6 pt-5 border-t border-slate-100">
                <h3 className="text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-3">
                  สรุปรายเดือน (6 เดือนล่าสุด)
                </h3>
                <div className="space-y-2 max-h-44 overflow-y-auto pr-1">
                  {monthlyTrend.slice(-6).reverse().map((m) => (
                    <div
                      key={m.year_month}
                      className="flex items-center justify-between text-xs py-1.5 px-2.5 rounded-lg bg-slate-50"
                    >
                      <span className="font-semibold text-slate-700">{m.label}</span>
                      <div className="flex items-center gap-3 tabular-nums">
                        <span className="text-slate-600">
                          ผู้เข้าชม <strong className="text-slate-900">{formatThaiNumber(m.visitors)}</strong>
                        </span>
                        <span className="text-teal-700 font-semibold">
                          {formatThaiNumber(m.page_views)} หน้า
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Hourly Distribution & Top Public Pages */}
        {analytics && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Hourly Traffic */}
            <div className="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm">
              <h2 className="text-base font-extrabold text-slate-900 flex items-center gap-2">
                <Clock className="h-5 w-5 text-[#0b2545]" />
                การกระจายตัวตามช่วงเวลา (รายชั่วโมง 00:00–23:00 น.)
              </h2>
              <p className="text-xs text-slate-500 mt-0.5 mb-5">
                วิเคราะห์ช่วงเวลาที่มีผู้เข้าใช้งานสูงสุดตามเวลาประเทศไทย (Asia/Bangkok)
              </p>

              <div className="h-44 flex items-end gap-1 pt-4 pb-2 border-b border-slate-100">
                {hourly.map((slot) => {
                  const pct = Math.max(
                    slot.page_views > 0 ? 8 : 2,
                    Math.round((Number(slot.page_views || 0) / maxHourlyViews) * 100)
                  );
                  return (
                    <div
                      key={slot.hour}
                      className="flex-1 h-full flex flex-col justify-end items-center group"
                      title={`${slot.label} น. — เปิดหน้าเว็บ ${formatThaiNumber(slot.page_views)} ครั้ง | ผู้เข้าชม ${formatThaiNumber(slot.visitors)}`}
                    >
                      <div
                        style={{ height: `${pct}%` }}
                        className="w-full rounded-t bg-gradient-to-t from-[#0b2545] to-teal-500 group-hover:opacity-80 transition-all"
                      />
                    </div>
                  );
                })}
              </div>
              <div className="flex justify-between text-[11px] text-slate-400 font-medium mt-2">
                <span>00:00</span>
                <span>06:00</span>
                <span>12:00</span>
                <span>18:00</span>
                <span>23:00</span>
              </div>
            </div>

            {/* Popular Public Pages */}
            <div className="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm">
              <h2 className="text-base font-extrabold text-slate-900 flex items-center gap-2">
                <FileText className="h-5 w-5 text-teal-700" />
                หน้าเว็บไซต์สาธารณะยอดนิยม (Top Public Pages)
              </h2>
              <p className="text-xs text-slate-500 mt-0.5 mb-4">
                นับเฉพาะหน้าสาธารณะที่ได้รับอนุญาต (ไม่รวมหน้าผู้ดูแลระบบ หน้าสมาชิก และพารามิเตอร์ลับ)
              </p>

              {topPages.length === 0 ? (
                <div className="py-10 text-center text-xs text-slate-500">
                  ยังไม่มีข้อมูลหน้ายอดนิยมในช่วงเวลานี้
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left border-collapse text-xs">
                    <thead>
                      <tr className="border-b border-slate-200 text-slate-500">
                        <th className="py-2 pr-2 font-bold">หน้าเว็บไซต์</th>
                        <th className="py-2 px-2 text-right font-bold">ผู้เข้าชม (Unique)</th>
                        <th className="py-2 pl-2 text-right font-bold">เปิดหน้าเว็บ (Views)</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {topPages.slice(0, 8).map((page, idx) => (
                        <tr key={page.url} className="hover:bg-slate-50/80">
                          <td className="py-2.5 pr-2">
                            <div className="font-bold text-slate-800 flex items-center gap-1.5">
                              <span className="inline-flex h-5 w-5 items-center justify-center rounded-md bg-slate-100 text-[11px] font-extrabold text-slate-600">
                                {idx + 1}
                              </span>
                              <span>{page.label || page.title}</span>
                            </div>
                            <div className="text-[11px] text-slate-400 font-mono pl-6">
                              {page.url}
                            </div>
                          </td>
                          <td className="py-2.5 px-2 text-right font-semibold text-slate-700 tabular-nums">
                            {formatThaiNumber(page.unique_visitors)}
                          </td>
                          <td className="py-2.5 pl-2 text-right font-extrabold text-[#0b2545] tabular-nums">
                            {formatThaiNumber(page.views)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Explicit Metric Definitions & Limitations (Section 5 Compliance) */}
        <section className="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#0b2545] border border-blue-200">
              <Info className="h-5 w-5" />
            </div>
            <div className="space-y-3 text-xs text-slate-600 leading-relaxed">
              <div>
                <h2 className="text-sm font-extrabold text-slate-900">
                  คำนิยามตัวชี้วัดและข้อจำกัดทางเทคนิค (Metric Definitions & Limitations)
                </h2>
                <p className="text-slate-500 mt-0.5">
                  การวัดผลทุกตัวอ้างอิงเขตเวลาประเทศไทย (<code className="font-mono text-slate-800">Asia/Bangkok</code>) และประมวลผลจากฐานข้อมูลเซิร์ฟเวอร์โดยตรง
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <div className="rounded-xl bg-slate-50 p-3 border border-slate-100">
                  <div className="font-bold text-slate-800">1. การเปิดหน้าเว็บ (Page Views)</div>
                  <p className="mt-1">
                    {definitions?.page_views ||
                      'จำนวนครั้งของการเปิดหน้าเว็บสาธารณะที่ผ่านการตรวจสอบและตัดการกดซ้ำภายใน 15 วินาที'}
                  </p>
                </div>
                <div className="rounded-xl bg-slate-50 p-3 border border-slate-100">
                  <div className="font-bold text-slate-800">2. ผู้เข้าชมไม่ซ้ำ (Unique Visitors)</div>
                  <p className="mt-1">
                    {definitions?.unique_visitors ||
                      'จำนวนรหัสผู้เข้าชมแบบนามแฝง (Pseudonymous Hash) ที่ไม่ซ้ำกันในช่วงเวลาที่กำหนด'}
                  </p>
                </div>
                <div className="rounded-xl bg-slate-50 p-3 border border-slate-100">
                  <div className="font-bold text-slate-800">3. เซสชันการใช้งาน (Sessions)</div>
                  <p className="mt-1">
                    {definitions?.sessions ||
                      'จำนวนรอบการเข้าใช้งานที่คั่นด้วยระยะเวลาไม่มีกิจกรรม (Inactivity Threshold) เกิน 30 นาที'}
                  </p>
                </div>
                <div className="rounded-xl bg-slate-50 p-3 border border-slate-100">
                  <div className="font-bold text-slate-800">4. กำลังใช้งานเว็บไซต์ (Online Visitors)</div>
                  <p className="mt-1">
                    {definitions?.online_visitors ||
                      'จำนวนผู้เข้าชมที่มีกิจกรรมบนหน้าเว็บสาธารณะภายในช่วง 5 นาทีล่าสุด'}
                  </p>
                </div>
                <div className="rounded-xl bg-slate-50 p-3 border border-slate-100">
                  <div className="font-bold text-slate-800">5. การคุ้มครองข้อมูลส่วนบุคคล (PDPA)</div>
                  <p className="mt-1">
                    ไม่จัดเก็บ IP Address ดิบเป็นตัวระบุหลัก ไม่บันทึก Token/Query String ลับ และไม่ติดตามหน้าสมาชิกหรือหน้าผู้ดูแลระบบ
                  </p>
                </div>
                <div className="rounded-xl bg-amber-50/70 p-3 border border-amber-200/70">
                  <div className="font-bold text-amber-900">6. ข้อจำกัดของการนับผู้เข้าชม (Limitations)</div>
                  <p className="mt-1 text-amber-800">
                    {definitions?.limitations ||
                      'รหัสผู้เข้าชมแบบนามแฝงอ้างอิงตามเบราว์เซอร์/อุปกรณ์ ไม่สามารถรับประกันว่าเป็นบุคคลธรรมดาคนเดียวกัน 100% หากเปลี่ยนอุปกรณ์หรือล้างข้อมูลเบราว์เซอร์'}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  );
}
