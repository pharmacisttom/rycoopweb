// Presentation-only fixture: synthetic identity/API responses, never a protected TEST route.
import React, { Suspense, lazy, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter, useNavigate } from 'react-router-dom';
import { ThemeProvider } from '../../src/context/ThemeContext';
import AdminAppShell from '../../src/components/admin/AdminAppShell';
import AdminActionProvider, {useAdminAction} from '../../src/components/admin/AdminActionProvider';
import '../../src/index.css';
import '../../src/styles/mobile-responsive.css';
const params=new URLSearchParams(location.search),kind=params.get('kind')||'access';
localStorage.setItem('coop_theme',params.get('theme')||'light');
const pages={documents:lazy(()=>import('../../src/pages/AdminStoredContentPage')),settingsContent:lazy(()=>import('../../src/pages/AdminStoredContentPage').then(module=>({default:()=>React.createElement(module.default,{kind:'settings'})}))),access:lazy(()=>import('../../src/pages/AdminAccessPage')),workspace:lazy(()=>import('../../src/pages/AdminWorkspacePage')),
  content:lazy(()=>import('../../src/pages/ContentAdminPage')),publicContent:lazy(()=>import('../../src/pages/PublicContentAdminPage')),
  cms:lazy(()=>import('../../src/pages/AdminDashboardPage')),membersDashboard:lazy(()=>import('../../src/pages/AdminMembersDashboardPage')),
  members:lazy(()=>import('../../src/pages/AdminMembersPage')),memberDetail:lazy(()=>import('../../src/pages/AdminMemberDetailPage')),
  membersHealth:lazy(()=>import('../../src/pages/AdminMembersHealthPage')),dividendImport:lazy(()=>import('../../src/pages/AdminDividendImportPage')),
  ledDashboard:lazy(()=>import('../../src/pages/AdminLedDashboardPage')),ledSearch:lazy(()=>import('../../src/pages/AdminLedSearchPage')),
  ledReview:lazy(()=>import('../../src/pages/AdminLedReviewPage')),ledBatch:lazy(()=>import('../../src/pages/AdminLedBatchPage')),ledSchema:lazy(()=>import('../../src/pages/AdminLedSchemaPage'))};
const Control=lazy(()=>import('../../src/pages/AdminControlCenterPage'));
function ActionHarness(){const navigate=useNavigate();window.__fixtureNavigate=navigate;const{run,busy}=useAdminAction();const[status,setStatus]=useState('');return <section><h1>ทดสอบคำสั่งสำคัญ</h1><button className="acc-btn" disabled={busy} onClick={async()=>{setStatus('');try{await run(async signal=>{const response=await fetch('/__test-action',{method:'POST',signal});const body=await response.json();if(!response.ok)throw Object.assign(new Error(body.message),{status:response.status,code:body.code});return body;},{label:'Synthetic critical action',scope:'roles.update'});setStatus('ACTION_OK');}catch(error){setStatus(error.code||error.name);}}}>ทำคำสั่งทดสอบ</button><p role="status">{status}</p></section>;}
const Page=pages[kind];
const contents=<Suspense fallback={<p>กำลังโหลด component…</p>}>{kind==='action'?<ActionHarness/>:Page?<Page/>:<Control section={kind}/>}</Suspense>;
createRoot(document.getElementById('root')).render(<MemoryRouter initialEntries={['/__admin_fixture']}><ThemeProvider>
  {window.__adminBaseline?<><p>ภาพก่อนปรับปรุง — ข้อมูลทดสอบ</p>{contents}</>:<AdminActionProvider><AdminAppShell title={null}><p className="admin-notice">ข้อมูลทดสอบสำหรับตรวจหน้าตาและการทำงาน ไม่ใช่ระบบ TEST จริง</p>{contents}</AdminAppShell></AdminActionProvider>}
</ThemeProvider></MemoryRouter>);
