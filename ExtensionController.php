<?php
namespace frontend\controllers;

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

/**
 * Extension controller
 */
class ExtensionController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'install' => ['get'],
                    'thanks'  => ['get'],
                ],
            ],
        ];
    }

    public function actionThanks() {
        return $this->render('thanks');
    }

    public function actionInstall() {
        return $this->render('install', [
            'dontWrap'          => 1,
            'currentResearchKw' => Yii::$app->getSession()->get('currentResearchKw'),
            'currentBrowseUrl'  => Yii::$app->getSession()->get('currentBrowseUrl')
        ]);
    }
}
