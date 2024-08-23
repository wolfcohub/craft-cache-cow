<?php

namespace wolfco\cachecow\events;

use yii\base\Event;

class UrlFetchEvent extends Event
{
    public string $message;
    public string $url;
    public int $code;
}