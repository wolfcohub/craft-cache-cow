<?php

namespace wolfco\cachecow\console\controllers;

use Craft;
use yii\console\Controller;
use wolfco\cachecow\services\CacheWarmerService;
use yii\console\ExitCode;

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
        $sitemapExists = CacheWarmerService::instance()->getSitemapExists();
        if (!$sitemapExists) {
            $this->stderr("Error: Cache warming not possible - no sitemap.xml file found!\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $jobsInProgress = CacheWarmerService::instance()->getCacheWarmJobsInProgress();
        if ($jobsInProgress > 0) {
            $this->stderr("Error: Cache warming already in progress - " . $jobsInProgress . " jobs remaining\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        try {
            CacheWarmerService::instance()->warmCache();
            $this->stdout("Cache warming started successfully!\n");
            return ExitCode::OK;

        } catch (\Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
            $this->stderr("Error: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
