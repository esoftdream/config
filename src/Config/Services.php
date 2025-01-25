<?php

namespace Esoftdream\Config;

use CodeIgniter\Config\BaseService;
use Esoftdream\Config;

class Services extends BaseService
{
    /**
     * Returns the Settings manager class.
     */
    public static function config()
    {

        return new Config();
    }
}
