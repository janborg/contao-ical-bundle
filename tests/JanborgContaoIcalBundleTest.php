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

namespace Janborg\ContaoIcal\Tests;

use Janborg\ContaoIcal\JanborgContaoIcalBundle;
use PHPUnit\Framework\TestCase;

class JanborgContaoIcalBundleTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $bundle = new JanborgContaoIcalBundle();

        $this->assertInstanceOf('Janborg\ContaoIcal\JanborgContaoIcalBundle', $bundle);
    }
}
