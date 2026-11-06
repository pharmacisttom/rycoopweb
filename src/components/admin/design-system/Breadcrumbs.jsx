import React from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight, Home } from 'lucide-react';

export default function Breadcrumbs({ items = [] }) {
  if (!items || items.length === 0) return null;

  return (
    <nav className="admin-breadcrumbs" aria-label="เส้นทางหน้าปัจจุบัน" style={{ marginBottom: '8px' }}>
      <ol
        style={{
          display: 'flex',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '6px',
          padding: 0,
          margin: 0,
          listStyle: 'none',
          fontSize: '0.85rem',
          color: 'var(--acc-text-secondary, #64748b)',
        }}
      >
        <li style={{ display: 'inline-flex', alignItems: 'center' }}>
          <Link
            to="/admin/dashboard"
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '4px',
              color: 'inherit',
              textDecoration: 'none',
            }}
          >
            <Home size={14} aria-hidden="true" />
            <span>หน้าหลัก</span>
          </Link>
        </li>

        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          return (
            <li
              key={`${item.label}-${index}`}
              style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px',
              }}
            >
              <ChevronRight size={13} aria-hidden="true" style={{ opacity: 0.6 }} />
              {(item.to || item.path) && !isLast ? (
                <Link
                  to={item.to || item.path}
                  style={{
                    color: 'inherit',
                    textDecoration: 'none',
                  }}
                >
                  {item.label}
                </Link>
              ) : (
                <span
                  aria-current={isLast ? 'page' : undefined}
                  style={{
                    fontWeight: isLast ? 600 : 400,
                    color: isLast ? 'var(--acc-text, #0f172a)' : 'inherit',
                  }}
                >
                  {item.label}
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
