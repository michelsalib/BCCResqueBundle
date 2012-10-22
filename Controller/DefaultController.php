<?php

namespace BCC\ResqueBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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
}
