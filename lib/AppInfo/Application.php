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
use OCA\HiorgOAuth\Db\SocialConnectDAO;

class Application extends App
{
    private $appName = 'hiorg_oauth';

    private $providersCount = 0;

    private $providerUrl;

    private $redirectUrl;
    /** @var IConfig */
    private $config;
    /** @var IURLGenerator */
    private $urlGenerator;

    public function __construct()
    {
        parent::__construct($this->appName);
    }

    public function register()
    {
        \OCP\Util::addStyle($this->appName, 'style');

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

        $providers = json_decode($this->config->getAppValue($this->appName, 'oauth_providers', '[]'), true);
        if (is_array($providers)) {
            foreach ($providers as $name => $provider) {
                if ($provider['appid']) {
                    ++$this->providersCount;
                    $this->providerUrl = $this->urlGenerator->linkToRoute($this->appName.'.login.hiorg', [
                        'provider' => $name,
                        'login_redirect_url' => $this->redirectUrl
                    ]);
                    \OC_App::registerLogIn([
                        'name' => 'Anmelden mit ' .ucfirst($name),
                        'href' => $this->providerUrl,
                    ]);
                }
            }
        }

        $useLoginRedirect = $this->providersCount === 1
            && PHP_SAPI !== 'cli'
            && $this->config->getSystemValue('social_login_auto_redirect', false);
        if ($useLoginRedirect && $request->getPathInfo() === '/login') {
            header('Location: ' . $this->providerUrl);
            exit();
        }
    }

    public function preDeleteUser(IUser $user)
    {
        $this->query(SocialConnectDAO::class)->disconnectAll($user->getUID());
    }

    private function addAltLogins($providersType)
    {
        $providers = json_decode($this->config->getAppValue($this->appName, $providersType.'_providers', '[]'), true);
        if (is_array($providers)) {
            foreach ($providers as $provider) {
                ++$this->providersCount;
                $this->providerUrl = $this->urlGenerator->linkToRoute($this->appName.'.login.'.$providersType, [
                    'provider' => $provider['name'],
                    'login_redirect_url' => $this->redirectUrl
                ]);
                \OC_App::registerLogIn([
                    'name' => $provider['title'],
                    'href' => $this->providerUrl,
                ]);
            }
        }
    }

    private function query($className)
    {
        return $this->getContainer()->query($className);
    }
}
