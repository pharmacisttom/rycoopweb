/**
 * PDPA Compliance & Privacy Protection Utilities (Frontend)
 *
 * Implements Personal Data Protection Act (PDPA B.E. 2562) masking standards.
 * - 13-digit Thai Citizen ID: 192.xxxxxx171
 * - Thai Full Name: นายสมxxx xxxมา
 * - Phone number: 081-xxx-x234
 * - IP address: 192.168.xxx.171
 */

/**
 * Mask 13-digit Thai Citizen ID.
 * Example: 1929900123171 -> 192.xxxxxx171
 */
export function maskIdCard(idCard) {
  if (!idCard) return '-';
  const digits = String(idCard).replace(/\D/g, '');
  if (digits.length < 6) return 'x'.repeat(digits.length);

  const prefix = digits.slice(0, 3);
  const suffix = digits.slice(-3);
  return `${prefix}.xxxxxx${suffix}`;
}

/**
 * Mask Thai Full Name.
 * Example: นายสมชาย ใจดีมา -> นายสมxxx xxxมา
 */
export function maskName(fullName) {
  if (!fullName) return '-';
  const cleaned = String(fullName).trim().replace(/\s+/g, ' ');

  const prefixes = [
    'นางสาว', 'นาง', 'นาย', 'ด.ช.', 'ด.ญ.', 'เด็กชาย', 'เด็กหญิง',
    'นพ.', 'พญ.', 'ทพ.', 'ทพญ.', 'ภก.', 'ภญ.',
    'ศ.', 'รศ.', 'ผศ.', 'ดร.', 'อ.',
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
    const keepLen = Math.min(2, firstName.length);
    maskedFirstName = firstName.slice(0, keepLen) + 'xxx';
  }

  let maskedLastName = '';
  if (lastName) {
    const keepLen = Math.min(2, lastName.length);
    maskedLastName = 'xxx' + lastName.slice(-keepLen);
  }

  const result = `${matchedPrefix}${maskedFirstName} ${maskedLastName}`.trim();
  return result || '-';
}

/**
 * Mask Phone Number.
 * Example: 0812345678 -> 081-xxx-x678
 */
export function maskPhone(phone) {
  if (!phone) return '-';
  const digits = String(phone).replace(/\D/g, '');
  if (digits.length === 10) {
    return `${digits.slice(0, 3)}-xxx-x${digits.slice(7)}`;
  }
  if (digits.length === 9) {
    return `${digits.slice(0, 2)}-xxx-x${digits.slice(6)}`;
  }
  return digits.length > 5 ? `${digits.slice(0, 3)}-xxxx-${digits.slice(-3)}` : digits;
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
