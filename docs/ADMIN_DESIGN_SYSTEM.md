# RYCOOPWEB — UNIFIED ADMIN DESIGN SYSTEM SPECIFICATION
## Version: 3.1.0-STABLE | Target: Cooperative Administration Platform

---

### 1. Design Principles & Objectives
The RYCOOPWEB Admin Design System is built to serve cooperative officers, auditors, and administrators. It prioritizes clarity, financial precision, non-destructive workflows, and high legibility in Thai and English typography.

1. **Hierarchy First:** Primary actions, critical alerts, and workflow statuses must stand out unambiguously.
2. **Predictable Geometry:** Consistent 8px spacing grid, uniform card borders, standardized drawer sizes, and responsive viewports.
3. **Safe Controls:** Destructive actions (deletions, financial resets, status changes) require two-factor confirmation or explicit modals.
4. **Cooperative Trust:** Navy Blue, Slate, and Pure White anchor the interface, projecting stability and institutional trust.

---

### 2. Color Palette & Tokens

#### 2.1 Primary & Institutional Colors
| Token Name | Hex Code | Purpose |
|---|---|---|
| `--rycoop-navy-deep` | `#0f2942` | Primary branding, topbar surface, modal headers |
| `--rycoop-navy-surface` | `#1e3a8a` | Active navigation states, brand accents |
| `--rycoop-blue-primary` | `#0284c7` | Primary buttons, active indicators, interactive links |
| `--rycoop-blue-hover` | `#0369a1` | Hover state for primary interactive elements |
| `--rycoop-blue-subtle` | `#f0f9ff` | Selected rows, subtle badge backgrounds, alert callouts |

#### 2.2 Neutral & Surface Colors
| Token Name | Hex Code | Purpose |
|---|---|---|
| `--rycoop-bg-app` | `#f8fafc` | Global admin application background |
| `--rycoop-bg-card` | `#ffffff` | Content containers, cards, tables, modal surfaces |
| `--rycoop-border-subtle` | `#e2e8f0` | Dividers, card borders, table row borders |
| `--rycoop-border-strong` | `#cbd5e1` | Input borders, active table headers |
| `--rycoop-text-primary` | `#0f172a` | Main headers, data values, active menu items |
| `--rycoop-text-secondary` | `#475569` | Descriptions, subtitles, table headers |
| `--rycoop-text-muted` | `#94a3b8` | Timestamps, placeholders, inactive icons |

#### 2.3 Semantic & Status Colors
| Status | Background | Border / Text | Usage |
|---|---|---|---|
| **Success / Published** | `#ecfdf5` | `#059669` / `#065f46` | Active campaigns, verified members, published news |
| **Warning / Pending** | `#fffbeb` | `#d97706` / `#92400e` | Pending approvals, draft status, unread logs |
| **Danger / Blocked** | `#fef2f2` | `#dc2626` / `#991b1b` | Locked accounts, failed imports, destructive actions |
| **Info / Scheduled** | `#eff6ff` | `#2563eb` / `#1e40af` | System notifications, future scheduled publications |

---

### 3. Typography & Text Standards

- **Primary Font:** `'Sarabun', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`
- **Monospace Font (Code/Numbers/IDs):** `'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace`

| Level | Size | Weight | Line Height | Usage |
|---|---|---|---|---|
| **Page Title (H1)** | 1.5rem (24px) | 700 / Semi-Bold | 1.3 | Top of main work areas |
| **Section Title (H2)** | 1.25rem (20px) | 600 / Medium | 1.35 | Card and drawer headers |
| **Card Title (H3)** | 1.05rem (17px) | 600 / Medium | 1.4 | Metric titles, sub-sections |
| **Body (Normal)** | 0.925rem (14.8px) | 400 / Regular | 1.5 | General descriptions, form labels |
| **Table Cell / Compact** | 0.875rem (14px) | 400–500 | 1.45 | Data tables, meta badges |
| **Caption / Help** | 0.775rem (12.4px) | 400 / Regular | 1.4 | Helper text under inputs, timestamp notes |

---

### 4. Spacing, Elevation & Layout Grid

- **Base Spacing Unit:** 4px (Grid: 4px, 8px, 12px, 16px, 20px, 24px, 32px, 48px)
- **Border Radius:**
  - Badges / Small Pills: `9999px` (Full pill)
  - Inputs / Buttons: `8px`
  - Cards / Containers: `12px`
  - Modals / Drawers: `16px`
- **Box Shadows:**
  - Card Subtle: `0 1px 3px 0 rgba(15, 23, 42, 0.05), 0 1px 2px -1px rgba(15, 23, 42, 0.05)`
  - Card Raised: `0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.05)`
  - Modal / Flyout: `0 20px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.08)`

---

### 5. Component Standards

#### 5.1 Single Shell Standard
- All administrative views are hosted exclusively inside `AdminControlCenterShell.jsx`.
- **Topbar:** Navy `#0f2942`, height `64px`, sticky top `z-index: 40`, containing breadcrumb/title, system health badge, role badge, user profile, and mobile hamburger.
- **Sidebar:** Width `260px` desktop (collapsible to `72px`), height `100vh`, fixed left.
- **Content Area:** Width `100%`, max-width `1600px`, padding `24px` desktop, `16px` tablet, `12px` mobile.

#### 5.2 Page Header Standard
Standardized via `src/components/admin/design-system/PageHeader.jsx`:
```jsx
<PageHeader
  title="จัดการป๊อปอัปประชาสัมพันธ์"
  subtitle="กำหนดป๊อปอัปและแคมเปญหน้าแรก สูงสุด 3 รายการ"
  breadcrumbs={[{ label: 'แดชบอร์ด', path: '/admin/dashboard' }, { label: 'ป๊อปอัป' }]}
  actions={<Button variant="primary" icon="plus">เพิ่มป๊อปอัป</Button>}
/>
```

#### 5.3 Data Tables (`CrudDataTable`)
- Clear table header styling with `#f8fafc` background and `#475569` uppercase text.
- Row hover with `#f1f5f9`.
- Responsive horizontal scroll wrapper with visual gradient edge when overflowing on mobile.
- Built-in pagination, items per page selector, and search input with debounce.

#### 5.4 Form Drawers (`CrudFormDrawer`)
- Slide-in from right, width `min(540px, 100vw)`.
- Explicit Cancel and Save action buttons fixed at the drawer footer.
- Keyboard accessible: `Escape` closes drawer (with unsaved changes check).
