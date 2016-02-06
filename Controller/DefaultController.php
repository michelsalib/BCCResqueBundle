<?php

namespace BCC\ResqueBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $this->getResque()->pruneDeadWorkers();

        return $this->render(
            'BCCResqueBundle:Default:index.html.twig',
            array(
                'resque' => $this->getResque(),
            )
        );
    }

    public function showQueueAction($queue, Request $request)
    {
        list($start, $count, $showingAll) = $this->getShowParameters($request);

        $queue = $this->getResque()->getQueue($queue);
        $jobs = $queue->getJobs($start, $count);

        if (!$showingAll) {
            $jobs = array_reverse($jobs);
        }

        return $this->render(
            'BCCResqueBundle:Default:queue_show.html.twig',
            array(
                'queue' => $queue,
                'jobs' => $jobs,
                'showingAll' => $showingAll
            )
        );
    }

    public function listFailedAction(Request $request)
    {
        list($start, $count, $showingAll) = $this->getShowParameters($request);

        $jobs = $this->getResque()->getFailedJobs($start, $count);

        if (!$showingAll) {
            $jobs = array_reverse($jobs);
        }

        return $this->render(
            'BCCResqueBundle:Default:failed_list.html.twig',
            array(
                'jobs' => $jobs,
                'showingAll' => $showingAll,
            )
        );
    }

    public function listScheduledAction()
    {
        return $this->render(
            'BCCResqueBundle:Default:scheduled_list.html.twig',
            array(
                'timestamps' => $this->getResque()->getDelayedJobTimestamps()
            )
        );
    }

    public function showTimestampAction($timestamp)
    {
        $jobs = array();

        // we don't want to enable the twig debug extension for this...
        foreach ($this->getResque()->getJobsForTimestamp($timestamp) as $job) {
            $jobs[] = print_r($job, true);
        }

        return $this->render(
            'BCCResqueBundle:Default:scheduled_timestamp.html.twig',
            array(
                'timestamp' => $timestamp,
                'jobs' => $jobs
            )
        );
    }

    /**
     * @return \BCC\ResqueBundle\Resque
     */
    protected function getResque()
    {
        return $this->get('bcc_resque.resque');
    }

    /**
     * decide which parts of a job queue to show
     *
     * @return array
     */
    private function getShowParameters(Request $request)
    {
        $showingAll = false;
        $start = -100;
        $count = -1;

        if ($request->query->has('all')) {
            $start = 0;
            $count = -1;
            $showingAll = true;
        }

        return array($start, $count, $showingAll);
    }
}
