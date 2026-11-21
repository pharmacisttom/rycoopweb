import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

/**
 * RayongCoop Standardized SweetAlert2 Configuration
 * RC16 Production Admin & Public UI Abstraction
 */

const SwalApp = Swal.mixin({
  buttonsStyling: false,
  customClass: {
    popup: 'swal2-rayongcoop-popup rounded-4 shadow-lg border-0',
    title: 'swal2-rayongcoop-title font-weight-bold',
    htmlContainer: 'swal2-rayongcoop-html',
    confirmButton: 'btn btn-primary px-4 py-2 me-2 font-weight-medium shadow-sm',
    cancelButton: 'btn btn-outline-secondary px-4 py-2 font-weight-medium',
    denyButton: 'btn btn-danger px-4 py-2 me-2 font-weight-medium',
  },
  reverseButtons: true,
  focusConfirm: false,
});

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3500,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer);
    toast.addEventListener('mouseleave', Swal.resumeTimer);
  },
  customClass: {
    popup: 'swal2-toast-rayongcoop shadow rounded-3 border-0',
  },
});

/**
 * 1. showSuccess()
 */
export function showSuccess(title = 'สำเร็จ!', text = 'ดำเนินการเรียบร้อยแล้ว', options = {}) {
  return SwalApp.fire({
    icon: 'success',
    iconColor: '#10b981',
    title,
    text: typeof text === 'string' ? text : undefined,
    html: typeof text !== 'string' ? text : undefined,
    confirmButtonText: 'ตกลง',
    ...options,
  });
}

/**
 * 2. showError()
 */
export function showError(title = 'เกิดข้อผิดพลาด', text = 'ไม่สามารถทำรายการได้ กรุณาลองใหม่อีกครั้ง', options = {}) {
  return SwalApp.fire({
    icon: 'error',
    iconColor: '#ef4444',
    title,
    text: typeof text === 'string' ? text : undefined,
    html: typeof text !== 'string' ? text : undefined,
    confirmButtonText: 'ตกลง',
    ...options,
  });
}

/**
 * 3. showWarning()
 */
export function showWarning(title = 'แจ้งเตือน', text = '', options = {}) {
  return SwalApp.fire({
    icon: 'warning',
    iconColor: '#f59e0b',
    title,
    text: typeof text === 'string' ? text : undefined,
    html: typeof text !== 'string' ? text : undefined,
    confirmButtonText: 'รับทราบ',
    ...options,
  });
}

/**
 * 4. showInfo()
 */
export function showInfo(title = 'ข้อมูล', text = '', options = {}) {
  return SwalApp.fire({
    icon: 'info',
    iconColor: '#0ea5e9',
    title,
    text: typeof text === 'string' ? text : undefined,
    html: typeof text !== 'string' ? text : undefined,
    confirmButtonText: 'ตกลง',
    ...options,
  });
}

/**
 * 5. showConfirm() -> Promise<boolean>
 */
export async function showConfirm(title = 'ยืนยันการทำรายการ?', text = 'กรุณาตรวจสอบความถูกต้องก่อนยืนยัน', options = {}) {
  const result = await SwalApp.fire({
    icon: options.icon || 'question',
    iconColor: options.iconColor || '#073B74',
    title,
    text: typeof text === 'string' ? text : undefined,
    html: typeof text !== 'string' ? text : undefined,
    showCancelButton: true,
    confirmButtonText: options.confirmText || 'ยืนยัน',
    cancelButtonText: options.cancelText || 'ยกเลิก',
    ...options,
  });
  return result.isConfirmed;
}

/**
 * 6. showDeleteConfirm() -> Promise<boolean>
 */
export async function showDeleteConfirm(
  title = 'ยืนยันการลบข้อมูล?',
  text = 'ข้อมูลนี้จะถูกลบหรือนำออกจากระบบ ท่านต้องการดำเนินการต่อหรือไม่?',
  options = {}
) {
  const result = await SwalApp.fire({
    icon: 'warning',
    iconColor: '#ef4444',
    title,
    text: typeof text === 'string' ? text : undefined,
    html: typeof text !== 'string' ? text : undefined,
    showCancelButton: true,
    confirmButtonText: options.confirmText || 'ลบข้อมูล',
    cancelButtonText: options.cancelText || 'ยกเลิก',
    customClass: {
      popup: 'swal2-rayongcoop-popup rounded-4 shadow-lg border-0',
      confirmButton: 'btn btn-danger px-4 py-2 me-2 font-weight-medium shadow-sm',
      cancelButton: 'btn btn-outline-secondary px-4 py-2 font-weight-medium',
    },
    ...options,
  });
  return result.isConfirmed;
}

/**
 * 7. showLoading()
 */
export function showLoading(title = 'กำลังประมวลผล...', text = 'กรุณารอสักครู่ ห้ามปิดหน้าต่างนี้') {
  return Swal.fire({
    title,
    text,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    },
    customClass: {
      popup: 'swal2-rayongcoop-popup rounded-4 shadow-lg border-0',
    },
  });
}

/**
 * 8. closeLoading()
 */
export function closeLoading() {
  Swal.close();
}

/**
 * 9. showToast()
 */
export function showToast(title = 'ดำเนินการเรียบร้อยแล้ว', icon = 'success', timer = 3500) {
  return Toast.fire({
    icon,
    title,
    timer,
  });
}

/**
 * 10. showValidationError()
 */
export function showValidationError(message = 'กรุณาตรวจสอบข้อมูลที่ระบุ', title = 'ข้อมูลไม่ถูกต้อง') {
  return SwalApp.fire({
    icon: 'error',
    iconColor: '#ef4444',
    title,
    text: message,
    confirmButtonText: 'แก้ไขข้อมูล',
  });
}

export default SwalApp;
