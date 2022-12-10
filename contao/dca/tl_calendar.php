<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;


/*
 * Extend palettes
 */
PaletteManipulator::create()
    ->addLegend('ical_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField('export_ical', 'ical_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_calendar')
;


/*
 * Add Selector(s)
 */

$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'] = array_merge(
    [
        'export_ical',
    ],
    $GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__']
);


/*
 * Create Subpalette(s)
 */

$GLOBALS['TL_DCA']['tl_calendar']['subpalettes'] = array_merge(
    [
        'export_ical' => 'ical_alias,ical_prefix,ical_description,ical_export_start,ical_export_end',
    ],
    $GLOBALS['TL_DCA']['tl_calendar']['subpalettes']
);




/*
 * Fields
 */
$GLOBALS['TL_DCA']['tl_calendar']['fields'] = array_merge(
    ['export_ical' => [
        'label'                   => &$GLOBALS['TL_LANG']['tl_calendar']['export_ical'],
        'exclude'                 => true,
        'filter'                  => true,
        'inputType'               => 'checkbox',
        'eval'                    => array('submitOnChange' => true, 'tl_class' => 'clr m12'),
        'sql'                     => "char(1) NOT NULL default ''"
    ]],
    ['ical_alias' => [
        'label'                   => &$GLOBALS['TL_LANG']['tl_calendar']['ical_alias'],
        'exclude'                 => true,
        'search'                  => true,
        'inputType'               => 'text',
        'eval'                    => array('rgxp' => 'alnum', 'unique' => true, 'spaceToUnderscore' => true, 'maxlength' => 128, 'tl_class' => 'w50'),
        'sql'                     => "varbinary(128) NOT NULL default ''"
    ]],
    ['ical_prefix' => [
        'label'                   => &$GLOBALS['TL_LANG']['tl_calendar']['ical_prefix'],
        'exclude'                 => true,
        'search'                  => true,
        'inputType'               => 'text',
        'eval'                    => array('maxlength' => 128, 'tl_class' => 'w50'),
        'sql'                     => "varchar(128) NOT NULL default ''"
    ]],

    ['ical_description' => [
        'label'                   => &$GLOBALS['TL_LANG']['tl_calendar']['ical_description'],
        'exclude'                 => true,
        'search'                  => true,
        'inputType'               => 'textarea',
        'eval'                    => array('style' => 'height:60px;', 'tl_class' => 'clr'),
        'sql'                     => "text NULL"
    ]],


    ['ical_export_start' => [
        'label'                   => &$GLOBALS['TL_LANG']['tl_calendar']['ical_export_start'],
        'exclude'                 => true,
        'filter'                  => true,
        'flag'                    => 8,
        'inputType'               => 'text',
        'eval'                    => array('mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'clr w50 wizard'),
        'sql'                     => "varchar(12) NOT NULL default ''"
    ]],

    ['ical_export_end' => [
        'label'                   => &$GLOBALS['TL_LANG']['tl_calendar']['ical_export_end'],
        'exclude'                 => true,
        'filter'                  => true,
        'flag'                    => 8,
        'inputType'               => 'text',
        'eval'                    => array('mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
        'sql'                     => "varchar(12) NOT NULL default ''"
    ]],
$GLOBALS['TL_DCA']['tl_calendar']['fields']);


