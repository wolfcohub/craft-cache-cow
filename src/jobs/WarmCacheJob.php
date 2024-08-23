<?php

namespace wolfco\cachecow\jobs;

use craft\queue\BaseJob;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use wolfco\cachecow\CacheCow;
use wolfco\cachecow\services\CacheWarmerService;
use yii\base\Event;

class WarmCacheJob extends BaseJob
{
    const LOG_FILE = '@root/storage/cachecow/log';
    public array $urls;
    private int $totalUrlCount;
    private array $fetchedUrls = [];

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->totalUrlCount = count($this->urls);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function execute($queue): void
    {
        $service = CacheCow::$plugin->cacheCow;
        try {
            // start with an empty log file
            $this->clearLogs();
            $_this = $this;
            Event::on(CacheWarmerService::class, CacheWarmerService::EVENT_URL_FETCH_SUCCESS, function ($event) use ($_this, $queue) {
                $_this->writeLog($event->url, $event->code, $event->message ?? '');
                $_this->fetchedUrls[] = $event->url;
                $newFetchedCount = count($this->fetchedUrls);
                $_this->setProgress($queue, $newFetchedCount / $_this->totalUrlCount);
                \Craft::info("Success {$event->url}, new fetched url count: {$newFetchedCount}");
            });
            Event::on(CacheWarmerService::class, CacheWarmerService::EVENT_URL_FETCH_FAILURE, function ($event) use ($_this, $queue) {
                $this->writeLog($event->url, $event->code, $event->message ?? '');
                $_this->fetchedUrls[] = $event->url;
                $newFetchedCount = count($this->fetchedUrls);
                $_this->setProgress($queue, $newFetchedCount / $_this->totalUrlCount);
                \Craft::info("ERROR {$event->url}, new fetched url count: {$newFetchedCount}");
            });
            $service->warmCache($this->urls)->wait();
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

    /**
     * Get log file path
     *
     * @return string
     */
    protected function getLogFile(): string
    {
        return \Craft::getAlias(self::LOG_FILE);
    }

    protected function ensureLogFileExists(): void
    {
        $logFile = $this->getLogFile();
        $logDir = dirname($logFile);

        // Check if the directory exists, if not, create it
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        // Check if the log file exists, if not, create it
        if (!file_exists($logFile)) {
            file_put_contents($logFile, json_encode([]));
        }
    }

    /**
     * erases log file contents
     */
    protected function clearLogs(): void
    {
        $this->ensureLogFileExists();
        file_put_contents($this->getLogFile(), json_encode([]));
    }

    /**
     * Get current log contents
     *
     * @return array
     */
    public function getLogContents(): array
    {
        $this->ensureLogFileExists();
        return json_decode(file_get_contents($this->getLogFile()));
    }

    /**
     * Write an entry to log file
     *
     * @param  string $url
     * @param int $code
     * @param string $message
     */
    protected function writeLog(string $url, int $code, string $message = ''): void
    {
        $this->ensureLogFileExists();
        $log = $this->getLogContents();
        $log = array_merge($log, ['url' => $url, 'code' => $code, 'message' => $message]);
        file_put_contents($this->getLogFile(), json_encode($log));
    }
}
