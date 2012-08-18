# Intro to BCC Resque Bundle

The BCC resque bundle provides integration of php-resque to Symfony2. It is inspired from resque, a Redis-backed Ruby library for creating background jobs, placing them on multiple queues, and processing them later.

## Features:

- Creating a Job, with container access in order to leverage your Symfony services
- Enqueue a Job wih parameters on a given queue
- Creating background worker on a given queue
- A UX to monitor your queues, workers and job statuses

TODOs:
- Log management
- Integrate scheduler
- Job status tracking
- Redis configuration
- Localisation
- Tests

## Screenshots
### Dashboard
![](https://github.com/michelsalib/BCCResqueBundle/raw/master/Resources/screens/home.png)

## Installation and configuration:

### Requirements

Make sure you have redis installed on your machine: http://redis.io/

### Get the bundle

Add to your `bcc-resque-bundle` to your dependencies:

``` json
{
    "require": {
        ...
        "bcc/resque-bundle": "dev-master"
    }
    ...
}
```

And make a `php composer.phar update`.

### Add BCCResqueBundle to your application kernel

``` php
<?php

    // app/AppKernel.php
    public function registerBundles()
    {
        return array(
            // ...
            new BCC\ResqueBundle\BCCResqueBundle(),
            // ...
        );
    }
```

### Import the routing configuration

Add to your `routing.yml`:

``` yml
#BCCResqueBundle routing
BCCResqueBundle:
    resource: "@BCCResqueBundle/Resources/config/routing.xml"
    prefix:   /resque
```

You can customize the prefix as you wish.

You can now acces the dashboard at this url: `/resque`

## Creating a Job

A job is a subclass of the `BCC\ResqueBundle\Job` class. You also can use the `BCC\Resque\ContainerAwareJob` if you need to leverage the container during job execution.
You will be forced to implement the run method that will contain your job logic:

``` php
<?php

namespace My;

use BCC\ResqueBundle\Job;

class MyJob extends Job
{
    public function run($args)
    {
        \file_put_contents($args['file'], $args['content']);
    }
}
```

As you can see you get an $args parameter that is the array of arguments of your Job.

## Adding a job to a queue

You can get the resque service simply by using the container. From your controller you can do:

``` php
<?php

// get resque
$resque = $this->get('bcc_resque.resque');

// create your job
$job = new MyJob();
$job->args = array(
    'file'    => '/tmp/file',
    'content' => 'hello',
);

// enqueue your job
$resque->enqueue($job);
```

## Running a worker on a queue

Just by using the following command you will create a worker on the default queue:
`app/console bcc:resque:worker-start default`

You can run a worker on several queues just separeate then using `,`. If you want a worker on every queues, just use `*`.
You can also run a worker foreground by adding the `--foreground` option;

## More features

### Changing the queue

You can change a job queue just by setting the `queue` field of the job:

From within the job:

``` php
<?php

namespace My;

use BCC\ResqueBundle\Job;

class MyJob extends Job
{
    public function __construct()
    {
        $this->queue = 'my_queue';
    }

    public function run($args)
    {
        ...
    }
}
```

Or from outsite the job:

``` php
<?php

// create your job
$job = new MyJob();
$job->job = 'my_queue';
```

### Access the container from inside your job

Just extend the `ContainerAwareJob`:

``` php
<?php

namespace My;

use BCC\ResqueBundle\ContainerAwareJob;

class MyJob extends ContainerAwareJob
{
    public function run($args)
    {
        $doctrine = $this->getContainer()->getDoctrine();
        ...
    }
}
```

### Stop a worker

Use the `app/console bcc:resque:worker-stop` command.

- No argument will display running workers that you can stop.
- Add a worker id to stop it: `app/console bcc:resque:worker-stop ubuntu:3949:default`
- Add the `--all` option to stop all the workers.
