<?php

namespace OCA\HiorgOAuth\AppInfo;

use OCP\AppFramework\App;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\Util;
use OCA\HiorgOAuth\Db\SocialConnectDAO;
use OCA\HiorgOAuth\Provider\Hiorg;
use OCA\HiorgOAuth\Provider\HiorgLogin;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\EventDispatcher\IEventDispatcher;


class Application extends App implements IBootstrap
{
    private $appName = 'hiorg_oauth';

    private $providersCount = 0;

    private $providerUrl;

    private $redirectUrl;
    /** @var IConfig */
    private $config;
    /** @var IURLGenerator */
    private $urlGenerator;

    private $regContext;

    public function __construct()
    {
        parent::__construct($this->appName);
    }

    public function register(IRegistrationContext $context) : void {
    
        require __DIR__ . '/../../3rdparty/autoload.php';
        $this->regContext = $context;
    }
    public function boot(IBootContext $context): void
{

        Util::addStyle($this->appName, 'style');

        $this->config = $this->query(IConfig::class);

        $this->query(IUserManager::class)->listen('\OC\User', 'preDelete', [$this, 'preDeleteUser']);

        $userSession = $this->query(IUserSession::class);
        if ($userSession->isLoggedIn()) {
            $uid = $userSession->getUser()->getUID();
            $session = $this->query(ISession::class);
            if ($this->config->getUserValue($uid, $this->appName, 'disable_password_confirmation')) {
                $session->set('last-password-confirm', time());
            }
            if ($logoutUrl = $session->get('hiorgoauth_logout_url')) {
                $userSession->listen('\OC\User', 'postLogout', function () use ($logoutUrl) {
                    header('Location: ' . $logoutUrl);
                    exit();
                });
            }
            return;
        }

        $this->urlGenerator = $this->query(IURLGenerator::class);
        $request = $this->query(IRequest::class);
        $this->redirectUrl = $request->getParam('redirect_url');

        $providers = json_decode($this->config->getAppValue($this->appName, 'hiorg_oauth_settings', '[]'), true);
        if (is_array($providers)) {
           $authUrl = $this->urlGenerator->linkToRoute($this->appName.'.login.hiorg');
        HiorgLogin::addLogin(
            'Mit HiOrg anmelden',
            $authUrl,
            'hiorg_oauth'
        );
        $this->regContext->registerAlternativeLogin(HiorgLogin::class);
        }
    }

    public function preDeleteUser(IUser $user)
    {
        $this->query(SocialConnectDAO::class)->disconnectAll($user->getUID());
    }

    private function query($className)
    {
        return $this->getContainer()->query($className);
    }
}
