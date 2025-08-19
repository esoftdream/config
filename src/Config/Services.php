<?php

namespace Esoftdream\Config;

use CodeIgniter\Config\BaseService;
use Esoftdream\Config;

/**
 * @method Config config(bool $getShared = true)
 */
class Services extends BaseService
{
    /**
     * Returns the Settings manager class.
     */
    public static function config($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('config');
        }

        return new Config();
    }
}
