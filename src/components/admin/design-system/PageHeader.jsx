import React from 'react';
import Breadcrumbs from './Breadcrumbs';

export default function PageHeader({
  title,
  subtitle,
  breadcrumbs = [],
  actions = null,
  badge = null,
}) {
  return (
    <header className="admin-page-header" style={{ marginBottom: '24px' }}>
      {breadcrumbs.length > 0 && <Breadcrumbs items={breadcrumbs} />}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          flexWrap: 'wrap',
          gap: '16px',
        }}
      >
        <div style={{ flex: '1 1 300px', minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
            <h1
              style={{
                margin: 0,
                fontSize: 'clamp(1.35rem, 2vw, 1.75rem)',
                fontWeight: 700,
                color: 'var(--acc-text, #0f172a)',
                lineHeight: 1.3,
              }}
            >
              {title}
            </h1>
            {badge && <div>{badge}</div>}
          </div>
          {subtitle && (
            <p
              style={{
                margin: '6px 0 0 0',
                fontSize: '0.9rem',
                color: 'var(--acc-text-secondary, #64748b)',
                lineHeight: 1.5,
              }}
            >
              {subtitle}
            </p>
          )}
        </div>
        {actions && (
          <div
            className="admin-header-actions"
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '10px',
              flexWrap: 'wrap',
            }}
          >
            {actions}
          </div>
        )}
      </div>
    </header>
  );
}
