<?php

namespace BCC\ResqueBundle;

use Psr\Log\NullLogger;

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

    /**
     * @var array
     */
    private $globalRetryStrategy = array();

    /**
     * @var array
     */
    private $jobRetryStrategy = array();

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
        $host = substr($host, 0, 1) == '/' ? $host : $host.':'.$port;

        \Resque::setBackend($host, $database);
    }

    public function setGlobalRetryStrategy($strategy)
    {
        $this->globalRetryStrategy = $strategy;
    }

    public function setJobRetryStrategy($strategy)
    {
        $this->jobRetryStrategy = $strategy;
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

        $this->attachRetryStrategy($job);

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
                if (count(self::array_diff_assoc_recursive($j->args, $job->args)) > 0) {
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

        $this->attachRetryStrategy($job);

        \ResqueScheduler::enqueueAt($at, $job->queue, \get_class($job), $job->args);

        return null;
    }

    public function enqueueIn($in,Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }

        $this->attachRetryStrategy($job);

        \ResqueScheduler::enqueueIn($in, $job->queue, \get_class($job), $job->args);

        return null;
    }

    public function removedDelayed(Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }
        
        $this->attachRetryStrategy($job);

        return \ResqueScheduler::removeDelayed($job->queue, \get_class($job),$job->args);
    }

    public function removeFromTimestamp($at, Job $job)
    {
        if ($job instanceof ContainerAwareJob) {
            $job->setKernelOptions($this->kernelOptions);
        }
        
        $this->attachRetryStrategy($job);

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
        $worker->setLogger(new NullLogger());
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

    /**
     * Attach any applicable retry strategy to the job.
     *
     * @param Job $job
     */
    protected function attachRetryStrategy($job)
    {
        $class = get_class($job);

        if (isset($this->jobRetryStrategy[$class])) {
            if (count($this->jobRetryStrategy[$class])) {
                $job->args['bcc_resque.retry_strategy'] = $this->jobRetryStrategy[$class];
            }
            $job->args['bcc_resque.retry_strategy'] = $this->jobRetryStrategy[$class];
        } elseif (count($this->globalRetryStrategy)) {
            $job->args['bcc_resque.retry_strategy'] = $this->globalRetryStrategy;
        }
    }
    
    /**
     * Intersect of recursive arrays
     * needed for enqueueOnce
     */ 
    protected static function array_diff_assoc_recursive($array1, $array2) {
    $difference=array();
    foreach($array1 as $key => $value) {
        if( is_array($value) ) {
            if( !isset($array2[$key]) || !is_array($array2[$key]) ) {
                $difference[$key] = $value;
            } else {
                $new_diff = array_diff_assoc_recursive($value, $array2[$key]);
                if( !empty($new_diff) )
                    $difference[$key] = $new_diff;
            }
        } else if( !array_key_exists($key,$array2) || $array2[$key] !== $value ) {
            $difference[$key] = $value;
        }
    }
    return $difference;
}
}
