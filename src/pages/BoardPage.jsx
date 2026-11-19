import { usePublicInformation } from '../context/PublicContentContext';
import React, { useMemo, useState } from 'react';
import { Building, Landmark } from 'lucide-react';

import SafeImage from '../components/common/SafeImage';
import './BoardPage.css';

function PeopleGrid({ members, type }) {
  const isBoard = type === 'board';

  return (
    <div className={isBoard ? 'board-org-chart' : 'grid-3'}>
      {members.map((member) => {
        const isFeaturedLeader =
          (isBoard && member.position === 'ประธานกรรมการ') ||
          (!isBoard && ['ผู้จัดการ', 'ผู้จัดการสหกรณ์'].includes(member.position));

        return (
          <article
            key={member.id}
            className={isBoard ? `surface-card board-person-card ${isFeaturedLeader ? 'board-person-chair' : ''}` : 'surface-card'}
            style={{
              padding: '2rem',
              textAlign: 'center',
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              ...(isFeaturedLeader && {
                gridColumn: '1 / -1',
                width: 'min(100%, 380px)',
                justifySelf: 'center',
              }),
            }}
          >
          <div style={{
            width: '130px',
            height: '130px',
            borderRadius: '50%',
            overflow: 'hidden',
            border: '4px solid var(--primary-100)',
            boxShadow: 'var(--shadow-md)',
            marginBottom: '1.25rem',
            background: 'var(--bg-subtle)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center'
          }}>
            <SafeImage
              src={member.image}
              alt={`ภาพ ${member.name}`}
              loading="lazy"
              style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'center center' }}
            />
          </div>

          {(isBoard ? member.term : member.department) && (
            <span className={`badge ${isBoard ? 'badge-primary' : 'badge-teal'}`} style={{ marginBottom: '0.5rem' }}>
              {isBoard ? member.term : member.department}
            </span>
          )}

          <h3 style={{ fontSize: '1.15rem', color: 'var(--primary-900)', marginBottom: '0.35rem' }}>
            {member.name}
          </h3>

          <p style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--accent-gold-dark)', marginBottom: '0.75rem' }}>
            {member.position}
          </p>

          {member.workplace && (
            <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
              <Building size={14} aria-hidden="true" />
              <span>{member.workplace}</span>
            </p>
          )}
          </article>
        );
      })}
    </div>
  );
}

export function PeopleDirectoryPage({ badge, title, subtitle, heading, Icon, members, type, children }) {
  return (
    <div className="section">
      <div className="container">
        <div className="section-title-wrap">
          <span className="section-badge">{badge}</span>
          <h1 className="section-title">{title}</h1>
          <p className="section-subtitle">{subtitle}</p>
          <div className="section-line" />
        </div>

        <section aria-labelledby="directory-heading">
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.65rem', marginBottom: '1.5rem' }}>
            <Icon size={25} color={type === 'board' ? 'var(--primary-600)' : 'var(--accent-teal-dark)'} aria-hidden="true" />
            <h2 id="directory-heading" style={{ fontSize: '1.5rem', color: 'var(--primary-900)' }}>{heading}</h2>
          </div>
          <PeopleGrid members={members} type={type} />
        </section>
        {children}
      </div>
    </div>
  );
}

export default function BoardPage() {
  const { COOP_INFO, BOARD_MEMBERS, legacyReference } = usePublicInformation();
  const members = Array.isArray(BOARD_MEMBERS) ? BOARD_MEMBERS.filter(person=>person && typeof person === 'object') : [];
  const boardGroups = Array.isArray(legacyReference?.boardGroups) ? legacyReference.boardGroups.filter(Boolean) : [];
  const publishedSources = legacyReference?.sources?.board;
  const terms = useMemo(() => [...new Set(members.map(person => person.term).filter(Boolean))].sort((a,b) => b.localeCompare(a,'th', {numeric:true})), [members]);
  const [selectedTerm, setSelectedTerm] = useState('ชุดที่ 39');
  const activeTerm = terms.includes(selectedTerm) ? selectedTerm : (terms.includes('ชุดที่ 39') ? 'ชุดที่ 39' : terms[0]);
  const selectedMembers = members.filter(person => (!activeTerm || person.term === activeTerm) && person.status !== 'former');
  const formerMembers = members.filter(person => person.term === activeTerm && person.status === 'former');
  const activeGroups = boardGroups.filter(group => {
    if (group.term) return group.term === activeTerm;
    // Older groups have no explicit term; inherit it from their original members.
    const first = members.find(person => person.id === Number(group.members?.[0]?.[0]));
    return first?.term === activeTerm;
  });
  return (
    <PeopleDirectoryPage
      badge="คณะผู้บริหาร"
      title={activeTerm ? `คณะกรรมการดำเนินงาน ${activeTerm}` : 'คณะกรรมการดำเนินงาน'}
      subtitle={`ทำเนียบคณะกรรมการดำเนินการ ${COOP_INFO.nameTh}`}
      heading={activeTerm ? `คณะกรรมการดำเนินงาน ${activeTerm}` : 'คณะกรรมการดำเนินงาน'}
      Icon={Landmark}
      members={selectedMembers}
      type="board"
    >
      {terms.length > 0 && <nav aria-label="เลือกชุดคณะกรรมการ" className="board-term-switcher">
        {terms.map(term=><button type="button" key={term} className={term===activeTerm?'board-term-active':''}
          aria-pressed={term===activeTerm} onClick={()=>setSelectedTerm(term)}>{term}</button>)}
      </nav>}
      {!terms.length && <p className="board-term-empty" role="status">ยังไม่มีรายชื่อคณะกรรมการที่เผยแพร่ผ่านระบบหลังบ้าน</p>}
      {!!activeTerm && selectedMembers.length === 0 && <p className="board-term-empty" role="status">ยังไม่มีรายชื่อกรรมการที่ดำรงตำแหน่งใน {activeTerm} ที่เผยแพร่</p>}
      {typeof publishedSources?.url==='string' && /^https:\/\//i.test(publishedSources.url) && <p style={{margin:'1.5rem 0',color:'var(--text-muted)'}}>แหล่งข้อมูลที่เผยแพร่: <a href={publishedSources.url} target="_blank" rel="noopener noreferrer">เอกสารอ้างอิงสหกรณ์</a>{publishedSources.checkedOn && <> · ตรวจเทียบ {publishedSources.checkedOn}</>}</p>}
      {activeGroups.map(group => (
        <section key={group.title} className="surface-card" style={{ padding: '1.5rem', marginBottom: '1rem' }}>
          <h2>{group.title}</h2>
          <div className="board-subcommittee-grid">{(Array.isArray(group.members) ? group.members : []).filter(([id]) => selectedMembers.some(member => member.id === Number(id))).map(([id, position]) => {
            const person=selectedMembers.find(member=>member.id===Number(id));
            return <article key={id} className="board-subcommittee-person">
              <div className="board-subcommittee-photo"><SafeImage src={person?.image} alt={`ภาพ ${person?.name||''}`} loading="lazy"/></div>
              <div><strong>{person?.name}</strong><span>{position}</span></div>
            </article>;
          })}</div>
        </section>
      ))}
      {formerMembers.length > 0 && (
        <section className="surface-card" style={{padding:'1.5rem',marginBottom:'1rem'}}>
          <h2>ทำเนียบกรรมการพ้นตำแหน่ง {activeTerm}</h2>
          <div className="board-subcommittee-grid">{formerMembers.map(person=><article key={person.id} className="board-subcommittee-person"><div className="board-subcommittee-photo"><SafeImage src={person.image} alt={`ภาพ ${person.name}`} loading="lazy"/></div><div><strong>{person.name}</strong><span>{person.position} · พ้นตำแหน่ง</span></div></article>)}</div>
        </section>
      )}
      {[["ที่ปรึกษาสหกรณ์", legacyReference?.advisors || []], ["คณะผู้ตรวจสอบกิจการสหกรณ์", legacyReference?.auditors || []]].map(([title, people]) => (
        <section key={title} className="surface-card" style={{ padding: '1.5rem', marginBottom: '1rem' }}>
          <h2>{title}</h2>
          <ul>{(Array.isArray(people) ? people : []).map((person,index) => <li key={index}>{Array.isArray(person) ? person.join(' — ') : (person?.name || '') + ' — ' + (person?.position || '')}</li>)}</ul>
        </section>
      ))}
    </PeopleDirectoryPage>
  );
}
