<?php

/**
 * Contao Open Source CMS
 */

/**
 * @file tl_settings.php
 * @author Sascha Weidner
 * @version 3.0.0
 * @package sioweb.contao.extensions.glossar
 * @copyright Sascha Weidner, Sioweb
 */


$semicolon = substr($GLOBALS['TL_DCA']['tl_settings']['palettes']['default'], -1, 1);
if($semicolon != ';')
  $semicolon = ';';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_settings']['palettes']['default'].$semicolon.'{glossar_legend},enableGlossar';

$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'enableGlossar';
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['enableGlossar'] = 'disableGlossarCache,ignoreInTags,illegalChars,jumpToGlossar';

$GLOBALS['TL_DCA']['tl_settings']['fields']['ignoreInTags'] = array
(
  'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['ignoreInTags'],
  'exclude'                 => true,
  'inputType'               => 'text',
  'eval'                    => array('maxlength'=>255, 'tl_class'=>'clr long'),
  'sql'                     => "text NULL"
);
$GLOBALS['TL_DCA']['tl_settings']['fields']['illegalChars'] = array
(
  'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['illegalChars'],
  'exclude'                 => true,
  'inputType'               => 'text',
  'eval'                    => array('maxlength'=>255, 'tl_class'=>'clr long'),
  'sql'                     => "text NULL"
);
$GLOBALS['TL_DCA']['tl_settings']['fields']['jumpToGlossar'] = array
(
  'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['jumpToGlossar'],
  'exclude'                 => true,
  'inputType'               => 'pageTree',
  'foreignKey'              => 'tl_page.title',
  'eval'                    => array('fieldType'=>'radio', 'tl_class'=>'w50 clr'),
  'sql'                     => "int(10) unsigned NOT NULL default '0'",
  'relation'                => array('type'=>'belongsTo', 'load'=>'lazy')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['enableGlossar'] = array(
  'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['enableGlossar'],
  'exclude'                 => true,
  'inputType'               => 'checkbox',
  'eval'                    => array('submitOnChange'=>true),
  'sql'                     => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['disableGlossarCache'] = array(
  'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['disableGlossarCache'],
  'exclude'                 => true,
  'inputType'               => 'checkbox',
  'eval'                    => array('submitOnChange'=>true),
  'sql'                     => "char(1) NOT NULL default ''"
);