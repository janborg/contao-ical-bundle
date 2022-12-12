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
    }

    /**
     * Update a particular calendar.
     */
    public function exportCalendar(): void
    {
        $this->vCal = new Vcalendar();
        $this->vCal->setMethod(Vcalendar::PUBLISH);
        $this->vCal->setXprop(Vcalendar::X_WR_CALNAME, $this->objCalendar->title);
        $this->vCal->setXprop(Vcalendar::X_WR_CALDESC, $this->objCalendar->ical_description);
        $this->vCal->setXprop(Vcalendar::X_WR_TIMEZONE, Config::get('timeZone'));

        $startDate = '' !== $this->objCalendar->ical_export_start ? $this->objCalendar->ical_export_start : 0;
        $endDate = '' !== $this->objCalendar->ical_export_end ? $this->objCalendar->ical_export_end : time() + System::getContainer()->getParameter('janborg_contaoical.defaultEndDateDays') * 24 * 3600;

        $objEvents = CalendarEventsModel::findCurrentByPid($this->objCalendar->id, $startDate, $endDate);

        while ($objEvents->next()) {
            $vEvent = new Vevent();

            switch ($objEvents->addTime) {
                case true:
                    //set StartDateTime
                    $vEvent->setDtstart(
                        date(DateTimeFactory::$YmdTHis, $objEvents->startTime),
                        [Vcalendar::VALUE => Vcalendar::DATE_TIME]
                    );

                    //set EndDateTime
                    if ($objEvents->startTime < $objEvents->endTime) {
                        $vEvent->setDtend(
                            date(DateTimeFactory::$YmdTHis, $objEvents->endTime),
                            [Vcalendar::VALUE => Vcalendar::DATE_TIME]
                        );
                    } else {
                        $vEvent->setDtend(
                            date(DateTimeFactory::$YmdTHis, $objEvents->startTime + System::getContainer()->getParameter('janborg_contaoical.defaultEventDuration') * 60),
                            [Vcalendar::VALUE => Vcalendar::DATE_TIME]
                        );
                    }

                    break;

                case false:
                    //set StartDateTime
                    $vEvent->setDtstart(
                        date(DateTimeFactory::$Ymd, $objEvents->startDate),
                        [Vcalendar::VALUE => Vcalendar::DATE]
                    );

                    //set EndDateTime
                    if (!isset($objEvents->endDate) || 0 === $objEvents->endDate) {
                        $vEvent->setDtend(
                            date(DateTimeFactory::$Ymd, $objEvents->startDate + 24 * 60 * 60),
                            [Vcalendar::VALUE => Vcalendar::DATE]
                        );
                    } else {
                        $vEvent->setDtend(
                            date(DateTimeFactory::$Ymd, $objEvents->endDate + 24 * 60 * 60),
                            [Vcalendar::VALUE => Vcalendar::DATE]
                        );
                    }
                    break;
            }

            $vEvent->setSummary(html_entity_decode(
                (isset($this->objCalendar->ical_prefix) ? $this->objCalendar->ical_prefix.' ' : '').$objEvents->title,
                ENT_QUOTES,
                'UTF-8'
            ));

            if (isset($objEvents->teaser)) {
                $vEvent->setDescription(html_entity_decode(strip_tags(preg_replace(
                    '/<br \\/>/',
                    "\n",
                    System::getContainer()->get('contao.insert_tag.parser')->replaceInline($objEvents->teaser)
                )), ENT_QUOTES, 'UTF-8'));
            }

            if (!empty($objEvents->location)) {
                $vEvent->setLocation(trim(html_entity_decode($objEvents->location, ENT_QUOTES, 'UTF-8')));
            }

            if ($objEvents->recurring) {
                $arrRepeat = StringUtil::deserialize($objEvents->repeatEach);
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

                if ($objEvents->recurrences > 0) {
                    $rrule['count'] = $objEvents->recurrences;
                }

                if ($arg > 1) {
                    $rrule['INTERVAL'] = $arg;
                }

                $vEvent->setRrule($rrule);
            }

            $this->vCal->setComponent($vEvent);
        }

        $this->iCalContent = $this->vCal->createCalendar();

        $objICalFile = new File(StringUtil::stripRootDir($this->shareDir.$this->exportFileName));

        $objICalFile->write($this->iCalContent);

        $objICalFile->close();
    }
}
