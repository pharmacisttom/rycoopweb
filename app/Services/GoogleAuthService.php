<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

class GoogleAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private bool $enabled;
    private string $primarySuperAdminEmail;

    public function __construct()
    {
        $this->clientId = (string) config('google.client_id', '');
        $this->clientSecret = (string) config('google.client_secret', '');
        $this->redirectUri = (string) config('google.redirect_uri', '');
        $this->enabled = (bool) config('google.enabled', false);
        $this->primarySuperAdminEmail = strtolower(trim((string) config('google.primary_superadmin_email', '')));
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret) && filter_var($this->redirectUri,FILTER_VALIDATE_URL)!==false && parse_url($this->redirectUri,PHP_URL_SCHEME)==='https' && !parse_url($this->redirectUri,PHP_URL_USER) && !parse_url($this->redirectUri,PHP_URL_PASS);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPrimarySuperAdminEmail(): string
    {
        return $this->primarySuperAdminEmail;
    }

    /**
     * Generate PKCE code_verifier (random 64-byte url-safe string).
     */
    public static function generateCodeVerifier(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    /**
     * Generate PKCE code_challenge from code_verifier using S256.
     */
    public static function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return self::base64UrlEncode($hash);
    }

    /**
     * Build the Google OAuth 2.0 / OpenID Connect authorization URL.
     */
    public function buildAuthorizationUrl(
        string $state,
        string $nonce,
        string $codeChallenge,
        ?string $customRedirectUri = null,
        ?string $loginHint = null
    ): string {
        $redirect = $customRedirectUri ?: $this->redirectUri;
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ];

        if ($loginHint) {
            $params['login_hint'] = $loginHint;
        }

        $authUrl = (string) config('google.endpoints.auth_url', 'https://accounts.google.com/o/oauth2/v2/auth');
        return $authUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens at Google Token endpoint.
     */
    public function exchangeCode(string $code, string $codeVerifier, ?string $customRedirectUri = null): array
    {
        $tokenUrl = (string) config('google.endpoints.token_url', 'https://oauth2.googleapis.com/token');
        $redirect = $customRedirectUri ?: $this->redirectUri;

        $postData = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirect,
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier,
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            Logger::error("Google token exchange failed: HTTP {$httpCode}, error: {$curlError}, response: {$response}");
            $errData = json_decode((string) $response, true);
            $msg = $errData['error_description'] ?? $errData['error'] ?? 'Google token exchange failed';
            throw new \RuntimeException($msg);
        }

        $tokenData = json_decode((string) $response, true);
        if (!is_array($tokenData) || empty($tokenData['id_token'])) {
            throw new \RuntimeException('Invalid token response from Google.');
        }

        return $tokenData;
    }

    /**
     * Parse and cryptographically verify Google OpenID Connect ID Token.
     */
    public function verifyIdToken(string $idToken, string $expectedNonce): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Malformed JWT ID Token.');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $header = json_decode(self::base64UrlDecode($headerB64), true);
        $payload = json_decode(self::base64UrlDecode($payloadB64), true);

        if (!is_array($header) || !is_array($payload)) {
            throw new \InvalidArgumentException('Invalid JWT ID Token json encoding.');
        }

        // 1. Validate Algorithm
        if (($header['alg'] ?? '') !== 'RS256') {
            throw new \InvalidArgumentException('Unsupported JWT signature algorithm: ' . ($header['alg'] ?? ''));
        }

        // 2. Validate Claims
        $now = time();
        $skew = 60; // 60s tolerance for clock skew

        // Issuer
        $allowedIssuers = ['https://accounts.google.com', 'accounts.google.com'];
        if (!in_array($payload['iss'] ?? '', $allowedIssuers, true)) {
            throw new \InvalidArgumentException('Invalid ID Token issuer: ' . ($payload['iss'] ?? ''));
        }

        // Audience
        if (($payload['aud'] ?? '') !== $this->clientId) {
            throw new \InvalidArgumentException('ID Token audience does not match configured Client ID.');
        }

        // Expiration
        if (!isset($payload['exp']) || ($payload['exp'] + $skew) < $now) {
            throw new \InvalidArgumentException('ID Token has expired.');
        }

        // Issued at
        if (!isset($payload['iat']) || ($payload['iat'] - $skew) > $now) {
            throw new \InvalidArgumentException('ID Token issued in the future.');
        }

        // Nonce verification (critical against replay attacks)
        if (($payload['nonce'] ?? '') !== $expectedNonce) {
            throw new \InvalidArgumentException('ID Token nonce mismatch.');
        }

        // Email verified requirement
        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
        if (!$emailVerified) {
            throw new \InvalidArgumentException('Google account email has not been verified.');
        }

        // Subject claim
        $sub = trim((string) ($payload['sub'] ?? ''));
        if ($sub === '') {
            throw new \InvalidArgumentException('ID Token missing immutable sub claim.');
        }

        // Email
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('ID Token missing valid email claim.');
        }

        // 3. Cryptographic Signature Verification
        $kid = (string) ($header['kid'] ?? '');
        $signedData = "{$headerB64}.{$payloadB64}";
        $rawSignature = self::base64UrlDecode($sigB64);

        $verified = $this->verifySignatureWithJwks($signedData, $rawSignature, $kid);

        // Fallback secondary verification via Google tokeninfo if JWKS fetch was temporarily blocked
        if (!$verified) {
            $verified = $this->verifyWithTokenInfo($idToken, $sub);
        }

        if (!$verified) {
            throw new \InvalidArgumentException('Cryptographic signature verification failed for Google ID Token.');
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => (string) ($payload['name'] ?? $email),
            'picture' => (string) ($payload['picture'] ?? ''),
            'email_verified' => true,
            'hd' => (string) ($payload['hd'] ?? ''), // Google Workspace hosted domain if present
            'claims' => $payload,
        ];
    }

    /**
     * Find linked identity by Google sub claim.
     */
    public function findIdentityBySub(string $googleSub): ?array
    {
        return Database::first(
            "SELECT i.*, u.username, u.email as user_email, u.name as user_name, u.status as user_status, 
                    u.two_factor_enabled, u.two_factor_confirmed_at, u.session_version,
                    r.id as role_id, r.slug as role_slug, r.name as role_name
             FROM user_google_identities i
             JOIN users u ON i.user_id = u.id
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE i.google_sub = ? AND u.deleted_at IS NULL
             LIMIT 1",
            [$googleSub]
        );
    }

    /**
     * Find linked identity by local user ID.
     */
    public function findIdentityByUserId(int $userId): ?array
    {
        return Database::first(
            "SELECT * FROM user_google_identities WHERE user_id = ? LIMIT 1",
            [$userId]
        );
    }

    /**
     * Bind a local user account to a verified Google sub and email.
     */
    public function linkUser(int $userId, string $googleSub, string $googleEmail): void
    {
        $existing = Database::first("SELECT id, user_id FROM user_google_identities WHERE google_sub = ? LIMIT 1", [$googleSub]);
        if ($existing && (int) $existing['user_id'] !== $userId) {
            throw new \InvalidArgumentException('Google sub claim is already linked to another user account.');
        }

        Database::execute(
            "INSERT INTO user_google_identities (user_id, google_sub, google_email, email_verified, linked_at, last_login_at, created_at, updated_at)
             VALUES (?, ?, ?, 1, NOW(), NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE google_email = VALUES(google_email), last_login_at = NOW(), updated_at = NOW()",
            [$userId, $googleSub, strtolower($googleEmail)]
        );

        AuditService::log('google_auth', 'link_account', (string) $userId, null, [
            'google_sub' => $googleSub,
            'google_email' => $googleEmail,
        ]);
    }

    /**
     * Unlink a Google identity from a local user account.
     */
    public function unlinkUser(int $userId): void
    {
        $identity = $this->findIdentityByUserId($userId);
        if ($identity) {
            Database::execute("DELETE FROM user_google_identities WHERE user_id = ?", [$userId]);
            AuditService::log('google_auth', 'unlink_account', (string) $userId, [
                'google_sub' => $identity['google_sub'],
                'google_email' => $identity['google_email'],
            ], null);
        }
    }

    /**
     * Update last login timestamp for linked identity.
     */
    public function recordLogin(string $googleSub): void
    {
        Database::execute(
            "UPDATE user_google_identities SET last_login_at = NOW(), updated_at = NOW() WHERE google_sub = ?",
            [$googleSub]
        );
    }

    // -------------------------------------------------------------------------
    // Signature Verification & Key Helpers
    // -------------------------------------------------------------------------

    private function verifySignatureWithJwks(string $signedData, string $rawSignature, string $kid): bool
    {
        if ($kid === '') {
            return false;
        }

        $keys = $this->getGoogleJwks();
        if (empty($keys[$kid])) {
            // Force refresh certs in case key was rotated
            $keys = $this->getGoogleJwks(true);
        }

        if (empty($keys[$kid])) {
            Logger::error("Google public key kid '{$kid}' not found in certs.");
            return false;
        }

        $pem = $this->jwkToPem($keys[$kid]);
        if (!$pem) {
            return false;
        }

        $pubKey = openssl_pkey_get_public($pem);
        if (!$pubKey) {
            return false;
        }

        $result = openssl_verify($signedData, $rawSignature, $pubKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    private function verifyWithTokenInfo(string $idToken, string $expectedSub): bool
    {
        $tokenInfoUrl = (string) config('google.endpoints.tokeninfo_url', 'https://oauth2.googleapis.com/tokeninfo');
        $url = $tokenInfoUrl . '?id_token=' . urlencode($idToken);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return false;
        }

        $data = json_decode((string) $response, true);
        return is_array($data) && ($data['sub'] ?? '') === $expectedSub && ($data['aud'] ?? '') === $this->clientId;
    }

    private function getGoogleJwks(bool $forceRefresh = false): array
    {
        $cacheFile = __DIR__ . '/../../storage/cache/google_jwks.json';
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        if (!$forceRefresh && file_exists($cacheFile)) {
            $cacheAge = time() - filemtime($cacheFile);
            if ($cacheAge < 3600) { // 1 hour cache
                $cached = json_decode((string) file_get_contents($cacheFile), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        $jwksUrl = (string) config('google.endpoints.jwks_url', 'https://www.googleapis.com/oauth2/v3/certs');
        $ch = curl_init($jwksUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            Logger::error("Failed to fetch Google JWKS certs: HTTP {$httpCode}");
            if (file_exists($cacheFile)) {
                return (array) json_decode((string) file_get_contents($cacheFile), true);
            }
            return [];
        }

        $jwks = json_decode((string) $response, true);
        $keys = [];
        if (isset($jwks['keys']) && is_array($jwks['keys'])) {
            foreach ($jwks['keys'] as $k) {
                if (!empty($k['kid'])) {
                    $keys[$k['kid']] = $k;
                }
            }
        }

        file_put_contents($cacheFile, json_encode($keys));
        return $keys;
    }

    /**
     * Convert an RSA JWK (n, e) into standard PKCS#1 / X.509 PEM public key.
     */
    private function jwkToPem(array $jwk): ?string
    {
        if (empty($jwk['n']) || empty($jwk['e'])) {
            return null;
        }

        $n = self::base64UrlDecode($jwk['n']);
        $e = self::base64UrlDecode($jwk['e']);

        // Build ASN.1 sequence for RSA Public Key
        $nLength = strlen($n);
        $eLength = strlen($e);

        // Prepend 0x00 if MSB is 1 (two's complement positive integer in ASN.1 DER)
        if (ord($n[0]) > 0x7f) {
            $n = "\x00" . $n;
            $nLength++;
        }
        if (ord($e[0]) > 0x7f) {
            $e = "\x00" . $e;
            $eLength++;
        }

        $derN = "\x02" . self::encodeDerLength($nLength) . $n;
        $derE = "\x02" . self::encodeDerLength($eLength) . $e;
        $derRsaPub = "\x30" . self::encodeDerLength(strlen($derN . $derE)) . $derN . $derE;

        // Wrap in SubjectPublicKeyInfo structure
        $rsaOid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption, NULL
        $bitString = "\x03" . self::encodeDerLength(strlen($derRsaPub) + 1) . "\x00" . $derRsaPub;
        $spki = "\x30" . self::encodeDerLength(strlen($rsaOid . $bitString)) . $rsaOid . $bitString;

        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($spki), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    private static function encodeDerLength(int $len): string
    {
        if ($len <= 0x7f) {
            return chr($len);
        }
        if ($len <= 0xff) {
            return "\x81" . chr($len);
        }
        return "\x82" . chr($len >> 8) . chr($len & 0xff);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=', STR_PAD_RIGHT));
    }
}
