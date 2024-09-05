<?php

namespace wolfco\cachecow\controllers;

use craft\web\Controller;
use wolfco\cachecow\jobs\WarmCacheJob;
use yii\web\MethodNotAllowedHttpException;

class CacheController extends Controller
{
    public function actionWarm()
    {
        $session = \Craft::$app->getSession();
        try {
            $this->requirePostRequest();

            $job = new WarmCacheJob();
            \Craft::$app->getQueue()->push($job);

            $session->setNotice("Cache warming started! See Queue Manager for status.");
        } catch (MethodNotAllowedHttpException $e) {
            $session->setError("This action requires POST request.");
        } catch (\Exception $e) {
            \Craft::error($e->getMessage(), __METHOD__);
        }
        return $this->redirect('utilities/clear-caches');
    }
}
