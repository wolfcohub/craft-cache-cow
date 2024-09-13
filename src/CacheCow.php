<?php

namespace wolfco\cachecow;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Utilities;
use craft\web\UrlManager;
use wolfco\cachecow\models\Settings;
use wolfco\cachecow\services\CacheWarmerService;
use wolfco\cachecow\utilities\Utility;
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

        Craft::setAlias('@plugins/cache-cow', __DIR__);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cache-cow/cache/warm'] = 'cache-cow/cache/warm';
            }
        );
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Utility::class;
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
