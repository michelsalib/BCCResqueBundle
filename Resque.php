<?php

namespace BCC\ResqueBundle;

class Resque
{
    /**
     * @var array
     */
    private $kernelOptions;

    /**
     * @var array
     */
    private $redisConfiguration;

    public function __construct(array $kernelOptions)
    {
        $this->kernelOptions = $kernelOptions;
    }

    public function setPrefix($prefix)
    {
        \Resque_Redis::prefix($prefix);
    }

    public function setRedisConfiguration($host, $port, $database)
    {
        $this->redisConfiguration = array(
            'host'     => $host,
            'port'     => $port,
            'database' => $database,
        );

        \Resque::setBackend($host.':'.$port, $database);
    }

    public function getRedisConfiguration()
    {
        return $this->redisConfiguration;
    }

    public function enqueue(Job $job, $trackStatus = false)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $result = \Resque::enqueue($job->queue, \get_class($job), $job->args, $trackStatus);

        if ($trackStatus) {
            return new \Resque_Job_Status($result);
        }

        return null;
    }

    public function enqueueOnce(Job $job, $trackStatus = false)
    {
        $queue = new Queue($job->queue);
        $jobs  = $queue->getJobs();

        foreach ($jobs AS $j) {
            if ($j->job->payload['class'] == get_class($job)) {
                if (count(array_intersect($j->args, $job->args)) == count($job->args)) {
                    return ($trackStatus) ? $j->job->payload['id'] : null;
                }
            }
        }

        return $this->enqueue($job, $trackStatus);
    }

    public function enqueueAt($at,Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        \ResqueScheduler::enqueueAt($at, $job->queue, \get_class($job), $job->args);

        return null;
    }

    public function enqueueIn($in,Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        \ResqueScheduler::enqueueIn($in, $job->queue, \get_class($job), $job->args);

        return null;
    }

    public function removedDelayed(Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        return \ResqueScheduler::removeDelayed($job->queue, \get_class($job),$job->args);
    }

    public function removeFromTimestamp($at, Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        return \ResqueScheduler::removeDelayedJobFromTimestamp($at, $job->queue, \get_class($job), $job->args);
    }

    public function getQueues()
    {
        return \array_map(function ($queue) {
            return new Queue($queue);
        }, \Resque::queues());
    }

    /**
     * @param $queue
     * @return Queue
     */
    public function getQueue($queue)
    {
        return new Queue($queue);
    }

    public function getWorkers()
    {
        return \array_map(function ($worker) {
            return new Worker($worker);
        }, \Resque_Worker::all());
    }

    public function getWorker($id)
    {
        $worker = \Resque_Worker::find($id);

        if (!$worker) {
            return null;
        }

        return new Worker($worker);
    }

    public function pruneDeadWorkers()
    {
        // HACK, prune dead workers, just in case
        $worker = new \Resque_Worker('temp');
        $worker->pruneDeadWorkers();
    }

    public function getDelayedJobTimestamps()
    {
        $timestamps= \Resque::redis()->zrange('delayed_queue_schedule', 0, -1);

        //TODO: find a more efficient way to do this
        $out=array();
        foreach ($timestamps as $timestamp) {
            $out[]=array($timestamp,\Resque::redis()->llen('delayed:'.$timestamp));
        }

        return $out;
    }

    public function getFirstDelayedJobTimestamp()
    {
        $timestamps=$this->getDelayedJobTimestamps();
        if (count($timestamps)>0) {
            return $timestamps[0];
        }

        return array(null,0);
    }

    public function getNumberOfDelayedJobs()
    {
        return \ResqueScheduler::getDelayedQueueScheduleSize();
    }

    public function getJobsForTimestamp($timestamp)
    {
        $jobs= \Resque::redis()->lrange('delayed:'.$timestamp,0, -1);
        $out=array();
        foreach ($jobs as $job) {
            $out[]=json_decode($job, true);
        }

        return $out;
    }

    /**
     * @param $queue
     * @return int
     */
    public function clearQueue($queue)
    {
        $length=\Resque::redis()->llen('queue:'.$queue);
        \Resque::redis()->del('queue:'.$queue);

        return $length;
    }

    public function getFailedJobs($start = -100, $count = 100)
    {
        $jobs = \Resque::redis()->lrange('failed', $start, $count);

        $result = array();

        foreach ($jobs as $job) {
            $result[] = new FailedJob(json_decode($job, true));
        }

        return $result;
    }
}
