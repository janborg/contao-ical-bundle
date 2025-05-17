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

namespace Janborg\ContaoIcal\Event;

use Contao\CalendarEventsModel;
use Kigkonsult\Icalcreator\Vevent;
use Symfony\Contracts\EventDispatcher\Event;

class EditVeventEvent extends Event
{
    public const NAME = 'janborg.contaical.editvevent';

    public function __construct(
        private Vevent $vevent,
        private CalendarEventsModel $calendarEvent,
    ) {
    }

    public function getVevent(): Vevent
    {
        return $this->vevent;
    }

    public function getCalendarEvent(): CalendarEventsModel
    {
        return $this->calendarEvent;
    }
}
