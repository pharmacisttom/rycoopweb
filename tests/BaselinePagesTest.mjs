import assert from 'node:assert/strict';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { StaticRouter } from 'react-router-dom/server.js';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

let fixture = null;
globalThis.localStorage = { getItem: key => key === 'coop_auth_user' ? JSON.stringify(fixture) : null };
const server = await createServer({ configFile: false, plugins: [react()], optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true }, appType: 'custom' });
try {
  const { AuthProvider } = await server.ssrLoadModule('/src/context/AuthContext.jsx');
  const { ThemeProvider } = await server.ssrLoadModule('/src/context/ThemeContext.jsx');
  const { ToastProvider } = await server.ssrLoadModule('/src/context/ToastContext.jsx');
  const { default: Workspace } = await server.ssrLoadModule('/src/pages/AdminWorkspacePage.jsx');
  const render = Page => renderToStaticMarkup(React.createElement(StaticRouter, { location: '/admin/workspace' },
    React.createElement(ThemeProvider, null, React.createElement(ToastProvider, null, React.createElement(AuthProvider, null, React.createElement(Page))))));
  fixture = { id: 10, name: 'Fixture editor', role: 'custom_editor', permissions: ['news.view'] };
  let html = render(Workspace);
  assert.ok(html.includes('/admin/content?tab=news'));
  assert.ok(!html.includes('/admin/content?tab=faqs'));
  assert.ok(!html.includes('href="/admin/access"'));
  assert.ok(!html.includes('href="/admin/dividends/import"'));
  fixture = { id: 11, name: 'Fixture admin', role: 'super_admin', permissions: [] };
  html = render(Workspace);
  assert.ok(html.includes('href="/admin/access"'));
  assert.ok(html.includes('href="/admin/dividends/import"'));
  const { default: Access } = await server.ssrLoadModule('/src/pages/AdminAccessPage.jsx');
  html = render(Access);
  assert.ok(html.includes('จัดการผู้ใช้งานและสิทธิ์'));
  assert.ok(html.includes('กำลังโหลดข้อมูล'));
  assert.ok(!html.includes('type="password"'), 'No credential form or stale account data before authorized API load');
  console.log('PASS: baseline pages render; workspace links respect permissions; access page waits for authorized data');
} finally {
  await server.close();
  delete globalThis.localStorage;
}
