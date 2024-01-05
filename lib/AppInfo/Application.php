<?php

namespace OCA\HiorgOAuth\AppInfo;

use OCP\AppFramework\App;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\ISession;
use OCP\IUser;
use OCP\Util;
use OCA\HiorgOAuth\Db\SocialConnectDAO;
use OCA\HiorgOAuth\Provider\HiorgLogin;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap
{
  private $appName = 'hiorg_oauth';

  /** @var IConfig */
  private $config;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var IRegistrationContext */
  private $regContext;

  public function __construct()
  {
    parent::__construct($this->appName);
  }

  public function register(IRegistrationContext $context): void
  {
    require __DIR__ . '/../../3rdparty/autoload.php';
    $this->regContext = $context;
  }

  public function boot(IBootContext $context): void
  {
    // CSS registrieren
    Util::addStyle($this->appName, 'style');

    // Zugriffsmöglichkeit auf die Konfiguration
    $this->config = $this->getContainer()->get(IConfig::class);

    // PreDelete Hook registrieren
    $this->getContainer()->get(IUserManager::class)->listen('\OC\User', 'preDelete', [$this, 'preDeleteUser']);

    // UserSession abfragen
    $userSession = $this->getContainer()->get(IUserSession::class);
    // Wenn angemeldet
    if ($userSession->isLoggedIn()) {
      // UId und Session holen
      $uid = $userSession->getUser()->getUID();
      $session = $this->getContainer()->get(ISession::class);

      if ($this->config->getUserValue($uid, $this->appName, 'disable_password_confirmation')) {
        $session->set('last-password-confirm', time());
      }
      return;
    }

    // URL Generator holen
    $this->urlGenerator = $this->getContainer()->get(IURLGenerator::class);

    // TODO: Braucht es die Abfrage wirklich? Sollte nicht besser abgefragt werden, ob die Keys existieren?
    $providers = json_decode($this->config->getAppValue($this->appName, 'hiorg_oauth_settings', '[]'), true);
    if (is_array($providers)) {
      // URL für Login generieren
      $authUrl = $this->urlGenerator->linkToRoute($this->appName . '.login.hiorg');
      // Login erstellen und registrieren
      HiorgLogin::addLogin(
        'Mit HiOrg anmelden',
        $authUrl,
        'hiorg_oauth'
      );
      $this->regContext->registerAlternativeLogin(HiorgLogin::class);
    }
  }

  /**
   * Hook vor dem Löschen des Benutzers
   * @return void
   */
  public function preDeleteUser(IUser $user): void
  {
    // Vor dem Löschen, alle Benutzer mit dieser ID abmelden
    $this->getContainer()->get(SocialConnectDAO::class)->disconnectAll($user->getUID());
  }
}
