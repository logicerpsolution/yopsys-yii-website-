<?php
namespace frontend\controllers;

use common\models\Research;
use common\models\Category;
use common\models\outsider\ApiService;
use Yii;
use common\models\U;
use common\models\Site;
use common\models\outsider\Amazon;
use common\models\outsider\BestBuy;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\httpclient\Client;
use kartik\rating\StarRating;
use yii\data\Pagination;
use yii\helpers\Url;
use yii\web\Session;

/**
 * Search controller
 */
class SearchController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['index'],
                        'allow'   => true,
                    ],
                ],
            ],
            'verbs'  => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'index'  => ['get'],
                ],
            ],
        ];
    }

    public function actionIndex() {
        $term      = gp('q');
        $mResearch = new Research();
        if ($term) {
            $searched = $mResearch->fullSearch($term, 0);
        } else {
            $searched = [];
        }

        return $this->render('index', [
            'searched' => $searched,
            'term'     => $term
        ]);
    }
      public function actionResult() {
		$term = $_GET['q'];     
		$mResearch = new Research();
		$searched = $mResearch->fullSearch($term, 0);
        return $this->render('index',['searched'=>$searched]);
    }
}
