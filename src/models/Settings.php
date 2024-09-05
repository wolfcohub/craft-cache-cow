<?php

namespace wolfco\cachecow\models;

use Craft;
use craft\base\Model;

/**
 * Cache Cow settings
 */
class Settings extends Model
{
    public $sitemapUrl = 'sitemap.xml';
    public function rules(): array
    {
        return [
            [['sitemapUrl'], 'string'],
        ];
    }
}
