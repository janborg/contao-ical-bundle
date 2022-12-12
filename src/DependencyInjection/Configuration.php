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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('janborg_contaoical');

        $treeBuilder->getRootNode()
            ->children()
            ->integerNode('defaultEndDateDays')
            ->defaultValue(365)
            ->end()
            ->integerNode('defaultEventDuration')
            ->defaultValue(60)
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
