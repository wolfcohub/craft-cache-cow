<?php

namespace wolfco\cachecow\console\controllers;

use Craft;
use wolfco\cachecow\CacheCow;
use yii\base\Event;
use craft\console\Controller;
use wolfco\cachecow\services\CacheWarmerService;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

class CacheController extends Controller
{
    /**
     * This command warms the cache. Progress is output to stdout and stderr
     *
     * Example usage:
     *
     * ```
     * php craft cache-cow/cache/warm
     * ```
     *
     * @return int Exit code
     */
    public function actionWarm(array $selectedHandles = []): int
    {
        $service = CacheCow::$plugin->cacheCow;
        $allHandles = array_merge(CacheCow::$plugin->getAllSiteHandles(), ['custom']);
        $cachesToWarm = count($selectedHandles) > 0 ? $selectedHandles : $allHandles;
        $jobsInProgress = $service->getCacheWarmJobsInProgress();
        if ($jobsInProgress > 0) {
            $this->stderr("Cache warming already in progress - " . $jobsInProgress . " jobs remaining" . PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $urlsToWarm =  [];
            if ($key = array_search('custom', $cachesToWarm)) {
                $urlsToWarm = array_reduce(CacheCow::$plugin->getSettings()->additionalUrls, 'array_merge', []);
                unset($cachesToWarm[$key]);
            }
            foreach ($cachesToWarm as $cacheHandle) {
                if (!in_array($cacheHandle, $allHandles)) {
                    $this->stderr("No cache found with handle '" . $cacheHandle . "'." . PHP_EOL . "Caches available for cache warming: " . implode(", ", $allHandles) . ", custom" . PHP_EOL, BaseConsole::FG_RED);
                    return ExitCode::UNSPECIFIED_ERROR;
                }
                if (!$service->getSitemapExists($cacheHandle)) {
                    $this->stderr("Missing sitemap file (" . CacheWarmerService::getSitemapUrl($cacheHandle) . ") for site " . $cacheHandle . "'." . PHP_EOL, BaseConsole::FG_RED);
                    return ExitCode::UNSPECIFIED_ERROR;
                }
                $siteUrls = $service->getSiteUrls($cacheHandle);
                array_push($urlsToWarm, ...$siteUrls);
            }
            Event::on(CacheWarmerService::class, CacheWarmerService::EVENT_URL_FETCH_SUCCESS, function ($event) {
                $this->stdout($event->message . PHP_EOL, BaseConsole::FG_GREEN);
            });
            Event::on(CacheWarmerService::class, CacheWarmerService::EVENT_URL_FETCH_FAILURE, function ($event) {
                $this->stderr($event->message . PHP_EOL, BaseConsole::FG_RED);
            });
            $service->warmCache($urlsToWarm);
            $this->stdout("Cache warming completed successfully!" . PHP_EOL, BaseConsole::FG_GREEN);
            return ExitCode::OK;

        } catch (\Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
            $this->stderr("Error: " . $e->getMessage() . PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
