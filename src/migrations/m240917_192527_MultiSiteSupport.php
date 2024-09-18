<?php

namespace wolfco\cachecow\migrations;

use Craft;
use craft\db\Migration;
use craft\services\ProjectConfig;

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
        $projectConfig = Craft::$app->getProjectConfig();

        // If `sitemapUrl` exists, migrate its value to the new `sitemaps` array.
        $sitemapUrl = $projectConfig->get('plugins.cache-cow.settings.sitemapUrl');
        if ($sitemapUrl) {
            $projectConfig->set('plugins.cache-cow.settings.sitemaps.' . ProjectConfig::ASSOC_KEY . '.0', ['default', $sitemapUrl]);
            $projectConfig->remove('plugins.cache-cow.settings.sitemapUrl');
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // If `sitemaps` associative array exists, set first value to the old `sitemapUrl`
        $sitemapUrl = $projectConfig->get('plugins.cache-cow.settings.sitemaps.' . ProjectConfig::ASSOC_KEY . '.0.1');
        if ($sitemapUrl) {
            $projectConfig->set('plugins.cache-cow.settings.sitemapUrl', $sitemapUrl);
            $projectConfig->remove('plugins.cache-cow.settings.sitemaps');
        }
        return true;
    }
}
