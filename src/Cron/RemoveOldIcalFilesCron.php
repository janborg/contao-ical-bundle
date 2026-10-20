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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Removes iCal files for calendars and events that do not exist or are not
 * exported or shared anymore on a hourly basis.
 */
#[AsCronJob('hourly')]
class RemoveOldIcalFilesCron
{
    public function __construct(
        private readonly ContaoFramework $framework,
        #[Autowire('%contao.web_dir%')]
        private readonly string $webDir,
        #[Autowire(service: 'monolog.logger.contao.cron')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $shareDir = $this->webDir.'/share/';

        // nothing to clean up, if no ics file has been exported yet
        if (!is_dir($shareDir)) {
            return;
        }

        $calendarAdapter = $this->framework->getAdapter(CalendarModel::class);
        $calendarEventsAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        // Delete old files
        foreach (scandir($shareDir) as $file) {
            if (is_dir($shareDir.$file)) {
                continue;
            }

            // keep files with extensions, other than ics
            if ('ics' !== strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                continue;
            }

            $alias = pathinfo($file, PATHINFO_FILENAME);

            $calendar = $calendarAdapter->findOneBy('ical_alias', $alias);

            $calendarEvent = $calendarEventsAdapter->findByAlias($alias);

            // delete file if neither $calendar nor calendarEvent exists with alias = filename
            if (null === $calendar && null === $calendarEvent) {
                $this->deleteFile($shareDir, $file, 'Verwaiste Ical Datei "%s" gelöscht');
                continue;
            }

            // delete file if calendar is protected
            if (null !== $calendar && $calendar->protected) {
                $this->deleteFile($shareDir, $file, 'Ical Datei "%s" gelöscht, da Calendar geschützt ist');
                continue;
            }

            // keep file, if it is linked to any not protected calendar with export_ical =
            // true, ical_share = true and ical_alias = filename
            if (null !== $calendar && $calendar->export_ical && $calendar->share_ical) {
                continue;
            }

            // keep file, if it is linked to any calendarEvent with alias = filename and its
            // calendar is not protected and has export_ical = true and share_ical_events = true
            $parentCalendar = null !== $calendarEvent ? $calendarAdapter->findById($calendarEvent->pid) : null;

            if (
                null !== $parentCalendar
                && !$parentCalendar->protected
                && $parentCalendar->export_ical
                && $parentCalendar->share_ical_events
                // TODO: delete calendarEvents from the past (parameter?!)
            ) {
                continue;
            }

            $this->deleteFile($shareDir, $file, 'Verwaiste Ical Datei "%s" gelöscht');
        }
    }

    private function deleteFile(string $shareDir, string $file, string $message): void
    {
        unlink($shareDir.$file);

        // log the path relative to the project dir, e.g. public/share/calendar.ics
        $this->logger->info(\sprintf($message, basename($this->webDir).'/share/'.$file));
    }
}
