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

namespace Janborg\ContaoIcal\Tests\Migration;

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Janborg\ContaoIcal\Migration\StartdateEnddateMigration;
use PHPUnit\Framework\Attributes\DataProvider;

class StartdateEnddateMigrationTest extends ContaoTestCase
{
    /**
     * @param array<string, string> $columnTypes
     */
    #[DataProvider('shouldRunProvider')]
    public function testOnlyRunsIfBothColumnsAreStrings(array $columnTypes, bool $expected): void
    {
        $columns = [];

        foreach ($columnTypes as $name => $type) {
            $columns[$name] = new Column($name, Type::getType($type));
        }

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects($this->once())
            ->method('listTableColumns')
            ->with('tl_calendar')
            ->willReturn($columns)
        ;

        $connection = $this->createStub(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        $this->assertSame($expected, (new StartdateEnddateMigration($connection))->shouldRun());
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: bool}>
     */
    public static function shouldRunProvider(): iterable
    {
        yield 'table without the columns' => [[], false];
        yield 'only the start column' => [['ical_export_start' => Types::STRING], false];
        yield 'only the end column' => [['ical_export_end' => Types::STRING], false];
        yield 'already migrated' => [['ical_export_start' => Types::INTEGER, 'ical_export_end' => Types::INTEGER], false];
        yield 'string columns' => [['ical_export_start' => Types::STRING, 'ical_export_end' => Types::STRING], true];
    }

    public function testConvertsTheExportDatesToIntegerColumns(): void
    {
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with('SELECT id, ical_export_start, ical_export_end FROM tl_calendar')
            ->willReturn([
                ['id' => 1, 'ical_export_start' => '1759276800', 'ical_export_end' => ''],
                ['id' => 2, 'ical_export_start' => null, 'ical_export_end' => '1767139200'],
            ])
        ;

        $connection
            ->expects($this->exactly(6))
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params = []) use (&$statements): int {
                    $statements[] = [preg_replace('/\s+/', ' ', trim($sql)), $params];

                    return 1;
                },
            )
        ;

        $result = (new StartdateEnddateMigration($connection))->run();

        $this->assertTrue($result->isSuccessful());

        $update = 'UPDATE tl_calendar SET ical_export_start = ?, ical_export_end = ? WHERE id = ?';

        $this->assertSame(
            [
                ['ALTER TABLE tl_calendar CHANGE ical_export_start ical_export_start VARCHAR(12) NULL DEFAULT NULL', []],
                ['ALTER TABLE tl_calendar CHANGE ical_export_end ical_export_end VARCHAR(12) NULL DEFAULT NULL', []],
                [$update, [1759276800, null, 1]],
                [$update, [null, 1767139200, 2]],
                ['ALTER TABLE tl_calendar CHANGE ical_export_start ical_export_start INT(10) NULL DEFAULT NULL', []],
                ['ALTER TABLE tl_calendar CHANGE ical_export_end ical_export_end INT(10) NULL DEFAULT NULL', []],
            ],
            $statements,
        );
    }

    public function testRunsWithoutCalendars(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([])
        ;

        $connection
            ->expects($this->exactly(4))
            ->method('executeStatement')
        ;

        $this->assertTrue((new StartdateEnddateMigration($connection))->run()->isSuccessful());
    }
}
