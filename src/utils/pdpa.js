/**
 * PDPA Compliance & Privacy Protection Utilities (Frontend)
 *
 * Implements Personal Data Protection Act (PDPA B.E. 2562) masking standards.
 * - 13-digit Thai Citizen ID: 3-****-*****-**-*
 * - Thai Full Name: นายกร**** สม****
 * - Member Number: 00*42
 * - Phone number: 081-XXX-5678
 * - IP address: 192.168.xxx.171
 */

/**
 * Mask 13-digit Thai Citizen ID.
 * Example: 3100600123456 -> 3-****-*****-**-*
 */
export function maskCitizenId(idCard) {
  if (!idCard) return '-';
  const digits = String(idCard).replace(/\D/g, '');
  if (digits.length === 13) {
    const d1 = digits.slice(0, 1);
    return `${d1}-****-*****-**-*`;
  }
  if (digits.length < 6) return '*'.repeat(digits.length);
  const prefix = digits.slice(0, 1);
  return `${prefix}-****-*****-**-*`;
}

export const maskIdCard = maskCitizenId;

/**
 * Mask 5-digit member number preserving leading zeros.
 * Example: 00042 -> 00*42
 */
export function maskMemberNo(memberNo) {
  if (!memberNo) return '-';
  const clean = String(memberNo).trim();
  const digits = clean.replace(/\D/g, '');
  if (!digits) return '-';
  const padded = digits.padStart(5, '0');
  if (padded.length === 5) {
    return `${padded.slice(0, 2)}*${padded.slice(3, 5)}`;
  }
  if (padded.length >= 4) {
    return `${padded.slice(0, 2)}*${padded.slice(-2)}`;
  }
  return `${padded.slice(0, 1)}*`;
}

/**
 * Mask Thai Full Name.
 * Example: นายกรณ์ชัย สมประสงค์ -> นายกร**** สม****
 */
export function maskName(fullName) {
  if (!fullName) return '-';
  const cleaned = String(fullName).trim().replace(/\s+/g, ' ');

  const prefixes = [
    'นางสาว', 'นาง', 'นาย', 'ด.ช.', 'ด.ญ.', 'เด็กชาย', 'เด็กหญิง',
    'นพ.', 'พญ.', 'ทพ.', 'ทพญ.', 'เภสัชกร', 'ภก.', 'ภญ.',
    'ศ.ดร.', 'รศ.ดร.', 'ผศ.ดร.', 'ดร.', 'ศ.', 'รศ.', 'ผศ.', 'อาจารย์', 'อ.',
    'ว่าที่ ร.ต.', 'ร.ต.', 'ร.ท.', 'ร.อ.', 'พ.ต.', 'พ.ท.', 'พ.อ.',
    'พล.ต.', 'พล.ท.', 'พล.อ.', 'จ.ส.อ.', 'จ.ส.ท.', 'จ.ส.ต.',
    'ส.อ.', 'ส.ท.', 'ส.ต.',
    'Mr.', 'Mrs.', 'Ms.', 'Miss', 'Dr.'
  ];

  let matchedPrefix = '';
  let nameWithoutPrefix = cleaned;

  for (const p of prefixes) {
    if (cleaned.startsWith(p)) {
      matchedPrefix = p;
      nameWithoutPrefix = cleaned.slice(p.length).trim();
      break;
    }
  }

  const parts = nameWithoutPrefix.split(' ');
  const firstName = parts[0] || '';
  const lastName = parts.slice(1).join(' ') || '';

  let maskedFirstName = '';
  if (firstName) {
    const keepLen = firstName.length <= 2 ? 1 : 2;
    maskedFirstName = firstName.slice(0, keepLen) + '****';
  }

  let maskedLastName = '';
  if (lastName) {
    const keepLen = lastName.length <= 2 ? 1 : 2;
    maskedLastName = lastName.slice(0, keepLen) + '****';
  }

  const result = `${matchedPrefix}${maskedFirstName} ${maskedLastName}`.trim();
  return result || '-';
}

export const maskFullName = maskName;

/**
 * Mask Phone Number.
 * Example: 0812345678 -> 081-XXX-5678
 */
export function maskPhone(phone) {
  if (!phone) return '-';
  const digits = String(phone).replace(/\D/g, '');
  if (digits.length === 10) {
    return `${digits.slice(0, 3)}-XXX-${digits.slice(-4)}`;
  }
  if (digits.length === 9) {
    return `${digits.slice(0, 2)}-XXX-${digits.slice(-4)}`;
  }
  return digits.length > 5 ? `${digits.slice(0, 3)}-XXX-${digits.slice(-3)}` : '***-***-****';
}

/**
 * Mask IP Address.
 * Example: 192.168.1.171 -> 192.168.xxx.171
 */
export function maskIp(ip) {
  if (!ip || ip === '127.0.0.1' || ip === '::1') return '127.0.0.xxx';
  const parts = String(ip).split('.');
  if (parts.length === 4) {
    return `${parts[0]}.${parts[1]}.xxx.${parts[3]}`;
  }
  return 'xxx.xxx.xxx.xxx';
}
