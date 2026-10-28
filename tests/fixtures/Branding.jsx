// Synthetic presentation fixture only; no protected route or API authentication is bypassed.
import React from 'react';
import { createRoot } from 'react-dom/client';
import CoopBrandLogo from '../../src/components/branding/CoopBrandLogo';
const dark = new URLSearchParams(location.search).get('theme') === 'dark';
document.documentElement.dataset.theme = dark ? 'dark' : 'light';
document.body.style.cssText = `margin:0;background:${dark?'#0b1120':'#f5f7fb'};color:${dark?'white':'#172033'};font-family:Sarabun,sans-serif`;
createRoot(document.getElementById('root')).render(<main>
  <header style={{background:'#102443',padding:20}}><CoopBrandLogo tone="dark" showEstablished /></header>
  <section style={{padding:20}}><h1>ตรวจตราสัญลักษณ์ — ข้อมูลทดสอบ</h1>
    <h2>Public / Mobile</h2><CoopBrandLogo variant="compact" showEnglish />
    <h2>Member</h2><CoopBrandLogo variant="mobile" />
    <h2>Admin Sidebar</h2><aside style={{width:260,maxWidth:'100%',boxSizing:'border-box',padding:16,background:'#102443'}}><CoopBrandLogo variant="sidebar" tone="dark" showEstablished /></aside>
    <h2>MFA / Login</h2><CoopBrandLogo variant="compact" showEstablished />
  </section>
</main>);
