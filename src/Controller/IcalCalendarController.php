<?php

declare(strict_types=1);

namespace Janborg\ContaoIcal\Controller;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Janborg\ContaoIcal\Response\CalendarResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IcalCalendarController
{
    public function __construct(
        protected ContaoFramework $framework,
        protected Security $security,
        protected TokenChecker $tokenChecker,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('/ical/event/{alias}', name: 'janborg_calendar_ical_event', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function ical_event(Request $request, string $alias): CalendarResponse|Response
    {
        // Initialize the Contao framework
        $this->framework->initialize();

        // Set the root page for the domain as the pageModel attribute
        $root = $this->findFirstPublishedRootByHostAndLanguage($request->getHost(), $request->getLocale());

        if (null !== $root) {
            $root->loadDetails();
            $request->attributes->set('pageModel', $root);
            $GLOBALS['objPage'] = $root;
        }

        $event = CalendarEventsModel::findByAlias($alias);

        // check, if Event exists
        if (null === $event) {
            throw new PageNotFoundException();
        }

        $calendar = CalendarModel::findById($event->pid);

        if ($calendar->protected && !$this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($calendar->groups, true))) {
            throw new AccessDeniedException();
        }

        $calendarIcalExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);

        $calendarIcalExporter->createVCalendar($calendar);

        $calendarIcalExporter->addEventToVcalendar($event, $calendarIcalExporter->vCal);

        $iCalContent = $calendarIcalExporter->vCal;

        return new CalendarResponse($iCalContent, StringUtil::standardize($event->title));
    }

    #[Route('/ical/calendar/{ical_alias}', name: 'janborg_calendar_ical_calendar', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function ical_calendar(Request $request, string $ical_alias): CalendarResponse|Response
    {
        $calendar = CalendarModel::findOneBy('ical_alias', $ical_alias);

        // Initialize the Contao framework
        $this->framework->initialize();

        // Set the root page for the domain as the pageModel attribute
        $root = $this->findFirstPublishedRootByHostAndLanguage($request->getHost(), $request->getLocale());

        if (null !== $root) {
            $root->loadDetails();
            $request->attributes->set('pageModel', $root);
            $GLOBALS['objPage'] = $root;
        }

        if (null === $calendar || !$calendar->export_ical) {
            throw new PageNotFoundException();
        }

        // check, if calendar is protected
        if ($calendar->protected && !$this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($calendar->groups, true))) {
            throw new AccessDeniedException();
        }

        $calendarIcalExporter = new CalendarIcalExporter($calendar, $this->eventDispatcher);

        $calendarIcalExporter->createVCalendar($calendar);

        $startDate = $calendar->ical_export_start ?? time();

        $endDate = $calendar->ical_export_end ?? time() + System::getContainer()->getParameter('janborg_contao_ical.defaultEndDateDays') * 24 * 3600;

        $objEvents = CalendarEventsModel::findCurrentByPid($calendar->id, $startDate, $endDate);

        foreach ($objEvents as $event) {
            $calendarIcalExporter->addEventToVcalendar($event, $calendarIcalExporter->vCal);
        }

        $iCalContent = $calendarIcalExporter->vCal;

        return new CalendarResponse($iCalContent, StringUtil::standardize($calendar->title));
    }

    protected function findFirstPublishedRootByHostAndLanguage(string $host, string $language): PageModel|null
    {
        $t = PageModel::getTable();
        $columns = ["$t.type='root' AND ($t.dns=? OR $t.dns='') AND ($t.language=? OR $t.fallback='1')"];
        $values = [$host, $language];
        $options = ['order' => "$t.dns DESC, $t.fallback"];

        if (!$this->tokenChecker->isPreviewMode()) {
            $time = Date::floorToMinute();
            $columns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        return PageModel::findOneBy($columns, $values, $options);
    }
}
