<?php

namespace ShSo\Test\Net\Gearman;

use ShSo\Net\Gearman\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Net_Gearman_ConnectionTest
 *
 * PHP version 5
 *
 * @category   Testing
 * @package    ShSo\Net\Gearman
 * @subpackage ShSo\Net\Gearman\Connection
 * @author     Till Klampaeckel <till@php.net>
 * @license    http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Net_Gearman
 * @since      0.2.4
 */
class ConnectionTest extends TestCase
{
    /**
     * When no server is supplied, it should connect to localhost:4730.
     *
     * @return void
     */
    public function testDefaultConnect()
    {
        $connection = Connection::connect();
        $this->assertInternalType('resource', $connection);
        $this->assertEquals('socket', strtolower(get_resource_type($connection)));

        $this->assertTrue(Connection::isConnected($connection));

        Connection::close($connection);
    }

    /**
     * 001-echo_req.phpt
     *
     * @return void
     */
    public function testSend()
    {
        $connection = Connection::connect();
        Connection::send($connection, 'echo_req', array('text' => 'foobar'));

        do {
            $ret = Connection::read($connection);
        } while (is_array($ret) && !count($ret));

        Connection::close($connection);

        $this->assertInternalType('array', $ret);
        $this->assertEquals('echo_res', $ret['function']);
        $this->assertEquals(17, $ret['type']);

        $this->assertInternalType('array', $ret['data']);
        $this->assertEquals('foobar', $ret['data']['text']);
    }
}
