<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Services\GoogleAuthService as Google;
$verifier='dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
if(Google::generateCodeChallenge($verifier)!=='E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM')throw new RuntimeException('RFC 7636 vector failed');echo "PASS: PKCE S256 RFC 7636 vector\n";
$a=Google::generateCodeVerifier();$b=Google::generateCodeVerifier();if($a===$b||strlen($a)<43||!preg_match('/^[A-Za-z0-9_~-]+$/D',$a))throw new RuntimeException('Verifier randomness/format');echo "PASS: independent URL-safe verifiers\n";
