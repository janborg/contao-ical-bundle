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

namespace Janborg\ContaoIcal\Tests\Controller;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Model\Collection;
use Contao\PageModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\CalendarIcalExporterFactory;
use Janborg\ContaoIcal\Controller\IcalCalendarController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IcalCalendarControllerTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->setParameter('contao.web_dir', '/tmp');
        $container->setParameter('janborg_contao_ical.defaultEndDateDays', 365);
        $container->setParameter('janborg_contao_ical.defaultEventDuration', 60);

        System::setContainer($container);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['objPage']);

        $this->resetStaticProperties([System::class]);

        parent::tearDown();
    }

    public function testExportsTheCalendarWithItsCurrentEvents(): void
    {
        $start = strtotime('2026-10-01 00:00:00 UTC');
        $end = strtotime('2026-12-31 00:00:00 UTC');

        $calendar = $this->createCalendar([
            'id' => 3,
            'title' => 'Team Events',
            'export_ical' => '1',
            'ical_export_start' => $start,
            'ical_export_end' => $end,
        ]);

        $eventsAdapter = $this->createAdapterMock(['findCurrentByPid']);
        $eventsAdapter
            ->expects($this->once())
            ->method('findCurrentByPid')
            ->with(3, $start, $end)
            ->willReturn(new Collection([$this->createEvent('Training')], 'tl_calendar_events'))
        ;

        $response = $this->createController([
            CalendarModel::class => $this->createCalendarAdapter('findOneBy', 'team-events', $calendar),
            CalendarEventsModel::class => $eventsAdapter,
        ])->ical_calendar(Request::create('https://example.com/ical/calendar/team-events'), 'team-events');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('attachment; filename="team-events.ics"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('X-WR-CALNAME:Team Events', (string) $response->getContent());
        $this->assertStringContainsString('SUMMARY:Training', (string) $response->getContent());
    }

    public function testExportsAnEmptyCalendarIfThereAreNoCurrentEvents(): void
    {
        $calendar = $this->createCalendar(['id' => 3, 'title' => 'Team Events', 'export_ical' => '1']);

        $response = $this->createController([
            CalendarModel::class => $this->createCalendarAdapter('findOneBy', 'team-events', $calendar),
            CalendarEventsModel::class => $this->createConfiguredAdapterStub(['findCurrentByPid' => null]),
        ])->ical_calendar(Request::create('https://example.com/ical/calendar/team-events'), 'team-events');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('BEGIN:VCALENDAR', (string) $response->getContent());
        $this->assertStringNotContainsString('BEGIN:VEVENT', (string) $response->getContent());
    }

    public function testThrowsPageNotFoundForUnknownCalendar(): void
    {
        $controller = $this->createController([
            CalendarModel::class => $this->createCalendarAdapter('findOneBy', 'unknown', null),
        ]);

        $this->expectException(PageNotFoundException::class);

        $controller->ical_calendar(Request::create('https://example.com/ical/calendar/unknown'), 'unknown');
    }

    public function testThrowsPageNotFoundForCalendarWithoutIcalExport(): void
    {
        $calendar = $this->createCalendar(['id' => 3, 'title' => 'Team Events', 'export_ical' => '']);

        $controller = $this->createController([
            CalendarModel::class => $this->createCalendarAdapter('findOneBy', 'team-events', $calendar),
        ]);

        $this->expectException(PageNotFoundException::class);

        $controller->ical_calendar(Request::create('https://example.com/ical/calendar/team-events'), 'team-events');
    }

    public function testDeniesAccessToProtectedCalendarForNonMembers(): void
    {
        $calendar = $this->createCalendar([
            'id' => 3,
            'title' => 'Team Events',
            'export_ical' => '1',
            'protected' => '1',
            'groups' => serialize(['1', '2']),
        ]);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->once())
            ->method('isGranted')
            ->with(ContaoCorePermissions::MEMBER_IN_GROUPS, ['1', '2'])
            ->willReturn(false)
        ;

        $controller = $this->createController(
            [CalendarModel::class => $this->createCalendarAdapter('findOneBy', 'team-events', $calendar)],
            $security,
        );

        $this->expectException(AccessDeniedException::class);

        $controller->ical_calendar(Request::create('https://example.com/ical/calendar/team-events'), 'team-events');
    }

    public function testExportsProtectedCalendarForMembers(): void
    {
        $calendar = $this->createCalendar([
            'id' => 3,
            'title' => 'Team Events',
            'export_ical' => '1',
            'protected' => '1',
            'groups' => serialize(['1']),
        ]);

        $security = $this->createStub(Security::class);
        $security
            ->method('isGranted')
            ->willReturn(true)
        ;

        $response = $this->createController(
            [
                CalendarModel::class => $this->createCalendarAdapter('findOneBy', 'team-events', $calendar),
                CalendarEventsModel::class => $this->createConfiguredAdapterStub(['findCurrentByPid' => null]),
            ],
            $security,
        )->ical_calendar(Request::create('https://example.com/ical/calendar/team-events'), 'team-events');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testExportsASingleEvent(): void
    {
        $event = $this->createEvent('Training', ['pid' => 3]);
        $calendar = $this->createCalendar(['id' => 3, 'title' => 'Team Events', 'ical_prefix' => 'HSG']);

        $response = $this->createController([
            CalendarModel::class => $this->createCalendarAdapter('findById', 3, $calendar),
            CalendarEventsModel::class => $this->createConfiguredAdapterStub(['findByAlias' => $event]),
        ])->ical_event(Request::create('https://example.com/ical/event/training'), 'training');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('attachment; filename="training.ics"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('SUMMARY:HSG Training', (string) $response->getContent());
    }

    public function testThrowsPageNotFoundForUnknownEvent(): void
    {
        $controller = $this->createController([
            CalendarEventsModel::class => $this->createConfiguredAdapterStub(['findByAlias' => null]),
        ]);

        $this->expectException(PageNotFoundException::class);

        $controller->ical_event(Request::create('https://example.com/ical/event/unknown'), 'unknown');
    }

    public function testThrowsPageNotFoundForEventWithoutCalendar(): void
    {
        $controller = $this->createController([
            CalendarModel::class => $this->createCalendarAdapter('findById', 3, null),
            CalendarEventsModel::class => $this->createConfiguredAdapterStub(['findByAlias' => $this->createEvent('Training', ['pid' => 3])]),
        ]);

        $this->expectException(PageNotFoundException::class);

        $controller->ical_event(Request::create('https://example.com/ical/event/training'), 'training');
    }

    public function testDeniesAccessToEventOfProtectedCalendarForNonMembers(): void
    {
        $calendar = $this->createCalendar(['id' => 3, 'title' => 'Team Events', 'protected' => '1', 'groups' => serialize(['1'])]);

        $security = $this->createStub(Security::class);
        $security
            ->method('isGranted')
            ->willReturn(false)
        ;

        $controller = $this->createController(
            [
                CalendarModel::class => $this->createCalendarAdapter('findById', 3, $calendar),
                CalendarEventsModel::class => $this->createConfiguredAdapterStub(['findByAlias' => $this->createEvent('Training', ['pid' => 3])]),
            ],
            $security,
        );

        $this->expectException(AccessDeniedException::class);

        $controller->ical_event(Request::create('https://example.com/ical/event/training'), 'training');
    }

    public function testSetsThePublishedRootPageOfTheDomain(): void
    {
        $root = $this->createClassWithPropertiesMock(PageModel::class, ['id' => 1]);
        $root
            ->expects($this->once())
            ->method('loadDetails')
        ;

        $pageAdapter = $this->createAdapterMock(['getTable', 'findOneBy']);
        $pageAdapter
            ->method('getTable')
            ->willReturn('tl_page')
        ;

        $pageAdapter
            ->expects($this->once())
            ->method('findOneBy')
            ->with(
                $this->callback(static fn (array $columns): bool => 2 === \count($columns) && str_contains($columns[1], "tl_page.start<='1760000000'")),
                ['example.com', 'de'],
            )
            ->willReturn($root)
        ;

        $request = Request::create('https://example.com/ical/event/unknown');
        $request->setLocale('de');

        $controller = $this->createController([
            PageModel::class => $pageAdapter,
            CalendarEventsModel::class => $this->createConfiguredAdapterStub(['findByAlias' => null]),
        ]);

        try {
            $controller->ical_event($request, 'unknown');
            $this->fail('Expected a PageNotFoundException');
        } catch (PageNotFoundException) {
        }

        $this->assertSame($root, $request->attributes->get('pageModel'));
        $this->assertSame($root, $GLOBALS['objPage']);
    }

    public function testDoesNotFilterUnpublishedRootPagesInPreviewMode(): void
    {
        $pageAdapter = $this->createAdapterMock(['getTable', 'findOneBy']);
        $pageAdapter
            ->method('getTable')
            ->willReturn('tl_page')
        ;

        $pageAdapter
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->countOf(1))
            ->willReturn(null)
        ;

        $dateAdapter = $this->createAdapterMock(['floorToMinute']);
        $dateAdapter
            ->expects($this->never())
            ->method('floorToMinute')
        ;

        $request = Request::create('https://example.com/ical/event/unknown');

        $controller = $this->createController(
            [
                PageModel::class => $pageAdapter,
                Date::class => $dateAdapter,
                CalendarEventsModel::class => $this->createConfiguredAdapterStub(['findByAlias' => null]),
            ],
            previewMode: true,
        );

        try {
            $controller->ical_event($request, 'unknown');
            $this->fail('Expected a PageNotFoundException');
        } catch (PageNotFoundException) {
        }

        $this->assertFalse($request->attributes->has('pageModel'));
        $this->assertArrayNotHasKey('objPage', $GLOBALS);
    }

    /**
     * @param array<class-string, Adapter<covariant object>> $adapters
     */
    private function createController(array $adapters, Security|null $security = null, bool $previewMode = false): IcalCalendarController
    {
        $adapters += [
            PageModel::class => $this->createConfiguredAdapterStub(['getTable' => 'tl_page', 'findOneBy' => null]),
            Date::class => $this->createConfiguredAdapterStub(['floorToMinute' => 1760000000]),
        ];

        $framework = $this->createContaoFrameworkMock($adapters);
        $framework
            ->expects($this->once())
            ->method('initialize')
        ;

        $tokenChecker = $this->createStub(TokenChecker::class);
        $tokenChecker
            ->method('isPreviewMode')
            ->willReturn($previewMode)
        ;

        return new IcalCalendarController(
            $framework,
            $security ?? $this->createStub(Security::class),
            $tokenChecker,
            new CalendarIcalExporterFactory($this->createStub(EventDispatcherInterface::class)),
        );
    }

    /**
     * @return Adapter<CalendarModel>
     */
    private function createCalendarAdapter(string $method, int|string $argument, CalendarModel|null $calendar): Adapter
    {
        $adapter = $this->createAdapterMock([$method]);
        $adapter
            ->expects($this->once())
            ->method($method)
            ->with(...('findOneBy' === $method ? ['ical_alias', $argument] : [$argument]))
            ->willReturn($calendar)
        ;

        return $adapter;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCalendar(array $data): CalendarModel
    {
        return $this->createClassWithPropertiesStub(CalendarModel::class, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createEvent(string $title, array $data = []): CalendarEventsModel
    {
        return $this->createClassWithPropertiesStub(CalendarEventsModel::class, $data + [
            'title' => $title,
            'published' => '1',
            'addTime' => '1',
            'startTime' => strtotime('2026-10-01 10:00:00 UTC'),
            'endTime' => strtotime('2026-10-01 11:00:00 UTC'),
        ]);
    }
}
