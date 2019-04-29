<?php

require_once 'vendor/autoload.php';

use ShSo\Net\Gearman\Client;
use ShSo\Net\Gearman\Set;
use ShSo\Net\Gearman\Task;

function complete($func, $handle, $result) {
    var_dump($func);
    var_dump($handle);
    var_dump($result);
}

function fail($task_object) {
    var_dump($task_object);
}

$client = new Client('localhost:7003');
$set = new Set();
$jobs = array(
        'AddTwoNumbers' => array('1', '2'),
        'Multiply' => array('3', '4')
    );

foreach ($jobs as $job => $args) {
    $task = new Task($job, $args);
    $task->attachCallback("complete",Task::TASK_COMPLETE);
    $task->attachCallback("fail",Task::TASK_FAIL);
    $set->addTask($task);
}

$client->runSet($set);

?>
