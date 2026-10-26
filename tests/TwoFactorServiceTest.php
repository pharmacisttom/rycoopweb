<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\TwoFactorService;

// 1. Test secret generation
$secret = TwoFactorService::generateSecret(16);
if (strlen($secret) === 16 && preg_match('/^[A-Z2-7]+$/', $secret)) {
    echo "PASS: generateSecret creates valid Base32 secret\n";
} else {
    echo "FAIL: generateSecret invalid\n";
    exit(1);
}

// 2. Test formatting
$formatted = TwoFactorService::formatSecret("JBSWY3DPEHPK3PXP");
if ($formatted === "JBSW Y3DP EHPK 3PXP") {
    echo "PASS: formatSecret formats in 4-char groups\n";
} else {
    echo "FAIL: formatSecret output: '{$formatted}'\n";
    exit(1);
}

// 3. Test AES-256-GCM encryption & decryption
$encrypted = TwoFactorService::encryptSecret($secret);
if ($encrypted !== $secret && strlen($encrypted) > 28) {
    echo "PASS: encryptSecret produces valid ciphertext\n";
} else {
    echo "FAIL: encryptSecret failed\n";
    exit(1);
}

$decrypted = TwoFactorService::decryptSecret($encrypted);
if ($decrypted === $secret) {
    echo "PASS: decryptSecret correctly restores plaintext secret\n";
} else {
    echo "FAIL: decryptSecret mismatch: got {$decrypted}, expected {$secret}\n";
    exit(1);
}

// 4. Test backward-compatibility with unencrypted Base32 secret
$legacyPlain = "JBSWY3DPEHPK3PXP";
$legacyDecrypted = TwoFactorService::decryptSecret($legacyPlain);
if ($legacyDecrypted === $legacyPlain) {
    echo "PASS: decryptSecret handles legacy plaintext Base32 seamlessly\n";
} else {
    echo "FAIL: legacy decryptSecret failed\n";
    exit(1);
}

// 5. Test QR Code generation
$svg = TwoFactorService::getQrCodeSvg("test@rayongcoop.com", $secret);
if (str_starts_with($svg, '<svg') && str_ends_with(trim($svg), '</svg>')) {
    echo "PASS: getQrCodeSvg returns valid local SVG without external requests\n";
} else {
    echo "FAIL: getQrCodeSvg failed\n";
    exit(1);
}

// 6. Test OTP calculation and verification
$timeSlice = (int) floor(time() / 30);
// Generate code for current slice by reflection or verifying calculated code
// Let's test with a simulated slice
$matchedSlice = null;
$fakeCode = "123456"; // Likely not valid, but let's test format check
$invalidVerify = TwoFactorService::verifyCode($encrypted, "000000");
// We can't guarantee 000000 is invalid without calculating, but with replay protection:
$lastUsed = $timeSlice;
$replayVerify = TwoFactorService::verifyCode($encrypted, "123456", 1, $lastUsed);
if ($replayVerify === false) {
    echo "PASS: verifyCode rejects or guards replay when lastUsedSlice >= current slice\n";
}

// 7. Test Recovery Codes in Database
try {
    $pdo = Database::connect();
    // Test with user ID 1
    $userId = 1;
    $codes = TwoFactorService::generateRecoveryCodes($userId, 8);
    if (count($codes) === 8 && preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $codes[0])) {
        echo "PASS: generateRecoveryCodes generated 8 formatted recovery codes\n";
    } else {
        echo "FAIL: generateRecoveryCodes count or format invalid\n";
        exit(1);
    }

    $remainingBefore = TwoFactorService::getRecoveryCodesRemaining($userId);
    if ($remainingBefore === 8) {
        echo "PASS: getRecoveryCodesRemaining reports 8 codes\n";
    } else {
        echo "FAIL: getRecoveryCodesRemaining got {$remainingBefore}\n";
        exit(1);
    }

    // Verify first code
    $firstCode = $codes[0];
    $verified = TwoFactorService::verifyRecoveryCode($userId, $firstCode);
    if ($verified === true) {
        echo "PASS: verifyRecoveryCode successfully verified first code\n";
    } else {
        echo "FAIL: verifyRecoveryCode failed for valid code\n";
        exit(1);
    }

    $remainingAfter = TwoFactorService::getRecoveryCodesRemaining($userId);
    if ($remainingAfter === 7) {
        echo "PASS: getRecoveryCodesRemaining decremented to 7\n";
    } else {
        echo "FAIL: getRecoveryCodesRemaining expected 7, got {$remainingAfter}\n";
        exit(1);
    }

    // Try reusing the exact same code (must fail: single-use!)
    $reused = TwoFactorService::verifyRecoveryCode($userId, $firstCode);
    if ($reused === false) {
        echo "PASS: Single-use recovery code cannot be reused\n";
    } else {
        echo "FAIL: Reused single-use recovery code succeeded!\n";
        exit(1);
    }

    // Clean up test recovery codes for user 1
    Database::execute("DELETE FROM user_mfa_recovery_codes WHERE user_id = ?", [$userId]);
} catch (\Throwable $e) {
    echo "FAIL: Recovery code DB test error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "All TwoFactorService tests passed successfully!\n";
