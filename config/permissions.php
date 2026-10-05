<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | System Roles (11 Roles)
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'member_admin' => [
            'name' => 'ผู้ดูแลระบบสมาชิก',
            'description' => 'ดูแลสมาชิกและปันผลเท่านั้น ไม่มีสิทธิ์จัดการ CMS หรือผู้ใช้งานหลัก',
        ],
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'ผู้ดูแลระบบระดับสูงสุด มีสิทธิ์เข้าถึงและจัดการทุกโมดูล',
        ],
        'manager' => [
            'name' => 'Manager',
            'description' => 'ผู้จัดการสหกรณ์ มีสิทธิ์อนุมัติเนื้อหาและดูรายงานภาพรวม',
        ],
        'executive' => [
            'name' => 'Executive',
            'description' => 'ผู้บริหาร / คณะกรรมการ มีสิทธิ์ดู Dashboard และรายงานทางการเงิน',
        ],
        'finance' => [
            'name' => 'Finance Officer',
            'description' => 'เจ้าหน้าที่การเงิน จัดการอัตราดอกเบี้ยและสถิติการเงิน',
        ],
        'loan_officer' => [
            'name' => 'Loan Officer',
            'description' => 'เจ้าหน้าที่สินเชื่อ จัดการผลิตภัณฑ์เงินกู้และคำขอ',
        ],
        'welfare_officer' => [
            'name' => 'Welfare Officer',
            'description' => 'เจ้าหน้าที่สวัสดิการ จัดการสวัสดิการสมาชิก',
        ],
        'pr_officer' => [
            'name' => 'PR Officer',
            'description' => 'เจ้าหน้าที่ประชาสัมพันธ์ จัดการข่าว ประกาศ แบนเนอร์ ป็อปอัป',
        ],
        'document_officer' => [
            'name' => 'Document Officer',
            'description' => 'เจ้าหน้าที่เอกสาร จัดการศูนย์เอกสารและแบบฟอร์ม',
        ],
        'complaint_officer' => [
            'name' => 'Complaint Officer',
            'description' => 'เจ้าหน้าที่รับเรื่องร้องเรียนและข้อเสนอแนะ',
        ],
        'auditor' => [
            'name' => 'Auditor',
            'description' => 'ผู้ตรวจสอบกิจการ ดูประวัติการใช้งานและ Audit Logs',
        ],
        'it_admin' => [
            'name' => 'IT Admin',
            'description' => 'เจ้าหน้าที่เทคโนโลยีสารสนเทศ ดูแลระบบ ความปลอดภัย และสำรองข้อมูล',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Granular Permissions (12 Permissions per module)
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'view' => 'ดูข้อมูลและรายการ',
        'create' => 'สร้างข้อมูลใหม่',
        'edit' => 'แก้ไขข้อมูล',
        'delete' => 'ลบข้อมูล (Soft Delete)',
        'restore' => 'กู้คืนข้อมูลที่ถูกลบ',
        'approve' => 'อนุมัติข้อมูล (Maker-Checker)',
        'reject' => 'ปฏิเสธข้อมูล',
        'publish' => 'เผยแพร่ข้อมูลสู่สาธารณะ',
        'unpublish' => 'ระงับการเผยแพร่ข้อมูล',
        'export' => 'ส่งออกข้อมูล (Excel/PDF)',
        'manage' => 'จัดการสิทธิ์และการตั้งค่าระดับสูง',
        'audit' => 'ตรวจสอบประวัติการทำงาน (Audit Trail)',
    ],

    /*
    |--------------------------------------------------------------------------
    | System Modules
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'dashboard',
        'news',
        'announcements',
        'hero_slides',
        'popups',
        'deposit_products',
        'loan_products',
        'interest_rates',
        'welfare',
        'documents',
        'eservice_links',
        'complaints',
        'boards',
        'staff',
        'faqs',
        'media',
        'privacy_cookies',
        'financial_statistics',
        'users',
        'roles',
        'audit_logs',
        'backups',
        'settings',
    ],
];
