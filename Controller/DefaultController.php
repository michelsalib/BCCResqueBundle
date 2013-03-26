<?php

namespace BCC\ResqueBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BCC\ResqueBundle\Resque;

class DefaultController extends Controller
{
    /**
     * @return \BCC\ResqueBundle\Resque
     */
    protected function getResque()
    {
        return $this->get('bcc_resque.resque');
    }

    public function indexAction()
    {
        return $this->render('BCCResqueBundle:Default:index.html.twig', array(
            'resque'  => $this->getResque(),
        ));
    }

    public function listQueuesAction()
    {
        return $this->render('BCCResqueBundle:Default:queue_list.html.twig', array(
            'queues'  => $this->getResque()->getQueues(),
        ));
    }

    public function listFailedAction()
    {
        return $this->render('BCCResqueBundle:Default:failed_list.html.twig', array(
            'failed'  => $this->getResque()->getFailedJobs(),
        ));
    }

    public function listScheduledAction()
    {
        return $this->render('BCCResqueBundle:Default:scheduled_list.html.twig', array(
            'timestamps' => $this->getResque()->getDelayedJobTimestamps()
        ));
    }

    public function showTimestampAction($timestamp)
    {
        $jobs = array();

        // we don't want to enable the twig debug extension for this...
        foreach($this->getResque()->getJobsForTimestamp($timestamp) as $job)
        {
            $jobs[]=print_r($job,true);
        }

        return $this->render('BCCResqueBundle:Default:scheduled_timestamp.html.twig', array(
            'timestamp' => $timestamp,
            'jobs'      => $jobs
        ));
    }
}
