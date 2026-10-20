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

class GenerateIcalOnCalendarSubmitCallback
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly CalendarIcalExporterFactory $exporterFactory,
    ) {
    }

    #[AsCallback(table: 'tl_calendar', target: 'config.onsubmit')]
    public function __invoke(DataContainer|null $dc = null): void
    {
        if (null === $dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()?->query->get('act')) {
            return;
        }

        $calendar = $this->framework->getAdapter(CalendarModel::class)->findById($dc->id);

        if (null === $calendar) {
            return;
        }

        $message = $this->framework->getAdapter(Message::class);

        if ($calendar->protected) {
            $message->addError('Der Kalender ist geschützt und kann daher nicht exportiert werden.');

            return;
        }

        // only proceed if ical export is enabled and not protected
        if (!$calendar->export_ical) {
            return;
        }

        // create ical file for calendar if enabled
        if ($calendar->share_ical) {
            $this->exporterFactory->create($calendar)->exportCalendar();
            $message->addInfo('Kalender als ics-Datei unter /public/share/ abgelegt.');
        }

        // create ical file for each event if enabled
        if ($calendar->share_ical_events) {
            $calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class)->findByPid($dc->id);

            if (null !== $calendarEvents) {
                $calendarExporter = $this->exporterFactory->create($calendar);

                foreach ($calendarEvents as $calendarEvent) {
                    $calendarExporter->exportCalendarEvent($calendarEvent);
                    $message->addInfo('Events als einzelne ics-Dateien unter /public/share/ abgelegt.');
                }
            }
        }
    }
}
