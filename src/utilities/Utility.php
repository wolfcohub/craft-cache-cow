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
        $additionalUrlCount = count(CacheCow::$plugin->getSettings()->additionalUrls);

        $cacheWarmingOptions = [];
        $canDoWarming = false;
        $missingSitemaps = false;

        foreach (\Craft::$app->sites->getAllSites() as $site) {
            $sitemapExists = CacheWarmerService::instance()->getSitemapExists($site->handle);
            if ($sitemapExists) {
                // set $canDoWarming = true if at least one sitemap is found
                $canDoWarming = true;
            } else {
                // set $missingSitemaps = true if at least one sitemap is missing
                $missingSitemaps = true;
            }
            $cacheWarmingOptions[] = [
                'name' => 'handles[]',
                'value' => $site->handle,
                'label' => $site->name,
                'checked' => $sitemapExists,
                'disabled' => !$sitemapExists,
                'info' => $sitemapExists ? null : "Sitemap file not found",

            ];
        }
        $cacheWarmingOptions[] = [
            'name' => 'handles[]',
            'value' => 'custom',
            'label' => 'Custom URLs',
            'checked' => $additionalUrlCount > 0,
            'disabled' => !$additionalUrlCount,
            'info' => !$additionalUrlCount ? "No custom URLs added" : "",
        ];
        if ($additionalUrlCount > 0) {
            // can do warming (even without sitemaps) if custom URLs configured
            $canDoWarming = true;
        }

        return Craft::$app->view->renderTemplate('cache-cow/index', [
            'jobsInProgress' => $jobsInProgress,
            'canDoWarming' => $canDoWarming,
            'cacheWarmingOptions' => $cacheWarmingOptions,
            'missingSitemaps' => $missingSitemaps,
        ]);
    }
}