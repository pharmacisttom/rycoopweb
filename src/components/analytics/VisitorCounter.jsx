import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Activity,
  AlertCircle,
  BarChart3,
  Calendar,
  CalendarDays,
  Clock,
  Eye,
  Globe,
  RefreshCw,
  Users,
} from 'lucide-react';
import { fetchVisitorStats } from '../../services/api';

const REFRESH_INTERVAL_MS = 60000; // 60 seconds bounded interval

/**
 * Format numeric values with Thai locale formatting.
 * Distinguishes true zero (0 -> "0") from unavailable (null/undefined -> "—").
 */
export function formatThaiNumber(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) {
    return '—';
  }
  return Number(value).toLocaleString('th-TH');
}

/**
 * Normalize summary payload from either /api/public/visitors/summary or detailed analytics.
 */
export function normalizeVisitorSummary(raw) {
  if (!raw || typeof raw !== 'object') return null;
  if (raw.available === false) return null;

  const today = raw.today_visitors ?? raw.today;
  const month = raw.month_visitors ?? raw.this_month;
  const total = raw.total_visitors ?? raw.total;
  const online = raw.online_visitors ?? raw.online_now;

  if (
    today === undefined &&
    month === undefined &&
    total === undefined &&
    online === undefined
  ) {
    return null;
  }

  return {
    online_visitors: Number(online ?? 0),
    today_visitors: Number(today ?? 0),
    month_visitors: Number(month ?? 0),
    total_visitors: Number(total ?? 0),
    today_page_views: Number(raw.today_page_views ?? 0),
    month_page_views: Number(raw.month_page_views ?? 0),
    total_page_views: Number(raw.total_page_views ?? 0),
    today_sessions: Number(raw.today_sessions ?? 0),
    timezone: raw.timezone || 'Asia/Bangkok',
    generated_at: raw.generated_at_bangkok || raw.last_updated || null,
  };
}

/**
 * Reusable Visitor Counter Widget (RYCOOPWEB 3.0)
 *
 * Variants:
 * - "footer": Compact navy/teal widget for the public website footer
 * - "home": Summary strip for the public homepage
 * - "full": Full 4-card + page-view summary for /visitor-stats
 * - "admin": Advanced KPI strip for Admin Control Center
 */
export function VisitorCounter({
  variant = 'footer',
  externalStats = null,
  externalLoading = null,
  externalError = null,
  onRetry = null,
  showLinkToStats = true,
  className = '',
}) {
  const isControlled = externalStats !== null || externalLoading !== null || externalError !== null;
  const [stats, setStats] = useState(() => normalizeVisitorSummary(externalStats));
  const [loading, setLoading] = useState(isControlled ? Boolean(externalLoading) : true);
  const [unavailable, setUnavailable] = useState(isControlled ? Boolean(externalError) : false);
  const [isStale, setIsStale] = useState(false);
  const [lastVerifiedAt, setLastVerifiedAt] = useState(null);
  const mountedRef = useRef(true);

  const loadSummary = useCallback(async (isBackgroundRefresh = false) => {
    if (isControlled) return;
    if (!isBackgroundRefresh) {
      setLoading(true);
    }

    try {
      const res = await fetchVisitorStats();
      if (!mountedRef.current) return;

      const normalized = res && res.success && res.available !== false
        ? normalizeVisitorSummary(res.data)
        : null;

      if (normalized) {
        setStats(normalized);
        setUnavailable(false);
        setIsStale(false);
        setLastVerifiedAt(normalized.generated_at || new Date().toLocaleTimeString('th-TH'));
      } else {
        setUnavailable(true);
        setIsStale(true);
      }
    } catch (err) {
      if (!mountedRef.current) return;
      setUnavailable(true);
      setIsStale(true);
    } finally {
      if (mountedRef.current) {
        setLoading(false);
      }
    }
  }, [isControlled]);

  useEffect(() => {
    mountedRef.current = true;
    if (isControlled) {
      const normalized = normalizeVisitorSummary(externalStats);
      if (normalized) {
        setStats(normalized);
        setUnavailable(false);
        setIsStale(Boolean(externalError));
        setLastVerifiedAt(normalized.generated_at || null);
      } else if (externalError) {
        setUnavailable(true);
      }
      setLoading(Boolean(externalLoading));
      return () => {
        mountedRef.current = false;
      };
    }

    loadSummary(false);

    const intervalId = setInterval(() => {
      if (typeof document !== 'undefined' && document.hidden) {
        return;
      }
      loadSummary(true);
    }, REFRESH_INTERVAL_MS);

    return () => {
      mountedRef.current = false;
      clearInterval(intervalId);
    };
  }, [isControlled, externalStats, externalLoading, externalError, loadSummary]);

  const handleRetry = () => {
    if (typeof onRetry === 'function') {
      onRetry();
    } else {
      loadSummary(false);
    }
  };

  const metrics = [
    {
      key: 'online',
      label: 'กำลังใช้งานเว็บไซต์',
      sublabel: 'ภายใน 5 นาทีล่าสุด',
      value: stats ? stats.online_visitors : null,
      icon: Activity,
      live: true,
      accent: 'teal',
    },
    {
      key: 'today',
      label: 'ผู้เข้าชมวันนี้',
      sublabel: stats ? `เปิดหน้าเว็บ ${formatThaiNumber(stats.today_page_views)} ครั้ง` : 'ตามเวลาประเทศไทย',
      value: stats ? stats.today_visitors : null,
      icon: Clock,
      live: false,
      accent: 'blue',
    },
    {
      key: 'month',
      label: 'ผู้เข้าชมเดือนนี้',
      sublabel: stats ? `เปิดหน้าเว็บ ${formatThaiNumber(stats.month_page_views)} ครั้ง` : 'สะสมรายเดือน',
      value: stats ? stats.month_visitors : null,
      icon: CalendarDays,
      live: false,
      accent: 'indigo',
    },
    {
      key: 'total',
      label: 'ผู้เข้าชมทั้งหมด',
      sublabel: stats ? `เปิดหน้าเว็บสะสม ${formatThaiNumber(stats.total_page_views)} ครั้ง` : 'สะสมทั้งหมดในระบบ',
      value: stats ? stats.total_visitors : null,
      icon: Users,
      live: false,
      accent: 'navy',
    },
  ];

  // 1. Compact Footer Variant
  if (variant === 'footer') {
    return (
      <div
        className={`rounded-2xl border border-white/10 bg-slate-900/75 backdrop-blur-sm px-4 py-3.5 text-slate-200 shadow-inner ${className}`}
        aria-label="สถิติผู้เข้าชมเว็บไซต์"
      >
        <div className="flex flex-wrap items-center justify-between gap-2 mb-2.5">
          <div className="flex items-center gap-2">
            <span className="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-teal-500/15 text-teal-300 border border-teal-400/20">
              <BarChart3 className="h-4 w-4" />
            </span>
            <div>
              <h4 className="text-xs font-bold tracking-wide text-white uppercase">
                สถิติผู้เข้าชมเว็บไซต์
              </h4>
              <p className="text-[11px] text-slate-400">
                ข้อมูลจริงจากเซิร์ฟเวอร์ (เวลาประเทศไทย)
              </p>
            </div>
          </div>

          {showLinkToStats && (
            <Link
              to="/visitor-stats"
              className="inline-flex items-center gap-1.5 rounded-lg bg-white/5 hover:bg-teal-500/20 border border-white/10 hover:border-teal-400/30 px-2.5 py-1 text-[11px] font-semibold text-teal-300 transition-colors"
            >
              <Eye className="h-3.5 w-3.5" />
              ดูสถิติโดยละเอียด
            </Link>
          )}
        </div>

        {unavailable && !stats ? (
          <div className="flex items-center justify-between gap-3 rounded-xl bg-slate-800/80 border border-amber-400/20 px-3 py-2 text-xs text-amber-200">
            <span className="inline-flex items-center gap-1.5">
              <AlertCircle className="h-4 w-4 text-amber-400 shrink-0" />
              ไม่สามารถโหลดข้อมูลได้ในขณะนี้
            </span>
            <button
              type="button"
              onClick={handleRetry}
              className="inline-flex items-center gap-1 rounded-md bg-white/10 hover:bg-white/20 px-2 py-1 text-[11px] font-semibold text-white transition-colors"
            >
              <RefreshCw className="h-3 w-3" />
              ลองใหม่
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {metrics.map((m) => {
              const Icon = m.icon;
              return (
                <div
                  key={m.key}
                  className="rounded-xl bg-slate-800/70 border border-white/5 px-3 py-2 flex items-center gap-2.5"
                >
                  <div className="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-950/80 text-teal-300 border border-teal-500/20">
                    <Icon className="h-4 w-4" />
                    {m.live && (
                      <span className="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                      </span>
                    )}
                  </div>
                  <div className="min-w-0">
                    <div className="text-[11px] text-slate-400 truncate">{m.label}</div>
                    {loading && !stats ? (
                      <div className="h-4 w-14 rounded bg-slate-700 animate-pulse mt-0.5" />
                    ) : (
                      <div className="text-sm font-extrabold text-white tracking-tight tabular-nums">
                        {formatThaiNumber(m.value)}
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {isStale && stats && (
          <div className="mt-2 flex items-center justify-between text-[11px] text-amber-300/90">
            <span>แสดงข้อมูลล่าสุดที่ตรวจสอบแล้ว ({lastVerifiedAt || 'ก่อนหน้า'})</span>
            <button
              type="button"
              onClick={handleRetry}
              className="underline hover:text-white"
            >
              รีเฟรช
            </button>
          </div>
        )}
      </div>
    );
  }

  // 2. Homepage Summary Strip Variant
  if (variant === 'home') {
    return (
      <section
        className={`rounded-2xl bg-gradient-to-r from-[#0b2545] via-[#13315c] to-[#0f4c5c] text-white p-5 sm:p-6 shadow-lg border border-blue-900/30 ${className}`}
        aria-label="สรุปสถิติผู้เข้าชมเว็บไซต์"
      >
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div className="flex items-start sm:items-center gap-3.5">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal-400/15 border border-teal-300/30 text-teal-300">
              <Globe className="h-6 w-6" />
            </div>
            <div>
              <div className="inline-flex items-center gap-2 text-xs font-semibold text-teal-300 uppercase tracking-wider">
                <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse" />
                Real-Time Web Traffic
              </div>
              <h3 className="text-lg sm:text-xl font-extrabold text-white mt-0.5">
                สถิติการเข้าใช้งานเว็บไซต์สหกรณ์
              </h3>
              <p className="text-xs text-blue-100/80">
                ประมวลผลจากฐานข้อมูลจริงตามมาตรฐาน PDPA (อัปเดตทุก 60 วินาที)
              </p>
            </div>
          </div>

          {unavailable && !stats ? (
            <div className="flex items-center gap-3 rounded-xl bg-white/10 border border-white/15 px-4 py-3 text-sm text-amber-200">
              <AlertCircle className="h-5 w-5 text-amber-300 shrink-0" />
              <span>ไม่สามารถโหลดข้อมูลได้</span>
              <button
                type="button"
                onClick={handleRetry}
                className="inline-flex items-center gap-1.5 rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-xs font-bold text-white transition-colors"
              >
                <RefreshCw className="h-3.5 w-3.5" />
                ลองอีกครั้ง
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 flex-1 max-w-3xl">
              {metrics.map((m) => {
                const Icon = m.icon;
                return (
                  <div
                    key={m.key}
                    className="rounded-xl bg-white/10 backdrop-blur-sm border border-white/10 px-3.5 py-2.5"
                  >
                    <div className="flex items-center justify-between text-xs text-blue-100/90 mb-1">
                      <span className="truncate font-medium">{m.label}</span>
                      <Icon className="h-3.5 w-3.5 text-teal-300 shrink-0" />
                    </div>
                    {loading && !stats ? (
                      <div className="h-6 w-16 rounded bg-white/20 animate-pulse my-0.5" />
                    ) : (
                      <div className="text-lg sm:text-xl font-extrabold text-white tabular-nums">
                        {formatThaiNumber(m.value)}
                      </div>
                    )}
                    <div className="text-[11px] text-teal-200/80 truncate mt-0.5">
                      {m.sublabel}
                    </div>
                  </div>
                );
              })}
            </div>
          )}

          {showLinkToStats && (
            <div className="flex lg:flex-col items-center justify-end gap-2">
              <Link
                to="/visitor-stats"
                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold text-xs px-4 py-2.5 shadow-sm transition-colors"
              >
                <BarChart3 className="h-4 w-4" />
                ดูสถิติทั้งหมด
              </Link>
            </div>
          )}
        </div>
      </section>
    );
  }

  // 3. Full / Admin Cards Variant
  return (
    <div className={className} aria-label="ตัวชี้วัดสถิติผู้เข้าชมเว็บไซต์">
      {unavailable && !stats ? (
        <div className="rounded-2xl border border-rose-200 bg-rose-50/80 p-6 text-center">
          <AlertCircle className="h-8 w-8 text-rose-600 mx-auto mb-2" />
          <h3 className="text-base font-bold text-rose-950">ไม่สามารถโหลดข้อมูลได้</h3>
          <p className="text-xs text-rose-700 mt-1 max-w-md mx-auto">
            ระบบไม่สามารถดึงข้อมูลสถิติผู้เข้าชมจากฐานข้อมูลได้ในขณะนี้ จึงไม่แสดงตัวเลขจำลองเพื่อรักษาความถูกต้องของข้อมูล
          </p>
          <button
            type="button"
            onClick={handleRetry}
            className="mt-4 inline-flex items-center gap-2 rounded-xl bg-rose-700 hover:bg-rose-800 px-4 py-2 text-xs font-bold text-white transition-colors"
          >
            <RefreshCw className="h-3.5 w-3.5" />
            ลองดึงข้อมูลอีกครั้ง
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {metrics.map((m) => {
            const Icon = m.icon;
            return (
              <div
                key={m.key}
                className="rounded-2xl bg-white border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition-shadow"
              >
                <div className="flex items-center justify-between mb-3">
                  <span className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[#0b2545]/5 text-[#0b2545] border border-[#0b2545]/10">
                    <Icon className="h-5 w-5 text-teal-700" />
                  </span>
                  {m.live ? (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">
                      <span className="h-2 w-2 rounded-full bg-emerald-500 animate-ping" />
                      ออนไลน์ (5 นาที)
                    </span>
                  ) : (
                    <span className="text-[11px] font-semibold text-slate-400">
                      Asia/Bangkok
                    </span>
                  )}
                </div>
                <div className="text-xs font-semibold text-slate-500">{m.label}</div>
                {loading && !stats ? (
                  <div className="h-8 w-24 rounded-lg bg-slate-200 animate-pulse mt-1.5" />
                ) : (
                  <div className="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1 tabular-nums">
                    {formatThaiNumber(m.value)}
                  </div>
                )}
                <div className="text-xs text-teal-700 font-medium mt-1.5">{m.sublabel}</div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

export default VisitorCounter;
