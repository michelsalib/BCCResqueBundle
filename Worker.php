<?php

namespace BCC\ResqueBundle;

class Worker
{
    /**
     * @var \Resque_Worker
     */
    protected $worker;

    public function __construct(\Resque_Worker $worker)
    {
        $this->worker = $worker;
    }

    public function getId()
    {
        return (string) $this->worker;
    }

    public function stop()
    {
        $parts = \explode(':', $this->getId());

        \posix_kill($parts[1], 3);
    }

    public function getQueues()
    {
        return \array_map(function ($queue) {
            return new Queue($queue);
        }, $this->worker->queues());
    }

    public function getProcessedCount()
    {
        return $this->worker->getStat('processed');
    }

    public function getFailedCount()
    {
        return $this->worker->getStat('failed');
    }

    public function getCurrentJobStart()
    {
        $job = $this->worker->job();

        if (!$job) {
            return null;
        }

        return new \DateTime($job['run_at']);
    }

    public function getCurrentJob()
    {
        $job = $this->worker->job();

        if (!$job) {
            return null;
        }

        $job = new \Resque_Job($job['queue'], $job['payload']);

        return $job->getInstance();
    }
}
