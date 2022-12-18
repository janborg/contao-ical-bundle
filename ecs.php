<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->sets([__DIR__.'/tools/ecs/vendor/contao/easy-coding-standard/config/contao.php']);

    $ecsConfig->ruleWithConfiguration(HeaderCommentFixer::class, [
        'header' => "This file is part of contao-ical-bundle.\n\n(c) Jan Lünborg 2022 <jan-github@luenborg.eu>\n\n@license MIT\nFor the full copyright and license information,\nplease view the LICENSE file that was distributed with this source code.\n\n@link https://github.com/janborg/contao-ical-bundle",    ]);

    // Adjust the configuration according to your needs.
};