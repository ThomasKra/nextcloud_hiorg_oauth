<?php
/*!
* Hybridauth
* https://hybridauth.github.io | https://github.com/hybridauth/hybridauth
*  (c) 2017 Hybridauth authors | https://hybridauth.github.io/license.html
*/

namespace OCA\HiorgOAuth\Provider;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\Adapter\OAuth2;
use Hybridauth\HttpClient\HttpClientInterface;
use Hybridauth\Logger\LoggerInterface;
use Hybridauth\Storage\StorageInterface;
use Hybridauth\Data;
use Hybridauth\User;

/**
 * HiOrg OAuth2 provider adapter.
 *

 */
class Hiorg extends OAuth2
{

    static public $versions = ['v1' => 'Version 1', 'v2' => 'Version 2'];
    /**
     * {@inheritdoc}
     */
    protected $scope;

    /**
     * {@inheritdoc}
     */
    protected $apiBaseUrl;


    /**
     * {@inheritdoc}
     */
    protected $authorizeUrl;
    
    /**
     * {@inheritdoc}
     */
    protected $accessTokenUrl;
    
    /**
     * {@inheritdoc}
     */
    protected $apiDocumentation = 'https://wiki.hiorg-server.de/admin/oauth2';
    
    /**
     * @var string Profile URL template as the fallback when no `link` returned from the API.
     */
    protected $profileUrl;

    protected $apiVersion;
    
    /**
     * Common adapters constructor.
     *
     * @param string              $version
     * @param array               $config
     * @param HttpClientInterface $httpClient
     * @param StorageInterface    $storage
     * @param LoggerInterface     $logger
     */
    public function __construct(
      $config = ['api_version' => 'v1'],
      HttpClientInterface $httpClient = null,
      StorageInterface    $storage = null,
      LoggerInterface     $logger = null
  ) {
    
    $this->apiVersion = $config['api_version'];
    switch($this->apiVersion){
      case 'v1':
        $config['scope'] = 'basic eigenedaten';
        break;
      case 'v2':
        $config['scope'] = 'openid profile personal/selbst:read';
        break;
      default:
        throw ('Version not existent');
    }
      parent::__construct($config, $httpClient, $storage, $logger);
      switch($this->apiVersion){
        case 'v1':
          $this->scope = $config['scope'];
          $this->apiBaseUrl = 'https://www.hiorg-server.de/api/oauth2/v1';
          $this->authorizeUrl = $this->apiBaseUrl. '/authorize.php';
          $this->accessTokenUrl = $this->apiBaseUrl. '/token.php';
          $this->profileUrl = $this->apiBaseUrl. '/user.php';
          break;
        case 'v2':
          $this->scope = $config['scope'];
          $this->apiBaseUrl = 'https://api.hiorg-server.de/oauth/v1';
          $this->authorizeUrl = $this->apiBaseUrl. '/authorize';
          $this->accessTokenUrl = $this->apiBaseUrl. '/token';
          $this->profileUrl = $this->apiBaseUrl. '/userinfo';
          $this->AuthorizeUrlParameters += [
            'ov' => $config['orga']
        ];
        break;
        default:
          throw ('Version not existent');
      }
    }
    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        parent::initialize();

        // Require proof on all HiOrg api calls
        if ($accessToken = $this->getStoredData('access_token')) {
            $this->apiRequestParameters['appsecret_proof'] = hash_hmac('sha256', $accessToken, $this->clientSecret);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUserProfile()
    {
        $response = $this->apiRequest($this->profileUrl);
       
        $data = new Data\Collection($response);

        switch($this->apiVersion){
          case 'v1':
            if (!$data->exists( 'username_at_orga')) {
                throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
            }

            if (!$data->exists('orga')) {
                throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
            }

            if($data->get('orga') !== $this->config->get('orga')) {
                throw new UnexpectedApiResponseException('Falsche orga: "' . $data->get('orga') . '".');
            }

            $userProfile = new User\Profile();
        
            $userProfile->identifier = $data->get( 'username_at_orga');
            $userProfile->displayName = $data->get('fullname');
            $userProfile->firstName = $data->get('vorname');
            $userProfile->lastName = $data->get('name');
            $userProfile->profileURL = "";
            $userProfile->webSiteURL = "";
            $userProfile->gender = "";
            $userProfile->language = "de_DE";
            $userProfile->description = "";
            $userProfile->email = $data->get('email');
            if ($gruppe = $data->get('gruppe')) {
              $userProfile->data['gruppe'] = $gruppe;
          }
          break;
          case 'v2':

            if (!$data->exists( 'preferred_username')) {
              throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
          }

          $userProfile = new User\Profile();
            $userProfile->identifier = $data->get( 'preferred_username').'@'.$this->config->get('orga');
            $userProfile->displayName = $data->get('name');
            $userProfile->firstName = $data->get('given_name');
            $userProfile->lastName = $data->get('family_name');
            $userProfile->profileURL = "";
            $userProfile->webSiteURL = "";
            $userProfile->gender = "";
            $userProfile->language = "de_DE";
            $userProfile->description = "";
            $userProfile->email = $data->get('email');

            $response = $this->apiRequest('https://api.hiorg-server.de/core/v1/personal/selbst');
            
            $data_selbst = new Data\Collection($response);
            
            if ($gruppe = $data_selbst->get('data')) {
              $userProfile->data['gruppe'] = array_sum(array_keys( get_object_vars($gruppe->attributes->gruppen_namen)));
          }
          break;
          default:
          throw ('Version not existent');
      }

        $userProfile->region = "";

        $userProfile->emailVerified = $userProfile->email;

        if ($groupMapping = $this->config->get('group_mapping')) {
            $userProfile->data['group_mapping'] = $groupMapping;
        }

        if ($quota = $this->config->get('quota')) {
            $userProfile->data['quota'] = $quota;
        }

        return $userProfile;
    }
}
