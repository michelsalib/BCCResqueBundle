<?php

namespace BCC\ResqueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartScheduledWorkerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('bcc:resque:scheduledworker-start')
            ->setDescription('Start a bcc scheduled resque worker')
            ->addOption('foreground', 'f', InputOption::VALUE_NONE, 'Should the worker run in foreground')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force creation of a new worker if the PID file exists')
            ->addOption('interval', 'i', InputOption::VALUE_REQUIRED, 'How often to check for new jobs across the queues', 5)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pidFile=$this->getContainer()->get('kernel')->getCacheDir().'/bcc_resque_scheduledworker.pid';
        if (file_exists($pidFile) && !$input->getOption('force')) {
            throw new \Exception('PID file exists - use --force to override');
        }

        if (file_exists($pidFile)) {
            unlink($pidFile);
        }

        $env = array(
            'APP_INCLUDE' => $this->getContainer()->getParameter('kernel.root_dir').'/bootstrap.php.cache',
            'VVERBOSE'    => 1,
            'RESQUE_PHP'  => $this->getContainer()->getParameter('bcc_resque.resque.vendor_dir').'/chrisboulton/php-resque/lib/Resque.php',
            'INTERVAL'    => $input->getOption('interval'),
        );

        $prefix = $this->getContainer()->getParameter('bcc_resque.prefix');
        if (!empty($prefix)) {
            $env['PREFIX'] = $this->getContainer()->getParameter('bcc_resque.prefix');
        }

        $redisHost     = $this->getContainer()->getParameter('bcc_resque.resque.redis.host');
        $redisPort     = $this->getContainer()->getParameter('bcc_resque.resque.redis.port');
        $redisDatabase = $this->getContainer()->getParameter('bcc_resque.resque.redis.database');
        $redisPassword = $this->getContainer()->getParameter('bcc_resque.resque.redis.password');

        if ($redisHost != null && $redisPort != null) {
            $env['REDIS_BACKEND'] = $redisHost.':'.$redisPort;
        }
        if (isset($redisDatabase)) {
            $env['REDIS_BACKEND_DB'] = $redisDatabase;
        }
        if (isset($redisPassword)) {
            $env['REDIS_BACKEND_PASSWORD'] = $redisPassword;
        }
        
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $phpExecutable = PHP_BINARY;
        } else {
            $phpExecutable = PHP_BINDIR . '/php';
            if (defined('PHP_WINDOWS_VERSION_BUILD')) {
                $phpExecutable = 'php';
            }
        }

        $workerCommand = strtr('%php% %dir%/resque-scheduler', array(
            '%php%' => $phpExecutable,
            '%dir%' => __DIR__ . '/../bin',
        ));

        if (!$input->getOption('foreground')) {
            $workerCommand = strtr('%nohup% %cmd% > %logs_dir%/resque-scheduler_%env%.log 2>&1 & echo $!', array(
                '%nohup%'    => self::commandExists('nohup') ? 'nohup' : 'disown',
                '%cmd%'      => $workerCommand,
                '%logs_dir%' => $this->getContainer()->getParameter('kernel.logs_dir'),
                '%env%'      => $this->getContainer()->getParameter('kernel.environment')
            ));
        }

		// In windows: When you pass an environment to CMD it replaces the old environment
		// That means we create a lot of problems with respect to user accounts and missing vars
		// this is a workaround where we add the vars to the existing environment.
		if (defined('PHP_WINDOWS_VERSION_BUILD')) {
			foreach($env as $key => $value) {
				putenv($key . '=' . $value);
			}
			$env = null;
		}

        $process = new Process($workerCommand, null, $env, null, null);

        $output->writeln(sprintf('Starting worker <info>%s</info>', $process->getCommandLine()));

        if ($input->getOption('foreground')) {
            $process->run(function ($type, $buffer) use ($output) {
                    $output->write($buffer);
                });
        } else {
            // else we recompose and display the worker id
            $process->run();
            $pid = trim($process->getOutput());
            if (function_exists('gethostname')) {
                $hostname = gethostname();
            } else {
                $hostname = php_uname('n');
            }
            $output->writeln(sprintf('<info>Worker started</info> %s:%s', $hostname, $pid));
            file_put_contents($pidFile,$pid);
        }
    }
}
