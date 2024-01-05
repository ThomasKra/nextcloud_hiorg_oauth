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

    /**
     * Speichern der Einstellungen für das HiOrg Plugin
     * 
     * @param mixed $disable_registration
     * @param mixed $allow_login_connect
     * @param mixed $prevent_create_email_exists
     * @param mixed $hiorgSettings
     * @return JSONResponse 
     */
    public function saveAdmin(bool $disable_registration, bool $allow_login_connect, bool $prevent_create_email_exists, $hiorgSettings): JSONResponse {
    $this->config->setAppValue($this->appName, 'disable_registration', $disable_registration ? true : false);
    $this->config->setAppValue($this->appName, 'allow_login_connect', $allow_login_connect ? true : false);
    $this->config->setAppValue($this->appName, 'prevent_create_email_exists', $prevent_create_email_exists ? true : false);
    $this->config->setAppValue($this->appName, 'hiorg_oauth_settings', json_encode($hiorgSettings));

    return new JSONResponse(['success' => true, 'providers' => $hiorgSettings]);
  }

  //TODO: Wird das überhaupt benötigt
  /**
   * @NoAdminRequired
   * @PasswordConfirmationRequired
   * @param mixed $disable_password_confirmation
   * @return JSONResponse
   */
  public function savePersonal($disable_password_confirmation): JSONResponse
  {
    $uid = $this->userSession->getUser()->getUID();
    $this->config->setUserValue($uid, $this->appName, 'disable_password_confirmation', $disable_password_confirmation ? 1 : 0);
    return new JSONResponse(['success' => true]);
  }

  //TODO: Wird das überhaupt benötigt
  /**
   * 
   * @NoAdminRequired
   * @param mixed $login
   * @return RedirectResponse
   */
  public function disconnecthiorgoauth($login): RedirectResponse
  {
    $this->socialConnect->disconnectLogin($login);
    return new RedirectResponse($this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'hiorgoauth']));
  }
}
