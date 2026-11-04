import React, { useMemo, useState } from 'react';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
import LoadingSkeleton from './LoadingSkeleton';
import EmptyState from './EmptyState';

export default function CrudDataTable({
  columns = [],
  rows = [],
  rowKey = 'id',
  loading = false,
  error = null,
  emptyTitle = 'ไม่พบข้อมูล',
  emptyDescription = 'ยังไม่มีข้อมูลในระบบ หรือไม่ตรงกับคำค้นหา',
  emptyActionLabel = null,
  onEmptyAction = null,
  searchPlaceholder = 'ค้นหาข้อมูล...',
  searchKeys = [], // array of object keys to search by
  filterComponent = null,
  actions = null,
  perPageDefault = 15,
  pagination = true,
  onRowClick = null,
}) {
  const [searchTerm, setSearchTerm] = useState('');
  const [sortKey, setSortKey] = useState(null);
  const [sortDirection, setSortDirection] = useState('asc'); // 'asc' | 'desc'
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(perPageDefault);

  // Filtered rows
  const filteredRows = useMemo(() => {
    if (!searchTerm.trim()) return rows;
    const term = searchTerm.toLowerCase();

    return rows.filter((row) => {
      if (searchKeys.length > 0) {
        return searchKeys.some((k) => {
          const val = row[k];
          return val !== null && val !== undefined && String(val).toLowerCase().includes(term);
        });
      }
      // Default: search all string/number fields
      return Object.values(row).some((val) => {
        if (typeof val === 'string' || typeof val === 'number') {
          return String(val).toLowerCase().includes(term);
        }
        return false;
      });
    });
  }, [rows, searchTerm, searchKeys]);

  // Sorted rows
  const sortedRows = useMemo(() => {
    if (!sortKey) return filteredRows;

    return [...filteredRows].sort((a, b) => {
      const valA = a[sortKey];
      const valB = b[sortKey];

      if (valA === valB) return 0;
      if (valA === null || valA === undefined) return 1;
      if (valB === null || valB === undefined) return -1;

      let comparison = 0;
      if (typeof valA === 'number' && typeof valB === 'number') {
        comparison = valA - valB;
      } else {
        comparison = String(valA).localeCompare(String(valB), 'th');
      }

      return sortDirection === 'asc' ? comparison : -comparison;
    });
  }, [filteredRows, sortKey, sortDirection]);

  // Paginated rows
  const totalItems = sortedRows.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
  const safePage = Math.min(currentPage, totalPages);

  const displayedRows = useMemo(() => {
    if (!pagination) return sortedRows;
    const start = (safePage - 1) * perPage;
    return sortedRows.slice(start, start + perPage);
  }, [sortedRows, pagination, safePage, perPage]);

  const handleSort = (key) => {
    if (sortKey === key) {
      if (sortDirection === 'asc') {
        setSortDirection('desc');
      } else {
        setSortKey(null);
        setSortDirection('asc');
      }
    } else {
      setSortKey(key);
      setSortDirection('asc');
    }
  };

  return (
    <div className="crud-data-table-container" style={{ width: '100%' }}>
      {/* Search and Filter Toolbar */}
      <div
        className="crud-table-toolbar"
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '12px',
          marginBottom: '16px',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flex: '1 1 300px', maxWidth: '480px' }}>
          <div
            style={{
              position: 'relative',
              width: '100%',
              display: 'flex',
              alignItems: 'center',
            }}
          >
            <Search
              size={17}
              style={{
                position: 'absolute',
                left: '12px',
                color: 'var(--acc-text-secondary, #94a3b8)',
                pointerEvents: 'none',
              }}
              aria-hidden="true"
            />
            <input
              type="search"
              value={searchTerm}
              onChange={(e) => {
                setSearchTerm(e.target.value);
                setCurrentPage(1);
              }}
              placeholder={searchPlaceholder}
              style={{
                width: '100%',
                padding: '9px 36px 9px 38px',
                borderRadius: '8px',
                border: '1px solid var(--acc-border, #cbd5e1)',
                backgroundColor: 'var(--acc-surface, #ffffff)',
                color: 'var(--acc-text, #0f172a)',
                fontSize: '0.9rem',
              }}
              aria-label="ช่องค้นหาข้อมูลในตาราง"
            />
            {searchTerm && (
              <button
                type="button"
                onClick={() => setSearchTerm('')}
                style={{
                  position: 'absolute',
                  right: '10px',
                  background: 'none',
                  border: 'none',
                  color: 'var(--acc-text-secondary, #94a3b8)',
                  cursor: 'pointer',
                  padding: '4px',
                  display: 'flex',
                  alignItems: 'center',
                }}
                aria-label="ล้างคำค้นหา"
              >
                <X size={15} />
              </button>
            )}
          </div>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
          {filterComponent}
          {actions}
        </div>
      </div>

      {/* Loading state */}
      {loading ? (
        <LoadingSkeleton variant="table" lines={5} />
      ) : error ? (
        <div
          role="alert"
          style={{
            padding: '16px',
            backgroundColor: '#fff1f2',
            border: '1px solid #fecdd3',
            borderRadius: '10px',
            color: '#be123c',
            margin: '16px 0',
          }}
        >
          <strong>เกิดข้อผิดพลาดในการโหลดข้อมูล:</strong> {error}
        </div>
      ) : displayedRows.length === 0 ? (
        <EmptyState
          title={emptyTitle}
          description={emptyDescription}
          actionLabel={emptyActionLabel}
          onAction={onEmptyAction}
        />
      ) : (
        <>
          <div
            className="admin-table-scroll"
            tabIndex={0}
            role="region"
            aria-label="ตารางข้อมูล (สามารถเลื่อนแนวนอนได้)"
            style={{
              overflowX: 'auto',
              borderRadius: '10px',
              border: '1px solid var(--acc-border, #e2e8f0)',
              backgroundColor: 'var(--acc-surface, #ffffff)',
            }}
          >
            <table
              className="admin-table"
              style={{
                width: '100%',
                borderCollapse: 'collapse',
                textAlign: 'left',
                fontSize: '0.9rem',
              }}
            >
              <thead>
                <tr style={{ backgroundColor: 'var(--acc-surface-elevated, #f8fafc)', borderBottom: '1px solid var(--acc-border, #e2e8f0)' }}>
                  {columns.map((col) => {
                    const isSorted = sortKey === col.key;
                    const canSort = col.sortable !== false;

                    return (
                      <th
                        key={col.key}
                        scope="col"
                        style={{
                          padding: '12px 16px',
                          fontWeight: 700,
                          color: 'var(--acc-text-secondary, #475569)',
                          width: col.width || 'auto',
                          textAlign: col.numeric ? 'right' : 'left',
                          cursor: canSort ? 'pointer' : 'default',
                          userSelect: 'none',
                          whiteSpace: 'nowrap',
                        }}
                        onClick={() => canSort && handleSort(col.key)}
                        aria-sort={isSorted ? (sortDirection === 'asc' ? 'ascending' : 'descending') : undefined}
                      >
                        <div
                          style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '6px',
                            justifyContent: col.numeric ? 'flex-end' : 'flex-start',
                            width: '100%',
                          }}
                        >
                          <span>{col.label}</span>
                          {canSort && (
                            <span style={{ opacity: isSorted ? 1 : 0.4 }}>
                              {isSorted ? (
                                sortDirection === 'asc' ? <ArrowUp size={14} /> : <ArrowDown size={14} />
                              ) : (
                                <ArrowUpDown size={14} />
                              )}
                            </span>
                          )}
                        </div>
                      </th>
                    );
                  })}
                </tr>
              </thead>
              <tbody>
                {displayedRows.map((row, idx) => {
                  const key = row[rowKey] ?? idx;
                  return (
                    <tr
                      key={key}
                      onClick={() => onRowClick && onRowClick(row)}
                      style={{
                        borderBottom: '1px solid var(--acc-border, #f1f5f9)',
                        cursor: onRowClick ? 'pointer' : 'default',
                        transition: 'background-color 0.15s ease',
                      }}
                      className="admin-table-row"
                    >
                      {columns.map((col) => (
                        <td
                          key={col.key}
                          style={{
                            padding: '14px 16px',
                            verticalAlign: 'middle',
                            textAlign: col.numeric ? 'right' : 'left',
                            fontVariantNumeric: col.numeric ? 'tabular-nums' : 'normal',
                          }}
                        >
                          {col.render ? col.render(row, idx) : (row[col.key] ?? '—')}
                        </td>
                      ))}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination Footer */}
          {pagination && (
            <div
              className="crud-table-pagination"
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                flexWrap: 'wrap',
                gap: '12px',
                marginTop: '16px',
                fontSize: '0.85rem',
                color: 'var(--acc-text-secondary, #64748b)',
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <span>แสดง</span>
                <select
                  value={perPage}
                  onChange={(e) => {
                    setPerPage(Number(e.target.value));
                    setCurrentPage(1);
                  }}
                  style={{
                    padding: '4px 8px',
                    borderRadius: '6px',
                    border: '1px solid var(--acc-border, #cbd5e1)',
                    backgroundColor: 'var(--acc-surface, #ffffff)',
                    color: 'inherit',
                  }}
                  aria-label="จำนวนรายการต่อหน้า"
                >
                  <option value={10}>10</option>
                  <option value={15}>15</option>
                  <option value={25}>25</option>
                  <option value={50}>50</option>
                  <option value={100}>100</option>
                </select>
                <span>รายการ จากทั้งหมด {totalItems.toLocaleString('th-TH')} รายการ</span>
              </div>

              <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                <span style={{ marginRight: '8px' }}>
                  หน้า {safePage} / {totalPages}
                </span>
                <button
                  type="button"
                  className="acc-btn"
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={safePage <= 1}
                  style={{ padding: '6px 10px', minHeight: '36px' }}
                  aria-label="หน้าก่อนหน้า"
                >
                  <ChevronLeft size={16} />
                </button>
                <button
                  type="button"
                  className="acc-btn"
                  onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                  disabled={safePage >= totalPages}
                  style={{ padding: '6px 10px', minHeight: '36px' }}
                  aria-label="หน้าถัดไป"
                >
                  <ChevronRight size={16} />
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
