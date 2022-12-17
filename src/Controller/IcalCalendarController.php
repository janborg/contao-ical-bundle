<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
 */

namespace Janborg\ContaoIcal\Controller;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\StringUtil;
use Symfony\Component\Routing\Annotation\Route;
use Janborg\ContaoIcal\Response\CalendarResponse;
use Janborg\ContaoIcal\CalendarIcalExporter;

class IcalCalendarController 
{
    #[Route('/ical/event/{alias}', name: 'janborg_calendar_ical_event', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function ical_event(string $alias): CalendarResponse
    {
        $event = CalendarEventsModel::findByAlias($alias);

        $calendar = CalendarModel::findById($event->pid);

        $calendarIcalExporter = new CalendarIcalExporter($calendar);

        $calendarIcalExporter->createVCalendar($calendar);

        $calendarIcalExporter->addEventToVcalendar($event, $calendarIcalExporter->vCal);

        $iCalContent = $calendarIcalExporter->vCal;

        $calendarResponse = new CalendarResponse($iCalContent, StringUtil::standardize($event->title));

        return $calendarResponse;
    }

    #[Route('/ical/calendar/{id}', name: 'janborg_calendar_ical_calendar', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function ical_calendar(string $id): CalendarResponse
    {
        $calendar = CalendarModel::findOneByID($id);

        $calendarIcalExporter = new CalendarIcalExporter($calendar);

        $calendarIcalExporter->createVCalendar($calendar);

        $events = CalendarEventsModel::findByPid($calendar->id);

        foreach ($events as $event) {
            $calendarIcalExporter->addEventToVcalendar($event, $calendarIcalExporter->vCal);
        }
        
        $iCalContent = $calendarIcalExporter->vCal;

        $calendarResponse = new CalendarResponse($iCalContent, StringUtil::standardize($calendar->title));

        return $calendarResponse;
    }
}