<?php

namespace wolfco\cachecow\jobs;

use craft\queue\BaseJob;
use yii\queue\RetryableJobInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class WarmUrlJob extends BaseJob implements RetryableJobInterface
{
    /** @var string */
    public string $url;

    /**
     * @inheritDoc
     * @throws GuzzleException
     */
    public function execute($queue): void
    {
        $client = new Client();
        try {
            $response = $client->request('GET', $this->url);
            \Craft::info("Fetched URL: {$this->url}, Status Code: {$response->getStatusCode()}", __METHOD__);
        } catch (GuzzleException $e) {
            \Craft::error("Failed to fetch URL: {$this->url}, Error: {$e->getMessage()}", __METHOD__);
            throw $e;
        }
    }

    protected function defaultDescription(): string
    {
        return "Warming URL: {$this->url}";
    }

    public function getTtr(): float|int
    {
        return 5;
    }

    public function canRetry($attempt, $error): bool
    {
        return $attempt === 0;
    }
}
