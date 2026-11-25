<?php

declare(strict_types=1);

namespace CrewWeaver\Tests\Unit;

use CrewWeaver\Authentication\OidcClient;
use CrewWeaver\Configuration\Configuration;
use CrewWeaver\Core\Session;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OidcClientTest extends TestCase
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _client(): OidcClient
    {
        $configuration=new Configuration(['OIDC_ISSUER'=>'https://identity.example','OIDC_CLIENT_ID'=>'crewweaver','OIDC_BOOTSTRAP_ISSUER'=>'https://identity.example','OIDC_BOOTSTRAP_SUBJECT'=>'admin-subject','OIDC_TRUSTED_APPROVED_GROUPS'=>'crewweaver-members','OIDC_TRUSTED_GLOBAL_ADMIN_GROUPS'=>'crewweaver-admins']);return new OidcClient($configuration,new Session($configuration));
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function testProfileUsesIssuerAndSubjectIdentity(): void
    {
        $profile=$this->_client()->profile(['sub'=>'stable-subject','email'=>'new@example.com','given_name'=>'Ada','family_name'=>'Lovelace','name'=>'Ada L.','picture'=>'https://example.com/a.png','groups'=>['crewweaver-members']]);self::assertSame('https://identity.example',$profile['issuer']);self::assertSame('stable-subject',$profile['subject']);self::assertSame('new@example.com',$profile['email']);self::assertTrue($this->_client()->isTrustedApproved($profile));
    }

    public function testBootstrapRequiresExactVerifiedIdentityOrTrustedGroup(): void
    {
        $client=$this->_client();$exact=['issuer'=>'https://identity.example','subject'=>'admin-subject','groups'=>[]];$impostor=['issuer'=>'https://identity.example','subject'=>'someone-else','groups'=>[]];$mapped=['issuer'=>'https://identity.example','subject'=>'mapped','groups'=>['crewweaver-admins']];self::assertTrue($client->isBootstrapAdministrator($exact));self::assertFalse($client->isBootstrapAdministrator($impostor));self::assertTrue($client->isBootstrapAdministrator($mapped));
    }

    public static function tokenTimeCases(): array
    {
        return [
            'provider 40 seconds ahead' => [40, 3600, null, null, null],
            'provider beyond default tolerance' => [90, 3600, null, null, BeforeValidException::class],
            'zero tolerance' => [40, 3600, null, 0, BeforeValidException::class],
            'configured tolerance is capped' => [150, 3600, null, 300, BeforeValidException::class],
            'future issue time with a valid not-before claim' => [90, 3600, -30, null, RuntimeException::class],
            'expired token within the skew window' => [-3600, -10, null, null, RuntimeException::class],
        ];
    }

    #[DataProvider('tokenTimeCases')]
    public function testExchangeValidatesSignedTokenTimes(
        int $issuedOffset,
        int $expirationOffset,
        ?int $notBeforeOffset,
        ?int $configuredSkew,
        ?string $expectedException
    ): void {
        $issuer = 'https://identity.example';
        $environment = [
            'OIDC_ISSUER' => $issuer,
            'OIDC_CLIENT_ID' => 'crewweaver',
            'OIDC_CLIENT_SECRET' => 'test-secret',
            'OIDC_REDIRECT_URI' => 'http://127.0.0.1:20123/auth/callback',
        ];
        if ($configuredSkew !== null) {
            $environment['OIDC_CLOCK_SKEW_SECONDS'] = $configuredSkew;
        }
        $configuration = new Configuration($environment);
        $session = $this->createMock(Session::class);
        $now = time();
        $session->method('get')->willReturnMap([
            ['oidc_state', null, 'state'],
            ['oidc_started_at', 0, $now],
            ['oidc_verifier', null, 'verifier'],
            ['oidc_nonce', null, 'nonce'],
        ]);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($key);
        $keyDetails = openssl_pkey_get_details($key);
        self::assertNotFalse($keyDetails);
        $claims = [
            'iss' => $issuer,
            'sub' => 'verified-user',
            'aud' => 'crewweaver',
            'nonce' => 'nonce',
            'iat' => $now + $issuedOffset,
            'exp' => $now + $expirationOffset,
        ];
        if ($notBeforeOffset !== null) {
            $claims['nbf'] = $now + $notBeforeOffset;
        }
        $client = $this->getMockBuilder(OidcClient::class)
            ->setConstructorArgs([$configuration, $session])
            ->onlyMethods(['_request'])
            ->getMock();
        $client->method('_request')->willReturnMap([
            [$issuer . '/.well-known/openid-configuration', null, [
                'issuer' => $issuer,
                'token_endpoint' => $issuer . '/token',
                'jwks_uri' => $issuer . '/jwks',
            ]],
            [$issuer . '/token', [
                'grant_type' => 'authorization_code',
                'code' => 'code',
                'redirect_uri' => $environment['OIDC_REDIRECT_URI'],
                'client_id' => 'crewweaver',
                'client_secret' => 'test-secret',
                'code_verifier' => 'verifier',
            ], ['id_token' => JWT::encode($claims, $key, 'RS256', 'test-key')]],
            [$issuer . '/jwks', null, ['keys' => [[
                'kid' => 'test-key',
                'kty' => 'RSA',
                'alg' => 'RS256',
                'n' => JWT::urlsafeB64Encode($keyDetails['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($keyDetails['rsa']['e']),
            ]]]],
        ]);

        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }
        $previousLeeway = JWT::$leeway;
        try {
            self::assertSame('verified-user', $client->exchange('code', 'state')['sub']);
        } finally {
            self::assertSame($previousLeeway, JWT::$leeway);
        }
    }
}
