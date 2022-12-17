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
    public const ROOT_KEY = 'janborg_contao_ical';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_KEY);

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
