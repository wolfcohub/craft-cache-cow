<?php

namespace wolfco\cachecow\services;

use craft\base\Component;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
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
     * @return PromiseInterface
     * @throws Exception
     */
    public function warmCache(array $urls): PromiseInterface
    {
        if (!count($urls)) {
            throw new Exception('Cache warm failed because there are no URLs to cache');
        }

        // Add middleware to handle flood control
        $stack = HandlerStack::create();
        $stack->push(GuzzleRetryMiddleware::factory());
        $client = new Client(['handler' => $stack]);
        $_this = $this;

        // generate iterable batch of requests
        $requests = function () use ($client, $urls, $_this) {
            foreach ($urls as $url) {
                yield function() use ($url, $client, $_this) {
                    return $client->getAsync($url)->then(
                        function (Response $response) use ($url, $_this) {
                            $_this->trigger(self::EVENT_URL_FETCH_SUCCESS, new UrlFetchEvent([
                                'message' => \Craft::t('app', 'Fetched {url} : {code}', ["url" => $url, "code" => $response->getStatusCode()]),
                                'code' => $response->getStatusCode(),
                                'url' => $url
                            ]));
                            return $response;
                        },
                        function (RequestException $reason) use ($url, $_this) {
                            $_this->trigger(self::EVENT_URL_FETCH_FAILURE, new UrlFetchEvent([
                                'message' => \Craft::t('app', 'Error fetching {url} : {code}', ['url' => $url, 'code' => $reason->getResponse()->getStatusCode()]),
                                'code' => $reason->getResponse()->getStatusCode(),
                                'url' => $url
                            ]));
                            return $reason;
                        }
                    );
                };
            }
        };

        // utilize multiple threads if available
        $pool = new Pool($client, $requests(), [
            'concurrency' => 5
        ]);
        return $pool->promise();
    }

    public function getSitemapExists(): bool
    {
        $sitemapPath = \Craft::getAlias('@webroot/' . CacheCow::$plugin->getSettings()->sitemapUrl);
        return file_exists($sitemapPath);
    }

    public function getCacheWarmJobsInProgress(): int
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
    public function getSiteUrls(): array
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
