<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
 */

namespace Janborg\ContaoIcal;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\File;
use Contao\StringUtil;
use Contao\System;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class CalendarIcalExporter
{
    public function __construct(CalendarModel $calendar)
    {
        $this->objCalendar = $calendar;

        $this->shareDir = System::getContainer()->getParameter('contao.web_dir').'/share/';

        $this->exportFileName = isset($calendar->ical_alias) ? $calendar->ical_alias.'.ics' : 'calendar'.$calendar->id.'.ics';

        $this->startDate = '' !== $calendar->ical_export_start ? $calendar->ical_export_start : 0;

        $this->endDate = '' !== $calendar->ical_export_end ? $calendar->ical_export_end : time() + System::getContainer()->getParameter('janborg_contaoical.defaultEndDateDays') * 24 * 3600;
    }

    /**
     * Creates an ics File in the share directory for a given Contao Calendar.
     */
    public function exportCalendar(): void
    {
        $this->createVCalendar($this->objCalendar);

        $objEvents = CalendarEventsModel::findCurrentByPid($this->objCalendar->id, $this->startDate, $this->endDate);

        foreach ($objEvents as $objEvent) {
            $this->addEventToVcalendar($objEvent, $this->vCal);
        }

        $this->createIcalFile(StringUtil::stripRootDir($this->shareDir), $this->exportFileName);
    }

    /**
     * Creates a VCalendar for a given Contao Calendar
     * @property CalendarModel $objCalendar
     */

    public function createVCalendar(CalendarModel $objCalendar): void
    {
        $this->vCal = new Vcalendar();
        $this->vCal->setMethod(Vcalendar::PUBLISH);
        $this->vCal->setXprop(Vcalendar::X_WR_CALNAME, $objCalendar->title);
        $this->vCal->setXprop(Vcalendar::X_WR_CALDESC, $objCalendar->ical_description);
        $this->vCal->setXprop(Vcalendar::X_WR_TIMEZONE, Config::get('timeZone'));

    }

    /**
     * Adds a Contao CalendarEvent to a given VCalendar
     * @property CalendarEventsModel $objEvent
     * @property Vcalendar $vCal
     */
    public function addEventToVcalendar(CalendarEventsModel $objEvent, Vcalendar $vCal): void
    {
        $vEvent = new Vevent();

        switch ($objEvent->addTime) {
            case true:
                //set StartDateTime
                $vEvent->setDtstart(
                    date(DateTimeFactory::$YmdTHis, $objEvent->startTime),
                    [Vcalendar::VALUE => Vcalendar::DATE_TIME]
                );

                //set EndDateTime
                if ($objEvent->startTime < $objEvent->endTime) {
                    $vEvent->setDtend(
                        date(DateTimeFactory::$YmdTHis, $objEvent->endTime),
                        [Vcalendar::VALUE => Vcalendar::DATE_TIME]
                    );
                } else {
                    $vEvent->setDtend(
                        date(DateTimeFactory::$YmdTHis, $objEvent->startTime + System::getContainer()->getParameter('janborg_contaoical.defaultEventDuration') * 60),
                        [Vcalendar::VALUE => Vcalendar::DATE_TIME]
                    );
                }

                break;

            case false:
                //set StartDateTime
                $vEvent->setDtstart(
                    date(DateTimeFactory::$Ymd, $objEvent->startDate),
                    [Vcalendar::VALUE => Vcalendar::DATE]
                );

                //set EndDateTime
                if (!isset($objEvent->endDate) || 0 === $objEvent->endDate) {
                    $vEvent->setDtend(
                        date(DateTimeFactory::$Ymd, $objEvent->startDate + 24 * 60 * 60),
                        [Vcalendar::VALUE => Vcalendar::DATE]
                    );
                } else {
                    $vEvent->setDtend(
                        date(DateTimeFactory::$Ymd, $objEvent->endDate + 24 * 60 * 60),
                        [Vcalendar::VALUE => Vcalendar::DATE]
                    );
                }
                break;
        }

        $vEvent->setSummary(html_entity_decode(
            (isset($this->objCalendar->ical_prefix) ? $this->objCalendar->ical_prefix.' ' : '').$objEvent->title,
            ENT_QUOTES,
            'UTF-8'
        ));

        if (isset($objEvent->teaser)) {
            $vEvent->setDescription(html_entity_decode(strip_tags(preg_replace(
                '/<br \\/>/',
                "\n",
                System::getContainer()->get('contao.insert_tag.parser')->replaceInline($objEvent->teaser)
            )), ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($objEvent->location)) {
            $vEvent->setLocation(trim(html_entity_decode($objEvent->location, ENT_QUOTES, 'UTF-8')));
        }

        if ($objEvent->recurring) {
            $arrRepeat = StringUtil::deserialize($objEvent->repeatEach);
            $arg = $arrRepeat['value'];
            $unit = $arrRepeat['unit'];

            if (1 === $arg) {
                $unit = substr($unit, 0, -1);
            }

            $freq = 'YEARLY';

            switch ($arrRepeat['unit']) {
                case 'days':
                    $freq = 'DAILY';
                    break;
                case 'weeks':
                    $freq = 'WEEKLY';
                    break;
                case 'months':
                    $freq = 'MONTHLY';
                    break;
                case 'years':
                    $freq = 'YEARLY';
                    break;
            }

            $rrule = ['FREQ' => $freq];

            if ($objEvent->recurrences > 0) {
                $rrule['count'] = $objEvent->recurrences;
            }

            if ($arg > 1) {
                $rrule['INTERVAL'] = $arg;
            }

            $vEvent->setRrule($rrule);
        }

        $vCal->setComponent($vEvent);
    }

    /**
     * Writes the Vcalendar to a file and saves it at the given path
     * @property string $path
     * @property string $filename
     */
    public function createIcalFile(string $path, string $filename): void
    {
        $this->iCalContent = $this->vCal->createCalendar();

        $this->objICalFile = new File($path.$filename);

        $this->objICalFile->write($this->iCalContent);

        $this->objICalFile->close();

    }
}
