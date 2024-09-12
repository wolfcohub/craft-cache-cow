<?php

namespace wolfco\cachecow\jobs;

use craft\queue\BaseJob;
use Exception;
use wolfco\cachecow\CacheCow;
use wolfco\cachecow\services\CacheWarmerService;
use yii\base\Event;

class WarmCacheJob extends BaseJob
{
    public array $urls = [];
    private array $fetchedUrls = [];
    private array $failedUrls = [];
    private int $totalUrlCount;
    private bool $hasFailed = false;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->urls = CacheWarmerService::getSiteUrls();
        $this->totalUrlCount = count($this->urls);
        $this->description = "Warming {$this->totalUrlCount} URLs";
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function execute($queue): void
    {
        $service = CacheCow::$plugin->cacheCow;
        try {
            $_this = $this;
            Event::on(CacheWarmerService::class, CacheWarmerService::EVENT_URL_FETCH_SUCCESS, function ($event) use ($_this, $queue) {
                $_this->fetchedUrls[] = $event->url;
                $newFetchedCount = count($this->fetchedUrls);
                $_this->setProgress($queue, $newFetchedCount / $_this->totalUrlCount);
                \Craft::info("Success {$event->url}, new fetched url count: {$newFetchedCount}");
            });
            Event::on(CacheWarmerService::class, CacheWarmerService::EVENT_URL_FETCH_FAILURE, function ($event) use ($_this, $queue) {
                $_this->failedUrls[] = $event->url;
                $_this->hasFailed = true;
                $newFetchedCount = count($this->fetchedUrls);
                $_this->setProgress($queue, $newFetchedCount / $_this->totalUrlCount);
                \Craft::info("ERROR {$event->url}, new fetched url count: {$newFetchedCount}");
            });
            $service->warmCache($this->urls);
            if ($this->hasFailed) {
                throw new \Exception("Failed to fetch one or more URLs:\n" . implode("\n", $this->failedUrls));
            }
            $this->setProgress($queue, 1);
        } catch (Exception $e) {
            \Craft::error("Cache warming failed due to error: {$e->getMessage()}", __METHOD__);
            throw $e;
        }
    }

    protected function defaultDescription(): string
    {
        return "Warming {$this->totalUrlCount} URLs";
    }
}
