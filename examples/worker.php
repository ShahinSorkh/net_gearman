<?php

require_once 'vendor/autoload.php';

use ShSo\Net\Gearman\Exception;
use ShSo\Net\Gearman\Worker;

try {
    $worker = new Worker(array('dev01:7003', 'dev01:7004'));
    $worker->addAbility('Hello');
    $worker->addAbility('Fail');
    $worker->addAbility('SQL');
    $worker->beginWork();
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
    exit;
}

?>
