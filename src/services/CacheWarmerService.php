<?php

namespace wolfco\cachecow\services;

use Craft;
use craft\base\Component;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleRetry\GuzzleRetryMiddleware;
use vipnytt\SitemapParser;
use vipnytt\SitemapParser\Exceptions\SitemapParserException;
use wolfco\cachecow\CacheCow;
use wolfco\cachecow\events\UrlFetchEvent;
use wolfco\cachecow\jobs\WarmCacheJob;
use yii\base\Exception;


class CacheWarmerService extends Component
{
    public const EVENT_URL_FETCH_SUCCESS = 'urlFetchSuccess';
    public const EVENT_URL_FETCH_FAILURE = 'urlFetchFailure';

    /**
     * @param array $urls
     * @return void
     * @throws Exception
     */
    public function warmCache(array $urls): void
    {
        if (!count($urls)) {
            throw new Exception('Cache warm failed because there are no URLs to cache');
        }
        // Add middleware to handle flood control
        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory());
        $client = new Client(['handler' => $stack]);

        foreach ($urls as $url) {
            try {
                $response = $client->get($url);
                $successCode = $response->getStatusCode();
                $successMessage = Craft::t('app', 'Fetched {url} : {code}', ["url" => $url, "code" => $successCode]);
                Craft::info($successMessage, 'cache-cow');
                $this->trigger(self::EVENT_URL_FETCH_SUCCESS, new UrlFetchEvent([
                    'message' => $successMessage,
                    'code' => $successCode,
                    'url' => $url
                ]));
            } catch (RequestException $reason) {
                $errorCode = $reason->getResponse()->getStatusCode();
                $errorMessage = Craft::t('app', 'Error fetching {url} : {code}', ['url' => $url, 'code' => $errorCode]);
                Craft::info($errorMessage, 'cache-cow');
                $this->trigger(self::EVENT_URL_FETCH_FAILURE, new UrlFetchEvent([
                    'message' => $errorMessage,
                    'code' => $reason->getResponse()->getStatusCode(),
                    'url' => $url
                ]));
            } catch (GuzzleException $e) {
                $errorMessage = Craft::t('app', 'Error fetching {url} : {message}', ['url' => $url, 'message' => $e->getMessage()]);
                Craft::info($errorMessage, 'cache-cow');
                $this->trigger(self::EVENT_URL_FETCH_FAILURE, new UrlFetchEvent([
                    'message' => $errorMessage,
                    'code' => 0,
                    'url' => $url
                ]));
            }
        }
    }

    /**
     * @param string $handle
     * @return bool
     */
    public static function getSitemapExists(string $handle = 'default'): bool
    {
        $sitemapPath = Craft::getAlias('@webroot/' . self::getSitemapUrl($handle));
        return file_exists($sitemapPath);
    }

    public static function getCacheWarmJobsInProgress(): int
    {
        // Query DB for jobs of type WarmUrlJob
        $jobs = (new \yii\db\Query())
            ->select('*')
            ->from('{{%queue}}')
            ->where(['like', 'job', WarmCacheJob::class])
            ->all();

        return count($jobs);
    }

    /**
     * @param string $handle
     * @return string
     */
    public static function getSitemapUrl(string $handle = 'default'): string
    {
        $sitemapUrl = CacheCow::$plugin->getSettings()->getSitemapByHandle($handle) ?? "";

        $sitemapUrlFromEnv = App::parseEnv($sitemapUrl);
        if (!empty($sitemapUrlFromEnv)) {
            // Sitemap is configured as an env variable, return the value
            return $sitemapUrlFromEnv;
        }
        return $sitemapUrl;
    }

    /**
     * @param string $handle
     * @return array
     * @throws Exception
     */
    public static function getSiteUrls(string $handle = 'default'): array
    {
        $parser = new SitemapParser();
        try {
            $sitemapPath = UrlHelper::baseSiteUrl() . self::getSitemapUrl($handle);
        } catch (SiteNotFoundException $e) {
            throw new Exception('Cache warming not possible as no site is configured');
        }
        try {
            $parser->parseRecursive($sitemapPath);
        } catch (SitemapParserException $e) {
            throw new Exception('Cache warming not possible because sitemap is missing or improperly formatted');
        }
        $urls = [];
        foreach ($parser->getUrls() as $url => $tags) {
            $urls[] = $url;
        }
        return $urls;
    }
}
