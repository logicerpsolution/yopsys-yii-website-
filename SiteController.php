<?php
namespace frontend\controllers;

use common\models\AuthProviderLink;
use common\models\LinkScore;
use common\models\Research;
use common\models\LandingForm;
use common\models\Site;
use Yii;
use common\models\LoginForm;
use common\models\U;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\httpclient\Client;
use linslin\yii2\curl;
use yii\web\Session;
use common\models\User;

/**
 * Site controller
 */
class SiteController extends Controller
{
    public $modelClass = 'common\models\Site';
    public $loginClass = 'common\models\LoginForm';
    public $mailClass = 'common\models\QueueMail';

    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only'  => ['signup'],
                'rules' => [
                    [
                        'actions' => ['signup', 'login', 'register'],
                        'allow'   => true,
                        'roles'   => ['?'],
                    ],
                ],
            ],
            'verbs'  => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'bb'     => ['get'],
                    'yc'     => ['get'],
                    'branch' => ['get'],
                    'logout' => ['get'],
                    'login'  => ['get', 'post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions() {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'auth'  => [
                'class'           => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'onAuthSuccess'],
            ],
        ];
    }

    /**
     * onAuthSuccess
     *
     * @param \yii\authclient\clients\Facebook $client
     * @return \yii\web\Response
     * @throws \Exception
     */
    public function onAuthSuccess($client) {
        $attributes            = $client->getUserAttributes();
        $urlAlreadyUser        = 'app#/login?msg=AlreadyUser';
        $suffixReturnToProfile = '&returnTo=profile';
        $clientUid             = '';
        //could be useful in the future, for now, just linking facebook account to yopsys
        $cliAccessToken = $client->getAccessToken();
        $more           = '';

        //let's see if we can here with a session access_token
        // (this is passed from app#/profile when the user links yopsys to facebook)
        $s = Yii::$app->session;
        if ($s->has('localAt') && $s->has('linkRequestTs') && time() - $s->get('linkRequestTs') < 15 * 60) {
            $tmpPassword = '';
            $at          = $s->get('localAt');
            unset($s['localAt']);
            unset($s['linkRequestTs']);
        } else {
            $at = '';
        }

        $currentClient = gp('authclient');
        if ($currentClient == "google") {
            $first = 1;
            //use first email, or the one equals to username
            foreach ($attributes['emails'] as $email) {
                $emailId = $email['value'];
                if ($first) {
                    $email = $emailId;
                    $first = 0;
                }
                $mUser = User::findOne(['username' => $emailId]);

                if ($mUser) {
                    $email = $emailId;
                    break;
                }
            }

            $firstName = ag($attributes, 'name.givenName');
            $lastName  = ag($attributes, 'name.familyName');
            $clientUid = ag($attributes, 'id');

        } elseif ($currentClient == "facebook") {
            $nameArray = explode(" ", $attributes['name']);
            $firstName = $nameArray[0];
            $lastName  = $nameArray[1];
            $email     = $attributes['email'];
            $clientUid = $attributes['id'];
        }

        if ($clientUid) {

            $mUser = User::findOneByAuthProviderUid(['uid' => $clientUid, 'name' => $currentClient]);

            //yopsys' user linked authProvider's user
            if ($mUser) {
                $tmpPassword = $mUser->password_hash;
            } else {
                $mUser = User::findByUsername($email);
                //the user is found by email as username
                if ($mUser) {

                    //the user is requesting to link to the auth provider
                    if ($at) {
                        $data = ['access_token' => $at];

                        $mAuthProvider = new AuthProviderLink();
                        $this->_linkUserToAuthProvider($mUser, $mAuthProvider, $currentClient, $attributes['id']);

                        $more = $suffixReturnToProfile;

                    } else {
                        //created on yopsys.com, stop here and request that the user logs in in before linking
                        return $this->redirect(\Yii::$app->urlManager->createUrl($urlAlreadyUser));
                    }
                } else {

                    ol('user not found at is : '.$at);
                    if ($at) {
                        $mUser = User::findIdentityByAccessToken($at);
                        if ($mUser) {

                            $mAuthProvider = new AuthProviderLink();
                            $this->_linkUserToAuthProvider($mUser, $mAuthProvider, $currentClient, $clientUid);
                            $url = "app#/log-me-in?at={$at}{$suffixReturnToProfile}";
                        } else {
                            $url = $urlAlreadyUser;
                        }

                        ol('going to : '.$url);

                        return $this->redirect(\Yii::$app->urlManager->createUrl($url));
                    } else {

                        //new user on yopsys too
                        $mUser = new User();
                        $this->_createUser($mUser, $firstName, $lastName, $email);

                        $mAuthProvider = new AuthProviderLink();
                        $this->_linkUserToAuthProvider($mUser, $mAuthProvider, $currentClient, $clientUid);

                        $tmpPassword = $mUser->password_hash;
                        $more        = '&returnTo=profile';
                    }
                }

            }

            if ($tmpPassword) {
                //if already a user
                $credentials = [
                    'username' => $mUser->username, 'password' => $tmpPassword
                ];
                $data        = $this->_fetchAccessTokenFromOauth2Server($credentials);
            }

            $url = "/app#/log-me-in?at=$data[access_token]{$more}";
            ol('going to : '.$url);

            return $this->redirect(\Yii::$app->urlManager->createUrl($url));
        } else {
            throw new \Exception('client not implemented');
        }
    }

    /**
     * actionAuthLink links auth providers (facebook, google, etc) to yopsy's user already logged in.
     *
     * @return void
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionAuthLink() {
        $localAt = gp('localAt');
        if ($localAt) {
            $s = Yii::$app->getSession();
            $s->set('localAt', $localAt);
            $s->set('linkRequestTs', time());
            $direction = ['site/auth'] + ['authclient' => gp('authclient')];
            $this->redirect($direction);
        } else {
            throw new \yii\web\NotFoundHttpException();
        }

    }

    public function actionApp() {
        $this->layout = 'ng';

        return $this->render('app');
    }

    public function actionIndex() {
        $mResearch = new Research();
        //$featured = $mResearch->searchDetails(['featured' => 1], 0)->all();
        $qFeatured = $mResearch->searchFeatured();
        $featured  = $qFeatured->all();

        return $this->render('index', [
            'aFeaturedResearch' => $featured,
            'maxItems'          => 10

        ]);
    }

    public function actionYc() {

        //return U::printa([$cookies->get('authUser'), $rq->getHeaders()->get('Cookie')], 'coocoo', 1);
        $aResearch = array();

        return $this->render('yc',
            [
                'q'            => 'query',
                'title'        => "yc debug page",
                'items'        => [],
                'mLandingForm' => new LandingForm(),
                'mResearch'    => new Research(),
                'mLinkScore'   => new LinkScore()
            ]
        );
    }

    public function actionExtension() {
        return $this->render('extension', [
            'dontWrap'          => 1,
            'currentResearchKw' => Yii::$app->getSession()->get('currentResearchKw'),
            'currentBrowseUrl'  => Yii::$app->getSession()->get('currentBrowseUrl')
        ]);
    }

    public function _actionBb() {
        if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
            die('404');
        }

        //Init curl
        $curl     = new curl\Curl();
        $key      = 'ju5bmyhnnuvfe4d8ab44f9fr';
        $apiUrl   = 'http://api.bestbuy.com/v1/';
        $endpoint = 'products((search=touchscreen)&salePrice<500&categoryPath.id=pcmcat209000050006)';
        //$endpoint   = 'categories(name=Electronics)';
        $commonQstr = '&format=json&apiKey='.$key;

        $query = '?show=name,sku,salePrice';

        $url      = $apiUrl.$endpoint.$query.$commonQstr;
        $response = $curl->get($url, true);

        if ($response[0] == '{' && substr($response, 0, -1) == '}') {

            header('content-type text/plain');

        }
        echo "url : $url\n\n\n<br>";
        echo "<br>\n\nresponse : <br>\n$response";
        die("\noo");

    }

    public function actionBb() {
        if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
            die('404');
        }

        $key    = 'ju5bmyhnnuvfe4d8ab44f9fr';
        $apiUrl = 'http://api.bestbuy.com/v1/';
        //original
        //$endpoint = 'products((search=touchscreen)&salePrice<500&categoryPath.id=pcmcat209000050006)';
        //$commonQstr = '&format=json&apiKey='.$key;
        //$query      = '?show=name,sku,salePrice';
        //$endpoint = 'products((search=touchscreen)&salePrice<500)';

        //$endpoint = 'products((search=headphone&search=bud)&salePrice<700)';
        $endpoint   = 'products(search=neurofeedback)';
        $commonQstr = 'format=json&apiKey='.$key;
        //$query      = '?show=name,sku,salePrice';
        $query = '?';

        /**
         * @var $cli \yii\httpclient\Client
         */
        //$cli= new Client(['baseUrl' => $apiUrl]);
        $cli = new Client();

        $url      = $apiUrl.$endpoint.$query.$commonQstr;
        $response = $cli->createRequest(['method' => 'GET'])->setUrl($url)->send();
        $response->setFormat('json');
        $content = $response->getData();

        header('content-type text/plain');

        echo "url : $url\n\n\n<br>";
        //echo "<br>\n\ncontent : <br>\n$content";
        U::printa($content, 'data');
        die("\noo");

    }

    public function actionLogin() {
        //all logins should go through api now.
        return;
        $session = Yii::$app->session;
        $session->removeFlash('error');
        if (isset($_POST['username'])) {
            $model    = new $this->modelClass;
            $username = $_POST['username'];
            $password = $_POST['password'];
            $mUser    = User::findOne(['username' => $username]);

            if ($mUser) {
                if (Yii::$app->security->validatePassword($password, $mUser->password_hash)) {
                    $mUser->storeToSession();
                    $userPublic = $mUser->toArrayPublic();
                    $session->set('user', $mUser);

                    //if already a user
                    return $this->redirect(\Yii::$app->urlManager->createUrl("app#/research/my"));
                } else {
                    $data = array(
                        'message' => "Invalid Credentials"
                    );
                    $session->setFlash('error', "Invalid Credentials");
                }
            } else {
                $data = array(
                    'message' => "Invalid Credentials"
                );
                $session->setFlash('error', "Invalid Credentials");
            }
        }

        return $this->render('login');
    }

    public function actionLogout() {
        $session = Yii::$app->session;
        $session->remove('user');

        return $this->redirect(\Yii::$app->urlManager->createUrl(""));
    }

    public function actionRegister() {
        return $this->render('register');
    }

    protected function _createUser($mUser, $firstName, $lastName, $email, $username = '') {

        $mUser->setAttribute('firstname', $firstName);
        $mUser->setAttribute('lastname', $lastName);
        $mUser->setAttribute('email', $email);
        $mUser->setAttribute('username', $username ? $username : $email);

        $tmpPassword = Yii::$app->security->generateRandomString(8);
        $mUser->setPassword($tmpPassword);
        $mUser->generateAuthKey();
        $mUser->save();

        $auth = Yii::$app->authManager;
        $auth->assign($auth->getRole(User::DEFAULT_ROLE), $mUser->id);


    }


    protected function _linkUserToAuthProvider($mUser, $mAuthProvider, $clientName, $clientUid) {

        $mAuthProvider->uid  = $clientUid;
        $mAuthProvider->name = $clientName;
        $mAuthProvider->link('user', $mUser);
        $mAuthProvider->save();
    }
}
