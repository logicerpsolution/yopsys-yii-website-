<?php
//yii gii/controller --controllerClass="frontend\\controllers\\ChromeXtController" --actions="link-score" --viewPath="@frontend/views/chrome-xt"

namespace frontend\controllers;

use common\models\LinkScore;

class ChromeXtController extends Controller
{
    public function actionLinkScore() {
        $this->layout = 'chromext';
        return $this->render('link-score', [
            'model' => new LinkScore(),
            'title' => 'Closer'
        ]);
    }

}
