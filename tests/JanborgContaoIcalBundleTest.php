<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
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
