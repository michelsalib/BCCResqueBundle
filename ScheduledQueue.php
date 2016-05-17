<?php

namespace BCC\ResqueBundle;

class ScheduledQueue extends Queue
{
    const PREFIX = 'scheduled-queue:';

    public function __construct($name)
    {
        parent::__construct($name);
        $this->clearOldJobs();
        $this->setTtl();
    }
    
    public function has(Job $job)
    {
        return $this->getTimestamp($job) !== null;
    }

    public function add(Job $job, $timestamp)
    {
        return \Resque::redis()->zadd(self::PREFIX.$this->getName(), $timestamp, serialize($job));
    }

    public function getJobs($start=0, $stop=-1)
    {
        $jobs = \Resque::redis()->zrangebyscore(self::PREFIX . $this->getName(), $start, $stop);

        $result = array();
        foreach ($jobs as $job) {
            $result[] = unserialize($job);
        }

        return $result;
    }

    private function clearOldJobs()
    {
        \Resque::redis()->zremrangebyscore(self::PREFIX.$this->getName(), 0, time() - 1);
    }

    private function setTtl()
    {
        $redis = \Resque::redis();
        $key = self::PREFIX.$this->getName();
        if ($redis->zcard($key)) {
            $ttl = $redis->zrevrange($key, 0, 1, "WITHSCORES");
            $redis->expire($key, $ttl[1] - time());
        } else {
            $redis->del($key);
        }
    }

    public function getTimestamp(Job $job)
	{
        return \Resque::redis()->zscore(self::PREFIX.$this->getName(), serialize($job));
	}
}
