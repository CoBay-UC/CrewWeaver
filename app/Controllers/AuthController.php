<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Authentication\CsrfToken;
use CrewWeaver\Authentication\OidcClient;
use CrewWeaver\Configuration\Configuration;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Core\View;
use CrewWeaver\Models\UserRepository;
use Throwable;

class AuthController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected Configuration $_configuration;
    protected AuthenticationService $_authentication;
    protected OidcClient $_oidc;
    protected UserRepository $_users;
    protected View $_view;
    protected CsrfToken $_csrf;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(Configuration $configuration, AuthenticationService $authentication, OidcClient $oidc, UserRepository $users, View $view, CsrfToken $csrf)
    {
        $this->_configuration = $configuration;
        $this->_authentication = $authentication;
        $this->_oidc = $oidc;
        $this->_users = $users;
        $this->_view = $view;
        $this->_csrf=$csrf;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */
    protected function _message(Throwable $exception): string
    {
        error_log($exception->__toString());
        return $this->_configuration->bool('APP_DEBUG')?$exception->getMessage():'Authentication could not be completed. Check the identity provider configuration or try again.';
    }

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function login(Request $request): Response
    {
        if ($this->_authentication->currentUser() !== null) { return Response::redirect('/'); }
        try {
            return Response::redirect($this->_oidc->authorizationUrl());
        } catch (Throwable $exception) {
            return $this->_view->render('Users/login', ['title' => 'Sign in', 'error' => $this->_message($exception)], 'Layouts/authentication');
        }
    }

    public function callback(Request $request): Response
    {
        try {
            $claims = $this->_oidc->exchange((string) $request->query('code'), (string) $request->query('state'));
            $profile = $this->_oidc->profile($claims);
            $administrator = $this->_oidc->isBootstrapAdministrator($profile);
            $user = $this->_users->provision($profile, $administrator || $this->_oidc->isTrustedApproved($profile), $administrator);
            $this->_authentication->login($user);
            return Response::redirect($user['status'] === 'active' ? '/' : '/pending');
        } catch (Throwable $exception) {
            return $this->_view->render('Users/login', ['title' => 'Sign in failed', 'error' => $this->_message($exception)], 'Layouts/authentication');
        }
    }

    public function pending(Request $request): Response
    {
        return $this->_view->render('Users/pending', ['title' => 'Approval pending', 'user' => $this->_authentication->currentUser()?->record(),'csrfToken'=>$this->_csrf->value()], 'Layouts/authentication');
    }

    public function logout(Request $request): Response
    {
        $this->_authentication->logout();
        return Response::redirect($this->_configuration->get('OIDC_LOGOUT_URI', '/'));
    }
}
