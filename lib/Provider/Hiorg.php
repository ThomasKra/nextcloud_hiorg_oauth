<?php
/*!
* Hybridauth
* https://hybridauth.github.io | https://github.com/hybridauth/hybridauth
*  (c) 2017 Hybridauth authors | https://hybridauth.github.io/license.html
*/

namespace OCA\HiorgOAuth\Provider;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\Adapter\OAuth2;
use Hybridauth\Data;
use Hybridauth\User;

/**
 * HiOrg OAuth2 provider adapter.
 *
 * Example:
 *
 *   $config = [
 *       'callback' => Hybridauth\HttpClient\Util::getCurrentUrl(),
 *       'keys'     => [ 'id' => '', 'secret' => '' ],
 *       'scope'    => 'basic eigenedaten'
 *   ];
 *
 *   $adapter = new Hybridauth\Provider\Hiorg( $config );
 *
 *   try {
 *       $adapter->authenticate();
 *
 *       $userProfile = $adapter->getUserProfile();
 *       $tokens = $adapter->getAccessToken();
 *       $response = $adapter->setUserStatus("Hybridauth test message..");
 *   }
 *   catch( Exception $e ){
 *       echo $e->getMessage() ;
 *   }
 */
class Hiorg extends OAuth2
{
    /**
     * {@inheritdoc}
     */
    protected $scope = 'basic eigenedaten';

    /**
     * {@inheritdoc}
     */
    protected $apiBaseUrl = 'https://www.hiorg-server.de/api/oauth2/v1';

    /**
     * {@inheritdoc}
     */
    protected $authorizeUrl = 'https://www.hiorg-server.de/api/oauth2/v1/authorize.php';

    /**
     * {@inheritdoc}
     */
    protected $accessTokenUrl = 'https://www.hiorg-server.de/api/oauth2/v1/token.php';

    /**
     * {@inheritdoc}
     */
    protected $apiDocumentation = 'https://wiki.hiorg-server.de/admin/oauth2';

    /**
     * @var string Profile URL template as the fallback when no `link` returned from the API.
     */
    protected $profileUrl = 'https://www.hiorg-server.de/api/oauth2/v1/user.php';

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

        if (!$data->exists( 'username_at_orga')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        if (!$data->exists('orga')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        if($data->get('orga') != $this->config->get('orga')) {
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

        $userProfile->region = "";

        $userProfile->emailVerified = $userProfile->email;

        if ($groupMapping = $this->config->get('group_mapping')) {
            $userProfile->data['group_mapping'] = $groupMapping;
        }

        if ($quota = $this->config->get('quota')) {
            $userProfile->data['quota'] = $quota;
        }

        if ($gruppe = $data->get('gruppe')) {
            $userProfile->data['gruppe'] = $gruppe;
        }
        return $userProfile;
    }
}
