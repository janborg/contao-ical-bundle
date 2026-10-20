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

namespace Janborg\ContaoIcal\Tests\Cron;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\Model\Collection;
use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Janborg\ContaoIcal\CalendarIcalExporterFactory;
use Janborg\ContaoIcal\Cron\GenerateIcalCron;
use Janborg\ContaoIcal\Tests\Concerns\ExporterTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

class GenerateIcalCronTest extends ContaoTestCase
{
    use ExporterTestTrait;

    public function testDoesNothingIfNoCalendarIsExported(): void
    {
        $calendarAdapter = $this->createAdapterMock(['findBy']);
        $calendarAdapter
            ->expects($this->once())
            ->method('findBy')
            ->with(['export_ical=?'], [true])
            ->willReturn(null)
        ;

        $this->runCron($calendarAdapter, $this->createFactoryExpectingNoExport());
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('skippedCalendarProvider')]
    public function testSkipsCalendarsThatAreNotSharedOrProtected(array $data): void
    {
        $this->runCron($this->createCalendarAdapter($data), $this->createFactoryExpectingNoExport());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function skippedCalendarProvider(): iterable
    {
        yield 'not shared' => [['share_ical' => '', 'share_ical_events' => '']];
        yield 'protected' => [['share_ical' => '1', 'share_ical_events' => '1', 'protected' => '1']];
    }

    public function testExportsTheCalendarFile(): void
    {
        $exporter = $this->createMock(CalendarIcalExporter::class);
        $exporter
            ->expects($this->once())
            ->method('exportCalendar')
        ;

        $exporter
            ->expects($this->never())
            ->method('exportCalendarEvent')
        ;

        $this->runCron($this->createCalendarAdapter(['share_ical' => '1']), $this->createFactory($exporter));
    }

    public function testExportsAFileForEachEvent(): void
    {
        $events = [
            $this->createClassWithPropertiesStub(CalendarEventsModel::class, ['alias' => 'first']),
            $this->createClassWithPropertiesStub(CalendarEventsModel::class, ['alias' => 'second']),
        ];

        $exported = [];

        $exporter = $this->createMock(CalendarIcalExporter::class);
        $exporter
            ->expects($this->never())
            ->method('exportCalendar')
        ;

        $exporter
            ->expects($this->exactly(2))
            ->method('exportCalendarEvent')
            ->willReturnCallback(
                static function (CalendarEventsModel $event) use (&$exported): void {
                    $exported[] = $event;
                },
            )
        ;

        $eventsAdapter = $this->createAdapterMock(['findBy']);
        $eventsAdapter
            ->expects($this->once())
            ->method('findBy')
            ->with('pid', 3)
            ->willReturn(new Collection($events, 'tl_calendar_events'))
        ;

        $this->runCron(
            $this->createCalendarAdapter(['share_ical_events' => '1']),
            $this->createFactory($exporter),
            $eventsAdapter,
        );

        $this->assertSame($events, $exported);
    }

    public function testSkipsTheEventExportIfTheCalendarHasNoEvents(): void
    {
        $this->runCron(
            $this->createCalendarAdapter(['share_ical_events' => '1']),
            $this->createFactoryExpectingNoExport(),
            $this->createConfiguredAdapterStub(['findBy' => null]),
        );
    }

    /**
     * @param Adapter<CalendarModel>            $calendarAdapter
     * @param Adapter<CalendarEventsModel>|null $eventsAdapter
     */
    private function runCron(Adapter $calendarAdapter, CalendarIcalExporterFactory $factory, Adapter|null $eventsAdapter = null): void
    {
        $framework = $this->createContaoFrameworkMock([
            CalendarModel::class => $calendarAdapter,
            CalendarEventsModel::class => $eventsAdapter ?? $this->createConfiguredAdapterStub(['findBy' => null]),
        ]);

        $framework
            ->expects($this->once())
            ->method('initialize')
        ;

        (new GenerateIcalCron($framework, $factory))();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return Adapter<CalendarModel>
     */
    private function createCalendarAdapter(array $data): Adapter
    {
        $calendar = $this->createClassWithPropertiesStub(CalendarModel::class, $data + ['id' => 3]);

        return $this->createConfiguredAdapterStub(['findBy' => new Collection([$calendar], 'tl_calendar')]);
    }
}
