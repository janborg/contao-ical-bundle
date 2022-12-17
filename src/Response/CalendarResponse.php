<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
 */

namespace Janborg\ContaoIcal\Response;

use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP response for a calendar file download.
 */
class CalendarResponse extends Response
{
    /**
     * Calendar.
     *
     * @var Vcalendar
     */
    protected $calendar;

    /**
     * Construct calendar response.
     *
     * @param Vcalendar $calendar Calendar
     * @param string    $filename Filename
     * @param int       $status   Response status
     * @param array     $headers  Response headers
     */
    public function __construct(Vcalendar $calendar, $filename, $status = 200, $headers = [])
    {
        $this->vCal = $calendar;

        $this->filename = $filename;

        $content = $this->vCal->createCalendar();

        $headers = array_merge($this->getDefaultHeaders(), $headers);
        parent::__construct($content, $status, $headers);
    }

    /**
     * Get default response headers for a calendar.
     *
     * @return array
     */
    protected function getDefaultHeaders()
    {
        $headers = [];

        $mimeType = 'text/calendar';
        $headers['Content-Type'] = sprintf('%s; charset=utf-8', $mimeType);

        $filename = $this->filename.'.ics';
        $headers['Content-Disposition'] = sprintf('attachment; filename="%s', $filename);

        return $headers;
    }
}
