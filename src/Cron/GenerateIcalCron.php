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

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Janborg\ContaoIcal\CalendarIcalExporterFactory;

/**
 * Generates iCal files for calendars and their events on a hourly basis.
 */
#[AsCronJob('hourly')]
class GenerateIcalCron
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CalendarIcalExporterFactory $exporterFactory,
    ) {
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        // find all Calendars to be exported
        $calendars = $this->framework->getAdapter(CalendarModel::class)->findBy(
            ['export_ical=?'],
            [true],
        );

        if (null === $calendars) {
            return;
        }

        foreach ($calendars as $calendar) {
            // skip calendar, if neither share_ical nor share_ical_events is true.
            if (!$calendar->share_ical && !$calendar->share_ical_events) {
                continue;
            }

            // skip calendar, if protected
            if ($calendar->protected) {
                continue;
            }

            // Export ics for the Calendar in public/share.
            if ($calendar->share_ical) {
                $this->exporterFactory->create($calendar)->exportCalendar();
            }

            // Export ics for each Event in public/share.
            if ($calendar->share_ical_events) {
                $events = $this->framework->getAdapter(CalendarEventsModel::class)->findBy('pid', $calendar->id);

                if (null === $events) {
                    continue;
                }

                $eventExporter = $this->exporterFactory->create($calendar);

                foreach ($events as $event) {
                    $eventExporter->exportCalendarEvent($event);
                }
            }
        }
    }
}
