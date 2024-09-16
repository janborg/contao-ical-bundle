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
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Symfony\Component\HttpFoundation\RequestStack;

class GenerateIcalOnCalendarEventSubmitCallback
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    #[\Contao\CoreBundle\DependencyInjection\Attribute\AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function __invoke(DataContainer|null $dc = null): void
    {
        if (null === $dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()->query->get('act')) {
            return;
        }

        $calendar = CalendarModel::findById(CalendarEventsModel::findById($dc->id)->pid);

        if (null !== $calendar && $calendar->share_ical) {
            $calenderExporter = new CalendarIcalExporter($calendar);
            $calenderExporter->exportCalendar();
        }
    }
}
