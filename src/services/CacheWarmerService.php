<?php

namespace wolfco\cachecow\services;

use craft\base\Component;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use vipnytt\SitemapParser;
use vipnytt\SitemapParser\Exceptions\SitemapParserException;
use wolfco\cachecow\jobs\WarmUrlJob;
use yii\base\Exception;


class CacheWarmerService extends Component
{
    /** @var string */
    public string $sitemapUrl = 'sitemap.xml';

    /**
     * @return void
     * @throws Exception
     */
    public function warmCache(): void
    {
        $urls = $this->getSiteUrls();

        if (!count($urls)) {
            throw new Exception('Cache warm failed because there are no URLs to cache');
        }
        foreach ($urls as $url => $tags) {
            \Craft::$app->queue->push(new WarmUrlJob([
                'url' => $url,
            ]));
        }
    }

    /**
     * @throws Exception
     */
    public function getSiteUrls(): array
    {
        $parser = new SitemapParser();

        try {
            $sitemapPath = UrlHelper::baseSiteUrl() . $this->sitemapUrl;
        } catch (SiteNotFoundException $e) {
            throw new Exception('Cache warming not possible as no site is configured');
        }
        try {
            $parser->parseRecursive($sitemapPath);
        } catch (SitemapParserException $e) {
            throw new Exception('Cache warming not possible because sitemap is missing or improperly formatted');
        }
        return $parser->getURLs();
    }

    public function getSitemapExists(): bool
    {
        $sitemapPath = \Craft::getAlias('@webroot/' . $this->sitemapUrl);
        return file_exists($sitemapPath);
    }

    public function getCacheWarmJobsInProgress(): int
    {
        // Query DB for jobs of type WarmUrlJob
        $jobs = (new \yii\db\Query())
            ->select('*')
            ->from('{{%queue}}')
            ->where(['like', 'job', WarmUrlJob::class])
            ->all();

        return count($jobs);
    }
}
