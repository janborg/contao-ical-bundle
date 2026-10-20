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
use Janborg\ContaoIcal\CalendarIcalExporterFactory;
use Janborg\ContaoIcal\Response\CalendarResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IcalCalendarController
{
    public function __construct(
        protected ContaoFramework $framework,
        protected Security $security,
        protected TokenChecker $tokenChecker,
        private readonly CalendarIcalExporterFactory $exporterFactory,
    ) {
    }

    #[Route(path: '/ical/event/{alias}', name: 'janborg_calendar_ical_event', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function ical_event(Request $request, string $alias): CalendarResponse|Response
    {
        // Initialize the Contao framework
        $this->framework->initialize();

        $this->setRootPage($request);

        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findByAlias($alias);

        // check, if Event exists
        if (null === $event) {
            throw new PageNotFoundException();
        }

        $calendar = $this->framework->getAdapter(CalendarModel::class)->findById($event->pid);

        if (null === $calendar) {
            throw new PageNotFoundException();
        }

        $this->denyAccessToProtectedCalendar($calendar);

        $calendarIcalExporter = $this->exporterFactory->create($calendar);

        $calendarIcalExporter->createVCalendar($calendar);

        $calendarIcalExporter->addEventToVcalendar($event, $calendarIcalExporter->vCal);

        return new CalendarResponse($calendarIcalExporter->vCal, StringUtil::standardize($event->title));
    }

    #[Route(path: '/ical/calendar/{ical_alias}', name: 'janborg_calendar_ical_calendar', defaults: ['_scope' => 'frontend', '_token_check' => true])]
    public function ical_calendar(Request $request, string $ical_alias): CalendarResponse|Response
    {
        // Initialize the Contao framework
        $this->framework->initialize();

        $calendar = $this->framework->getAdapter(CalendarModel::class)->findOneBy('ical_alias', $ical_alias);

        $this->setRootPage($request);

        if (null === $calendar || !$calendar->export_ical) {
            throw new PageNotFoundException();
        }

        $this->denyAccessToProtectedCalendar($calendar);

        $calendarIcalExporter = $this->exporterFactory->create($calendar);

        $calendarIcalExporter->createVCalendar($calendar);

        $objEvents = $this->framework->getAdapter(CalendarEventsModel::class)->findCurrentByPid($calendar->id, $calendarIcalExporter->startDate, $calendarIcalExporter->endDate);

        // deliver an empty calendar, if there are no events in the export period
        foreach ($objEvents ?? [] as $event) {
            $calendarIcalExporter->addEventToVcalendar($event, $calendarIcalExporter->vCal);
        }

        return new CalendarResponse($calendarIcalExporter->vCal, StringUtil::standardize($calendar->title));
    }

    protected function findFirstPublishedRootByHostAndLanguage(string $host, string $language): PageModel|null
    {
        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        $t = $pageAdapter->getTable();
        $columns = ["$t.type='root' AND ($t.dns=? OR $t.dns='') AND ($t.language=? OR $t.fallback='1')"];
        $values = [$host, $language];
        $options = ['order' => "$t.dns DESC, $t.fallback"];

        if (!$this->tokenChecker->isPreviewMode()) {
            $time = $this->framework->getAdapter(Date::class)->floorToMinute();
            $columns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        return $pageAdapter->findOneBy($columns, $values, $options);
    }

    /**
     * Sets the root page for the domain as the pageModel attribute.
     */
    private function setRootPage(Request $request): void
    {
        $root = $this->findFirstPublishedRootByHostAndLanguage($request->getHost(), $request->getLocale());

        if (null === $root) {
            return;
        }

        $root->loadDetails();
        $request->attributes->set('pageModel', $root);
        $GLOBALS['objPage'] = $root;
    }

    private function denyAccessToProtectedCalendar(CalendarModel $calendar): void
    {
        if ($calendar->protected && !$this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($calendar->groups, true))) {
            throw new AccessDeniedException();
        }
    }
}
