<?php

namespace wolfco\cachecow;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use wolfco\cachecow\models\Settings;
use craft\events\RegisterUrlRulesEvent;
use craft\events\TemplateEvent;
use craft\web\UrlManager;
use craft\web\View;
use wolfco\cachecow\services\CacheWarmerService;
use yii\base\Event;
use yii\log\FileTarget;

/**
 * Cache Cow plugin
 *
 * @method static CacheCow getInstance()
 * @method Settings getSettings()
 * @author Wolfco <jack@wolfco.us>
 * @copyright Wolfco
 * @license https://craftcms.github.io/license/ Craft License
 */
class CacheCow extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = false;
    public static Plugin $plugin;

    public function init(): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'wolfco\cachecow\console\controllers';
        }

        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'cacheCow' => CacheWarmerService::class,
        ]);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cache-cow/cache/warm'] = 'cache-cow/cache/warm';
            }
        );

        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_TEMPLATE,
            function (TemplateEvent $event) {
                $jobsInProgress = CacheWarmerService::instance()->getCacheWarmJobsInProgress();
                if ($event->template === '_components/utilities/ClearCaches.twig') {
                    // Custom template to append to the Utilities -> Caches UI
                    $sitemapExists = CacheWarmerService::instance()->getSitemapExists();
                    $event->output .= Craft::$app->view->renderTemplate('cache-cow/index', [
                        'jobsInProgress' => $jobsInProgress,
                        'sitemapExists' => $sitemapExists,
                    ]);
                }
            }
        );
        Craft::getLogger()->dispatcher->targets['cacheCow'] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/cache-cow-' . date('Y-m-d') . '.log'),
            'categories' => ['cache-cow'],
            'levels' => ['error', 'warning', 'info'],
            'logVars' => [],
        ]);
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate(
            'cache-cow/_settings',
            ['settings' => $this->getSettings()]
        );
    }
}
