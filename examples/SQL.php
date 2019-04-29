<?php

require_once 'vendor/autoload.php';

use ShSo\Net\Gearman\Exception;
use ShSo\Net\Gearman\Job\Common;

class Net_Gearman_Job_SQL extends Common
{
    public function run($arg)
    {
        if (!isset($arg->sql) || !strlen($arg->sql)) {
            throw new Exception;
        }

        $db = DB::connect('mysql://testing:testing@192.168.243.20/testing');
        $db->setFetchMode(DB_FETCHMODE_ASSOC);
        $res = $db->getAll($arg->sql);
        return $res;
    }
}

?>
