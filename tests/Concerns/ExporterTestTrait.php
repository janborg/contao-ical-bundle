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

namespace Janborg\ContaoIcal\Tests\Concerns;

use Contao\CoreBundle\Framework\Adapter;
use Contao\Message;
use Janborg\ContaoIcal\CalendarIcalExporter;
use Janborg\ContaoIcal\CalendarIcalExporterFactory;

/**
 * Shared helpers for tests of classes that export calendars via the
 * CalendarIcalExporterFactory.
 */
trait ExporterTestTrait
{
    private function createFactory(CalendarIcalExporter $exporter): CalendarIcalExporterFactory
    {
        $factory = $this->createStub(CalendarIcalExporterFactory::class);
        $factory
            ->method('create')
            ->willReturn($exporter)
        ;

        return $factory;
    }

    private function createFactoryExpectingNoExport(): CalendarIcalExporterFactory
    {
        $factory = $this->createMock(CalendarIcalExporterFactory::class);
        $factory
            ->expects($this->never())
            ->method('create')
        ;

        return $factory;
    }

    /**
     * Creates a Message adapter that expects exactly the given info messages in this
     * order and no error message.
     *
     * @param list<string> $infos
     *
     * @return Adapter<Message>
     */
    private function createMessageAdapter(array $infos): Adapter
    {
        $message = $this->createAdapterMock(['addError', 'addInfo']);
        $message
            ->expects($this->never())
            ->method('addError')
        ;

        $message
            ->expects($this->exactly(\count($infos)))
            ->method('addInfo')
            ->willReturnCallback(
                static function (string $info) use (&$infos): void {
                    self::assertSame(array_shift($infos), $info);
                },
            )
        ;

        return $message;
    }
}
