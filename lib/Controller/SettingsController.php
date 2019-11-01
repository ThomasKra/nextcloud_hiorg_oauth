<?php

namespace OCA\HiorgOAuth\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCA\HiorgOAuth\Db\SocialConnectDAO;

class SettingsController extends Controller
{
    /** @var IConfig */
    private $config;
    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var IUserSession */
    private $userSession;
    /** @var IL10N */
    private $l;
    /** @var SocialConnectDAO */
    private $socialConnect;

    public function __construct(
        $appName,
        IRequest $request,
        IConfig $config,
        IURLGenerator $urlGenerator,
        IUserSession $userSession,
        IL10N $l,
        SocialConnectDAO $socialConnect
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->l = $l;
        $this->socialConnect = $socialConnect;
    }

    public function saveAdmin(
        $disable_registration,
        $allow_login_connect,
        $prevent_create_email_exists,
        $update_profile_on_login,
        $providers
    ) {
        $this->config->setAppValue($this->appName, 'disable_registration', $disable_registration ? true : false);
        $this->config->setAppValue($this->appName, 'allow_login_connect', $allow_login_connect ? true : false);
        $this->config->setAppValue($this->appName, 'prevent_create_email_exists', $prevent_create_email_exists ? true : false);
        $this->config->setAppValue($this->appName, 'update_profile_on_login', $update_profile_on_login ? true : false);
        $this->config->setAppValue($this->appName, 'oauth_providers', json_encode($providers));

        
        return new JSONResponse(['success' => true]);
    }

    /**
     * @NoAdminRequired
     * @PasswordConfirmationRequired
     */
    public function savePersonal($disable_password_confirmation)
    {
        $uid = $this->userSession->getUser()->getUID();
        $this->config->setUserValue($uid, $this->appName, 'disable_password_confirmation', $disable_password_confirmation ? 1 : 0);
        return new JSONResponse(['success' => true]);
    }

    /**
     * @NoAdminRequired
     */
    public function disconnecthiorgoauth($login)
    {
        $this->socialConnect->disconnectLogin($login);
        return new RedirectResponse($this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section'=>'hiorgoauth']));
    }
}
