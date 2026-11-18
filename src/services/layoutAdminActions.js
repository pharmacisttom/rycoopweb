import alertService from './alertService';

// Use the approved SweetAlert flow consistently, including automated browsers.
export function confirmLayoutRemoval(title, warning = 'ลบจากร่างเท่านั้น ประวัติที่เผยแพร่ยังคงอยู่') {
  return alertService.confirm({
    title: `ยืนยันการลบ ${title}`,
    text: warning,
    confirmButtonText: 'ยืนยันการลบ',
    isDanger: true,
    icon: 'warning',
  });
}
