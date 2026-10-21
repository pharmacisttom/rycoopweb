import React from 'react';
import { UsersRound } from 'lucide-react';
import { PeopleDirectoryPage } from './BoardPage';
import reference from '../data/legacyReference.json';
import { COOP_INFO, STAFF_MEMBERS } from '../data/mockData';

export default function BoardOfficerPage() {
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
      <p style={{ marginTop: '1.5rem' }}>ข้อมูลอ้างอิงจาก <a href={reference.sources.staff.url}>เว็บไซต์เดิมของสหกรณ์</a> ตรวจเทียบ {reference.sources.staff.checkedOn}</p>
    </PeopleDirectoryPage>
  );
}
