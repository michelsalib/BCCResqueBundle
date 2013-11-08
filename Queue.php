<?php

namespace BCC\ResqueBundle;

class Queue
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getSize()
    {
        return \Resque::size($this->name);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getJobs($start=0, $stop=-1)
    {
        $jobs = \Resque::redis()->lrange('queue:' . $this->name, $start, $stop);

        $result = array();
        foreach ($jobs as $job) {
            $job = new \Resque_Job($this->name, \json_decode($job, true));
            $result[] = $job->getInstance();
        }

        return $result;
    }
}
