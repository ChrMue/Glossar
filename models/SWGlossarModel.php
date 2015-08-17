<?php

/**
 * Contao Open Source CMS
 */

/**
 * @file SWGlossarModel.php
 * @class SWGlossarModel
 * @author Sascha Weidner
 * @version 3.0.0
 * @package sioweb.contao.extensions.glossar
 * @copyright Sascha Weidner, Sioweb
 */


if(!class_exists('SWGlossarModel')) {
class SWGlossarModel extends \Model {
  
  /**
   * Table name
   * @var string
   */
  protected static $strTable = 'tl_sw_glossar';

  public static function findAllInitial($arrOptions,$initial) {
    $t = static::$strTable;
    $arrColumns = array("left($t.alias,1) = ?");
    return static::findBy($arrColumns, $initial, $arrOptions);
  }

  public static function findByPids($pids,$arrOptions = array()) {
    $t = static::$strTable;
    $arrColumns = array("pid IN('".implode("','",$pids)."')");
    return static::findBy($arrColumns, array(), $arrOptions);
  }

  public static function findAllByAlias($arrAlias,$pid,$arrOptions = array()) {
    $t = static::$strTable;
    $arrColumns = array("pid = ? AND alias IN('".implode("','",$arrAlias)."')");
    return static::findBy($arrColumns, array($pid), $arrOptions);
  }
  
}}