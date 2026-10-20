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

namespace Janborg\ContaoIcal\Tests\DependencyInjection;

use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Janborg\ContaoIcal\Controller\IcalCalendarController;
use Janborg\ContaoIcal\Cron\GenerateIcalCron;
use Janborg\ContaoIcal\Cron\RemoveOldIcalFilesCron;
use Janborg\ContaoIcal\DependencyInjection\JanborgContaoIcalExtension;
use Janborg\ContaoIcal\EventListener\DataContainer\GenerateIcalOnCalendarEventSubmitCallback;
use Janborg\ContaoIcal\EventListener\DataContainer\GenerateIcalOnCalendarSubmitCallback;
use Janborg\ContaoIcal\Migration\StartdateEnddateMigration;
use Janborg\ContaoIcal\Response\CalendarResponse;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class JanborgContaoIcalExtensionTest extends ContaoTestCase
{
    public function testUsesTheConfigurationRootKeyAsAlias(): void
    {
        $this->assertSame('janborg_contao_ical', (new JanborgContaoIcalExtension())->getAlias());
    }

    public function testSetsDefaultParameters(): void
    {
        $container = $this->loadExtension([]);

        $this->assertSame(365, $container->getParameter('janborg_contao_ical.defaultEndDateDays'));
        $this->assertSame(60, $container->getParameter('janborg_contao_ical.defaultEventDuration'));
    }

    public function testSetsConfiguredParameters(): void
    {
        $container = $this->loadExtension([['defaultEndDateDays' => 30, 'defaultEventDuration' => 90]]);

        $this->assertSame(30, $container->getParameter('janborg_contao_ical.defaultEndDateDays'));
        $this->assertSame(90, $container->getParameter('janborg_contao_ical.defaultEventDuration'));
    }

    public function testRejectsNonIntegerConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->loadExtension([['defaultEndDateDays' => 'a year']]);
    }

    public function testRegistersTheServices(): void
    {
        $container = $this->loadExtension([]);

        foreach ([
            IcalCalendarController::class,
            GenerateIcalCron::class,
            RemoveOldIcalFilesCron::class,
            GenerateIcalOnCalendarSubmitCallback::class,
            GenerateIcalOnCalendarEventSubmitCallback::class,
            StartdateEnddateMigration::class,
        ] as $serviceId) {
            $this->assertTrue($container->hasDefinition($serviceId), $serviceId.' is not registered');
            $this->assertTrue($container->getDefinition($serviceId)->isAutoconfigured(), $serviceId.' is not autoconfigured');
        }

        $this->assertTrue($container->getDefinition(IcalCalendarController::class)->hasTag('controller.service_arguments'));
    }

    public function testDoesNotRegisterValueObjectsAsServices(): void
    {
        $container = $this->loadExtension([]);

        // Excluded classes are kept as "container.excluded" definitions and removed on compile
        $this->assertTrue($container->getDefinition(CalendarIcalExporter::class)->hasTag('container.excluded'));
        $this->assertFalse($container->hasDefinition(CalendarResponse::class));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function loadExtension(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();

        (new JanborgContaoIcalExtension())->load($configs, $container);

        return $container;
    }
}
