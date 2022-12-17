<?php

namespace Janborg\ContaoIcal\Response;

use Symfony\Component\HttpFoundation\Response;
use Kigkonsult\Icalcreator\Vcalendar;

/**
 * HTTP response for a calendar file download
 *
 */
class CalendarResponse extends Response
{
    /**
     * Calendar
     *
     * @var Vcalendar
     */
    protected $calendar;


    /**
     * Construct calendar response
     *
     * @param Vcalendar $calendar Calendar
     * @param string    $filename Filename
     * @param int      $status   Response status
     * @param array    $headers  Response headers
     */
    public function __construct(Vcalendar $calendar, $filename, $status = 200, $headers = array())
    {
        $this->vCal = $calendar;

        $this->filename = $filename;

        $content = $this->vCal->createCalendar();

        $headers = array_merge($this->getDefaultHeaders(), $headers);
        parent::__construct($content, $status, $headers);
    }


    /**
     * Get default response headers for a calendar
     *
     * @return array
     */
    protected function getDefaultHeaders()
    {
        $headers = array();

        $mimeType = 'text/calendar';
        $headers['Content-Type'] = sprintf('%s; charset=utf-8', $mimeType);

        $filename = $this->filename.'.ics';
        $headers['Content-Disposition'] = sprintf('attachment; filename="%s', $filename);

        return $headers;
    }
}