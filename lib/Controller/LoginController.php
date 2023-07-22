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
use OCP\ILogger;
use OCP\Mail\IMailer;
use OC\User\LoginException;
use OCA\HiorgOAuth\Storage\SessionStorage;
use OCA\HiorgOAuth\Db\SocialConnectDAO;
use Hybridauth\User\Profile;
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

    /** @var ILogger */
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
        ILogger $logger
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
     */
    public function hiorg()
    {
        $config = [];

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
      
        return $this->auth($config);
    }
    
    private function auth(array $config)
    {
        if (empty($config)) {
            throw new LoginException($this->l->t('Unknown config for HiOrg Login'));
        }
        if ($redirectUrl = $this->request->getParam('login_redirect_url')) {
            $this->session->set('login_redirect_url', $redirectUrl);
        }

        try {
            $adapter = new Hiorg($config, null, $this->storage);
            $adapter->authenticate();
            $profile = $adapter->getUserProfile();
        }  catch (\Exception $e) {
            throw new LoginException($e->getMessage());
        }
        $profileId = preg_replace('#.*/#', '', rtrim($profile->identifier, '/'));
        if (empty($profileId)) {
            throw new LoginException($this->l->t('Can not get identifier from provider'));
        }

        if (!empty($config['authorize_url_parameters']['hd'])) {
            $profileHd = preg_match('#@(.+)#', $profile->email, $m) ? $m[1] : null;
            if ($config['authorize_url_parameters']['hd'] !== $profileHd) {
                $this->storage->clear();
                throw new LoginException($this->l->t('Login from %s domain is not allowed for HiOrg provider', [$profileHd]));
            }
        }

        if (!empty($config['logout_url'])) {
            $this->session->set('hiorgoauth_logout_url', $config['logout_url']);
        } else {
            $this->session->remove('hiorgoauth_logout_url');
        }

        $profile->data['default_group'] = $config['default_group'];
        
        $uid = $profileId;
        return $this->login($uid, $profile);
    }

    private function login($uid, Profile $profile, $newGroupPrefix = '')
    {
        $user = $this->userManager->get($uid);
        if (null === $user) {
            $connectedUid = $this->socialConnect->findUID($uid);
            $user = $this->userManager->get($connectedUid);
        }
        if ($this->userSession->isLoggedIn()) {
            if (!$this->config->getAppValue($this->appName, 'allow_login_connect')) {
                throw new LoginException($this->l->t('HiOrg login connect is disabled'));
            }
            if (null !== $user) {
                throw new LoginException($this->l->t('This account already connected'));
            }
            $currentUid = $this->userSession->getUser()->getUID();
            $this->socialConnect->connectLogin($currentUid, $uid);
            return new RedirectResponse($this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section'=>'hiorgoauth']));
        }

        if (null === $user) {
            if ($this->config->getAppValue($this->appName, 'disable_registration')) {
                throw new LoginException($this->l->t('Auto creating new users is disabled'));
            }
            if (
                $profile->email && $this->config->getAppValue($this->appName, 'prevent_create_email_exists')
                && count($this->userManager->getByEmail($profile->email)) !== 0
            ) {
                throw new LoginException($this->l->t('Email already registered'));
            }
            $password = substr(base64_encode(random_bytes(64)), 0, 30);
            $user = $this->userManager->createUser($uid, $password);

            // Quota only set when new user is created
            if(!empty($profile->data['quota']))
            {
                $user->setQuota($profile->data['quota']);
            }

            $this->config->setUserValue($uid, $this->appName, 'disable_password_confirmation', 1);

            $this->notifyAdmins($uid, $profile->displayName ?: $profile->identifier, $profile->data['default_group']);
        }

        $user->setDisplayName($profile->displayName ?: $profile->identifier);
        $user->setEMailAddress((string)$profile->email);
        

        if ($profile->photoURL) {
            $curl = new Curl();
            try {
                $photo = $curl->request($profile->photoURL);
                $avatar = $this->avatarManager->getAvatar($uid);
                $avatar->set($photo);
            } catch (\Exception $e) {}
        }
        
        

        if (!empty($profile->data[ 'group_mapping']) && is_array($profile->data[ 'group_mapping'])) {
            $groupNames = $profile->data[ 'group_mapping'];
            $userGroup = $profile->data['gruppe'];

            $this->logger->debug('User Group: '.$userGroup);

            for ($i = 0; $i < 11; $i++) {
                $num = strval(2 ** $i);
                if ($groupNames['id_'.$num] !== '') {
                    $this->logger->info("HiOrg-Group ($num) is assigned to (" . strval( $groupNames['id_'.$num]) . ").");
                    if ($this->groupManager->groupExists($groupNames['id_'.$num])) {
                        $group = $this->groupManager->get($groupNames['id_'.$num]);
                        if ( $userGroup & 2 ** $i) /* 2^i */ {
                          $this->logger->debug('User is in group '. (2 ** $i));
                            /* 
                            user has this HiOrg-Server group
                            check if user is already a member or add user to group
                            */
                            if (!$group->inGroup($user)) {
                                $group->addUser($user);
                                $this->logger->info("Added user ( $profile->displayName) to group (" . strval($groupNames['id_'.$num]) . ").");
                            } else {
                                $this->logger->info("User ( $profile->displayName) is not in group (" . strval($groupNames['id_'.$num]) . ").");
                            }
                        } else {
                            /*
                            user does NOT have this HiOrg-Server group
                            check if user is already a member and remove from group 
                            */
                            if ($group->inGroup($user)) {
                                $group->removeUser($user);
                                $this->logger->info("Removed user ( $profile->displayName) from group (" . $groupNames['id_'.$num] . ").");
                            } else {
                                $this->logger->info("User ( $profile->displayName) is not in group (" . $groupNames['id_'.$num] . ").");
                            }
                        }
                    } else {
                        $this->logger->warning("Group (" . $this->group_id[$num] . ") does not exist!");
                    }
                }
            }
        }

        $defaultGroup = $profile->data['default_group'];
        if ($defaultGroup && $group = $this->groupManager->get($defaultGroup)) {
            $group->addUser($user);
        }


        $this->userSession->completeLogin($user, ['loginName' => $user->getUID(), 'password' => '']);
        $this->userSession->createSessionToken($this->request, $user->getUID(), $user->getUID());

        if ($redirectUrl = $this->session->get('login_redirect_url')) {
            return new RedirectResponse($redirectUrl);
        }

        $this->session->set('last-password-confirm', time());

        return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
    }

    private function notifyAdmins($uid, $displayName, $groupId)
    {
        $admins = $this->groupManager->get('admin')->getUsers();
        if ($groupId) {
            $group = $this->groupManager->get($groupId);
            $subAdmins = $this->groupManager->getSubAdmin()->getGroupsSubAdmins($group);
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
            } catch(\Exception $ex)
            {
                $this->logger->error("Email an Admins konnte nicht geschickt werden.");
            }
        }
    }
}
