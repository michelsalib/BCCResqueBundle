<?php

namespace BCC\ResqueBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BCC\ResqueBundle\Resque;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('BCCResqueBundle:Default:index.html.twig', array(
            'resque'  => $this->get('bcc_resque.resque'),
        ));
    }

    public function listQueuesAction()
    {
        $resque = $this->get('bcc_resque.resque');

        return $this->render('BCCResqueBundle:Default:queue_list.html.twig', array(
            'queues'  => $resque->getQueues(),
        ));
    }

    public function listScheduledAction()
    {
        /** @var $resque Resque */
        $resque = $this->get('bcc_resque.resque');
        $timestamps=$resque->getDelayedJobTimestamps();
        return $this->render('BCCResqueBundle:Default:scheduled_list.html.twig',array('timestamps'=>$timestamps));
    }

    public function showTimestampAction($timestamp)
    {
        /** @var $resque Resque */
        $resque = $this->get('bcc_resque.resque');

        $jobs=array();
        // we don't want to enable the twig debug extension for this...
        foreach($resque->getJobsForTimestamp($timestamp) as $job)
        {
            $jobs[]=print_r($job,true);
        }

        return $this->render('BCCResqueBundle:Default:scheduled_timestamp.html.twig',array('timestamp'=>$timestamp,'jobs'=>$jobs));
    }
}
