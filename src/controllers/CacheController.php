<?php

namespace wolfco\cachecow\controllers;

use craft\errors\MissingComponentException;
use craft\web\Controller;
use wolfco\cachecow\CacheCow;
use wolfco\cachecow\jobs\WarmCacheJob;
use wolfco\cachecow\services\CacheWarmerService;
use yii\base\Event;
use yii\helpers\BaseConsole;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;

class CacheController extends Controller
{


    public function actionWarm()
    {
        $session = \Craft::$app->getSession();
        $service = CacheCow::$plugin->cacheCow;
        try {
            $this->requirePostRequest();

            $job = new WarmCacheJob([
                'urls' => $service->getSiteUrls(),
            ]);
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
