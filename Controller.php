<?php
namespace frontend\controllers;

use common\models\Site;

/**
 * Site controller
 */
class Controller extends \yii\web\Controller
{
    use \common\traits\Controller;

    public function beforeAction($action) {
        \Yii::$app->view->params['mUser'] = Site::findIdentityInSession();
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

    public function render($view, $params = []) {
        //2nd overwrites first
        $params = array_merge($params, ['m' => $this->model]);
        return parent::render($view, $params);
    }


}
