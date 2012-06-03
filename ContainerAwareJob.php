<?php

namespace BCC\ResqueBundle;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ContainerAwareJob extends Job
{
    /**
     * @var KernelInterface
     */
    private $kernel = null;

    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        if ($this->kernel === null) {
            $this->kernel = $this->createKernel();
            $this->kernel->boot();
        }

        return $this->kernel->getContainer();
    }

    public function setKernelOptions(array $kernelOptions)
    {
        $this->args = \array_merge($this->args, $kernelOptions);
    }

    /**
     * @return KernelInterface
     */
    protected function createKernel()
    {
        $finder = new Finder();
        $finder->name('*Kernel.php')->depth(0)->in($this->args['kernel.root_dir']);
        $results = iterator_to_array($finder);
        $file = current($results);
        $class = $file->getBasename('.php');

        require_once $file;

        return new $class(
            isset($this->args['kernel.environment']) ? $this->args['kernel.environment'] : 'dev',
            isset($this->args['kernel.debug']) ? $this->args['kernel.debug'] : true
        );
    }

    public function tearDown()
    {
        if ($this->kernel) {
            $this->kernel->shutdown();
        }
    }
}
