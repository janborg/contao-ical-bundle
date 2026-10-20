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

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\StringUtil;
use Contao\System;

/**
 * Removes iCal files for calendars and events that do not exist or are not
 * exported or shared anymore on a hourly basis.
 */
#[AsCronJob('hourly')]
class RemoveOldIcalFilesCron
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share/'; // TODO: use DI

        // nothing to clean up, if no ics file has been exported yet
        if (!is_dir($shareDir)) {
            return;
        }

        // Delete old files
        foreach (scandir($shareDir) as $file) {
            if (is_dir($shareDir.$file)) {
                continue;
            }

            $objFile = new File(StringUtil::stripRootDir($shareDir).$file);

            // keep files with extensions, other than ics
            if ('ics' !== $objFile->extension) {
                continue;
            }

            $calendar = CalendarModel::findOneBy('ical_alias', $objFile->filename);

            $calendarEvent = CalendarEventsModel::findByAlias($objFile->filename);

            // delete file if neither $calendar nor calendarEvent exists with alias = filename
            if (null === $calendar && null === $calendarEvent) {
                $objFile->delete();
                // TODO: use DI
                System::getContainer()->get('monolog.logger.contao.cron')->info('Verwaiste Ical Datei "'.$objFile->path.'" gelöscht');
                continue;
            }

            // delete file if calendar is protected
            if (null !== $calendar && $calendar->protected) {
                $objFile->delete();
                // TODO: use DI
                System::getContainer()->get('monolog.logger.contao.cron')->info('Ical Datei "'.$objFile->path.'" gelöscht, da Calendar geschützt ist');
                continue;
            }

            // keep file, if it is linked to any not protected calendar with export_ical =
            // true, ical_share = true and ical_alias = filename
            if (null !== $calendar && $calendar->export_ical && $calendar->share_ical) {
                continue;
            }

            // keep file, if it is linked to any calendarEvent with alias = filename and its
            // calendar is not protected and has export_ical = true and share_ical_events = true
            $parentCalendar = null !== $calendarEvent ? CalendarModel::findById($calendarEvent->pid) : null;

            if (
                null !== $parentCalendar
                && !$parentCalendar->protected
                && $parentCalendar->export_ical
                && $parentCalendar->share_ical_events
                // TODO: delete calendarEvents from the past (parameter?!)
            ) {
                continue;
            }

            $objFile->delete();
            // TODO: use DI
            System::getContainer()->get('monolog.logger.contao.cron')->info('Verwaiste Ical Datei "'.$objFile->path.'" gelöscht');
        }
    }
}
