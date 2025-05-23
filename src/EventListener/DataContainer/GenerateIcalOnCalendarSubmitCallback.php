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

namespace Janborg\ContaoIcal\EventListener\DataContainer;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class GenerateIcalOnCalendarSubmitCallback
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[AsCallback(table: 'tl_calendar', target: 'config.onsubmit')]
    public function __invoke(DataContainer|null $dc = null): void
    {
        if (null === $dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()->query->get('act')) {
            return;
        }

        $calendar = CalendarModel::findById($dc->id);

        // only proceed if ical export is enabled and not protected
        if (!$calendar->export_ical || $calendar->protected) {
            return;
        }

        // create ical file for calendar if enabled
        if ($calendar->share_ical) {
            $calenderExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);
            $calenderExporter->exportCalendar();
        }

        // create ical file for each event if enabled
        if ($calendar->share_ical_events) {
            $calendarEvents = CalendarEventsModel::findByPid($dc->id);

            if (null !== $calendarEvents) {
                foreach ($calendarEvents as $calendarEvent) {
                    $calenderExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);
                    $calenderExporter->exportCalendarEvent($calendarEvent);
                }
            }
        }
    }
}
