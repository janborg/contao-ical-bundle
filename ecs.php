<?php

declare(strict_types=1);

use Contao\EasyCodingStandard\Set\SetList;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([SetList::CONTAO])
    ->withConfiguredRule(HeaderCommentFixer::class, [
        'header' => "This file is part of contao-ical-bundle.\n\n(c) Jan Lünborg 2022 <jan-github@luenborg.eu>\n\n@license MIT\nFor the full copyright and license information,\nplease view the LICENSE file that was distributed with this source code.\n\n@link https://github.com/janborg/contao-ical-bundle",
    ]);