<?php

declare(strict_types=1);

namespace CrewWeaver\Authentication;

use CrewWeaver\Configuration\Configuration;
use CrewWeaver\Core\Session;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;

class OidcClient
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected Configuration $_configuration;
    protected Session $_session;
    protected ?array $_discovery = null;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(Configuration $configuration, Session $session)
    {
        $this->_configuration = $configuration;
        $this->_session = $session;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function _request(string $url, ?array $form = null): array
    {
        $handle = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ];
        if ($form !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('OIDC provider request failed.');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OIDC provider returned invalid JSON.');
        }
        return $decoded;
    }

    protected function _discovery(): array
    {
        if ($this->_discovery === null) {
            $issuer = rtrim($this->_configuration->get('OIDC_ISSUER'), '/');
            $this->_discovery = $this->_request($issuer . '/.well-known/openid-configuration');
            if (($this->_discovery['issuer'] ?? null) !== $issuer) {
                throw new RuntimeException('OIDC discovery issuer mismatch.');
            }
        }
        return $this->_discovery;
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function authorizationUrl(): string
    {
        $discovery = $this->_discovery();
        $state = $this->_base64Url(random_bytes(32));
        $nonce = $this->_base64Url(random_bytes(32));
        $verifier = $this->_base64Url(random_bytes(64));
        $this->_session->set('oidc_state', $state);
        $this->_session->set('oidc_nonce', $nonce);
        $this->_session->set('oidc_verifier', $verifier);
        $this->_session->set('oidc_started_at', time());
        $parameters = [
            'response_type' => 'code',
            'client_id' => $this->_configuration->get('OIDC_CLIENT_ID'),
            'redirect_uri' => $this->_configuration->get('OIDC_REDIRECT_URI'),
            'scope' => $this->_configuration->get('OIDC_SCOPES', 'openid profile email'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->_base64Url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ];
        return $discovery['authorization_endpoint'] . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $state): array
    {
        $expectedState = $this->_session->get('oidc_state');
        $startedAt = (int) $this->_session->get('oidc_started_at', 0);
        if (!is_string($expectedState) || !hash_equals($expectedState, $state) || time() - $startedAt > 600) {
            throw new RuntimeException('OIDC state is invalid or expired.');
        }
        $discovery = $this->_discovery();
        $tokens = $this->_request($discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->_configuration->get('OIDC_REDIRECT_URI'),
            'client_id' => $this->_configuration->get('OIDC_CLIENT_ID'),
            'client_secret' => $this->_configuration->get('OIDC_CLIENT_SECRET'),
            'code_verifier' => (string) $this->_session->get('oidc_verifier'),
        ]);
        if (!isset($tokens['id_token'])) {
            throw new RuntimeException('OIDC response omitted the ID token.');
        }
        $keys = JWK::parseKeySet($this->_request($discovery['jwks_uri']));
        $clockSkew = max(0, min(120, $this->_configuration->int('OIDC_CLOCK_SKEW_SECONDS', 60)));
        $previousLeeway = JWT::$leeway;
        try {
            JWT::$leeway = $clockSkew;
            $claims = (array) JWT::decode($tokens['id_token'], $keys);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
        $issuer = rtrim($this->_configuration->get('OIDC_ISSUER'), '/');
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        if (($claims['iss'] ?? null) !== $issuer || !in_array($this->_configuration->get('OIDC_CLIENT_ID'), $audiences, true)) {
            throw new RuntimeException('OIDC token issuer or audience is invalid.');
        }
        if (count($audiences) > 1 && ($claims['azp'] ?? null) !== $this->_configuration->get('OIDC_CLIENT_ID')) {
            throw new RuntimeException('OIDC authorized party is invalid.');
        }
        $expectedNonce = $this->_session->get('oidc_nonce');
        if (!is_string($expectedNonce) || !isset($claims['nonce']) || !hash_equals($expectedNonce, (string) $claims['nonce'])) {
            throw new RuntimeException('OIDC nonce is invalid.');
        }
        $now = time();
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === ''
            || (!is_int($claims['exp'] ?? null) && !is_float($claims['exp'] ?? null))
            || $claims['exp'] <= $now) {
            throw new RuntimeException('OIDC token is missing required claims or expired.');
        }
        if ((!is_int($claims['iat'] ?? null) && !is_float($claims['iat'] ?? null))
            || $claims['iat'] > $now + $clockSkew) {
            throw new RuntimeException('OIDC token issue time is missing or too far in the future.');
        }
        foreach (['oidc_state', 'oidc_nonce', 'oidc_verifier', 'oidc_started_at'] as $sessionKey) {
            $this->_session->remove($sessionKey);
        }
        return $claims;
    }

    public function profile(array $claims): array
    {
        $claim = fn (string $setting, string $default): mixed => $claims[$this->_configuration->get($setting, $default)] ?? null;
        $subject = (string) $claim('OIDC_SUBJECT_CLAIM', 'sub');
        if ($subject === '') {
            throw new RuntimeException('OIDC subject claim is empty.');
        }
        $displayName = (string) ($claim('OIDC_DISPLAY_NAME_CLAIM', 'name') ?: $claim('OIDC_EMAIL_CLAIM', 'email') ?: $subject);
        return [
            'issuer' => rtrim($this->_configuration->get('OIDC_ISSUER'), '/'),
            'subject' => $subject,
            'email' => $claim('OIDC_EMAIL_CLAIM', 'email'),
            'first_name' => $claim('OIDC_GIVEN_NAME_CLAIM', 'given_name'),
            'last_name' => $claim('OIDC_FAMILY_NAME_CLAIM', 'family_name'),
            'display_name' => $displayName,
            'avatar_url' => $claim('OIDC_AVATAR_CLAIM', 'picture'),
            'groups' => (array) $claim('OIDC_GROUPS_CLAIM', 'groups'),
        ];
    }

    public function isTrustedApproved(array $profile): bool
    {
        return count(array_intersect($profile['groups'], $this->_configuration->csv('OIDC_TRUSTED_APPROVED_GROUPS'))) > 0;
    }

    public function isBootstrapAdministrator(array $profile): bool
    {
        $identityMatch = hash_equals((string) $this->_configuration->optional('OIDC_BOOTSTRAP_ISSUER'), $profile['issuer'])
            && hash_equals((string) $this->_configuration->optional('OIDC_BOOTSTRAP_SUBJECT'), $profile['subject']);
        $groupMatch = count(array_intersect($profile['groups'], $this->_configuration->csv('OIDC_TRUSTED_GLOBAL_ADMIN_GROUPS'))) > 0;
        return $identityMatch || $groupMatch;
    }
}
