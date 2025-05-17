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

namespace Janborg\ContaoIcal\Cron;

use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCronJob('hourly')]
class GenerateIcalCron
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->framework->initialize();
    }

    public function __invoke(): void
    {
        $calendars = CalendarModel::findBy(['export_ical=?'], [1]);

        foreach ($calendars as $calendar) {
            $calendarExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);

            $calendarExporter->exportCalendar();
        }
    }
}
