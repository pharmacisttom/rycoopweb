import { usePublicInformation } from '../context/PublicContentContext';
import React from 'react';
import { UsersRound } from 'lucide-react';
import { PeopleDirectoryPage } from './BoardPage';


export default function BoardOfficerPage() {
  const { COOP_INFO, STAFF_MEMBERS, legacyReference } = usePublicInformation();
  return (
    <PeopleDirectoryPage
      badge="ฝ่ายจัดการ"
      title="ฝ่ายจัดการและเจ้าหน้าที่"
      subtitle={`รายชื่อฝ่ายจัดการและเจ้าหน้าที่ ${COOP_INFO.nameTh}`}
      heading="ฝ่ายจัดการและเจ้าหน้าที่"
      Icon={UsersRound}
      members={STAFF_MEMBERS}
      type="staff"
    >
      <p style={{ marginTop: '1.5rem' }}>ข้อมูลอ้างอิงจาก <a href={legacyReference.sources.staff.url}>เว็บไซต์เดิมของสหกรณ์</a> ตรวจเทียบ {legacyReference.sources.staff.checkedOn}</p>
    </PeopleDirectoryPage>
  );
}
