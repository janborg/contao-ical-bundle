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

namespace Janborg\ContaoIcal\Tests\Response;

use Janborg\ContaoIcal\Response\CalendarResponse;
use Kigkonsult\Icalcreator\Vcalendar;
use PHPUnit\Framework\TestCase;

class CalendarResponseTest extends TestCase
{
    public function testCreatesCalendarResponseWithExpectedContentAndContentType(): void
    {
        $calendar = new Vcalendar();
        $calendar->setMethod(Vcalendar::PUBLISH);

        $response = new CalendarResponse($calendar, 'team-events');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('METHOD:PUBLISH', $response->getContent());
        $this->assertStringContainsString('END:VCALENDAR', $response->getContent());
    }

    public function testUsesQuotedIcsFilenameInContentDispositionHeader(): void
    {
        $response = new CalendarResponse(new Vcalendar(), 'team-events');

        $this->assertSame('attachment; filename="team-events.ics"', $response->headers->get('Content-Disposition'));
    }
}
