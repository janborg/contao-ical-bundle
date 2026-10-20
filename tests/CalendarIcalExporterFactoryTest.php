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

namespace Janborg\ContaoIcal\Tests;

use Contao\CalendarModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\CalendarIcalExporterFactory;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CalendarIcalExporterFactoryTest extends ContaoTestCase
{
    protected function tearDown(): void
    {
        $this->resetStaticProperties([System::class]);

        parent::tearDown();
    }

    public function testCreatesAnExporterForTheGivenCalendar(): void
    {
        $container = new Container();
        $container->setParameter('contao.web_dir', '/tmp');
        $container->setParameter('janborg_contao_ical.defaultEndDateDays', 365);

        System::setContainer($container);

        $calendar = $this->createClassWithPropertiesStub(CalendarModel::class, ['ical_alias' => 'team-events']);

        $exporter = (new CalendarIcalExporterFactory($this->createStub(EventDispatcherInterface::class)))->create($calendar);

        $this->assertSame($calendar, $exporter->objCalendar);
        $this->assertSame('team-events.ics', $exporter->exportFileName);
    }
}
