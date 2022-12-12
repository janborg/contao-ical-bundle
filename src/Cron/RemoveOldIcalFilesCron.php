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
use Contao\File;
use Contao\StringUtil;
use Contao\System;

/**
 * @CronJob("hourly")
 */
class RemoveOldIcalFilesCron
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
        $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share/';

        // Delete old files
        foreach (scandir($shareDir) as $file) {
            if (is_dir($shareDir.$file)) {
                continue;
            }

            $objFile = new File(StringUtil::stripRootDir($shareDir).$file);

            if (
                null === CalendarModel::findBy(
                    ['export_ical = ?', 'ical_alias = ?'],
                    [true, $objFile->filename]
                )
                && 'ics' === $objFile->extension
            ) {
                $objFile->delete();

                System::getContainer()->get('monolog.logger.contao.cron')->info('Verwaiste Ical Datei "'.$objFile->path.'" gelöscht');
            }
        }
    }
}
