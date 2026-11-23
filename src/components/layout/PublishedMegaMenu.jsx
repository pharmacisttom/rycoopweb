import React from 'react';
import { Link } from 'react-router-dom';
import { Landmark, Award, Users, Shield, Wallet, Calculator, FileText, Newspaper, Megaphone, Heart, GraduationCap, Stethoscope, Download, Globe, Calendar, Receipt, PiggyBank, Banknote, CheckCircle, BookOpen } from 'lucide-react';

const ICONS = {
  landmark:Landmark, award:Award, users:Users, shield:Shield, wallet:Wallet,
  calculator:Calculator, 'file-text':FileText, newspaper:Newspaper,
  megaphone:Megaphone, heart:Heart, graduation:GraduationCap,
  stethoscope:Stethoscope, download:Download, globe:Globe,
  calendar:Calendar, receipt:Receipt, 'piggy-bank':PiggyBank,
  banknote:Banknote, 'check-circle':CheckCircle, 'book-open':BookOpen,
};

/** Render only a validated, explicitly published admin snapshot. Legacy
 * mega-menu JSX remains the fallback until the first publication. */
export default function PublishedMegaMenu({ category, onNavigate }) {
  if (!category?.enabled) return null;
  const groups = [];
  for (const item of category.items || []) {
    if (!item.enabled) continue;
    let group = groups.find(g => g.title === item.group);
    if (!group) {
      group = {title:item.group,items:[]};
      groups.push(group);
    }
    group.items.push(item);
  }
  return <div className="mega-menu-grid">
    <div className="mega-menu-col-main" style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(240px,1fr))',gap:20}}>
      {groups.map(group=><div key={group.title}>
        <div className="mega-menu-category-title"><strong>{group.title}</strong></div>
        <div style={{display:'flex',flexDirection:'column',gap:'0.25rem'}}>
          {group.items.map(item=>{
            const Icon=ICONS[item.icon] || FileText;
            return <Link key={item.id} to={item.path} className="mega-menu-card-item" onClick={onNavigate}>
              <div className="mega-menu-icon-wrap" style={{background:'var(--primary-100)',color:'var(--primary-700)'}}><Icon size={18}/></div>
              <div><div className="mega-menu-item-title">{item.title}</div>
                {item.description && <div className="mega-menu-item-desc">{item.description}</div>}</div>
            </Link>;
          })}
        </div>
      </div>)}
    </div>
  </div>;
}
