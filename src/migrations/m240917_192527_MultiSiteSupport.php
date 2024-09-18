<?php

namespace wolfco\cachecow\migrations;

use Craft;
use craft\db\Migration;

/**
 * m240917_192527_MultiSiteSupport migration.
 */
class m240917_192527_MultiSiteSupport extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $plugin = Craft::$app->plugins->getPlugin('cache-cow');
        if (!$plugin) {
            return false;
        }

        $settings = $plugin->getSettings();

        if (isset($settings->sitemapUrl)) {
            $sitemapUrl = $settings->sitemapUrl;
            $newSettings = [
                'sitemaps' => [$sitemapUrl] // Convert the single sitemapUrl into an array
            ];

            Craft::$app->plugins->savePluginSettings($plugin, $newSettings);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $plugin = Craft::$app->plugins->getPlugin('cache-cow');
        if (!$plugin) {
            return false;
        }
        $settings = $plugin->getSettings();
        if (isset($settings->sitemaps) && is_array($settings->sitemaps)) {
            $sitemapUrl = $settings->sitemaps[0] ?? null;

            if ($sitemapUrl) {
                $oldSettings = [
                    'sitemapUrl' => $sitemapUrl
                ];
                Craft::$app->plugins->savePluginSettings($plugin, $oldSettings);
            }
        }
        return true;
    }
}
