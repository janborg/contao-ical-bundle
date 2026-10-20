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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Message;
use Janborg\ContaoIcal\CalendarIcalExporterFactory;
use Symfony\Component\HttpFoundation\RequestStack;

class GenerateIcalOnCalendarEventSubmitCallback
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly CalendarIcalExporterFactory $exporterFactory,
    ) {
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function __invoke(DataContainer|null $dc = null): void
    {
        if (null === $dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()?->query->get('act')) {
            return;
        }

        $calendarEvent = $this->framework->getAdapter(CalendarEventsModel::class)->findById($dc->id);

        if (null === $calendarEvent) {
            return;
        }

        $calendar = $this->framework->getAdapter(CalendarModel::class)->findById($calendarEvent->pid);

        if (null === $calendar) {
            return;
        }

        $message = $this->framework->getAdapter(Message::class);

        if ($calendar->protected) {
            $message->addError('Der Kalender ist geschützt und kann daher nicht exportiert werden.');

            return;
        }

        if ($calendar->export_ical && $calendar->share_ical) {
            $this->exporterFactory->create($calendar)->exportCalendar();
            $message->addInfo('Kalender als ics-Datei unter /public/share/ abgelegt.');
        }

        if ($calendar->export_ical && $calendar->share_ical_events) {
            $this->exporterFactory->create($calendar)->exportCalendarEvent($calendarEvent);
            $message->addInfo('Event als ics-Datei unter /public/share/ abgelegt.');
        }
    }
}
