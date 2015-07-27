<?php

/**
 * Contao Open Source CMS
 */

namespace sioweb\contao\extensions\glossar;
use Contao;

/**
 * @file config.php
 * @author Sascha Weidner
 * @version 3.0.0
 * @package sioweb.contao.extensions.glossar
 * @copyright Sascha Weidner, Sioweb
 */


class Glossar extends \Frontend { 

  private $glossar = array();

  public function searchGlossarTerms($strContent, $strTemplate) {
    global $objPage;

    if (!isset($_GET['items']) && $GLOBALS['TL_CONFIG']['useAutoItem'] && isset($_GET['auto_item']))
      \Input::setGet('items', \Input::get('auto_item'));

    if($objPage->disableGlossar == 1)
      return $strContent;

    // HOOK: search for terms in Events, faq and news
    $arrGlossar = array($objPage->glossar);
    if (isset($GLOBALS['TL_HOOKS']['glossarContent']) && is_array($GLOBALS['TL_HOOKS']['glossarContent'])) {
      foreach ($GLOBALS['TL_HOOKS']['glossarContent'] as $type => $callback) {
        $this->import($callback[0]);
        if(!empty($cb_output = $this->$callback[0]->$callback[1](\Input::get('items'),$strContent,$template)))
          $arrGlossar[] = $cb_output;
      }
    }

    if(!empty($arrGlossar))
      $this->glossar = implode('|',$arrGlossar);
    $this->glossar = str_replace('||','|',$this->glossar);

    $Glossar = \SWGlossarModel::findBy(array("title IN ('".str_replace('|',"','",$this->glossar)."')"),array(),array('order'=>' CHAR_LENGTH(title) DESC'));
    $strContent = $this->replace($strContent,$Glossar);

    if(\Config::get('glossar_no_fallback') == 1 || $objPage->glossar_no_fallback == 1)
      return $strContent;

    /* Replace the fallback languages */
    $Glossar = \SWGlossarModel::findBy(array("title IN ('".str_replace('|',"','",$objPage->fallback_glossar)."')"),array(),array('order'=>' CHAR_LENGTH(title) DESC'));
    $strContent = $this->replace($strContent,$Glossar);
    
    return $strContent;
  }

  private function replace($strContent,$Glossar) {

    if(!$strContent || !$Glossar)
      return $strContent;

    while($Glossar->next()) {
      $this->glossar = $Glossar;

      if(!$this->glossar->maxWidth)
        $this->glossar->maxWidth = $GLOBALS['glossar']['css']['maxWidth'];
      if(!$this->glossar->maxHeight)
        $this->glossar->maxHeight = $GLOBALS['glossar']['css']['maxHeight'];

      $replaceFunction = 'replaceTitle2Link';
      if(!$Glossar->jumpTo && !$GLOBALS['TL_CONFIG']['jumpToGlossar'])
        $replaceFunction = 'replaceTitle2Span';

      $ignoredTags = array('a');
      if($GLOBALS['TL_CONFIG']['ignoreInTags'])
        $ignoredTags = explode(',',$GLOBALS['TL_CONFIG']['ignoreInTags']);
      if($this->glossar->ignoreInTags)
        $ignoredTags = explode(',',$this->glossar->ignoreInTags);

      if(\Config::get('strictSearch') == 1)
        $Glossar->strictSearch = 1;

      if(empty($Glossar->type) || $Glossar->type == 'default' || $Glossar->type == 'glossar') {
        $IllegalPlural = '';
        if(\Config::get('illegalChars'))
          $IllegalPlural = \Config::get('illegalChars');
        $IllegalPlural = html_entity_decode($IllegalPlural);

        $plural = preg_replace('/[.]+(?<!\\.)/is','\\.',$IllegalPlural.(!$Glossar->noPlural ? $GLOBALS['glossar']['illegal']:''));
        $preg_query = '/(?!(?:[^<]+>|[^>]+(<\/'.implode('>|<\/',$ignoredTags).'>)))(' . $Glossar->title . (!$Glossar->noPlural?'[^ '.$plural.']*':'') . ($Glossar->strictSearch?'\b':'').')/is';
        if($Glossar->title && preg_match_all( $preg_query, $strContent, $third)) {
          $strContent = preg_replace_callback( $preg_query, array($this,$replaceFunction), $strContent);
        }
      }
      $preg_query = '/(?!(?:[^<]+>|[^>]+(<\/'.implode('>|<\/',$ignoredTags).'>)))\b(' . $Glossar->title . ')/is';
      if($Glossar->type == 'abbr' && $Glossar->title && preg_match_all($preg_query, $strContent, $third)) {
        $strContent = preg_replace_callback($preg_query, array($this,'replaceAbbr'), $strContent);
      }
    }
    return $strContent;
  }
  
  /* InitializeSystem */
  public function getGlossarTerm() {

    if(\Input::post('id'))
      $Glossar = \SWGlossarModel::findByPk(\Input::post('id'));

    if($Glossar === null)
      return false;
      
    $glossarObj = new \FrontendTemplate('glossar_layer');
    $glossarObj->setData($Glossar->row());
    $glossarObj->class = 'ce_glossar_layer';
    if($GLOBALS['TL_CONFIG']['jumpToGlossar']) {
      $link = \PageModel::findByPk($GLOBALS['TL_CONFIG']['jumpToGlossar']);
      $glossarObj->link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$glossarObj->alias);
    }
    if($glossarObj->jumpTo) {
      $link = \PageModel::findByPk($glossarObj->jumpTo);
      $glossarObj->link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$glossarObj->alias);
    }
    
    echo json_encode(array('content'=>$glossarObj->parse()));
    die();
  }

  private function replaceAbbr($treffer) {
    return '<abbr class="glossar" title="'.$this->glossar->explanation.'">'.$treffer[2].'</abbr>';
  }

  private function replaceTitle2Span($treffer) {
    return '<span class="glossar" data-maxwidth="'.($this->glossar->maxWidth ? $this->glossar->maxWidth : 0).'" data-maxheight="'.($this->glossar->maxHeight ? $this->glossar->maxHeight : 0).'" data-glossar="'.$this->glossar->id.'">'.$treffer[2].'</span>';
  }

  private function replaceTitle2Link($treffer) {
    if($GLOBALS['TL_CONFIG']['jumpToGlossar'])
      $link = \PageModel::findByPk($GLOBALS['TL_CONFIG']['jumpToGlossar']);
    if($this->glossar->jumpTo)
      $link = \PageModel::findByPk($this->glossar->jumpTo);
    if($link)
      $link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').standardize(\String::restoreBasicEntities($this->glossar->alias)));
    return '<a class="glossar" data-maxwidth="'.($this->glossar->maxWidth ? $this->glossar->maxWidth : 0).'" data-maxheight="'.($this->glossar->maxHeight ? $this->glossar->maxHeight : 0).'" data-glossar="'.$this->glossar->id.'" href="'.$link.'">'.$treffer[2].'</a>';
  }
  
  public function getSearchablePages($arrPages, $intRoot=0, $blnIsSitemap=false) {
    
    $Glossar = \SWGlossarModel::findAll();

    if($Glossar === null)
      return false;

    while($Glossar->next()) {
      $url = $GLOBALS['TL_CONFIG']['jumpToGlossar'];
      if($Glossar->jumpTo) $url = $Glossar->jumpTo;

      $objParent = \PageModel::findWithDetails($url);
      $domain = ($objParent->rootUseSSL ? 'https://' : 'http://') . ($objParent->domain ?: \Environment::get('host')) . TL_PATH . '/';

      if(!empty($url)) {
        $link = \PageModel::findByPk($url);
        $arrPages[] = $domain.$this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$Glossar->alias);
      }
    }

    return $arrPages;
  }

  public function importGlossar() {
    return 'Import!';
  }

  public function exportGlossar() {
    $objGlossar = new \BackendTemplate('be_glossar_export');
    $objGlossar->setData(array(
      'headline'        => 'Export',
      'glossarMessage'  => '',
      'glossarSubmit'   => 'Export',
      'glossarLabel'    => 'Format wählen',
      'glossarHelp'     => 'Bitte wählen Sie das Format aus, mit der der Exporter Ihre Einträge exportieren soll.',
    ));

    return $objGlossar->parse();
  }

  public function importTerms() {
    return 'Import!';
  }

  public function exportTerms() {
    $objGlossar = new \BackendTemplate('be_glossar_export');
    $objGlossar->setData(array(
      'headline'        => 'Export',
      'glossarMessage'  => '',
      'glossarSubmit'   => 'Export',
      'glossarLabel'    => 'Format wählen',
      'glossarHelp'     => 'Bitte wählen Sie das Format aus, mit der der Exporter Ihre Einträge exportieren soll.',
    ));

    return $objGlossar->parse();
  }
}