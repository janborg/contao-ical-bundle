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

namespace Janborg\ContaoIcal\Cron;

use Contao\File;
use Contao\System;
use Contao\StringUtil;
use Contao\CalendarModel;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;

/**
 * Removes iCal files for calendars and events that do not exist or are not exported or shared anymore on a hourly basis.
 *
 */

#[AsCronJob('hourly')]
class RemoveOldIcalFilesCron
{
    public function __construct(private readonly ContaoFramework $framework)
    {
        $this->framework->initialize();
    }

    public function __invoke(): void
    {
        $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share/'; // TODO: use DI

        // Delete old files
        foreach (scandir($shareDir) as $file) {
            if (is_dir($shareDir.$file)) {
                continue;
            }

            $objFile = new File(StringUtil::stripRootDir($shareDir).$file);

            $calendar = CalendarModel::findByIcal_alias($objFile->filename);

            $calendarEvent = CalendarEventsModel::findByAlias($objFile->filename);

            // check if file_extension is 'ics'
            if ('ics' !== $objFile->extension) {
                continue;
            }

            // delete file if neither $calendar nor calendarEvent exists with alias = filename 
            if (null === $calendar && null === $calendarEvent) {
                $objFile->delete();
                // TODO: use DI
                System::getContainer()->get('monolog.logger.contao.cron')->info('Verwaiste Ical Datei "'.$objFile->path.'" gelöscht');
                continue;
            }    

            // ckeck if file is not linked to any calendar with export_ical = true, ical_share = true and ical_alias = filename
            if (
                null !== $calendar &&
                $calendar->export_ical && 
                $calendar->share_ical
            ) {
                continue;
            }
            
            
            // check if file is not linked to any calendarEvent with alias = filename and calendar has export_ical = true and ical_share = true 
            $parentCalendar = CalendarModel::findByPk($calendarEvent->pid);
            if (
                null !== $calendarEvent &&
                null !== $parentCalendar &&
                $parentCalendar->export_ical && 
                $parentCalendar->share_ical_events
            ) { 
                continue;                
            }

            $objFile->delete();
            // TODO: use DI
            System::getContainer()->get('monolog.logger.contao.cron')->info('Verwaiste Ical Datei "'.$objFile->path.'" gelöscht');
        }
    }
}
