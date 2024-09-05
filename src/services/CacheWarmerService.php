<?php

namespace wolfco\cachecow\services;

use Craft;
use craft\base\Component;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
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
    const EVENT_URL_FETCH_SUCCESS = 'urlFetchSuccess';
    const EVENT_URL_FETCH_FAILURE = 'urlFetchFailure';

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

    public function getSitemapExists(): bool
    {
        $sitemapPath = Craft::getAlias('@webroot/' . CacheCow::$plugin->getSettings()->sitemapUrl);
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
     * @return array
     * @throws Exception
     */
    public static function getSiteUrls(): array
    {
        $parser = new SitemapParser();
        try {
            $sitemapPath = UrlHelper::baseSiteUrl() . CacheCow::$plugin->getSettings()->sitemapUrl;
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
            $urls[$url] = $url;
        }
        return $urls;
    }
}
