<?php

namespace wolfco\cachecow\controllers;

use Craft;
use craft\web\Controller;
use wolfco\cachecow\CacheCow;
use wolfco\cachecow\jobs\WarmCacheJob;
use wolfco\cachecow\services\CacheWarmerService;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;
use yii\web\MethodNotAllowedHttpException;

class CacheController extends Controller
{
    public function actionWarm()
    {
        $session = Craft::$app->getSession();
        $service = CacheCow::$plugin->cacheCow;
        try {
            $this->requirePostRequest();
            $cachesToWarm = Craft::$app->getRequest()->getBodyParam('handles');
            $allHandles = CacheCow::$plugin->getAllSiteHandles();
            $urlsToWarm =  [];

            $key = array_search('custom', $cachesToWarm);
            if ($key !== false) {
                $urlsToWarm = array_reduce(CacheCow::$plugin->getSettings()->additionalUrls, 'array_merge', []);
                unset($cachesToWarm[$key]);
            }
            foreach ($cachesToWarm as $cacheHandle) {
                if (!in_array($cacheHandle, $allHandles)) {
                    throw new \Exception("No site found with handle '" . $cacheHandle . "'.");
                }
                if (!$service->getSitemapExists($cacheHandle)) {
                    throw new \Exception("Missing sitemap file (" . CacheWarmerService::getSitemapUrl($cacheHandle) . ") for site " . $cacheHandle . "'.");
                }
                $siteUrls = $service->getSiteUrls($cacheHandle);
                array_push($urlsToWarm, ...$siteUrls);
            }
            $job = new WarmCacheJob([
                'urls' => $urlsToWarm,
            ]);
            Craft::$app->getQueue()->push($job);

            $session->setNotice("Cache warming started! See Queue Manager for status.");
        } catch (MethodNotAllowedHttpException $e) {
            $session->setError("This action requires POST request.");
        } catch (\Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
            $session->setError($e->getMessage());
        }
        return $this->redirect('utilities/cache-cow');
    }
}
