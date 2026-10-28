// Real presentation components, synthetic identity, isolated fixture URL (not an admin route).
import React from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter } from 'react-router-dom';
import { ThemeProvider } from '../../src/context/ThemeContext';
import Navbar from '../../src/components/layout/Navbar';
import Footer from '../../src/components/layout/Footer';
import MfaAuthShell from '../../src/components/auth/MfaAuthShell';
import AdminControlCenterShell from '../../src/components/admin/AdminControlCenterShell';
import AdminMemberShell from '../../src/components/member/AdminMemberShell';
import DashboardHeader from '../../src/components/dashboard/DashboardHeader';
import LoginPage from '../../src/pages/LoginPage';
import '../../src/index.css';
import '../../src/styles/mobile-responsive.css';
import '../../src/pages/MfaPage.css';
const parameters=new URLSearchParams(location.search);const kind=parameters.get('kind')||'navbar';
window.__fixtureKind=kind;localStorage.setItem('coop_theme',parameters.get('theme')||'light');
const contents={navbar:<Navbar/>,footer:<Footer/>,mfa:<MfaAuthShell/>,admin:<AdminControlCenterShell title="ตรวจตราสัญลักษณ์ — ข้อมูลทดสอบ"><p>Presentation fixture only</p></AdminControlCenterShell>,
  member:<AdminMemberShell title="ระบบสมาชิก — ข้อมูลทดสอบ"/>,dashboard:<DashboardHeader user={{name:'Synthetic member',role:'member'}}/>,login:<LoginPage/>};
createRoot(document.getElementById('root')).render(<MemoryRouter initialEntries={['/__component_fixture']}><ThemeProvider>{contents[kind]}</ThemeProvider></MemoryRouter>);
