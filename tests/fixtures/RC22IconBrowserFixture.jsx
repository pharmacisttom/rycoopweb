import React from 'react';
import {createRoot} from 'react-dom/client';
import {MemoryRouter, Link} from 'react-router-dom';
import {BookOpen, Menu, CheckCircle2} from 'lucide-react';
import BrandIcon from '../../src/components/branding/BrandIcon';
import {AdminSidebarItemIcon} from '../../src/components/admin/AdminSidebarItemIcon';
import PublishedMegaMenu from '../../src/components/layout/PublishedMegaMenu';
import '../../src/components/branding/BrandIcon.css';

const fixtureCategory={id:'about',title:'เกี่ยวกับสหกรณ์',enabled:true,
  items:[{id:'a',group:'ข้อมูลสหกรณ์',title:'ประวัติและวิสัยทัศน์',description:'ตัวอย่างข้อมูลเผยแพร่',path:'/about',icon:'landmark',enabled:true},
         {id:'b',group:'ข้อมูลสหกรณ์',title:'คณะกรรมการ',description:'ข้อมูลคณะกรรมการ',path:'/board_committee',icon:'users',enabled:true}]};
function App(){
  return <MemoryRouter>
    <div data-testid="rc22-shell" style={{maxWidth:1080,margin:'auto',padding:16,fontFamily:'system-ui',color:'#17324f'}}>
      <header><h1>RYCOOPWEB Icon System QA</h1></header>
      <nav aria-label="Public social and authentication">
        <a data-testid="google-signin" href="#google"><BrandIcon brand="google"/>เข้าสู่ระบบด้วย Google</a>
        <a data-testid="facebook-link" href="#facebook"><BrandIcon brand="facebook"/>Facebook</a>
        <a data-testid="line-link" href="#line"><BrandIcon brand="line"/>LINE</a>
      </nav>
      <aside aria-label="Admin modules" style={{display:'flex',flexWrap:'wrap',gap:14,marginTop:20}}>
        <Link to="/admin/manual"><AdminSidebarItemIcon path="/admin/manual"/> คู่มือ</Link>
        <Link to="/admin/mega-menu"><AdminSidebarItemIcon path="/admin/mega-menu"/> Mega Menu</Link>
        <Link to="/admin/members"><AdminSidebarItemIcon path="/admin/members"/> สมาชิก</Link>
      </aside>
      <main>
        <section aria-label="Lucide icon labels" style={{display:'flex',gap:8,margin:'20px 0'}}>
          <button type="button"><Menu size={18} aria-hidden="true"/> เมนู</button>
          <button type="button"><CheckCircle2 size={18} aria-hidden="true"/> ยืนยัน</button>
          <button type="button"><BookOpen size={18} aria-hidden="true"/> คู่มือ</button>
        </section>
        <div data-testid="published-menu" style={{position:'relative',minHeight:280}}>
          <PublishedMegaMenu category={fixtureCategory} onNavigate={()=>{document.documentElement.dataset.menuClicked='1';}}/>
        </div>
      </main>
    </div>
  </MemoryRouter>;
}
createRoot(document.getElementById('root')).render(<App/>);
