<?php

namespace wolfco\cachecow\controllers;

use craft\web\Controller;
use wolfco\cachecow\jobs\WarmCacheJob;
use wolfco\cachecow\services\CacheWarmerService;
use yii\web\Response;

class LogController extends Controller
{

    protected array|int|bool $allowAnonymous = [];

    public function actionFetchLog(): Response
    {
        $logFile = \Craft::getAlias(WarmCacheJob::LOG_FILE);

        if (!file_exists($logFile)) {
            return $this->asJson(['success' => false, 'message' => 'Log file not found.']);
        }

        $isActiveJob = CacheWarmerService::instance()->getCacheWarmJobsInProgress() > 0;
        $logEntries = json_decode(file_get_contents($logFile), true);

        return $this->asJson(['success' => true, 'isActiveJob' => $isActiveJob, 'logEntries' => $logEntries]);
    }
}