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

namespace Janborg\ContaoIcal\Tests;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Janborg\ContaoIcal\Event\EditVeventEvent;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CalendarIcalExporterTest extends ContaoTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setContainer();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_HOOKS']);

        $this->resetStaticProperties([System::class]);

        parent::tearDown();
    }

    public function testInitializesExportPropertiesFromCalendarConfiguration(): void
    {
        $start = strtotime('2026-10-01 00:00:00 UTC');
        $end = strtotime('2026-12-31 23:59:59 UTC');

        $exporter = $this->createExporter([
            'ical_alias' => 'team-events',
            'ical_export_start' => $start,
            'ical_export_end' => $end,
        ]);

        $this->assertSame('/tmp/share/', $exporter->shareDir);
        $this->assertSame('team-events.ics', $exporter->exportFileName);
        $this->assertSame($start, $exporter->startDate);
        $this->assertSame($end, $exporter->endDate);
    }

    public function testInitializesDefaultExportPropertiesWhenCalendarConfigurationIsMissing(): void
    {
        $this->setContainer('/var/www/public', 14);
        $before = time();

        $exporter = $this->createExporter(['id' => 42]);

        $after = time();

        $this->assertSame('/var/www/public/share/', $exporter->shareDir);
        $this->assertSame('calendar42.ics', $exporter->exportFileName);
        $this->assertGreaterThanOrEqual($before, $exporter->startDate);
        $this->assertLessThanOrEqual($after, $exporter->startDate);
        $this->assertGreaterThanOrEqual($before + 14 * 24 * 3600, $exporter->endDate);
        $this->assertLessThanOrEqual($after + 14 * 24 * 3600, $exporter->endDate);
    }

    public function testCreatesPublishingVcalendarWithCalendarMetadata(): void
    {
        $GLOBALS['TL_CONFIG']['timeZone'] = 'Europe/Berlin';

        $calendarModel = $this->createCalendar([
            'title' => 'Team events',
            'ical_description' => 'All events of the first team',
        ]);

        $exporter = new CalendarIcalExporter($calendarModel, $this->createStub(EventDispatcherInterface::class));
        $exporter->createVCalendar($calendarModel);

        $content = $exporter->getIcalContent();

        $this->assertStringContainsString('METHOD:PUBLISH', $content);
        $this->assertStringContainsString('X-WR-CALNAME:Team events', $content);
        $this->assertStringContainsString('X-WR-CALDESC:All events of the first team', $content);
        $this->assertStringContainsString('X-WR-TIMEZONE:Europe/Berlin', $content);
    }

    public function testAddsPublishedTimedEventWithCalendarPrefixAndLocation(): void
    {
        $exporter = $this->createExporter(['ical_prefix' => 'Community']);
        $calendar = new Vcalendar();
        $start = strtotime('2026-10-01 10:00:00 UTC');
        $end = strtotime('2026-10-01 11:30:00 UTC');

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => true,
            'startTime' => $start,
            'endTime' => $end,
            'title' => 'Planning &amp; breakfast',
            'location' => '  Meeting room  ',
        ]), $calendar);

        $content = $calendar->createCalendar();

        $this->assertStringContainsString('SUMMARY:Community Planning & breakfast', $content);
        $this->assertStringContainsString('LOCATION:Meeting room', $content);
        $this->assertStringContainsString('DTSTART:'.date(DateTimeFactory::$YmdTHis, $start), $content);
        $this->assertStringContainsString('DTEND:'.date(DateTimeFactory::$YmdTHis, $end), $content);
    }

    public function testUsesEventTitleAsSummaryWithoutCalendarPrefix(): void
    {
        $exporter = $this->createExporter();
        $calendar = new Vcalendar();

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => true,
            'startTime' => strtotime('2026-10-01 10:00:00 UTC'),
            'endTime' => strtotime('2026-10-01 11:00:00 UTC'),
            'title' => 'Training',
        ]), $calendar);

        $content = $calendar->createCalendar();

        $this->assertStringContainsString('SUMMARY:Training', $content);
        $this->assertStringNotContainsString('LOCATION', $content);
        $this->assertStringNotContainsString('DESCRIPTION', $content);
    }

    public function testSkipsUnpublishedEvent(): void
    {
        $exporter = $this->createExporter();
        $calendar = new Vcalendar();

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => false,
        ]), $calendar);

        $this->assertStringNotContainsString('BEGIN:VEVENT', $calendar->createCalendar());
    }

    public function testUsesConfiguredDefaultDurationWhenTimedEventHasNoEndTime(): void
    {
        $exporter = $this->createExporter();
        $calendar = new Vcalendar();
        $start = strtotime('2026-10-01 10:00:00 UTC');

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => true,
            'startTime' => $start,
            'endTime' => 0,
            'title' => 'Open end',
        ]), $calendar);

        $this->assertStringContainsString(
            'DTEND:'.date(DateTimeFactory::$YmdTHis, $start + 60 * 60),
            $calendar->createCalendar(),
        );
    }

    public function testExportsAllDayEventWithExclusiveEndDate(): void
    {
        $exporter = $this->createExporter();
        $calendar = new Vcalendar();
        $start = strtotime('2026-10-01 00:00:00 UTC');

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => false,
            'startDate' => $start,
            'endDate' => 0,
            'title' => 'Holiday',
        ]), $calendar);

        $content = $calendar->createCalendar();

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261001', $content);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261002', $content);
    }

    public function testExportsMultiDayAllDayEventWithExclusiveEndDate(): void
    {
        $exporter = $this->createExporter();
        $calendar = new Vcalendar();

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => false,
            'startDate' => strtotime('2026-10-01 00:00:00 UTC'),
            'endDate' => strtotime('2026-10-03 00:00:00 UTC'),
            'title' => 'Tournament',
        ]), $calendar);

        $content = $calendar->createCalendar();

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261001', $content);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261004', $content);
    }

    public function testConvertsTeaserToPlainTextDescription(): void
    {
        $insertTagParser = $this->createMock(InsertTagParser::class);
        $insertTagParser
            ->expects($this->once())
            ->method('replaceInline')
            ->with('<p>Meet at {{link::12}}<br>Bring shoes &amp; water</p>')
            ->willReturn('<p>Meet at <a href="/hall">the hall</a><br>Bring shoes &amp; water</p>')
        ;

        $this->container->set('contao.insert_tag.parser', $insertTagParser);

        $exporter = $this->createExporter();
        $calendar = new Vcalendar();

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => true,
            'startTime' => strtotime('2026-10-01 10:00:00 UTC'),
            'endTime' => strtotime('2026-10-01 11:00:00 UTC'),
            'title' => 'Training',
            'teaser' => '<p>Meet at {{link::12}}<br>Bring shoes &amp; water</p>',
        ]), $calendar);

        $this->assertStringContainsString(
            'DESCRIPTION:Meet at the hall\nBring shoes & water',
            $calendar->createCalendar(),
        );
    }

    #[DataProvider('recurringEventProvider')]
    public function testExportsRecurringEventRule(string $unit, int $interval, int $count, string $expectedRule): void
    {
        $exporter = $this->createExporter();
        $calendar = new Vcalendar();

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => true,
            'startTime' => strtotime('2026-10-01 10:00:00 UTC'),
            'endTime' => strtotime('2026-10-01 11:00:00 UTC'),
            'title' => 'Recurring',
            'recurring' => true,
            'repeatEach' => serialize(['value' => $interval, 'unit' => $unit]),
            'recurrences' => $count,
        ]), $calendar);

        $content = $calendar->createCalendar();

        $this->assertStringContainsString($expectedRule, $content);

        if (1 === $interval) {
            $this->assertStringNotContainsString('INTERVAL=', $content);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: int, 3: string}>
     */
    public static function recurringEventProvider(): iterable
    {
        yield 'daily' => ['days', 1, 3, 'RRULE:FREQ=DAILY;COUNT=3'];
        yield 'weekly interval' => ['weeks', 2, 0, 'RRULE:FREQ=WEEKLY;INTERVAL=2'];
        yield 'monthly' => ['months', 1, 1, 'RRULE:FREQ=MONTHLY;COUNT=1'];
        yield 'yearly' => ['years', 1, 2, 'RRULE:FREQ=YEARLY;COUNT=2'];
        yield 'unlimited' => ['days', 1, 0, 'RRULE:FREQ=DAILY'."\r\n"];
    }

    public function testDispatchesEventThatCanModifyVevent(): void
    {
        $event = $this->createEvent([
            'published' => true,
            'addTime' => true,
            'startTime' => strtotime('2026-10-01 10:00:00 UTC'),
            'endTime' => strtotime('2026-10-01 11:00:00 UTC'),
            'title' => 'Original',
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(
                static function (object $dispatched) use ($event): object {
                    self::assertInstanceOf(EditVeventEvent::class, $dispatched);
                    self::assertSame($event, $dispatched->getCalendarEvent());

                    $dispatched->getVevent()->setSummary('Modified by listener');

                    return $dispatched;
                },
            )
        ;

        $exporter = $this->createExporter([], $eventDispatcher);
        $calendar = new Vcalendar();

        $exporter->addEventToVcalendar($event, $calendar);

        $this->assertStringContainsString('SUMMARY:Modified by listener', $calendar->createCalendar());
    }

    public function testAppliesLegacyEditVeventHook(): void
    {
        $listener = new class() {
            public function onEditVEvent(Vevent $vEvent, CalendarEventsModel $event): Vevent
            {
                $vEvent->setSummary('Hooked: '.$event->title);

                return $vEvent;
            }
        };

        $GLOBALS['TL_HOOKS']['editVEvent'] = [[$listener, 'onEditVEvent']];

        $exporter = $this->createExporter();
        $calendar = new Vcalendar();

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => true,
            'startTime' => strtotime('2026-10-01 10:00:00 UTC'),
            'endTime' => strtotime('2026-10-01 11:00:00 UTC'),
            'title' => 'Original',
        ]), $calendar);

        $this->assertStringContainsString('SUMMARY:Hooked: Original', $calendar->createCalendar());
    }

    private function setContainer(string $webDir = '/tmp', int $defaultEndDateDays = 365): void
    {
        $this->container = new Container();
        $this->container->setParameter('contao.web_dir', $webDir);
        $this->container->setParameter('janborg_contao_ical.defaultEndDateDays', $defaultEndDateDays);
        $this->container->setParameter('janborg_contao_ical.defaultEventDuration', 60);

        System::setContainer($this->container);
    }

    /**
     * @param array<string, mixed> $calendarData
     */
    private function createExporter(array $calendarData = [], EventDispatcherInterface|null $eventDispatcher = null): CalendarIcalExporter
    {
        return new CalendarIcalExporter(
            $this->createCalendar($calendarData),
            $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
        );
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
    private function createEvent(array $data): CalendarEventsModel
    {
        return $this->createClassWithPropertiesStub(CalendarEventsModel::class, $data);
    }
}
