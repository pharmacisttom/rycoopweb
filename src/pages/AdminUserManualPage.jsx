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
  ['ประกาศสำคัญ','/admin/content?tab=news','ตรวจหมวด ข่าวสาร/ประกาศ และสิทธิ์เผยแพร่ บันทึกร่างก่อนประกาศตามกระบวนการ'],
  ['คำถามที่พบบ่อย','/admin/content?tab=faqs','เพิ่มคำถามและคำตอบ ตรวจความถูกต้องและบันทึกร่าง'],
  ['ข้อมูลสหกรณ์และบุคลากร','/admin/public-content','เลือกหมวด แก้ไขข้อมูล แนบแหล่งอ้างอิงและวันที่มีผล ก่อนบันทึกร่าง'],
  ['คณะกรรมการชุดที่ 38/39','/admin/public-content?section=boards','แยกชุดละ 15 คน ตรวจตำแหน่ง กรรมการซ้ำ และไม่แก้ทับชุดเดิม'],
  ['คณะอนุกรรมการ','/admin/public-content?section=groups','เลือกสมาชิกจากชุดกรรมการที่ตรงกัน ตรวจตำแหน่งและความสัมพันธ์ก่อนเผยแพร่'],
  ['จัดการ Layout หน้าแรก','/admin/layout','เปิด/ปิดองค์ประกอบหน้าแรก เพิ่มหัวข้อหลัก/ย่อย กำหนดสีและอัลบั้ม แล้วบันทึกร่างและใช้ MFA เผยแพร่'],
  ['แก้ไขข้อความเมนูเว็บไซต์','/admin/navigation-copy','ปรับข้อความนำทางตามสิทธิ์ แล้วตรวจหน้าสาธารณะ'],
  ['แคมเปญ Popup','/admin/popups','เพิ่มหรือแก้ไข Popup ตามสิทธิ์ ตรวจภาพ ลำดับ หน้าแสดงผล การเปิด/ปิด และ Preview ก่อนเผยแพร่'],
  ['คลังรูปภาพ','/admin/media','อัปโหลดภาพ ใช้ชนิดไฟล์ที่อนุญาต ตั้งชื่อและคำอธิบายภาพ แล้วเชื่อมกับเนื้อหา'],
  ['ศูนย์เอกสาร','/admin/documents','จัดหมวดและเผยแพร่เอกสารตามสิทธิ์และขั้นตอนอนุมัติ'] ]},
 {title:'สมาชิก การเงิน และข้อมูลบริการ',items:[
  ['จัดการสวัสดิการ','/admin/welfare','ตรวจรายการและสถานะ เปิด/ปิดข้อมูลตามสิทธิ์ โดยฟังก์ชันที่ยังอยู่ระหว่าง UAT ให้ทดสอบบน TEST เท่านั้น'],
  ['ภาพรวมและค้นหาสมาชิก','/admin/members','ค้นข้อมูล ดูรายละเอียด และแก้ไขเฉพาะผู้มีสิทธิ์ โดยระวังข้อมูลส่วนบุคคล'],
  ['นำเข้าข้อมูลปันผล','/admin/dividends/import','ตรวจรูปแบบไฟล์ ตรวจความครบถ้วน ทดลองนำเข้าและกระทบยอดก่อนยืนยัน'],
  ['ตรวจสุขภาพข้อมูลสมาชิก','/admin/members/health','ตรวจความผิดปกติและสถานะความพร้อมของข้อมูล'],
  ['ตรวจสอบข้อมูล LED','/admin/led/dashboard','ตรวจตามสิทธิ์ บันทึกหลักฐานและไม่ตัดสินจากผลการค้นหาเพียงแหล่งเดียว'],
  ['เงินฝากและอัตราดอกเบี้ย','/admin/public-content','เลือกหมวดอัตราเงินฝาก/สินเชื่อ ระบุวันที่มีผลและเอกสารประกาศ'],
  ['รายงานการเงิน','/admin/reports?category=finance','เรียกรายงานตามขอบเขตสิทธิ์ ตรวจวันที่และเกณฑ์ก่อนส่งต่อ'] ]},
 {title:'ผู้ใช้ ความปลอดภัย การอนุมัติ และระบบ',items:[
  ['บัญชีเจ้าหน้าที่และคำเชิญ','/admin/users','ตรวจสิทธิ์ Super Admin เพิ่ม/เพิกถอนคำเชิญ ตรวจสถานะ Google Link และ MFA ก่อนเปลี่ยนบทบาท'],
  ['ผู้ใช้ / Roles / Permissions','/admin/access','เพิ่มหรือเปลี่ยนบทบาทตามหลัก least privilege ตรวจ MFA และผู้อนุมัติ'],
  ['ศูนย์ความปลอดภัย','/admin/security','ตรวจเหตุการณ์เข้าสู่ระบบผิดปกติและการแจ้งเตือน'],
  ['Audit Trail','/admin/audit-logs','สืบค้นการเปลี่ยนแปลงด้วยผู้ใช้ เวลา รายการ และผลการดำเนินการ'],
  ['สำรองและกู้คืน','/admin/backups','ตรวจสถานะและความสมบูรณ์ของ Backup การ Restore ต้องผ่านกระบวนการที่ได้รับอนุญาต'],
  ['สถานะระบบ','/admin/health','ดูสุขภาพ Application/Database/Jobs และส่งต่อปัญหาให้ผู้ดูแล'],
  ['คำขอ Maker / Checker','/admin/approvals','ผู้ทำรายการและผู้อนุมัติต้องแยกกัน ตรวจหลักฐานก่อนอนุมัติ'],
  ['ตั้งค่าเนื้อหาระบบ','/admin/settings/content','แก้เฉพาะรายการที่ระบบอนุญาต ตรวจเวอร์ชันล่าสุดและยืนยัน Step-up MFA สำหรับรายการสำคัญ'],
  ['ตั้งค่าบัญชี','/admin/settings?tab=profile','จัดการข้อมูลบัญชีและการยืนยันตัวตนโดยไม่แชร์รหัสผ่านหรือ TOTP'] ]}
];
export default function AdminUserManualPage(){
 return <AdminControlCenterShell title="คู่มือการใช้งานระบบหลังบ้าน" subtitle="RYCOOPWEB • คู่มือเจ้าหน้าที่และผู้ดูแลระบบ">
  <article className="admin-module admin-manual" aria-label="คู่มือการใช้งานระบบหลังบ้าน">
   <div className="admin-manual-intro">
    <h1>คู่มือการใช้งาน RYCOOPWEB Admin</h1>
    <p>คู่มือปฏิบัติงาน RYCOOPWEB Admin • ฉบับปรับปรุง 21 พฤศจิกายน 2569 สำหรับ Google Login/MFA, Cookie Consent, SweetAlert2 และการจัดการข้อมูลแต่ละโมดูล โดยสิทธิ์ขึ้นกับบทบาทและ Permission ของผู้ใช้ ทั้งนี้ผล CI ไม่เท่ากับการรับรอง CRUD กับ Production ทุกโมดูล</p>
    <div className="admin-manual-actions">
     <button type="button" onClick={()=>window.print()}>พิมพ์ / บันทึก PDF</button>
     <a href="#manual-index">ดูสารบัญ</a>
    </div>
   </div>
   <nav id="manual-index" className="admin-manual-index" aria-label="สารบัญคู่มือ">
    <h2>สารบัญ</h2>
    {groups.map((g,i)=><a key={i} href={'#manual-'+i}>{i+1}. {g.title}</a>)}
    <a href="#manual-crud">ขั้นตอน CRUD และการทดสอบรายการ</a>
    <a href="#manual-cookie">Cookie Consent และ SweetAlert2</a>
    <a href="#manual-guidelines">แนวปฏิบัติการบันทึกและเผยแพร่ข้อมูล</a>
    <a href="#manual-problems">แก้ปัญหาเบื้องต้น</a>
   </nav>
   {groups.map((g,i)=><section key={i} id={'manual-'+i} className="admin-manual-section">
     <h2>{i+1}. {g.title}</h2>
     {g.items.map(([name,url,description])=><div className="admin-manual-item" key={url+name}>
       <h3>{name}</h3><p>{description}</p><Link to={url}>เปิดหน้าระบบ</Link>
     </div>)}
   </section>)}
   <section id="manual-crud" className="admin-manual-section">
    <h2>การจัดการข้อมูล (CRUD): เพิ่ม ดู แก้ไข และปิด/ลบรายการ</h2>
    <ol>
     <li><strong>Create:</strong> เปิดเมนูตามสิทธิ์ กดเพิ่มข้อมูล กรอกฟิลด์ที่จำเป็น ตรวจเอกสาร/รูปภาพและบันทึกร่าง ตรวจข้อความตอบกลับ</li>
     <li><strong>Read:</strong> ค้นหารายการที่สร้าง ตรวจรายละเอียด สถานะ เจ้าของข้อมูล วันมีผล และสิทธิ์การมองเห็น</li>
     <li><strong>Update:</strong> แก้จากข้อมูลล่าสุด ตรวจ Version/Conflict (409) และไม่บันทึกทับการแก้ของผู้อื่น</li>
     <li><strong>Delete/Disable:</strong> ตรวจผลกระทบและสิทธิ์ ยืนยันด้วย Dialog; การปิดใช้งาน/Soft Delete ไม่ใช่การลบถาวร</li>
     <li><strong>Publish/Approve:</strong> เป็น Workflow แยกจาก Save; ตรวจ Preview, Maker–Checker และ Step-up MFA ตามที่ระบบกำหนด</li>
     <li><strong>Verify:</strong> โหลดรายการซ้ำ ตรวจ Audit Trail และหน้าสาธารณะเมื่อมีการเผยแพร่; เมื่อผิดพลาดให้บันทึกรหัส HTTP กับเวลาโดยไม่เปิดเผยข้อมูลลับ</li>
    </ol>
    <p>ไม่ควรทดสอบ Create/Update/Delete ด้วยข้อมูลจริงของสมาชิกหรือการเงินบน Production การตรวจ CRUD ให้ใช้ฐานข้อมูล TEST ที่แยกจาก Production หรือ Fixture จำลองเท่านั้น ฟังก์ชันแบบอ่านอย่างเดียว เช่น Reports/Audit Logs ไม่จำเป็นต้องมีปุ่มเพิ่ม/ลบ</p>
   </section>
   <section id="manual-cookie" className="admin-manual-section">
    <h2>Cookie Consent และ SweetAlert2</h2>
    <ol>
     <li>ผู้ที่ยังไม่ได้บันทึกการเลือกจะเห็น Cookie Dialog ที่หน้าเว็บไซต์หรือหน้า CMS แต่จะไม่ขัดขวางหน้า Login และ MFA</li>
     <li>เลือกยอมรับหรือปฏิเสธคุกกี้ไม่จำเป็น ระบบจะบันทึกผ่าน API พร้อม CSRF ก่อนปิด Dialog; หากบันทึกไม่สำเร็จให้ลองใหม่</li>
     <li>เมื่อเคยเลือกแล้ว ให้ใช้ปุ่ม “ตั้งค่าคุกกี้” เพื่อกลับมาเปลี่ยนการเลือก โดยไม่ต้องล้างข้อมูลผู้ใช้</li>
     <li>SweetAlert2 Toast จะแสดงเมื่อมีเหตุการณ์แจ้งเตือน ไม่ได้เปิดค้างตั้งแต่โหลดหน้า ส่วนคำสั่งสำคัญอาจใช้ Dialog เดิมภายใน Native Modal เพื่อไม่ให้ Layer บังปุ่ม</li>
     <li>การยืนยัน MFA/Step-up เป็นระบบความปลอดภัยแยกต่างหาก ห้ามข้ามหรือใช้รหัสของผู้อื่น</li>
    </ol>
   </section>
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
      <dt>Cookie Dialog ไม่ปรากฏ</dt><dd>ตรวจว่าเคยเลือกแล้วหรือไม่ และใช้ปุ่ม “ตั้งค่าคุกกี้”; ทดสอบผู้ใช้ใหม่ในหน้าต่างส่วนตัวโดยไม่ใช้ Session เจ้าหน้าที่จริง</dd>
      <dt>SweetAlert2 ไม่ปรากฏ</dt><dd>ให้ทำรายการที่มีข้อความแจ้งเตือนหรือยืนยันก่อน; หาก Dialog ถูกบัง ให้เก็บภาพหน้าจอและแจ้ง URL/Browser ไม่ต้องปรับ z-index เอง</dd>
      <dt>CRUD บันทึกไม่ได้ หรือพบ 401/403/409/419</dt><dd>401 ตรวจ Session; 403 ตรวจสิทธิ์/Step-up; 409 โหลด Version ล่าสุด; 419 ขอ CSRF ใหม่โดยรีเฟรชหน้าและทำรายการใหม่ ห้ามข้าม Security Gate</dd>
      <dt>CSS ซ้อนหรือหน้าแสดงผิด</dt><dd>บันทึก URL เวลา เบราว์เซอร์ ภาพหน้าจอ และแจ้งผู้ดูแลเพื่อตรวจ Asset/Console</dd>
    </dl>
   </section>
   <p className="admin-manual-footnote">เอกสารในระบบนี้เป็นคู่มือใช้งานเชิงปฏิบัติ ไม่ใช่หลักฐานว่าทุกโมดูลผ่าน UAT แล้ว | ปรับปรุง 21 พฤศจิกายน 2569</p>
  </article>
 </AdminControlCenterShell>;
}
