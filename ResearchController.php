<?php
namespace frontend\controllers;

use Yii;
use common\models\U;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\web\Session;

use common\models\Research;

/**
 * Research controller
 */
class ResearchController extends Controller
{
    public $modelClass = 'common\models\Research';

    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'featured' => ['get'],
                ],
            ],
        ];
    }

    public function beforeAction($action) {
        $this->_initModel();

        return parent::beforeAction($action);
    }


    /**
     * @inheritdoc
     */
    public function actions() {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    public function actionFeatured($id) {

        $mResearch = new Research();
        $featured  = $mResearch->viewFeatured($id, 0);

        return $this->render('featured', [
            'mResearch' => $featured[0]

        ]);
    }
}
