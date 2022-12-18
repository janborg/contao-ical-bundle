<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
 */

namespace Janborg\ContaoIcal\EventListener\DataContainer;

use Contao\CalendarModel;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Symfony\Component\HttpFoundation\RequestStack;

class GenerateIcalOnCalendarSubmitCallback
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @Callback(table="tl_calendar", target="config.onsubmit")
     */
    public function __invoke(DataContainer $dc = null): void
    {
        if (null === $dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()->query->get('act')) {
            return;
        }

        $calendar = CalendarModel::findByPk($dc->id);

        if ($calendar->export_ical && $calendar->share_ical) {
            $calenderExporter = new CalendarIcalExporter($calendar);
            $calenderExporter->exportCalendar();
        } else {
            return;
        }
    }
}
