<?php

namespace wolfco\cachecow\console\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use vipnytt\SitemapParser;
use vipnytt\SitemapParser\Exceptions\SitemapParserException;
use wolfco\cachecow\CacheCow;
use yii\base\Event;
use craft\console\Controller;
use wolfco\cachecow\services\CacheWarmerService;
use yii\base\Exception;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

class CacheController extends Controller
{
    /**
     * This command warms the cache.
     *
     * Example usage:
     *
     * ```
     * php craft cache-cow/cache/warm
     * ```
     *
     * @return int Exit code
     */
    public function actionWarm(): int
    {
        $service = CacheCow::$plugin->cacheCow;
        $sitemapExists = $service->getSitemapExists();
        if (!$sitemapExists) {
            $this->stderr("Error: Cache warming not possible - no sitemap.xml file found!" . PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $jobsInProgress = $service->getCacheWarmJobsInProgress();
        if ($jobsInProgress > 0) {
            $this->stderr("Error: Cache warming already in progress - " . $jobsInProgress . " jobs remaining" . PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $urls = $service->getSiteUrls();
            Event::on(CacheWarmerService::class, CacheWarmerService::EVENT_URL_FETCH_SUCCESS, function ($event) {
                $this->stdout($event->message . PHP_EOL, BaseConsole::FG_GREEN);
            });
            Event::on(CacheWarmerService::class, CacheWarmerService::EVENT_URL_FETCH_FAILURE, function ($event) {
                $this->stderr($event->message . PHP_EOL, BaseConsole::FG_RED);
            });
            $service->warmCache($urls)->wait();
            $this->stdout("Cache warming completed successfully!" . PHP_EOL, BaseConsole::FG_GREEN);
            return ExitCode::OK;

        } catch (\Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
            $this->stderr("Error: " . $e->getMessage() . PHP_EOL, BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
