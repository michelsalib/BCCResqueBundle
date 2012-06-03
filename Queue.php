<?php

namespace BCC\ResqueBundle;

class Queue
{
    private $name;

    function __construct($name)
    {
        $this->name = $name;
    }

    function getSize()
    {
        return \Resque::size($this->name);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getJobs()
    {
        $jobs = \Resque::redis()->lrange('queue:' . $this->name, -100, 100);

        $result = array();
        foreach ($jobs as $job) {
            $job = new \Resque_Job($this->name, \json_decode($job, true));
            $result[] = $job->getInstance();
        }

        return $result;
    }
}
