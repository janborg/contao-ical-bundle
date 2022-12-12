<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
 */

namespace Janborg\ContaoIcal\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class JanborgContaoIcalExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
        $loader->load('listener.yaml');

        // Configuration
        $container->setParameter('janborg_contaoical.defaultEndDateDays', $config['defaultEndDateDays']);
        $container->setParameter('janborg_contaoical.defaultEventDuration', $config['defaultEventDuration']);
    }
}
