<?php

namespace BCC\ResqueBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BCCResqueExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('bcc_resque.resque.vendor_dir', $config['vendor_dir']);
        $container->setParameter('bcc_resque.resque.app_include', $config['app_include']);
        $container->setParameter('bcc_resque.resque.class', $config['class']);
        $container->setParameter('bcc_resque.resque.redis.host', $config['redis']['host']);
        $container->setParameter('bcc_resque.resque.redis.port', $config['redis']['port']);
        $container->setParameter('bcc_resque.resque.redis.database', $config['redis']['database']);

        if (!empty($config['prefix'])) {
            $container->setParameter('bcc_resque.prefix', $config['prefix']);
            $container->getDefinition('bcc_resque.resque')->addMethodCall('setPrefix', array($config['prefix']));
        }

        if (!empty($config['worker']['root_dir'])) {
            $container->setParameter('bcc_resque.worker.root_dir', $config['worker']['root_dir']);
        }

        if (!empty($config['auto_retry'])) {
            if (isset($config['auto_retry'][0])) {
                $container->getDefinition('bcc_resque.resque')->addMethodCall('setGlobalRetryStrategy', array($config['auto_retry'][0]));
            } else {
                if (isset($config['auto_retry']['default'])) {
                    $container->getDefinition('bcc_resque.resque')->addMethodCall('setGlobalRetryStrategy', array($config['auto_retry']['default']));
                    unset($config['auto_retry']['default']);
                }
                $container->getDefinition('bcc_resque.resque')->addMethodCall('setJobRetryStrategy', array($config['auto_retry']));
            }
        }
    }
}
