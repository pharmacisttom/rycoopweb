import React from 'react';
import '../../src/index.css';
import { createRoot } from 'react-dom/client';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import AdminActionProvider from '../../src/components/admin/AdminActionProvider';
import AdminDynamicLayoutPage from '../../src/pages/AdminDynamicLayoutPage';
import { ActivityAlbumPage } from '../../src/components/layout/DynamicLayoutRenderer';
const publicView = new URLSearchParams(location.search).get('view') === 'public';
createRoot(document.getElementById('root')).render(<MemoryRouter initialEntries={[publicView ? '/gallery/browser-album' : '/admin/layout']}><AdminActionProvider><Routes><Route path="/admin/layout" element={<AdminDynamicLayoutPage />} /><Route path="/gallery/:slug" element={<ActivityAlbumPage />} /></Routes></AdminActionProvider></MemoryRouter>);
