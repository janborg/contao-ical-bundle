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
use Contao\CalendarEventsModel;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Generates iCal files for calendars and their events on a hourly basis.
 *
 */

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
        // find all Calendars to be exported
        $calendars = CalendarModel::findAll(
            ['export_ical=?'],
            [true]
        );
        
        if (null === $calendars) {
            return;
        }

        foreach ($calendars as $calendar) {

            // Continue if neither Calender, nor Events are set to be share as file in public/share.
            if (!$calendar->share_ical && !$calendar->share_ical_events) {
                continue;
            }

            // Export ics for the Calendar in public/share.
            if ($calendar->share_ical) {
                $calendarExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);
                $calendarExporter->exportCalendar();
            }

            // Export ics for each Event in public/share.
            if ($calendar->share_ical_events) {
                $events = CalendarEventsModel::findBy('pid', $calendar->id);
                foreach ($events as $event) {
                    $eventExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);
                    $eventExporter->exportCalendarEvent($event);
                }
            }
        }
    }
}
