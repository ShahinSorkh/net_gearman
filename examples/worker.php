<?php

require_once '../vendor/autoload.php';

use ShSo\Net\Gearman\Examples\Sum;

use ShSo\Net\Gearman\Exception;
use ShSo\Net\Gearman\Worker;

try {
    $worker = new Worker('localhost:4730');
    $worker->addAbility('Sum');
    $worker->beginWork();
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
    exit;
}

?>
