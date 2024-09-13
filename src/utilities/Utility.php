<?php

namespace wolfco\cachecow\utilities;

use Craft;
use wolfco\cachecow\CacheCow;
use wolfco\cachecow\services\CacheWarmerService;

class Utility extends \craft\base\Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Cache Cow';
    }

    /**
     * @inheritDoc
     */
    public static function id(): string
    {
        return 'cache-cow';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        $iconPath = Craft::getAlias('@plugins/cache-cow/icon-mask.svg');
        if (!is_string($iconPath)) {
            return null;
        }
        return $iconPath;
    }

    /**
     * @inheritDoc
     */
    public static function contentHtml(): string
    {
        $jobsInProgress = CacheWarmerService::instance()->getCacheWarmJobsInProgress();
        $sitemapExists = CacheWarmerService::instance()->getSitemapExists();
        $additionalUrlCount = count(CacheCow::$plugin->getSettings()->additionalUrls);
        return Craft::$app->view->renderTemplate('cache-cow/index', [
            'jobsInProgress' => $jobsInProgress,
            'sitemapExists' => $sitemapExists,
            'additionalUrlCount' => $additionalUrlCount,
        ]);

    }
}