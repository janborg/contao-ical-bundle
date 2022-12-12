<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
 */

namespace Janborg\ContaoIcal\Cron;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Janborg\ContaoIcal\CalendarIcalExporter;

/**
 * @CronJob("hourly")
 */
class GenerateIcalCron
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
        $this->framework->initialize();
    }

    public function __invoke(): void
    {
        $calendars = CalendarModel::findBy('export_ical=?', [1]);

        foreach ($calendars as $calendar) {
            $calendarExporter = new CalendarIcalExporter($calendar);

            $calendarExporter->exportCalendar();
        }
    }
}
