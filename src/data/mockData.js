import legacyReference from './legacyReference.json';
/**
 * Official Reference Data for Rayong Public Health Savings and Credit Cooperative Limited
 * สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
 */

export const COOP_INFO = legacyReference.coopInfo;

export const KEY_STATS = [];

export const INTEREST_RATES = legacyReference.interestRates;

export const LOAN_PRODUCTS = legacyReference.loanProducts;

// Master loan products reference to verify consistency
export const LOAN_PRODUCTS_MASTER = [
  'เงินกู้สามัญ',
  'เงินกู้ฉุกเฉิน',
  'เงินกู้พิเศษ',
  'เงินกู้ผ่อนชำระสินค้า ฯ',
  'เงินกู้เพื่อการศึกษา',
  'เงินกู้เพื่อพัฒนาคุณภาพชีวิต',
  'เงินกู้เพื่อจัดซื้อรถยนต์',
  'เงินกู้พิเศษเพื่อความมั่นคงในชีวิต',
  'เงินกู้เพื่อปรับปรุงโครงสร้างหนี้',
  'เงินกู้รับการค้ำประกัน'
];

export const WELFARE_ITEMS = [
  {
    title: 'สวัสดิการสมาชิกถึงแก่กรรม',
    amount: 'สูงสุด 200,000 บาท',
    desc: 'ช่วยเหลือครอบครัวสมาชิกตามอายุการเป็นสมาชิก เพื่อบรรเทาความเดือดร้อน',
    icon: 'HeartHandshake',
    category: 'สงเคราะห์ครอบครัว',
  },
  {
    title: 'ทุนการศึกษาบุตรสมาชิก',
    amount: '1,500 - 5,000 บาท/ปี',
    desc: 'ส่งเสริมการศึกษาบุตรตั้งแต่ระดับประถม มัธยม ถึงระดับปริญญาตรี',
    icon: 'GraduationCap',
    category: 'การศึกษา',
  },
  {
    title: 'สวัสดิการรักษาพยาบาล / นอน รพ.',
    amount: 'คืนละ 500 บาท (สูงสุด 20 คืน/ปี)',
    desc: 'เงินช่วยเหลือกรณีสมาชิกเข้ารับการรักษาพยาบาลเป็นผู้ป่วยใน',
    icon: 'Stethoscope',
    category: 'สุขภาพ',
  },
  {
    title: 'สวัสดิการมงคลสมรส / คลอดบุตร',
    amount: '3,000 - 5,000 บาท',
    desc: 'ร่วมแสดงความยินดีในโอกาสเริ่มต้นชีวิตคู่และต้อนรับสมาชิกใหม่ในครอบครัว',
    icon: 'Gift',
    category: 'ครอบครัว',
  },
  {
    title: 'สวัสดิการสมาชิกเกษียณอายุ',
    amount: '5,000 - 20,000 บาท',
    desc: 'มอบของขวัญและเงินมุทิตาจิตแด่สมาชิกผู้เกษียณอายุราชการ',
    icon: 'Award',
    category: 'เกษียณอายุ',
  },
  {
    title: 'เงินช่วยเหลือประสบภัยพิบัติ',
    amount: 'สูงสุด 10,000 บาท',
    desc: 'ช่วยเหลือกรณีที่อยู่อาศัยของสมาชิกประสบอุทกภัย วาตภัย หรืออัคคีภัย',
    icon: 'ShieldAlert',
    category: 'บรรเทาสาธารณภัย',
  },
];

export const BOARD_MEMBERS = legacyReference.boardMembers;

export const STAFF_MEMBERS = legacyReference.staffMembers;

export const NEWS_LIST = [];

export const ANNOUNCEMENTS = [];

export const DOCUMENTS = [];

export const FAQS = [];

export const MEMBER_COMPLAINTS = [];
