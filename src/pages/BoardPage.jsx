import React from 'react';
import { Building, Landmark } from 'lucide-react';
import { BOARD_MEMBERS, COOP_INFO } from '../data/mockData';
import SafeImage from '../components/common/SafeImage';

function PeopleGrid({ members, type }) {
  const isBoard = type === 'board';

  return (
    <div className="grid-3">
      {members.map((member) => {
        const isFeaturedLeader =
          (isBoard && member.position === 'ประธานกรรมการ') ||
          (!isBoard && member.position === 'ผู้จัดการสหกรณ์');

        return (
          <article
            key={member.id}
            className="surface-card"
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

export function PeopleDirectoryPage({ badge, title, subtitle, heading, Icon, members, type }) {
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
      </div>
    </div>
  );
}

export default function BoardPage() {
  return (
    <PeopleDirectoryPage
      badge="คณะผู้บริหาร"
      title="คณะกรรมการดำเนินการ"
      subtitle={`ทำเนียบคณะกรรมการดำเนินการ ${COOP_INFO.nameTh}`}
      heading="คณะกรรมการดำเนินการ"
      Icon={Landmark}
      members={BOARD_MEMBERS}
      type="board"
    />
  );
}
