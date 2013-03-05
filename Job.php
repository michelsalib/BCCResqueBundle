<?php

namespace BCC\ResqueBundle;

abstract class Job
{
    /**
     * @var \Resque_Job
     */
    public $job;

    /**
     * @var string The queue name
     */
    public $queue = 'default';

    /**
     * @var array The job args
     */
    public $args = array();

    public function getName()
    {
        return \get_class($this);
    }

    public function setUp()
    {

    }

    public function perform()
    {
        $this->run($this->args);
    }

    abstract public function run($args);

    public function tearDown()
    {

    }
}
