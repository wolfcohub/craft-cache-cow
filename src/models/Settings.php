<?php

namespace wolfco\cachecow\models;

use Craft;
use craft\base\Model;

/**
 * Cache Cow settings
 */
class Settings extends Model
{
    public $sitemaps = [];
    public array $additionalUrls = [];

    public function rules(): array
    {
        return [
            ['sitemaps', 'each', 'rule' => ['string']],
        ];
    }

    /**
     * Get the sitemap for site handle
     * @param  string $handle
     * @return string
     */
    public function getSitemapByHandle(string $handle): string
    {
        if (isset($this->sitemaps[$handle])) {
            return $this->sitemaps[$handle];
        }
        return 'sitemap.xml';
    }
}
