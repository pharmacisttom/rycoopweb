import React, { useState, useEffect } from 'react';
import {
  Database, RefreshCw, CheckCircle2, AlertTriangle,
  FileCode, Layers, ShieldCheck, Clock, Loader2, Info
} from 'lucide-react';
import LedSubNav from '../components/led/LedSubNav';
import LedWarningNotice from '../components/led/LedWarningNotice';
import { fetchLedSchema, refreshLedSchema } from '../services/ledApi';
import { useToast } from '../context/ToastContext';

export default function AdminLedSchemaPage() {
  const { toast } = useToast();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [schemaData, setSchemaData] = useState(null);

  useEffect(() => {
    loadSchema();
  }, []);

  const loadSchema = async () => {
    setLoading(true);
    try {
      const res = await fetchLedSchema();
      if (res.success && res.data) {
        setSchemaData(res.data);
      } else {
        toast.error(res.message || 'ไม่สามารถโหลด Schema ได้');
      }
    } catch (e) {
      toast.error('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    } finally {
      setLoading(false);
    }
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      const res = await refreshLedSchema();
      if (res.success && res.data) {
        setSchemaData(res.data);
        toast.success('รีเฟรชโครงสร้างข้อมูลจากกรมบังคับคดีสำเร็จ');
      } else {
        toast.error(res.message || 'ไม่สามารถรีเฟรช Schema ได้');
      }
    } catch (e) {
      toast.error('เกิดข้อผิดพลาดในการรีเฟรช Schema');
    } finally {
      setRefreshing(false);
    }
  };

  const fields = schemaData?.fields || [];

  return (
    <div className="admin-led-page animate-fade-in space-y-8">
      {/* Sub Navigation */}
      <LedSubNav />

      {/* Warning Notice */}
      <LedWarningNotice />

      {/* Schema Discovery Container */}
      <div className="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 lg:p-8 shadow-sm space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-4">
          <div>
            <div className="flex items-center gap-2">
              <Database className="w-5 h-5 text-[#073B74] dark:text-blue-400" />
              <h2 className="text-lg lg:text-xl font-bold text-slate-900 dark:text-white">
                CKAN DataStore Schema Discovery
              </h2>
            </div>
            <p className="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mt-1">
              โครงสร้างฟิลด์ข้อมูลจริง (Metadata) ที่อ่านได้สดจาก Open Data API ของกรมบังคับคดี โดยไม่มีการเดาชื่อคอลัมน์
            </p>
          </div>

          <button
            onClick={handleRefresh}
            disabled={refreshing || loading}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#073B74] hover:bg-[#0B5ED7] text-white text-xs lg:text-sm font-semibold shadow-sm transition-all disabled:opacity-50 self-start sm:self-auto"
          >
            <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />
            {refreshing ? 'กำลังรีเฟรช...' : 'รีเฟรช Schema จาก API'}
          </button>
        </div>

        {/* Resource Meta Info Banner */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 dark:bg-slate-800/40 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/60 text-xs">
          <div>
            <span className="text-slate-500 dark:text-slate-400">Resource ID (ชุดข้อมูล):</span>
            <p className="font-mono font-bold text-slate-800 dark:text-slate-200 text-xs mt-0.5 break-all">
              {schemaData?.resource_id || 'e916cc37-78b6-4dbf-bc0f-4bee020cb830'}
            </p>
          </div>
          <div>
            <span className="text-slate-500 dark:text-slate-400">จำนวนฟิลด์ทั้งหมด:</span>
            <p className="font-bold text-slate-800 dark:text-slate-200 text-sm mt-0.5">
              {fields.length} ฟิลด์ {schemaData?.total_records ? `(จาก ${Number(schemaData.total_records).toLocaleString()} รายการ)` : ''}
            </p>
          </div>
          <div>
            <span className="text-slate-500 dark:text-slate-400">รีเฟรชล่าสุดเมื่อ:</span>
            <p className="font-semibold text-slate-800 dark:text-slate-200 text-xs mt-0.5">
              {schemaData?.refreshed_at || '-'}
            </p>
          </div>
        </div>

        {/* Fields Table */}
        {loading ? (
          <div className="py-24 text-center space-y-3">
            <Loader2 className="w-10 h-10 animate-spin text-[#0B5ED7] mx-auto" />
            <p className="text-sm text-slate-500">กำลังเชื่อมต่อและอ่าน Schema จาก CKAN DataStore API...</p>
          </div>
        ) : fields.length === 0 ? (
          <div className="py-16 text-center rounded-2xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900 p-6 space-y-2">
            <AlertTriangle className="w-10 h-10 text-red-500 mx-auto" />
            <h4 className="font-bold text-red-800 dark:text-red-300 text-sm">
              ไม่สามารถอ่าน Schema ได้จาก API กรมบังคับคดี
            </h4>
            <p className="text-xs text-red-600 dark:text-red-400 max-w-md mx-auto">
              กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ตของเซิร์ฟเวอร์ หรือสถานะของ opendata.led.go.th
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
            <table className="w-full text-left text-xs lg:text-sm">
              <thead className="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold">
                <tr>
                  <th className="py-3.5 px-4">#</th>
                  <th className="py-3.5 px-4">ชื่อฟิลด์ (Field Name)</th>
                  <th className="py-3.5 px-4">ประเภท (Data Type)</th>
                  <th className="py-3.5 px-4">คำอธิบาย / Label</th>
                  <th className="py-3.5 px-4">ตัวอย่างข้อมูลจริง (Masked Value)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {fields.map((f, idx) => (
                  <tr key={f.name || idx} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                    <td className="py-3.5 px-4 text-slate-400 font-mono text-xs">
                      {idx + 1}
                    </td>
                    <td className="py-3.5 px-4 font-mono font-bold text-[#073B74] dark:text-blue-400">
                      {f.name}
                    </td>
                    <td className="py-3.5 px-4">
                      <span className="px-2 py-0.5 rounded text-[11px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        {f.type}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                      {f.label || f.notes || '-'}
                    </td>
                    <td className="py-3.5 px-4 font-mono text-xs text-slate-600 dark:text-slate-400">
                      {f.example_value || '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
