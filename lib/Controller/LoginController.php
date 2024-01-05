<?php

namespace OCA\HiorgOAuth\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\IAvatarManager;
use OCP\IGroupManager;
use OCP\ISession;
use Psr\Log\LoggerInterface;
use OCP\Mail\IMailer;
use OCA\HiorgOAuth\Storage\SessionStorage;
use OCA\HiorgOAuth\Db\SocialConnectDAO;
use OCA\HiorgOAuth\HiOrgLoginException;
use Hybridauth\HttpClient\Curl;
use OCA\HiorgOAuth\Provider\Hiorg;

class LoginController extends Controller
{
  /** @var IConfig */
  private $config;
  /** @var IURLGenerator */
  private $urlGenerator;
  /** @var SessionStorage */
  private $storage;
  /** @var IUserManager */
  private $userManager;
  /** @var IUserSession */
  private $userSession;
  /** @var IAvatarManager */
  private $avatarManager;
  /** @var IGroupManager */
  private $groupManager;
  /** @var ISession */
  private $session;
  /** @var IL10N */
  private $l;
  /** @var IMailer */
  private $mailer;
  /** @var SocialConnectDAO */
  private $socialConnect;

  /** @var LoggerInterface */
  private $logger;


  public function __construct(
    $appName,
    IRequest $request,
    IConfig $config,
    IURLGenerator $urlGenerator,
    SessionStorage $storage,
    IUserManager $userManager,
    IUserSession $userSession,
    IAvatarManager $avatarManager,
    IGroupManager $groupManager,
    ISession $session,
    IL10N $l,
    IMailer $mailer,
    SocialConnectDAO $socialConnect,
    LoggerInterface $logger
  ) {
    parent::__construct($appName, $request);
    $this->config = $config;
    $this->urlGenerator = $urlGenerator;
    $this->storage = $storage;
    $this->userManager = $userManager;
    $this->userSession = $userSession;
    $this->avatarManager = $avatarManager;
    $this->groupManager = $groupManager;
    $this->session = $session;
    $this->l = $l;
    $this->mailer = $mailer;
    $this->socialConnect = $socialConnect;
    $this->logger = $logger;
  }

  /**
   * @PublicPage
   * @NoCSRFRequired
   * @UseSession
   * @return RedirectResponse
   */
  public function hiorg(): RedirectResponse
  {
    $config = [];

    // Konfiguration laden
    $hiorgSettings = json_decode($this->config->getAppValue($this->appName, 'hiorg_oauth_settings', '[]'), true);

    if (is_array($hiorgSettings)) {
      $callbackUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . '.login.hiorg');
      $config = [
        'api_version' => $hiorgSettings['api_version'],
        'callback' => $callbackUrl,
        'keys'     => [
          'id'     => $hiorgSettings['appid'],
          'secret' => $hiorgSettings['secret'],
        ],
        'default_group' => $hiorgSettings['defaultGroup'],
        'orga' => $hiorgSettings['orga'],
        'group_mapping' => $hiorgSettings['group_mapping'],
        'quota' => $hiorgSettings['quota'],
      ];

      $config['authorize_url_parameters']['orga'] = $hiorgSettings['orga'];
      if (isset($hiorgSettings['auth_params']) && is_array($hiorgSettings['auth_params'])) {
        foreach ($hiorgSettings['auth_params'] as $k => $v) {
          if (!empty($v)) {
            $config['authorize_url_parameters'][$k] = $v;
          }
        }
      }
    }
    
    // Prüfen, ob es eine Konfiguration gibt
    if (empty($config)) {
      throw new HiOrgLoginException($this->l->t('Unknown config for HiOrg Login'));
    }
    $this->logger->debug('Config: '.json_encode($config));
    if ($redirectUrl = $this->request->getParam('login_redirect_url')) {
      $this->session->set('login_redirect_url', $redirectUrl);
    }
    // Versuchen über den HiOrg zu Authentifizieren
    try {
      $adapter = new Hiorg($this->logger, $config, null, $this->storage);
      $adapter->authenticate();
      $profile = $adapter->getUserProfile();
    } catch (\Exception $e) {
      throw new HiOrgLoginException($e->getMessage());
    }
    // Profile ID abfragen (Benutzername - NachnameV@OV)
    $profileId = preg_replace('#.*/#', '', rtrim($profile->identifier, '/'));
    if (empty($profileId)) {
      throw new HiOrgLoginException($this->l->t('Can not get identifier from provider'));
    }

    // Standard-Gruppe mit aufnehmen
    $profile->data['default_group'] = $config['default_group'];

    // UserId
    $uid = $profileId;
    
    // Benuter-Objekt abrufen
    $user = $this->userManager->get($uid);
    if (null === $user) {
      $connectedUid = $this->socialConnect->findUID($uid);
      $user = $this->userManager->get($connectedUid);
    }
    // Prüfen, ob bereits angmeldet
    if ($this->userSession->isLoggedIn()) {
      throw new HiOrgLoginException($this->l->t('HiOrg login connect is disabled'));
    }

    // Wenn Benutzer nicht existiert, dann anlegen, wenn erlaubt
    if (null === $user) {
      $this->logger->debug('Benutzer existiert nicht -> anlegen, wenn erlaubt');
      if ($this->config->getAppValue($this->appName, 'disable_registration')) {
        throw new HiOrgLoginException($this->l->t('Auto creating new users is disabled'));
      }
      if (
        $profile->email && $this->config->getAppValue($this->appName, 'prevent_create_email_exists')
        && count($this->userManager->getByEmail($profile->email)) !== 0
      ) {
        throw new HiOrgLoginException($this->l->t('Email already registered'));
      }
      /**
       * @disregard P1010 Undefined function
       */ 
      $password = substr(base64_encode(random_bytes(64)), 0, 30);
      $user = $this->userManager->createUser($uid, $password);

      // Quota setzen
      if (!empty($profile->data['quota'])) {
        $user->setQuota($profile->data['quota']);
      }

      $this->config->setUserValue($uid, $this->appName, 'disable_password_confirmation', 1);

      $this->logger->debug('Admins per Email informieren');
      $this->notifyAdmins($uid, $profile->displayName ?: $profile->identifier, $profile->data['default_group']);
    }
    // Benutzername und Email Adresse setzen
    $user->setDisplayName($profile->displayName ?: $profile->identifier);
    $user->setSystemEMailAddress((string)$profile->email);


    if ($profile->photoURL) {
      $curl = new Curl();
      try {
        $photo = $curl->request($profile->photoURL);
        $avatar = $this->avatarManager->getAvatar($uid);
        $avatar->set($photo);
      } catch (\Exception $e) {
      }
    }

    // Gruppen nach dem Mapping zuweisen
    if (!empty($profile->data['group_mapping']) && is_array($profile->data['group_mapping'])) {
      $groupNames = $profile->data['group_mapping'];
      $userGroup = $profile->data['gruppe'];

      $this->logger->debug('Gruppensumme: ' . $userGroup);

      for ($i = 0; $i < 11; $i++) {
        $num = strval(2 ** $i);
        if ($groupNames['id_' . $num] !== '') {
          $this->logger->debug("HiOrg-Gruppe ($num) ist der Nextcloud-Gruppe (" . strval($groupNames['id_' . $num]) . ") zugewiesen.");
          if ($this->groupManager->groupExists($groupNames['id_' . $num])) {
            $group = $this->groupManager->get($groupNames['id_' . $num]);
            if ($userGroup & 2 ** $i) /* 2^i */ {
              $this->logger->debug('Benutzer ist in Gruppe ' . (2 ** $i));

              // user has this HiOrg-Server group
              // check if user is already a member or add user to group

              if (!$group->inGroup($user)) {
                $group->addUser($user);
                $this->logger->info("Benutzer ( $profile->displayName) zur Gruppe (" . strval($groupNames['id_' . $num]) . ") hinzugefügt.");
              } else {
                $this->logger->debug("Benutzer ( $profile->displayName) ist bereits in der Gruppe (" . strval($groupNames['id_' . $num]) . ") -> nichts zu tun.");
              }
            } else {
              // user does NOT have this HiOrg-Server group
              // check if user is already a member and remove from group 
              if ($group->inGroup($user)) {
                $group->removeUser($user);
                $this->logger->info("Benutzer ( $profile->displayName) aus Gruppe (" . $groupNames['id_' . $num] . ") entfernt.");
              } else {
                $this->logger->debug("Benutzer ( $profile->displayName) ist nicht in der Gruppe (" . $groupNames['id_' . $num] . ") -> nichts zu tun.");
              }
            }
          } else {
            $this->logger->warning("Gruppe (" . strval($groupNames['id_' . $num]) . ") existiert nicht!");
          }
        }
      }
    }

    //  Standard-Gruppe zuweisen
    $defaultGroup = $profile->data['default_group'];
    if ($defaultGroup && $group = $this->groupManager->get($defaultGroup)) {
      $group->addUser($user);
    }

    // Login abschließen
    /**
     * @disregard P1013 Undefined method
     */ 
    $this->userSession->completeLogin($user, ['loginName' => $user->getUID(), 'password' => '']);
    /**
     * @disregard P1013 Undefined method
     */ 
    $this->userSession->createSessionToken($this->request, $user->getUID(), $user->getUID());

    $this->session->set('last-password-confirm', time());

    // Zur Cloud weiterleiten
    return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
  }

  /**
   * @param string|string[]|null $uid 
   * @param string $displayName
   * @param string $groupId 
   */
  private function notifyAdmins($uid, $displayName, $groupId): void
  {
    $admins = $this->groupManager->get('admin')->getUsers();
    if ($groupId) {
      $group = $this->groupManager->get($groupId);
      $subAdmins = $this-> $this->groupManager->getSubAdmin()->getGroupsSubAdmins($group);
      foreach ($subAdmins as $user) {
        if (!in_array($user, $admins)) {
          $admins[] = $user;
        }
      }
    }

    $sendTo = [];
    foreach ($admins as $user) {
      $email = $user->getEMailAddress();
      if ($email && $user->isEnabled()) {
        $sendTo[$email] = $user->getDisplayName() ?: $user->getUID();
      }
    }

    if ($sendTo) {
      $template = $this->mailer->createEMailTemplate('hiorgoauth.NewUser');

      $template->setSubject($this->l->t('New user created'));
      $template->addHeader();
      $template->addBodyText($this->l->t('User %s (%s) just created via hiorg oauth', [$displayName, $uid]));
      $template->addFooter();

      $message = $this->mailer->createMessage();
      $message->setTo($sendTo);
      $message->useTemplate($template);
      try {
        $errors = $this->mailer->send($message);
      } catch (\Exception $ex) {
        $this->logger->error("Email an Admins konnte nicht geschickt werden.");
      }
    }
  }
}

