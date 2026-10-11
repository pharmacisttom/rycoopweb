<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Services\RichTextService;
function checkRich(bool $condition,string $label): void {if(!$condition)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;}
$safe=RichTextService::clean('<h2>ข่าวประชาสัมพันธ์</h2><p><strong>สำคัญ</strong> <em>ประกาศ</em></p><ul><li>รายการแรก</li></ul><a href="https://example.com">รายละเอียด</a>');
checkRich(str_contains($safe,'<h2>') && str_contains($safe,'<strong>') && str_contains($safe,'<ul>') && str_contains($safe,'https://example.com'),'headings, emphasis, lists and safe links preserved');
$dirty=RichTextService::clean('<script>alert(1)</script><img src=x onerror="alert(2)"><p onclick="alert(3)" style="position:fixed">ข้อความ</p><a href="javascript:alert(4)">ลิงก์</a><iframe src="https://example.com"></iframe><svg onload="alert(5)"></svg>');
checkRich(!preg_match('/script|onerror|onclick|javascript:|iframe|<svg|position:/i',$dirty),'script, event handlers, active embeds and unsafe URLs removed');
checkRich(RichTextService::text('<p><br></p>')==='','empty formatted content detected');
checkRich(RichTextService::text('<p>สวัสดี &amp; ขอบคุณ</p>')==='สวัสดี & ขอบคุณ','Thai text and entities retained');
checkRich(str_contains(RichTextService::clean("บรรทัดแรก\nบรรทัดสอง"),'<br'),'legacy plain text line breaks preserved');
echo 'Rich text tests passed.'.PHP_EOL;
