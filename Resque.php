<?php

namespace BCC\ResqueBundle;

class Resque
{
    /**
     * @var array
     */
    private $kernelOptions;

    function __construct(array $kernelOptions)
    {
        $this->kernelOptions = $kernelOptions;

        // HACK, prune dead workers, just in case
        $worker = new \Resque_Worker('temp');
        $worker->pruneDeadWorkers();
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
        $id = null;

        $queue = new \BCC\ResqueBundle\Queue($job->queue);
        $jobs  = $queue->getJobs();

        foreach ($jobs AS $j) {
            if ($j->job->payload['class'] == get_class($job) && is_null($id)) {
                if (count(array_intersect($j->args, $job->args)) == count($job->args)) {
                    $id = $j->job->payload['id'];
                }
            }
        }

        if (is_null($id)) {
            $id = $this->enqueue($job, $trackStatus);
        }

        return $id;
    }

    public function getQueues()
    {
        return \array_map(function ($queue) {
            return new Queue($queue);
        }, \Resque::queues());
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

        if(!$worker) {
            return null;
        }

        return new Worker($worker);
    }
}
