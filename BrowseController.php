<?php
namespace frontend\controllers;

use Yii;
use common\models\U;
use common\models\outsider\BestBuy;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\httpclient\Client;
use kartik\rating\StarRating;
use yii\data\Pagination;
use yii\helpers\Url;

/**
 * Browse controller
 */
class BrowseController extends Controller
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
                        'roles'   => ['?'],
                    ],
                ],
            ],
            'verbs'  => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'index' => ['get'],
                ],
            ],
        ];
    }

    public function actionIndex() {
        $params = Yii::$app->getRequest()->getQueryParams();

        $landings = [
            'cameras'              => 'productTemplate=Digital_Cameras',
            'dslr'                 => '(categoryPath.id=abcat0401005|categoryPath.id=pcmcat180000050013)&productTemplate=Digital_Cameras',
            'earbuds'              => 'categoryPath.id=pcmcat143000050007&productTemplate=Headphones',
            'headphones'           => 'productTemplate=Headphones',
            'on-hear-headphones'   => 'categoryPath.id=pcmcat331200050000&productTemplate=Headphones',
            'over-hear-headphones' => 'categoryPath.id=pcmcat331200050001&productTemplate=Headphones',
            'wireless-headphones'  => 'categoryPath.id=pcmcat331200050015&productTemplate=Headphones',
            ''                     => 'productTemplate=Headphones',
            'index'                => ''
        ];

        $bb = new BestBuy();
        //$data   = $bb->productAttrSearch('longDescription', $params['q'])->request()->getData();
        $q = ag($params, 'q');
        if ($q && $q != 'in-ear') {
            $searchDef = 'search='.$q.'*&';
        } else {
            $searchDef = '';
        }

        $landing = ag($params, 'landing');
        $details = ag($params, 'features');
        $title   = '';

        //best|browse from frontend/config/main
        if (ag($params, 'quality') == 'best') {
            $searchDef .= 'customerReviewCount>1&customerReviewAverage>4&';
            $title = 'Best ';
        } else {
            //$searchDef .= 'customerReviewCount > 1 & customerReviewAverage > 3 & ';
        }
        $brand = ag($params, 'brand', 'all');
        if ($brand != 'all') {
            $searchDef .= "manufacturer=".rawurlencode($brand)."&";
            $title .= ucfirst($brand).' ';
        }

        $category = ag($params, 'category', 'all');
        if ($category != 'all') {
            $searchDef .= "categoryPath.name=\"".rawurlencode($category)."\"&";
            $title .= ucfirst($category).' ';
        }


        $landingFormatted = ucwords(str_replace('-', ' ', $landing));
        if ($landingFormatted == 'Index') {
            if ($q) {
                $landingFormatted = $q;
            } else {
                $landingFormatted = '';
            }
        }
        $title .= $landingFormatted;

        //price
        if (!($priceMax = ag($params, 'price-max')) && preg_match_all('/under-(\d+)/', $details, $m)) {
            $priceMax = ag($m, '1.0');
        }

        if ($priceMin = ag($params, 'price-min')) {
            $searchDef .= "salePrice>$priceMin&";
        }

        if ($priceMax) {
            $searchDef .= "salePrice<=$priceMax&";
            if ($priceMin) {
                $title .= " Between $$priceMin AND $$priceMax ";
            } else {
                $title .= " Under \${$priceMax} ";
            }
        }

        \Yii::$app->view->registerMetaTag([
            'name'    => 'description',
            'content' => $title
        ]);

        if ($kw = ag($params, 'kw')) {
            $kw .= ', ';

        }

        \Yii::$app->view->registerMetaTag([
            'name'    => 'keywords',
            'content' => "{$kw}$title, $landingFormatted"
        ]);


        $session = Yii::$app->getSession();
        $session->set('currentResearchKw', $landingFormatted);
        $session->set('currentBrowseUrl', strtolower($_SERVER['REQUEST_URI']));

        $landingDef = ag($landings, $landing);
        if ($landingDef) {
            $searchDef .= $landingDef;
        } else {
            $searchDef = substr($searchDef, 0, -1);
        }

        $qstr = '?sort=customerReviewAverage.desc,customerReviewCount.desc';

        $page    = ag($params, 'page', 1);
        $perPage = ag($params, 'per-page', 10);

        $qstr .= "&pageSize=$perPage&page=$page";
        $qstr .= "&facet=categoryPath.name,20";
        if ($searchDef) {
            $endpoint = 'products('.$searchDef.')';
        } else {
            $endpoint = 'products';
        }

        //$data = $bb->search('products', 'search', $q)->requestDebug();
        //$data = $bb->categorySearch($q)->requestDebug();
        //$data = $bb->setUrl('categories(name=On-Ear*&name=Headphones*)')->requestDebug();
        //U::printa([$landings, $endpoint.$qstr.$bb->getSuffix()], $landing.' to '.$landingDef, 0);
        $data = $bb->setUrl($endpoint.$qstr)
                   ->request()->getData();
        //U::printa($data, 're data');

        $total = ag($data, 'total');
        if (!$total == 0 || $total > 500) {
            ol1('no total:'.$_SERVER['REQUEST_URI'].':'.$bb->getUrl().$bb->getSuffix(), "browse total warning : $total");
        }

        $pagination = new Pagination([
            'totalCount' => $total,
            'page'       => $page - 1,
            'pageSize'   => $perPage
        ]);

        if($landing == 'index') {
            if($q) {
                $landing = $q;
            }
            elseif($category != 'all') {
                $landing = $category;
            }
            else {
                $landing = 'product';
            }

        }

        return $this->render('index', [
            'dontWrap'     => true,
            'landing'      => $landing,
            'bestBuy'      => $data,
            'pagination'   => $pagination,
            'perPage'      => $perPage,
            'page'         => $page,
            'q'            => $q,
            'brand'        => $brand,
            'categoryName' => $category,
            'priceMin'     => $priceMin,
            'priceMax'     => $priceMax,
            'title'        => $title,
            'mLandingForm' => new \common\models\LandingForm()
        ]);
    }
}
