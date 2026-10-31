import AdminActionProvider from '../../src/components/admin/AdminActionProvider';
import AdminAppShell from '../../src/components/admin/AdminAppShell';
// Synthetic presentation fixture only. PHP authorization is covered separately.
import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter } from 'react-router-dom';
import { ThemeProvider } from '../../src/context/ThemeContext';
import PrOfficerInvitations from '../../src/components/admin/PrOfficerInvitations';
import AdminWorkspacePage from '../../src/pages/AdminWorkspacePage';
import '../../src/index.css';
import '../../src/components/admin/AdminUI.css';
const role=new URLSearchParams(location.search).get('role');
function Invite(){const [items,setItems]=useState([]),[revision,setRevision]=useState(0);useEffect(()=>{fetch('/api/admin/staff/').then(r=>r.json()).then(r=>setItems(r.invitations));},[revision]);return <PrOfficerInvitations roles={[{id:5,slug:'pr_officer',name:'PR Officer'}]} invitations={items} onRefresh={()=>setRevision(v=>v+1)}/>;}
createRoot(document.getElementById('root')).render(<MemoryRouter><ThemeProvider><AdminActionProvider><AdminAppShell title={null}><main className="admin-module"><p>ข้อมูลสังเคราะห์สำหรับ QA</p>{role==='pr_officer'?<AdminWorkspacePage/>:<Invite/>}</main></AdminAppShell></AdminActionProvider></ThemeProvider></MemoryRouter>);
