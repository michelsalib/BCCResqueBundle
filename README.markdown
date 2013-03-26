# Intro to BCC Resque Bundle

The BCC resque bundle provides integration of php-resque to Symfony2. It is inspired from resque, a Redis-backed Ruby library for creating background jobs, placing them on multiple queues, and processing them later.

## Features:

- Creating a Job, with container access in order to leverage your Symfony services
- Enqueue a Job wih parameters on a given queue
- Creating background worker on a given queue
- A UX to monitor your queues, workers and job statuses
- ability to schedule jobs to run at a specific time or after a number of seconds delay

TODOs:
- Log management
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
        "bcc/resque-bundle": "1.1"
    }
    ...
}
```

To install, run `php composer.phar [update|install]`.

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
# app/config/routing.yml
BCCResqueBundle:
    resource: "@BCCResqueBundle/Resources/config/routing.xml"
    prefix:   /resque
```

You can customize the prefix as you wish.

You can now acces the dashboard at this url: `/resque`

### Optional, set configuration

You may want to add some configuration to your `config.yml`

``` yml
# app/config/config.yml
bcc_resque:
    class: BCC\ResqueBundle\Resque           # the resque class if different from default
    vendor_dir: %kernel.root_dir%/../vendor  # the vendor dir if different from default
    prefix: my-resque-prefix                 # optional prefix to separate Resque data per site/app
    redis:
        host: localhost                      # the redis host
        port: 6379                           # the redis port
        database: 1                          # the redis database
```

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

Executing the following commands will create a work on :
- the `default` queue : `app/console bcc:resque:worker-start default`
- the `q1` and `q2` queue : `app/console bcc:resque:worker-start q1,q2` (separate name with `,`)
- all existing queues : `app/console bcc:resque:worker-start "*"`

You can also run a worker foreground by adding the `--foreground` option;

By default `VERBOSE` environment variable is set when calling php-resque
- `--verbose` option sets `VVERBOSE`
- `--quiet` disables both so no debug output is thrown

See php-resque logging option : https://github.com/chrisboulton/php-resque#logging

## Adding a delayed job to a queue

You can specify that a job is run at a specific time or after a specific delay (in seconds).

From your controller you can do:

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

// enqueue your job to run at a specific \DateTime or int unix timestamp
$resque->enqueueAt(\DateTime|int $at, $job);

// or

// enqueue your job to run after a number of seconds
$resque->enqueueIn($seconds, $job);

```

You must also run a `scheduledworker`, which is responsible for taking items out of the special delayed queue and putting
them into the originally specified queue.

`app/console bcc:resque:scheduledworker-start`

Stop it later with `app/console bcc:resque:scheduledworker-stop`.

Note that when run in background mode it creates a PID file in 'cache/<environment>/bcc_resque_scheduledworker.pid'. If you
clear your cache while the scheduledworker is running you won't be able to stop it with the `scheduledworker-stop` command.

Alternatively, you can run the scheduledworker in the foreground with the `--foreground` option.

Note also you should only ever have one scheduledworker running, and if the PID file already exists you will have to use
the `--force` option to start a scheduledworker.

## Manage production workers with supervisord

It's probably best to use supervisord (http://supervisord.org) to run the workers in production, rather than re-invent job
spawning, monitoring, stopping and restarting.

Here's a sample conf file

```ini
[program:myapp_phpresque_default]
command = /usr/bin/php /home/sites/myapp/prod/current/vendor/chrisboulton/php-resque/resque.php
user = myusername
environment = APP_INCLUDE='/home/sites/myapp/prod/current/vendor/autoload.php',VERBOSE='1',QUEUE='default'

[program:myapp_phpresque_scheduledworker]
command = /usr/bin/php /home/sites/myapp/prod/current/vendor/chrisboulton/php-resque-scheduler/resque-scheduler.php
user = myusername
environment = APP_INCLUDE='/home/sites/myapp/prod/current/vendor/autoload.php',VERBOSE='1',RESQUE_PHP='/home/sites/myapp/prod/current/vendor/chrisboulton/php-resque/lib/Resque.php'

[group:myapp]
programs=myapp_phpresque_default,myapp_phpresque_scheduledworker
```

(If you use a custom Resque prefix, add an extra environment variable: PREFIX='my-resque-prefix')

Then in Capifony you can do

`sudo supervisorctl stop myapp:*` before deploying your app and `sudo supervisorctl start myapp:*` afterwards.

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

Or from outside the job:

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
