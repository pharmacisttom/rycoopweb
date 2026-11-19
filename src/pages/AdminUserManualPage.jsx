import React from 'react';
import {Link} from 'react-router-dom';
import AdminControlCenterShell from '../components/admin/AdminControlCenterShell';
import './AdminUserManualPage.css';

const groups = [
 {title:'เริ่มต้นใช้งานและการนำทาง',items:[
  ['เข้าสู่ระบบผู้ดูแล','/admin/login','เข้าสู่ระบบตามบัญชีที่ได้รับอนุญาต ผ่าน MFA เมื่อระบบร้องขอ และออกจากระบบเมื่อเลิกงาน'],
  ['Dashboard และสถิติ','/admin/dashboard','ดูภาพรวมการดำเนินงาน ตัวชี้วัด การใช้งาน และการเข้าชมเว็บไซต์ตามสิทธิ์'],
  ['พื้นที่ทำงานเจ้าหน้าที่','/admin/workspace','เลือกงานหรือรายการรอดำเนินการตามบทบาทที่ได้รับ'] ]},
 {title:'เว็บไซต์ ข่าวสาร และโครงสร้างองค์กร',items:[
  ['ข่าวประชาสัมพันธ์','/admin/content?tab=news','เพิ่มข่าว กรอกหัวข้อ/รายละเอียด แนบภาพ ตรวจตัวอย่าง บันทึกร่าง แล้วส่งตรวจหรือเผยแพร่ตามสิทธิ์'],
  ['คำถามที่พบบ่อย','/admin/content?tab=faqs','เพิ่มคำถามและคำตอบ ตรวจความถูกต้องและบันทึกร่าง'],
  ['ข้อมูลสหกรณ์และบุคลากร','/admin/public-content','เลือกหมวด แก้ไขข้อมูล แนบแหล่งอ้างอิงและวันที่มีผล ก่อนบันทึกร่าง'],
  ['คณะกรรมการชุดที่ 38/39','/admin/public-content?section=boards','แยกชุดละ 15 คน ตรวจตำแหน่ง กรรมการซ้ำ และไม่แก้ทับชุดเดิม'],
  ['คณะอนุกรรมการ','/admin/public-content?section=groups','เลือกสมาชิกจากชุดกรรมการที่ตรงกัน ตรวจตำแหน่งและความสัมพันธ์ก่อนเผยแพร่'],
  ['จัดการ Layout หน้าแรก','/admin/layout','เปิด/ปิดองค์ประกอบหน้าแรก เพิ่มหัวข้อหลัก/ย่อย กำหนดสีและอัลบั้ม แล้วบันทึกร่างและใช้ MFA เผยแพร่'],
  ['แก้ไขข้อความเมนูเว็บไซต์','/admin/navigation-copy','ปรับข้อความนำทางตามสิทธิ์ แล้วตรวจหน้าสาธารณะ'],
  ['คลังรูปภาพ','/admin/media','อัปโหลดภาพ ใช้ชนิดไฟล์ที่อนุญาต ตั้งชื่อและคำอธิบายภาพ แล้วเชื่อมกับเนื้อหา'],
  ['ศูนย์เอกสาร','/admin/documents','จัดหมวดและเผยแพร่เอกสารตามสิทธิ์และขั้นตอนอนุมัติ'] ]},
 {title:'สมาชิก การเงิน และข้อมูลบริการ',items:[
  ['ภาพรวมและค้นหาสมาชิก','/admin/members','ค้นข้อมูล ดูรายละเอียด และแก้ไขเฉพาะผู้มีสิทธิ์ โดยระวังข้อมูลส่วนบุคคล'],
  ['นำเข้าข้อมูลปันผล','/admin/dividends/import','ตรวจรูปแบบไฟล์ ตรวจความครบถ้วน ทดลองนำเข้าและกระทบยอดก่อนยืนยัน'],
  ['ตรวจสุขภาพข้อมูลสมาชิก','/admin/members/health','ตรวจความผิดปกติและสถานะความพร้อมของข้อมูล'],
  ['ตรวจสอบข้อมูล LED','/admin/led/dashboard','ตรวจตามสิทธิ์ บันทึกหลักฐานและไม่ตัดสินจากผลการค้นหาเพียงแหล่งเดียว'],
  ['เงินฝากและอัตราดอกเบี้ย','/admin/public-content','เลือกหมวดอัตราเงินฝาก/สินเชื่อ ระบุวันที่มีผลและเอกสารประกาศ'],
  ['รายงานการเงิน','/admin/reports?category=finance','เรียกรายงานตามขอบเขตสิทธิ์ ตรวจวันที่และเกณฑ์ก่อนส่งต่อ'] ]},
 {title:'ผู้ใช้ ความปลอดภัย การอนุมัติ และระบบ',items:[
  ['ผู้ใช้ / Roles / Permissions','/admin/access','เพิ่มหรือเปลี่ยนบทบาทตามหลัก least privilege ตรวจ MFA และผู้อนุมัติ'],
  ['ศูนย์ความปลอดภัย','/admin/security','ตรวจเหตุการณ์เข้าสู่ระบบผิดปกติและการแจ้งเตือน'],
  ['Audit Trail','/admin/audit-logs','สืบค้นการเปลี่ยนแปลงด้วยผู้ใช้ เวลา รายการ และผลการดำเนินการ'],
  ['สำรองและกู้คืน','/admin/backups','ตรวจสถานะและความสมบูรณ์ของ Backup การ Restore ต้องผ่านกระบวนการที่ได้รับอนุญาต'],
  ['สถานะระบบ','/admin/health','ดูสุขภาพ Application/Database/Jobs และส่งต่อปัญหาให้ผู้ดูแล'],
  ['คำขอ Maker / Checker','/admin/approvals','ผู้ทำรายการและผู้อนุมัติต้องแยกกัน ตรวจหลักฐานก่อนอนุมัติ'],
  ['ตั้งค่าบัญชี','/admin/settings?tab=profile','จัดการข้อมูลบัญชีและการยืนยันตัวตนโดยไม่แชร์รหัสผ่านหรือ TOTP'] ]}
];
export default function AdminUserManualPage(){
 return <AdminControlCenterShell title="คู่มือการใช้งานระบบหลังบ้าน" subtitle="RYCOOPWEB • คู่มือเจ้าหน้าที่และผู้ดูแลระบบ">
  <article className="admin-module admin-manual" aria-label="คู่มือการใช้งานระบบหลังบ้าน">
   <div className="admin-manual-intro">
    <h1>คู่มือการใช้งาน RYCOOPWEB Admin</h1>
    <p>คู่มืออ้างอิงเมนูระบบใน Source Code ปัจจุบัน การเข้าถึงแต่ละหน้าแตกต่างกันตาม Role และ Permission บางฟังก์ชันใน RC02/RC03 ยังอยู่ระหว่างตรวจรับ TEST</p>
    <div className="admin-manual-actions">
     <button type="button" onClick={()=>window.print()}>พิมพ์ / บันทึก PDF</button>
     <a href="#manual-index">ดูสารบัญ</a>
    </div>
   </div>
   <nav id="manual-index" className="admin-manual-index" aria-label="สารบัญคู่มือ">
    <h2>สารบัญ</h2>
    {groups.map((g,i)=><a key={i} href={'#manual-'+i}>{i+1}. {g.title}</a>)}
    <a href="#manual-guidelines">แนวปฏิบัติการบันทึกและเผยแพร่ข้อมูล</a>
    <a href="#manual-problems">แก้ปัญหาเบื้องต้น</a>
   </nav>
   {groups.map((g,i)=><section key={i} id={'manual-'+i} className="admin-manual-section">
     <h2>{i+1}. {g.title}</h2>
     {g.items.map(([name,url,description])=><div className="admin-manual-item" key={url+name}>
       <h3>{name}</h3><p>{description}</p><Link to={url}>เปิดหน้าระบบ</Link>
     </div>)}
   </section>)}
   <section id="manual-guidelines" className="admin-manual-section">
    <h2>แนวปฏิบัติการบันทึกและเผยแพร่</h2>
    <ol><li>ตรวจว่ากำลังอยู่ใน TEST หรือ Production</li><li>ตรวจความถูกต้องและแหล่งอ้างอิงของข้อมูลก่อนบันทึก</li><li>บันทึกร่างและตรวจตัวอย่าง/ประวัติการเปลี่ยนแปลง</li><li>อนุมัติหรือยืนยัน MFA เฉพาะเมื่อระบบร้องขอ และเป็นผู้มีสิทธิ์</li><li>ตรวจหน้าสาธารณะหลังเผยแพร่และบันทึกหลักฐานการตรวจรับ</li></ol>
    <p>ห้ามแนบรหัสผ่าน TOTP Session Cookie หรือข้อมูลสมาชิกลงในภาพรายงานและ Ticket ที่เปิดเผยทั่วไป</p>
   </section>
   <section id="manual-problems" className="admin-manual-section">
    <h2>ปัญหาที่พบบ่อย</h2>
    <dl>
      <dt>เซสชันหมดอายุ</dt><dd>เข้าสู่ระบบใหม่โดยไม่พยายามข้าม MFA</dd>
      <dt>เมนูไม่ปรากฏ</dt><dd>ตรวจสิทธิ์ของบัญชีและค้นเมนู Sidebar หรือปุ่ม “ระบบทั้งหมด”</dd>
      <dt>บันทึกร่างไม่ได้</dt><dd>ตรวจข้อมูลจำเป็น วันที่มีผล Permission และข้อความแจ้งเตือน</dd>
      <dt>หน้าเว็บไม่เปลี่ยนหลังบันทึก</dt><dd>การบันทึกร่างยังไม่ใช่การเผยแพร่ ต้องตรวจสถานะอนุมัติและวันที่มีผล</dd>
      <dt>รูปภาพไม่แสดง</dt><dd>ตรวจชนิดไฟล์ ขนาด สิทธิ์ Media Library และ URL รูป</dd>
      <dt>CSS ซ้อนหรือหน้าแสดงผิด</dt><dd>บันทึก URL เวลา เบราว์เซอร์ ภาพหน้าจอ และแจ้งผู้ดูแลเพื่อตรวจ Asset/Console</dd>
    </dl>
   </section>
   <p className="admin-manual-footnote">เอกสารในระบบนี้เป็นคู่มือใช้งานเชิงปฏิบัติ ไม่ใช่หลักฐานว่าทุกโมดูลผ่าน UAT แล้ว | ปรับปรุง 18 พฤศจิกายน 2569</p>
  </article>
 </AdminControlCenterShell>;
}
