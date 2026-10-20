<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg 2022 <jan-github@luenborg.eu>
 *
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 *
 * @link https://github.com/janborg/contao-ical-bundle
 */

namespace Janborg\ContaoIcal\Tests\ContaoManager;

use Contao\CalendarBundle\ContaoCalendarBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\ContaoManager\Plugin;
use Janborg\ContaoIcal\JanborgContaoIcalBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

class PluginTest extends ContaoTestCase
{
    public function testReturnsTheBundles(): void
    {
        $bundles = (new Plugin())->getBundles($this->createStub(ParserInterface::class));

        $this->assertCount(1, $bundles);
        $this->assertInstanceOf(BundleConfig::class, $bundles[0]);
        $this->assertSame(JanborgContaoIcalBundle::class, $bundles[0]->getName());
        $this->assertSame([ContaoCalendarBundle::class, ContaoCoreBundle::class], $bundles[0]->getLoadAfter());
    }

    public function testLoadsTheBundleRoutes(): void
    {
        $routesFile = realpath(__DIR__.'/../../config/routes.yaml');
        $collection = new RouteCollection();

        $loader = $this->createMock(LoaderInterface::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with($this->callback(static fn (string $file): bool => realpath($file) === $routesFile))
            ->willReturn($collection)
        ;

        $resolver = $this->createMock(LoaderResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('resolve')
            ->with($this->callback(static fn (string $file): bool => realpath($file) === $routesFile))
            ->willReturn($loader)
        ;

        $this->assertSame($collection, (new Plugin())->getRouteCollection($resolver, $this->createStub(KernelInterface::class)));
    }
}
