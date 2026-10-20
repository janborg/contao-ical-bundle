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

namespace Janborg\ContaoIcal;

use Contao\CalendarModel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates a CalendarIcalExporter for a given Contao Calendar.
 */
class CalendarIcalExporterFactory
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function create(CalendarModel $calendar): CalendarIcalExporter
    {
        return new CalendarIcalExporter($calendar, $this->eventDispatcher);
    }
}
