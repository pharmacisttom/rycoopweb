/**
 * Centralized SweetAlert2 Notification and Confirmation Service for RYCOOPWEB Admin.
 *
 * Enforces:
 * - Brand styling: Navy (#0f2d59), Deep Blue (#0284c7), Crisp White (#ffffff), Slate Grey (#64748b)
 * - Safe text rendering (no HTML injection)
 * - Accessible keyboard navigation & focus restoration
 * - Confirmation workflows for critical CRUD operations (save, delete, suspend, activate)
 */
import Swal from 'sweetalert2';

// Primary brand palette custom styling classes
const customClass = {
  popup: 'rycoop-swal-popup',
  title: 'rycoop-swal-title',
  htmlContainer: 'rycoop-swal-html',
  confirmButton: 'rycoop-swal-confirm-btn',
  cancelButton: 'rycoop-swal-cancel-btn',
  denyButton: 'rycoop-swal-deny-btn',
};

// Base configured SweetAlert2 instance
const AdminSwal = Swal.mixin({
  buttonsStyling: true,
  reverseButtons: true,
  focusCancel: true,
  confirmButtonColor: '#0f2d59',
  cancelButtonColor: '#64748b',
  denyButtonColor: '#dc2626',
});

// Toast configured instance for non-intrusive status updates
const ToastSwal = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3500,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer);
    toast.addEventListener('mouseleave', Swal.resumeTimer);
  },
});

export const alertService = {
  /**
   * Show success dialog after confirmed server response
   */
  async success(title, text = '') {
    return AdminSwal.fire({
      icon: 'success',
      title: title || 'ดำเนินการสำเร็จ',
      text,
      confirmButtonText: 'ตกลง',
      confirmButtonColor: '#0284c7',
    });
  },

  /**
   * Show error dialog with optional technical details/reference
   */
  async error(title, text = '', reference = null) {
    const fullText = reference ? `${text}\n(รหัสอ้างอิง: ${reference})` : text;
    return AdminSwal.fire({
      icon: 'error',
      title: title || 'เกิดข้อผิดพลาด',
      text: fullText,
      confirmButtonText: 'รับทราบ',
      confirmButtonColor: '#dc2626',
    });
  },

  /**
   * Show warning dialog
   */
  async warning(title, text = '') {
    return AdminSwal.fire({
      icon: 'warning',
      title: title || 'แจ้งเตือน',
      text,
      confirmButtonText: 'รับทราบ',
      confirmButtonColor: '#f59e0b',
    });
  },

  /**
   * Show validation errors in bullet points
   */
  async validationError(errors, title = 'ข้อมูลที่กรอกไม่ถูกต้อง') {
    let message = '';
    if (typeof errors === 'string') {
      message = errors;
    } else if (Array.isArray(errors)) {
      message = errors.join('\n');
    } else if (typeof errors === 'object' && errors !== null) {
      const messages = [];
      Object.entries(errors).forEach(([field, errList]) => {
        if (Array.isArray(errList)) {
          messages.push(...errList);
        } else if (typeof errList === 'string') {
          messages.push(errList);
        }
      });
      message = messages.join('\n');
    }

    return AdminSwal.fire({
      icon: 'warning',
      title,
      text: message || 'กรุณาตรวจสอบข้อมูลในฟอร์มอีกครั้ง',
      confirmButtonText: 'แก้ไขข้อมูล',
      confirmButtonColor: '#0f2d59',
    });
  },

  /**
   * Confirm general action with custom title and labels
   */
  async confirm({
    title = 'ยืนยันการดำเนินการ',
    text = 'ต้องการดำเนินการขั้นตอนนี้ต่อไปหรือไม่?',
    confirmButtonText = 'ยืนยัน',
    cancelButtonText = 'ยกเลิก',
    isDanger = false,
    icon = 'question',
  } = {}) {
    const result = await AdminSwal.fire({
      icon,
      title,
      text,
      showCancelButton: true,
      confirmButtonText,
      cancelButtonText,
      confirmButtonColor: isDanger ? '#dc2626' : '#0f2d59',
      cancelButtonColor: '#64748b',
    });
    return result.isConfirmed;
  },

  /**
   * Confirm deletion with red danger button
   */
  async confirmDelete(itemTitle = 'รายการนี้', customWarning = '') {
    const warning = customWarning || `คุณต้องการลบ "${itemTitle}" หรือไม่?\nการดำเนินการนี้จะถูกบันทึกใน Audit Trail`;
    const result = await AdminSwal.fire({
      icon: 'warning',
      title: 'ยืนยันการลบข้อมูล',
      text: warning,
      showCancelButton: true,
      confirmButtonText: 'ยืนยันการลบ',
      cancelButtonText: 'ยกเลิก',
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      focusCancel: true,
    });
    return result.isConfirmed;
  },

  /**
   * Confirm activation or suspension of an account or record
   */
  async confirmStatusToggle(name, newStatus) {
    const isActivating = newStatus === 'active';
    const actionTh = isActivating ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';
    const result = await AdminSwal.fire({
      icon: isActivating ? 'question' : 'warning',
      title: `ยืนยัน${actionTh}`,
      text: `คุณต้องการ${actionTh} "${name}" หรือไม่?${!isActivating ? ' เซสชันที่เปิดอยู่จะถูกเพิกถอนทันที' : ''}`,
      showCancelButton: true,
      confirmButtonText: `ยืนยัน${actionTh}`,
      cancelButtonText: 'ยกเลิก',
      confirmButtonColor: isActivating ? '#0284c7' : '#dc2626',
      cancelButtonColor: '#64748b',
      focusCancel: true,
    });
    return result.isConfirmed;
  },

  /**
   * Confirm discard of unsaved changes before navigating away or closing modal
   */
  async confirmUnsavedChanges() {
    const result = await AdminSwal.fire({
      icon: 'warning',
      title: 'มีข้อมูลที่ยังไม่ได้บันทึก',
      text: 'คุณต้องการออกจากหน้านี้โดยไม่บันทึกข้อมูลหรือไม่? ข้อมูลที่คุณกรอกไว้จะสูญหาย',
      showCancelButton: true,
      confirmButtonText: 'ทิ้งการแก้ไข',
      cancelButtonText: 'แก้ไขต่อ',
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#0f2d59',
      focusCancel: true,
    });
    return result.isConfirmed;
  },

  /**
   * Show active progress/loading indicator during asynchronous operations
   */
  showLoading(title = 'กำลังดำเนินการ...', text = 'กรุณารอสักครู่ ระบบกำลังประมวลผล') {
    AdminSwal.fire({
      title,
      text,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });
  },

  /**
   * Close active loading indicator
   */
  closeLoading() {
    if (Swal.isVisible()) {
      Swal.close();
    }
  },

  /**
   * Toast notification (success, info, warning, error)
   */
  toast({ icon = 'success', title = 'ดำเนินการสำเร็จ' } = {}) {
    return ToastSwal.fire({
      icon,
      title,
    });
  },
};

export default alertService;
