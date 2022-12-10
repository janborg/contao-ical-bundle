<?php

declare(strict_types=1);

/*
 * This file is part of contao-ical-bundle.
 *
 * (c) Jan Lünborg
 *
 * @license MIT
 */

namespace Janborg\ContaoIcal;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class JanborgContaoIcalBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
