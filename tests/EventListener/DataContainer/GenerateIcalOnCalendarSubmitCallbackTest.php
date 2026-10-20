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

namespace Janborg\ContaoIcal\Tests\EventListener\DataContainer;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\DataContainer;
use Contao\Message;
use Contao\Model\Collection;
use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Janborg\ContaoIcal\CalendarIcalExporterFactory;
use Janborg\ContaoIcal\EventListener\DataContainer\GenerateIcalOnCalendarSubmitCallback;
use Janborg\ContaoIcal\Tests\Concerns\ExporterTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GenerateIcalOnCalendarSubmitCallbackTest extends ContaoTestCase
{
    use ExporterTestTrait;

    #[DataProvider('ignoredSubmitProvider')]
    public function testIgnoresSubmitsOutsideOfTheEditView(bool $withDataContainer, int $id, string|null $act): void
    {
        $framework = $this->createContaoFrameworkMock();
        $framework
            ->expects($this->never())
            ->method('getAdapter')
        ;

        $requestStack = new RequestStack();

        if (null !== $act) {
            $requestStack->push(Request::create('/contao?act='.$act));
        }

        $callback = new GenerateIcalOnCalendarSubmitCallback($framework, $requestStack, $this->createFactoryExpectingNoExport());
        $callback($withDataContainer ? $this->createClassWithPropertiesStub(DataContainer::class, ['id' => $id]) : null);
    }

    /**
     * @return iterable<string, array{0: bool, 1: int, 2: string|null}>
     */
    public static function ignoredSubmitProvider(): iterable
    {
        yield 'no data container' => [false, 0, 'edit'];
        yield 'no record id' => [true, 0, 'edit'];
        yield 'not in edit view' => [true, 3, 'editAll'];
        yield 'no request' => [true, 3, null];
    }

    public function testDoesNothingIfTheCalendarDoesNotExist(): void
    {
        $this->invokeCallback(null, $this->createFactoryExpectingNoExport(), $this->createMessageAdapter([]));
    }

    public function testAddsAnErrorForProtectedCalendars(): void
    {
        $message = $this->createAdapterMock(['addError', 'addInfo']);
        $message
            ->expects($this->once())
            ->method('addError')
            ->with('Der Kalender ist geschützt und kann daher nicht exportiert werden.')
        ;

        $message
            ->expects($this->never())
            ->method('addInfo')
        ;

        $this->invokeCallback(
            ['export_ical' => '1', 'share_ical' => '1', 'protected' => '1'],
            $this->createFactoryExpectingNoExport(),
            $message,
        );
    }

    public function testDoesNothingIfTheIcalExportIsDisabled(): void
    {
        $this->invokeCallback(
            ['export_ical' => '', 'share_ical' => '1', 'share_ical_events' => '1'],
            $this->createFactoryExpectingNoExport(),
            $this->createMessageAdapter([]),
        );
    }

    public function testExportsTheCalendarFile(): void
    {
        $exporter = $this->createMock(CalendarIcalExporter::class);
        $exporter
            ->expects($this->once())
            ->method('exportCalendar')
        ;

        $this->invokeCallback(
            ['export_ical' => '1', 'share_ical' => '1'],
            $this->createFactory($exporter),
            $this->createMessageAdapter(['Kalender als ics-Datei unter /public/share/ abgelegt.']),
        );
    }

    public function testExportsAFileForEachEvent(): void
    {
        $exporter = $this->createMock(CalendarIcalExporter::class);
        $exporter
            ->expects($this->never())
            ->method('exportCalendar')
        ;

        $exporter
            ->expects($this->exactly(2))
            ->method('exportCalendarEvent')
        ;

        $events = new Collection(
            [
                $this->createClassWithPropertiesStub(CalendarEventsModel::class, ['alias' => 'first']),
                $this->createClassWithPropertiesStub(CalendarEventsModel::class, ['alias' => 'second']),
            ],
            'tl_calendar_events',
        );

        $eventsAdapter = $this->createAdapterMock(['findByPid']);
        $eventsAdapter
            ->expects($this->once())
            ->method('findByPid')
            ->with(3)
            ->willReturn($events)
        ;

        $this->invokeCallback(
            ['export_ical' => '1', 'share_ical_events' => '1'],
            $this->createFactory($exporter),
            $this->createMessageAdapter(array_fill(0, 2, 'Events als einzelne ics-Dateien unter /public/share/ abgelegt.')),
            $eventsAdapter,
        );
    }

    /**
     * @param array<string, mixed>|null         $calendar
     * @param Adapter<Message>                  $message
     * @param Adapter<CalendarEventsModel>|null $eventsAdapter
     */
    private function invokeCallback(array|null $calendar, CalendarIcalExporterFactory $factory, Adapter $message, Adapter|null $eventsAdapter = null): void
    {
        $calendarAdapter = $this->createAdapterMock(['findById']);
        $calendarAdapter
            ->expects($this->once())
            ->method('findById')
            ->with(3)
            ->willReturn(null === $calendar ? null : $this->createClassWithPropertiesStub(CalendarModel::class, $calendar + ['id' => 3]))
        ;

        $framework = $this->createContaoFrameworkStub([
            CalendarModel::class => $calendarAdapter,
            CalendarEventsModel::class => $eventsAdapter ?? $this->createConfiguredAdapterStub(['findByPid' => null]),
            Message::class => $message,
        ]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/contao?act=edit'));

        $callback = new GenerateIcalOnCalendarSubmitCallback($framework, $requestStack, $factory);
        $callback($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 3]));
    }
}
