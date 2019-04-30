<?php

namespace ShSo\Net\Gearman\Examples;

use ShSo\Net\Gearman\Job\Common;
use ShSo\Net\Gearman\Job\Exception;

/**
 * Sum up a bunch of numbers
 *
 * @author      Joe Stump <joe@joestump.net>
 * @package     Net_Gearman
 */
class Sum extends Common
{
    /**
     * Run the summing job
     *
     * @access      public
     * @param       array       $arg
     * @return      array
     */
    public function run($arg)
    {
        $sum = 0;
        foreach ($arg as $i) {
            if (!is_numeric($i))
                throw new Exception('not a number');
            $sum += $i;
        }

        return array('sum' => $sum);
    }
}
