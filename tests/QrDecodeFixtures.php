<?php
declare(strict_types=1);

// Invoked by QrCodeRoundTripTest.mjs. All accounts and secrets are memory-only fixtures.
require __DIR__ . '/WorkflowFixture.php';

use App\Core\{Auth, Database};
use App\Controllers\Admin\MfaController;
use App\Services\{QrCodeGenerator as Qr, TwoFactorService as Mfa};

$cases = [];
function qrFixture(string $name, string $payload, int $ecc = Qr::ECC_M, int $margin = 4, int $size = 320): array {
    return ['name'=>$name,'payload'=>$payload,'margin'=>$margin,'svg'=>Qr::svg($payload,$size,$margin,$ecc)];
}
try {
    $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    $cases[] = qrFixture('short-ascii', 'HELLO');
    $cases[] = qrFixture('previously-broken-otp-uri', 'otpauth://totp/RayongCoop%3Afixture%40example.invalid?secret='.$secret.'&issuer=RayongCoop');
    $cases[] = qrFixture('standard-otp-uri', Mfa::getOtpAuthUrl('fixture@example.invalid',$secret,'RayongCoop'));
    $cases[] = qrFixture('thai-issuer-and-long-email', Mfa::getOtpAuthUrl(str_repeat('a',60).'@example.invalid',$secret,'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด'));
    $cases[] = qrFixture('raw-utf8', 'สหกรณ์ระยอง ทดสอบ QR และ 2FA');
    $cases[] = qrFixture('version-above-ten', 'https://example.invalid/'.str_repeat('fixture-payload-',40));
    foreach ([Qr::ECC_L,Qr::ECC_M,Qr::ECC_Q,Qr::ECC_H] as $level) {
        $cases[] = qrFixture('ecc-'.$level, Mfa::getOtpAuthUrl('fixture@example.invalid',$secret,'RayongCoop'),$level);
    }
    $cases[] = qrFixture('custom-quiet-zone', Mfa::getOtpAuthUrl('fixture@example.invalid',$secret,'RayongCoop'),Qr::ECC_M,8,360);
    // Exercise the actual enrollment controller response, not a replacement image generator.
    workflowLogin(1,false,true);
    $response = new WorkflowResponse();
    (new MfaController(workflowRequest(),$response))->enroll();
    if ($response->status !== 200) throw new RuntimeException('Fixture enrollment failed');
    $setup = $response->data;
    $prefix = 'data:image/svg+xml;base64,';
    if (!str_starts_with($setup['qr_code_uri'],$prefix)) throw new RuntimeException('Enrollment QR must be local SVG');
    $svg = base64_decode(substr($setup['qr_code_uri'],strlen($prefix)),true);
    if ($svg === false) throw new RuntimeException('Invalid enrollment SVG encoding');
    $cases[] = ['name'=>'controller-enrollment','margin'=>4,'payload'=>Mfa::getOtpAuthUrl($setup['account'],$setup['secret'],$setup['issuer']),'svg'=>$svg];
} finally {
    Auth::logout();
    (new ReflectionProperty(Database::class,'instance'))->setValue(null,null);
    ob_end_clean();
}
echo json_encode($cases,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
