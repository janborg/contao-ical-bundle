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
use Contao\System;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Janborg\ContaoIcal\Event\EditVeventEvent;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CalendarIcalExporterTest extends TestCase
{
    protected function setUp(): void
    {
        $this->setContainer();
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

    private function setContainer(string $webDir = '/tmp', int $defaultEndDateDays = 365): void
    {
        $container = new Container();
        $container->setParameter('contao.web_dir', $webDir);
        $container->setParameter('janborg_contao_ical.defaultEndDateDays', $defaultEndDateDays);
        $container->setParameter('janborg_contao_ical.defaultEventDuration', 60);

        System::setContainer($container);
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

    /**
     * @dataProvider recurringEventProvider
     */
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

        $this->assertStringContainsString($expectedRule, $calendar->createCalendar());
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
    }

    public function testDispatchesEventThatCanModifyVevent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(
                static function (object $event): object {
                    self::assertInstanceOf(EditVeventEvent::class, $event);
                    $event->getVevent()->setSummary('Modified by listener');

                    return $event;
                },
            )
        ;

        $exporter = $this->createExporter([], $eventDispatcher);
        $calendar = new Vcalendar();

        $exporter->addEventToVcalendar($this->createEvent([
            'published' => true,
            'addTime' => true,
            'startTime' => strtotime('2026-10-01 10:00:00 UTC'),
            'endTime' => strtotime('2026-10-01 11:00:00 UTC'),
            'title' => 'Original',
        ]), $calendar);

        $this->assertStringContainsString('SUMMARY:Modified by listener', $calendar->createCalendar());
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
        /** @var CalendarModel&MockObject $calendar */
        $calendar = $this->getMockBuilder(CalendarModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get', '__isset'])
            ->getMock()
        ;

        $calendar
            ->method('__get')
            ->willReturnCallback(static fn (string $property): mixed => $data[$property] ?? null)
        ;

        $calendar
            ->method('__isset')
            ->willReturnCallback(static fn (string $property): bool => isset($data[$property]))
        ;

        return $calendar;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createEvent(array $data): CalendarEventsModel
    {
        /** @var CalendarEventsModel&MockObject $event */
        $event = $this->getMockBuilder(CalendarEventsModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get', '__isset'])
            ->getMock()
        ;

        $event
            ->method('__get')
            ->willReturnCallback(static fn (string $property): mixed => $data[$property] ?? null)
        ;

        $event
            ->method('__isset')
            ->willReturnCallback(static fn (string $property): bool => isset($data[$property]))
        ;

        return $event;
    }
}
