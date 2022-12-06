<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
 */

namespace Janborg\IcalBundle\Tests;

use Janborg\IcalBundle\JanborgContaoIcalBundle;
use PHPUnit\Framework\TestCase;

class JanborgContaoIcalBundleTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $bundle = new JanborgContaoIcalBundle();

        $this->assertInstanceOf('Janborg\IcalBundle\JanborgContaoIcalBundle', $bundle);
    }
}
