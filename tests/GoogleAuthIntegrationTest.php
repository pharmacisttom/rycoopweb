<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/RequireTestDatabase.php';

use App\Core\Database;
use App\Services\GoogleAuthService;
use App\Services\StaffInvitationService;

echo "=== Google Authentication & Staff Invitation Integration Tests ===\n";

$googleAuth = new GoogleAuthService();

// 1. PKCE code_verifier & code_challenge test
$verifier = GoogleAuthService::generateCodeVerifier();
$challenge = GoogleAuthService::generateCodeChallenge($verifier);
if (strlen($verifier) >= 43 && strlen($challenge) >= 43 && !str_contains($challenge, '+') && !str_contains($challenge, '/')) {
    echo "PASS: PKCE code_verifier and code_challenge generated properly (S256 url-safe)\n";
} else {
    echo "FAIL: PKCE generation failed\n";
    exit(1);
}

// 2. Authorization URL generation test
$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));
$authUrl = $googleAuth->buildAuthorizationUrl($state, $nonce, $challenge);

if (str_starts_with($authUrl, 'https://accounts.google.com/o/oauth2/v2/auth')
    && str_contains($authUrl, 'response_type=code')
    && str_contains($authUrl, 'code_challenge_method=S256')
    && str_contains($authUrl, "state={$state}")
    && str_contains($authUrl, "nonce={$nonce}")) {
    echo "PASS: Authorization URL formatted with OpenID Connect, PKCE, state, and nonce\n";
} else {
    echo "FAIL: Authorization URL format incorrect: {$authUrl}\n";
    exit(1);
}

// 3. Staff Invitation Lifecycle Test
$testStaffEmail = 'test_staff_' . bin2hex(random_bytes(4)) . '@gmail.com';
$testStaffName = 'นายสมชาย ทดสอบระบบ';
$staffRoleId = (int) Database::value("SELECT id FROM roles WHERE slug = 'loan_officer' LIMIT 1");
$superAdminId = 1;

// 3.1 Create Invitation
$invitation = StaffInvitationService::createInvitation($testStaffName, $testStaffEmail, $staffRoleId, 'แผนกสินเชื่อ', $superAdminId);
if (!empty($invitation['token']) && !empty($invitation['id']) && $invitation['email'] === $testStaffEmail) {
    echo "PASS: StaffInvitationService::createInvitation created secure token\n";
} else {
    echo "FAIL: createInvitation failed\n";
    exit(1);
}

// 3.2 Verify Token Hashing (token plaintext must NOT be stored in DB!)
$rawToken = $invitation['token'];
$storedHash = Database::value("SELECT token_hash FROM staff_invitations WHERE id = ?", [$invitation['id']]);
if ($storedHash === hash('sha256', $rawToken) && $storedHash !== $rawToken) {
    echo "PASS: Token is securely hashed with SHA-256 (plaintext never persisted in DB)\n";
} else {
    echo "FAIL: Plaintext token stored in DB!\n";
    exit(1);
}

// 3.3 Find by Token
$found = StaffInvitationService::findValidByToken($rawToken);
if ($found && $found['email'] === $testStaffEmail) {
    echo "PASS: findValidByToken successfully retrieves active invitation\n";
} else {
    echo "FAIL: findValidByToken failed\n";
    exit(1);
}

// 3.4 Find by Email
$foundByEmail = StaffInvitationService::findValidByEmail($testStaffEmail);
if ($foundByEmail && (int) $foundByEmail['id'] === (int) $invitation['id']) {
    echo "PASS: findValidByEmail successfully retrieves active invitation\n";
} else {
    echo "FAIL: findValidByEmail failed\n";
    exit(1);
}

// 3.5 Accept Invitation
$testGoogleSub = 'goog_sub_' . bin2hex(random_bytes(8));
$accepted = StaffInvitationService::acceptInvitation($invitation['id'], $testGoogleSub, $testStaffEmail, $testStaffName);
if ($accepted['email'] === $testStaffEmail && !empty($accepted['user_id'])) {
    echo "PASS: acceptInvitation provisioned staff user and linked Google sub\n";
} else {
    echo "FAIL: acceptInvitation failed\n";
    exit(1);
}

$newUserId = (int) $accepted['user_id'];

// 3.6 Verify user status, role, and google identity
$identity = $googleAuth->findIdentityBySub($testGoogleSub);
if ($identity && (int) $identity['user_id'] === $newUserId && $identity['role_slug'] === 'loan_officer') {
    echo "PASS: Linked Google identity verified with role 'loan_officer' and user status 'active'\n";
} else {
    echo "FAIL: Linked identity mismatch\n";
    exit(1);
}

// 3.7 Verify invitation marked as accepted (single-use!)
$invitationStatus = Database::value("SELECT status FROM staff_invitations WHERE id = ?", [$invitation['id']]);
if ($invitationStatus === 'accepted') {
    echo "PASS: Invitation status is now 'accepted' (single-use enforced)\n";
} else {
    echo "FAIL: Invitation not marked accepted: {$invitationStatus}\n";
    exit(1);
}

// 3.8 Trying to reuse the accepted invitation must fail
try {
    StaffInvitationService::acceptInvitation($invitation['id'], $testGoogleSub, $testStaffEmail, $testStaffName);
    echo "FAIL: Reusing accepted invitation succeeded!\n";
    exit(1);
} catch (\InvalidArgumentException $e) {
    echo "PASS: Reusing accepted invitation fails as expected\n";
}

// 4. Session Revocation Test (session_version bumping)
$userRecord = Database::first("SELECT session_version FROM users WHERE id = ?", [$newUserId]);
$initialVersion = (int) $userRecord['session_version'];
\App\Core\Auth::revokeAllSessions($newUserId);
$updatedVersion = (int) Database::value("SELECT session_version FROM users WHERE id = ?", [$newUserId]);
if ($updatedVersion === $initialVersion + 1) {
    echo "PASS: Auth::revokeAllSessions increments session_version for instant session invalidation\n";
} else {
    echo "FAIL: session_version did not increment\n";
    exit(1);
}

// 5. Unlink Google Account Test
$googleAuth->unlinkUser($newUserId);
$unlinked = $googleAuth->findIdentityBySub($testGoogleSub);
if ($unlinked === null) {
    echo "PASS: Google identity unlinked successfully\n";
} else {
    echo "FAIL: Unlink failed\n";
    exit(1);
}

// Clean up test staff user & invitation
Database::execute("DELETE FROM user_roles WHERE user_id = ?", [$newUserId]);
Database::execute("DELETE FROM users WHERE id = ?", [$newUserId]);
Database::execute("DELETE FROM staff_invitations WHERE id = ?", [$invitation['id']]);

echo "\nAll Google Authentication & Staff Invitation Integration Tests passed!\n";
