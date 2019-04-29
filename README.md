# ShSo\Net\Gearman

## About

Net_Gearman is a PEAR package for interfacing with Danga's Gearman. Gearman is a system to farm out work to other machines, dispatching function calls to machines that are better suited to do work, to do work in parallel, to load balance lots of function calls, or to call functions between languages.

Net_Gearman was in production at Yahoo! and Digg doing all sorts of offloaded near time processing.

This package got no update since Feb 23, 2011. I'm going to maintain from now on.

## Installation

```sh
php composer.phar require shso/net_gearman
```

## Examples

### Client

```php
<?php

$client = new ShSo\Net\Gearman\Client('localhost:7003');
$client->someBackgroundJob(array(
    'userid' => 5555,
    'action' => 'new-comment'
));

?>
```

### Job

```php
<?php

class Net_Gearman_Job_someBackgroundJob extends ShSo\Net\Gearman\Job\Common
{
    public function run($args)
    {
        if (!isset($args['userid']) || !isset($args['action'])) {
            throw new ShSo\Net\Gearman\Job\Exception('Invalid/Missing arguments');
        }

        // Insert a record or something based on the $args

        return array(); // Results are returned to Gearman, except for
                        // background jobs like this one.
    }
}

?>
```

### Worker

```php
<?php

$worker = new ShSo\Net\Gearman\Worker('localhost:7003');
$worker->addAbility('someBackgroundJob');
$worker->beginWork();

?>
```
