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
use Contao\Message;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class GenerateIcalOnCalendarEventSubmitCallback
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function __invoke(DataContainer|null $dc = null): void
    {
        if (null === $dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()->query->get('act')) {
            return;
        }

        $calendarEvent = CalendarEventsModel::findById($dc->id);
        $calendar = CalendarModel::findById($calendarEvent->pid);

        if (null !== $calendar && $calendar->protected) {
            Message::addError('Der Kalender ist geschützt und kann daher nicht exportiert werden.');

            return;
        }

        if (
            null !== $calendar
            && $calendar->export_ical
            && $calendar->share_ical
            && !$calendar->protected
        ) {
            $calenderExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);
            $calenderExporter->exportCalendar();
            Message::addInfo('Kalender als ics-Datei unter /public/share/ abgelegt.');
        }

        if (
            null !== $calendar
            && $calendar->export_ical
            && $calendar->share_ical_events
            && !$calendar->protected
        ) {
            $calenderExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);
            $calenderExporter->exportCalendarEvent($calendarEvent);
            Message::addInfo('Event als ics-Datei unter /public/share/ abgelegt.');
        }
    }
}
