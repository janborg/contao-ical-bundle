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
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\Routing\Annotation\Route;
use Janborg\ContaoIcal\Response\CalendarResponse;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Symfony\Component\HttpFoundation\Response;

class IcalCalendarController
{
    #[Route('/ical/event/{alias}', name: 'janborg_calendar_ical_event', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function ical_event(string $alias): CalendarResponse|Response
    {
        $event = CalendarEventsModel::findByAlias($alias);

        $calendar = CalendarModel::findById($event->pid);

        $security = System::getContainer()->get('security.helper');

        if ($calendar->protected && !$security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($calendar->groups, true))) {
            $response = new Response('', 403);
            return $response;
        }

        $calendarIcalExporter = new CalendarIcalExporter($calendar);

        $calendarIcalExporter->createVCalendar($calendar);

        $calendarIcalExporter->addEventToVcalendar($event, $calendarIcalExporter->vCal);

        $iCalContent = $calendarIcalExporter->vCal;

        $calendarResponse = new CalendarResponse($iCalContent, StringUtil::standardize($event->title));

        return $calendarResponse;
    }

    #[Route('/ical/calendar/{id}', name: 'janborg_calendar_ical_calendar', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function ical_calendar(string $id): CalendarResponse|Response
    {
        $calendar = CalendarModel::findOneByID($id);

        $security = System::getContainer()->get('security.helper');

        if ($calendar->protected && !$security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($calendar->groups, true))) {
            $response = new Response('', 403);
            return $response;
        }

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
