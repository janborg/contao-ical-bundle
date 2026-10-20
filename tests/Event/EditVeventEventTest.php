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

namespace Janborg\ContaoIcal\Tests\Event;

use Contao\CalendarEventsModel;
use Contao\TestCase\ContaoTestCase;
use Janborg\ContaoIcal\Event\EditVeventEvent;
use Kigkonsult\Icalcreator\Vevent;

class EditVeventEventTest extends ContaoTestCase
{
    public function testExposesTheVeventAndTheCalendarEvent(): void
    {
        $vevent = new Vevent();
        $calendarEvent = $this->createClassWithPropertiesStub(CalendarEventsModel::class, ['id' => 1]);

        $event = new EditVeventEvent($vevent, $calendarEvent);

        $this->assertSame($vevent, $event->getVevent());
        $this->assertSame($calendarEvent, $event->getCalendarEvent());
    }
}
