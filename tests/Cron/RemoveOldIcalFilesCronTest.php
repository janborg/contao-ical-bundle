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
use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\Cron\RemoveOldIcalFilesCron;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

class RemoveOldIcalFilesCronTest extends ContaoTestCase
{
    private string $webDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webDir = self::getTempDir().'/public';

        if (!is_dir($this->webDir.'/share')) {
            mkdir($this->webDir.'/share', 0777, true);
        }

        foreach (glob($this->webDir.'/share/*') ?: [] as $file) {
            unlink($file);
        }
    }

    public function testDoesNothingIfTheShareDirectoryDoesNotExist(): void
    {
        $framework = $this->createContaoFrameworkMock();
        $framework
            ->expects($this->never())
            ->method('getAdapter')
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('info')
        ;

        (new RemoveOldIcalFilesCron($framework, self::getTempDir().'/missing', $logger))();
    }

    public function testTreatsTheIcsExtensionCaseInsensitively(): void
    {
        $this->createShareFile('old.ICS');

        $this->runCron(null, null, null, 'Verwaiste Ical Datei "public/share/old.ICS" gelöscht');

        $this->assertFileDoesNotExist($this->webDir.'/share/old.ICS');
    }

    public function testKeepsFilesThatAreNoIcsFiles(): void
    {
        $this->createShareFile('readme.txt');

        $this->runCron(null, null, null);

        $this->assertFileExists($this->webDir.'/share/readme.txt');
    }

    public function testDeletesOrphanedFiles(): void
    {
        $this->createShareFile('old.ics');

        $this->runCron(null, null, null, 'Verwaiste Ical Datei "public/share/old.ics" gelöscht');

        $this->assertFileDoesNotExist($this->webDir.'/share/old.ics');
    }

    public function testDeletesFilesOfProtectedCalendars(): void
    {
        $this->createShareFile('team.ics');

        $this->runCron(
            ['export_ical' => '1', 'share_ical' => '1', 'protected' => '1'],
            null,
            null,
            'Ical Datei "public/share/team.ics" gelöscht, da Calendar geschützt ist',
        );

        $this->assertFileDoesNotExist($this->webDir.'/share/team.ics');
    }

    public function testKeepsFilesOfSharedCalendars(): void
    {
        $this->createShareFile('team.ics');

        $this->runCron(['export_ical' => '1', 'share_ical' => '1'], null, null);

        $this->assertFileExists($this->webDir.'/share/team.ics');
    }

    public function testKeepsFilesOfEventsInCalendarsSharingTheirEvents(): void
    {
        $this->createShareFile('training.ics');

        $this->runCron(null, ['pid' => 3], ['export_ical' => '1', 'share_ical_events' => '1']);

        $this->assertFileExists($this->webDir.'/share/training.ics');
    }

    /**
     * @param array<string, mixed>|null $calendar
     * @param array<string, mixed>|null $parentCalendar
     */
    #[DataProvider('obsoleteFileProvider')]
    public function testDeletesFilesThatAreNotSharedAnymore(array|null $calendar, array|null $parentCalendar): void
    {
        $this->createShareFile('training.ics');

        $this->runCron($calendar, ['pid' => 3], $parentCalendar, 'Verwaiste Ical Datei "public/share/training.ics" gelöscht');

        $this->assertFileDoesNotExist($this->webDir.'/share/training.ics');
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>|null, 1: array<string, mixed>|null}>
     */
    public static function obsoleteFileProvider(): iterable
    {
        yield 'parent calendar is missing' => [null, null];
        yield 'parent calendar does not share events' => [null, ['export_ical' => '1', 'share_ical_events' => '']];
        yield 'parent calendar is not exported' => [null, ['export_ical' => '', 'share_ical_events' => '1']];
        yield 'parent calendar is protected' => [null, ['export_ical' => '1', 'share_ical_events' => '1', 'protected' => '1']];
        yield 'calendar is not shared anymore' => [['export_ical' => '1', 'share_ical' => ''], null];
    }

    /**
     * @param array<string, mixed>|null $calendar
     * @param array<string, mixed>|null $event
     * @param array<string, mixed>|null $parentCalendar
     */
    private function runCron(array|null $calendar, array|null $event, array|null $parentCalendar, string|null $expectedLog = null): void
    {
        $calendarAdapter = $this->createConfiguredAdapterStub([
            'findOneBy' => null === $calendar ? null : $this->createClassWithPropertiesStub(CalendarModel::class, $calendar),
            'findById' => null === $parentCalendar ? null : $this->createClassWithPropertiesStub(CalendarModel::class, $parentCalendar),
        ]);

        $eventsAdapter = $this->createConfiguredAdapterStub([
            'findByAlias' => null === $event ? null : $this->createClassWithPropertiesStub(CalendarEventsModel::class, $event),
        ]);

        $framework = $this->createContaoFrameworkMock([
            CalendarModel::class => $calendarAdapter,
            CalendarEventsModel::class => $eventsAdapter,
        ]);

        $framework
            ->expects($this->once())
            ->method('initialize')
        ;

        $logger = $this->createMock(LoggerInterface::class);

        if (null === $expectedLog) {
            $logger
                ->expects($this->never())
                ->method('info')
            ;
        } else {
            $logger
                ->expects($this->once())
                ->method('info')
                ->with($expectedLog)
            ;
        }

        (new RemoveOldIcalFilesCron($framework, $this->webDir, $logger))();
    }

    private function createShareFile(string $name): void
    {
        file_put_contents($this->webDir.'/share/'.$name, 'BEGIN:VCALENDAR');
    }
}
