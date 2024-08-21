<?php

namespace wolfco\cachecow\controllers;

use craft\web\Controller;
use wolfco\cachecow\services\CacheWarmerService;
use yii\web\MethodNotAllowedHttpException;

class CacheController extends Controller
{
    public function actionWarm()
    {
        try {
            $this->requirePostRequest();
            CacheWarmerService::instance()->warmCache();
            \Craft::$app->getSession()->setNotice("Cache warming started! See Queue Manager for status.");
        } catch (MethodNotAllowedHttpException $e) {
            \Craft::$app->getSession()->setError("This action requires POST request.");
        } catch (\Exception $e) {
            \Craft::error($e->getMessage(), __METHOD__);
        }
        return $this->redirect('utilities/clear-caches');
    }
}
