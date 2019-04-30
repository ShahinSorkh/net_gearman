<?php

require_once '../vendor/autoload.php';

use ShSo\Net\Gearman\Examples\SQL;

use ShSo\Net\Gearman\Client;
use ShSo\Net\Gearman\Set;
use ShSo\Net\Gearman\Task;

$set = new Set();

function result($func, $handle, $result) {
    var_dump($func);
    var_dump($handle);
    var_dump($result);
}

$pairs = [
    [4, 6],
    [3, 'foo'],
    [8, 10],
];

foreach ($pairs as $pair) {
    $task = new Task('Sum', $pair);
    $task->attachCallback('result');
    $set->addTask($task);
}

$client = new Client('localhost:4730');
$client->runSet($set);

?>
