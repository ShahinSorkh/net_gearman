<?php
/**
 * Interface for Danga's Gearman job scheduling system
 *
 * PHP version 5.1.0+
 *
 * LICENSE: This source file is subject to the New BSD license that is
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php. If you did not receive
 * a copy of the New BSD License and are unable to obtain it through the web,
 * please send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Net
 * @package   ShSo\Net\Gearman
 * @author    Joe Stump <joe@joestump.net>
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/Net_Gearman
 * @link      http://www.danga.com/gearman/
 */

namespace ShSo\Net\Gearman;
/**
 * Gearman worker class
 *
 * Run an instance of a worker to listen for jobs. It then manages the running
 * of jobs, etc.
 *
 * <code>
 * <?php
 *
 * $servers = array(
 *     '127.0.0.1:7003',
 *     '127.0.0.1:7004'
 * );
 *
 * $abilities = array('HelloWorld', 'Foo', 'Bar');
 *
 * try {
 *     $worker = new ShSo\Net\Gearman\Worker($servers);
 *     foreach ($abilities as $ability) {
 *         $worker->addAbility('HelloWorld');
 *     }
 *     $worker->beginWork();
 * } catch (ShSo\Net\Gearman\Exception $e) {
 *     echo $e->getMessage() . "\n";
 *     exit;
 * }
 *
 * ?>
 * </code>
 *
 * @category  Net
 * @package   ShSo\Net\Gearman
 * @author    Joe Stump <joe@joestump.net>
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version   Release: @package_version@
 * @link      http://www.danga.com/gearman/
 * @see       ShSo\Net\Gearman\Job, ShSo\Net\Gearman\Connection
 */
class Worker
{
    /**
     * Pool of connections to Gearman servers
     *
     * @var array $conn
     */
    protected $conn = array();

    /**
     * Pool of retry connections
     *
     * @var array $conn
     */
    protected $retryConn = array();

    /**
     * Pool of worker abilities
     *
     * @var array $conn
     */
    protected $abilities = array();

    /**
     * Parameters for job contructors, indexed by ability name
     *
     * @var array $initParams
     */
    protected $initParams = array();

    /**
     * Callbacks registered for this worker
     *
     * @var array $callback
     * @see ShSo\Net\Gearman\Worker::JOB_START
     * @see ShSo\Net\Gearman\Worker::JOB_COMPLETE
     * @see ShSo\Net\Gearman\Worker::JOB_FAIL
     */
    protected $callback = array(
        self::JOB_START     => array(),
        self::JOB_COMPLETE  => array(),
        self::JOB_FAIL      => array()
    );

    /**
     * Unique id for this worker
     *
     * @var string $id
     */
    protected $id = "";


    /**
     * Callback types
     *
     * @const integer JOB_START    Ran when a job is started
     * @const integer JOB_COMPLETE Ran when a job is finished
     * @const integer JOB_FAIL     Ran when a job fails
     */
    const JOB_START    = 1;
    const JOB_COMPLETE = 2;
    const JOB_FAIL     = 3;

    /**
     * Constructor
     *
     * @param array $servers List of servers to connect to
     * @param string $id     Optional unique id for this worker
     *
     * @return void
     * @throws ShSo\Net\Gearman\Exception
     * @see ShSo\Net\Gearman\Connection
     */
    public function __construct($servers = null, $id = "")
    {
        if (is_null($servers)){
            $servers = array("localhost");
        } elseif (!is_array($servers) && strlen($servers)) {
            $servers = array($servers);
        } elseif (is_array($servers) && !count($servers)) {
            throw new Exception('Invalid servers specified');
        }

        if(empty($id)){
            $id = "pid_".getmypid()."_".uniqid();
        }

        $this->id = $id;

        foreach ($servers as $s) {
            try {
                $conn = Connection::connect($s);

                Connection::send($conn, "set_client_id", array("client_id" => $this->id));

                $this->conn[$s] = $conn;

            } catch (Exception $e) {

                $this->retryConn[$s] = time();
            }
        }

        if (empty($this->conn)) {
            throw new Exception(
                "Couldn't connect to any available servers"
            );
        }
    }

    /**
     * Announce an ability to the job server
     *
     * @param string  $ability Name of functcion/ability
     * @param integer $timeout How long to give this job
     * @param array $initParams Parameters for job constructor
     *
     * @return void
     * @see ShSo\Net\Gearman\Connection::send()
     */
    public function addAbility($ability, $timeout = null, $initParams=array())
    {
        $call   = 'can_do';
        $params = array('func' => $ability);
        if (is_int($timeout) && $timeout > 0) {
            $params['timeout'] = $timeout;
            $call              = 'can_do_timeout';
        }

        $this->initParams[$ability] = $initParams;

        $this->abilities[$ability] = $timeout;

        foreach ($this->conn as $conn) {
            Connection::send($conn, $call, $params);
        }
    }

    /**
     * Begin working
     *
     * This starts the worker on its journey of actually working. The first
     * argument is a PHP callback to a function that can be used to monitor
     * the worker. If no callback is provided then the worker works until it
     * is killed. The monitor is passed two arguments; whether or not the
     * worker is idle and when the last job was ran.
     *
     * @param callback $monitor Function to monitor work
     *
     * @return void
     * @see ShSo\Net\Gearman\Connection::send(), ShSo\Net\Gearman\Connection::connect()
     * @see ShSo\Net\Gearman\Worker::doWork(), ShSo\Net\Gearman\Worker::addAbility()
     */
    public function beginWork($monitor = null)
    {
        if (!is_callable($monitor)) {
            $monitor = array($this, 'stopWork');
        }

        $write     = null;
        $except    = null;
        $working   = true;
        $lastJob   = time();
        $retryTime = 5;

        while ($working) {
            $sleep = true;
            $currentTime = time();

            foreach ($this->conn as $server => $socket) {
                $worked = false;
                try {
                    $worked = $this->doWork($socket);
                } catch (Exception $e) {
                    unset($this->conn[$server]);
                    $this->retryConn[$server] = $currentTime;
                }
                if ($worked) {
                    $lastJob = time();
                    $sleep   = false;
                }
            }

            $idle = false;
            if ($sleep && count($this->conn)) {
                foreach ($this->conn as $socket) {
                    Connection::send($socket, 'pre_sleep');
                }

                $read = $this->conn;
                socket_select($read, $write, $except, 60);
                $idle = (count($read) == 0);
            }

            $retryChange = false;
            foreach ($this->retryConn as $s => $lastTry) {
                if (($lastTry + $retryTime) < $currentTime) {
                    try {
                        $conn = Connection::connect($s);
                        $this->conn[$s]         = $conn;
                        $retryChange            = true;
                        unset($this->retryConn[$s]);
                        Connection::send($conn, "set_client_id", array("client_id" => $this->id));
                    } catch (Exception $e) {
                        $this->retryConn[$s] = $currentTime;
                    }
                }
            }

            if (count($this->conn) == 0) {
                // sleep to avoid wasted cpu cycles if no connections to block on using socket_select
                sleep(1);
            }

            if ($retryChange === true) {
                // broadcast all abilities to all servers
                foreach ($this->abilities as $ability => $timeout) {
                    $this->addAbility(
                        $ability, $timeout, $this->initParams[$ability]
                    );
                }
            }

            if (call_user_func($monitor, $idle, $lastJob) == true) {
                $working = false;
            }
        }
    }

    /**
     * Listen on the socket for work
     *
     * Sends the 'grab_job' command and then listens for either the 'noop' or
     * the 'no_job' command to come back. If the 'job_assign' comes down the
     * pipe then we run that job.
     *
     * @param resource $socket The socket to work on
     *
     * @return boolean Returns true if work was done, false if not
     * @throws ShSo\Net\Gearman\Exception
     * @see ShSo\Net\Gearman\Connection::send()
     */
    protected function doWork($socket)
    {
        Connection::send($socket, 'grab_job');

        $resp = array('function' => 'noop');
        while (count($resp) && $resp['function'] == 'noop') {
            $resp = Connection::blockingRead($socket);
        }

        if (in_array($resp['function'], array('noop', 'no_job'))) {
            return false;
        }

        if ($resp['function'] != 'job_assign') {
            throw new Exception('Holy Cow! What are you doing?!');
        }

        $name   = $resp['data']['func'];
        $handle = $resp['data']['handle'];
        $arg    = array();

        if (isset($resp['data']['arg']) &&
            Connection::stringLength($resp['data']['arg'])) {
            $arg = json_decode($resp['data']['arg'], true);
            if($arg === null){
                $arg = $resp['data']['arg'];
            }
        }

        $job = Job::factory(
            $name, $socket, $handle, $this->initParams[$name]
        );
        try {
            $this->start($handle, $name, $arg);
            $res = $job->run($arg);
            if (!is_array($res)) {
                $res = array('result' => $res);
            }

            $job->complete($res);
            $this->complete($handle, $name, $res);
        } catch (Job\Exception $e) {
            $job->fail();
            $this->fail($handle, $name, $e);
        }

        // Force the job's destructor to run
        $job = null;

        return true;
    }

    /**
     * Attach a callback
     *
     * @param callback $callback A valid PHP callback
     * @param integer  $type     Type of callback
     *
     * @return void
     * @throws ShSo\Net\Gearman\Exception When an invalid callback is specified.
     * @throws ShSo\Net\Gearman\Exception When an invalid type is specified.
     */
    public function attachCallback($callback, $type = self::JOB_COMPLETE)
    {
        if (!is_callable($callback)) {
            throw new Exception('Invalid callback specified');
        }
        if (!isset($this->callback[$type])) {
            throw new Exception('Invalid callback type specified.');
        }
        $this->callback[$type][] = $callback;
    }

    /**
     * Run the job start callbacks
     *
     * @param string $handle The job's Gearman handle
     * @param string $job    The name of the job
     * @param mixed  $args   The job's argument list
     *
     * @return void
     */
    protected function start($handle, $job, $args)
    {
        if (count($this->callback[self::JOB_START]) == 0) {
            return; // No callbacks to run
        }

        foreach ($this->callback[self::JOB_START] as $callback) {
            call_user_func($callback, $handle, $job, $args);
        }
    }

    /**
     * Run the complete callbacks
     *
     * @param string $handle The job's Gearman handle
     * @param string $job    The name of the job
     * @param array  $result The job's returned result
     *
     * @return void
     */
    protected function complete($handle, $job, array $result)
    {
        if (count($this->callback[self::JOB_COMPLETE]) == 0) {
            return; // No callbacks to run
        }

        foreach ($this->callback[self::JOB_COMPLETE] as $callback) {
            call_user_func($callback, $handle, $job, $result);
        }
    }

    /**
     * Run the fail callbacks
     *
     * @param string $handle The job's Gearman handle
     * @param string $job    The name of the job
     * @param object $error  The exception thrown
     *
     * @return void
     */
    protected function fail($handle, $job, \Exception $error)
    {
        if (count($this->callback[self::JOB_FAIL]) == 0) {
            return; // No callbacks to run
        }

        foreach ($this->callback[self::JOB_FAIL] as $callback) {
            call_user_func($callback, $handle, $job, $error);
        }
    }

    /**
     * Stop working
     *
     * @return void
     */
    public function endWork()
    {
        foreach ($this->conn as $conn) {
            Connection::close($conn);
        }
    }

    /**
     * Destructor
     *
     * @return void
     * @see ShSo\Net\Gearman\Worker::stop()
     */
    public function __destruct()
    {
        $this->endWork();
    }

    /**
     * Should we stop work?
     *
     * @return boolean
     */
    public function stopWork()
    {
        return false;
    }
}
