<?php
declare(strict_types=1);
namespace App\Services;

class MemberSchemaException extends MemberDataException
{
    public function __construct(public readonly array $missing)
    {
        parent::__construct('ฐานข้อมูลระบบสมาชิกยังอัปเดตไม่ครบ กรุณาให้ผู้ดูแลเซิร์ฟเวอร์รันการอัปเดตฐานข้อมูลก่อนใช้งาน');
    }
}
