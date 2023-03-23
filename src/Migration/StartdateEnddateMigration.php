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

namespace Janborg\ContaoIcal\Migration;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\StringType;

class StartdateEnddateMigration extends AbstractMigration
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ContaoFramework
     */
    private $framework;

    public function __construct(Connection $connection, ContaoFramework $framework)
    {
        $this->connection = $connection;
        $this->framework = $framework;
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        $columns = $schemaManager->listTableColumns('tl_calendar');

        if (!isset($columns['ical_export_start']) && !isset($columns['ical_export_end'])) {

            return false;
        }

        if (
            $columns['ical_export_start']->getType() instanceof StringType &&
            $columns['ical_export_end']->getType() instanceof StringType
        ) {
            return true;
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $this->framework->initialize();

        $this->connection->executeQuery(
            'ALTER TABLE tl_calendar CHANGE ical_export_start ical_export_start VARCHAR(12) NULL DEFAULT NULL'
        );

        $this->connection->executeQuery(
            'ALTER TABLE tl_calendar CHANGE ical_export_end ical_export_end VARCHAR(12) NULL DEFAULT NULL'
        );

        $objCalendars = CalendarModel::findAll();

        foreach ($objCalendars as $objCalendar) {
            '' === $objCalendar->ical_export_start ? $int_start = null : $int_start = (int) ($objCalendar->ical_export_start);

            '' === $objCalendar->ical_export_end ? $int_end = null : $int_end = (int) ($objCalendar->ical_export_end);

            $this->connection->executeQuery(
                '
                UPDATE
                    tl_calendar
                SET
                    ical_export_start = ?,
                    ical_export_end = ?
                WHERE
                    id = ?
            ',
                [
                    $int_start,
                    $int_end,
                    $objCalendar->id,
                ]
            );
        }

        $this->connection->executeQuery(
            'ALTER TABLE tl_calendar CHANGE ical_export_start ical_export_start INT(10) NULL DEFAULT NULL'
        );

        $this->connection->executeQuery(
            'ALTER TABLE tl_calendar CHANGE ical_export_end ical_export_end INT(10) NULL DEFAULT NULL'
        );

        return $this->createResult(
            true,
            'Changed type for ical_export_start and ical_export_end to INT'
        );
    }
}
