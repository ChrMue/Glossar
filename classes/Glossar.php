<?php

/**
 * Contao Open Source CMS
 */

namespace sioweb\contao\extensions\glossar;
use Contao;

/**
 * @file Glossar.php
 * @class Glossar
 * @author Sascha Weidner
 * @version 3.0.0
 * @package sioweb.contao.extensions.glossar
 * @copyright Sascha Weidner, Sioweb
 */


class Glossar extends \Frontend { 

  private $term = array();

  /* Delete all cached glossary data*/
  public function purgeGlossar() {
    $this->import('Database');
    $time = time();
    $this->Database->prepare("UPDATE tl_page SET glossar = NULL, fallback_glossar = NULL,glossar_time = ? WHERE glossar_time != ?")->execute($time,$time);
    if (isset($GLOBALS['TL_HOOKS']['clearGlossar']) && is_array($GLOBALS['TL_HOOKS']['clearGlossar'])) {
      foreach ($GLOBALS['TL_HOOKS']['clearGlossar'] as $type => $callback) {
        $this->import($callback[0]);
        $this->$callback[0]->$callback[1]($time);
      }
    }
  }

  public function addSourceTable($sourceTable,$tags) {
    $Term = \SwGlossarModel::findBy(array("id IN ('".implode("','",$tags)."')"),array());
    if(!empty($Term)) {
      $arrLinks = array();
      while($Term->next()) {
        if($GLOBALS['TL_CONFIG']['jumpToGlossar'])
          $link = \GlossarPageModel::findByPk($GLOBALS['TL_CONFIG']['jumpToGlossar']);
        if($this->term->jumpTo)
          $link = \GlossarPageModel::findByPk($Term->jumpTo);
        if($link)
          $link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').standardize(\String::restoreBasicEntities($Term->alias)));
        
        $arrLinks[] = '<a href="'.$link.'" title="'.$Term->title.'">'.$Term->title.'</a>';
      }
    }

    return $arrLinks;
  }

  public function replaceGlossarInsertTags($Tag) {
    $Tag = explode('::',$Tag);
    if($Tag[0] !== 'glossar')
      return false;

    switch($Tag[1]) {
      case 'term':
        if(($Item = \Input::get('items')) != '') {
          $Glossar = \SwGlossarModel::findByAlias($Item,array());
          if(!empty($Glossar))
            return $Glossar->title;
        }
      break;
    }
    return false;
  }

  public function searchGlossarTerms($strContent, $strTemplate) {
    global $objPage;

    if (!isset($_GET['items']) && $GLOBALS['TL_CONFIG']['useAutoItem'] && isset($_GET['auto_item']))
      \Input::setGet('items', \Input::get('auto_item'));
    $arrGlossar = array($objPage->glossar);

    if($objPage->disableGlossar == 1)
      return $strContent;

    // HOOK: search for terms in Events, faq and news
    $arrGlossar = array($objPage->glossar);
    if (isset($GLOBALS['TL_HOOKS']['glossarContent']) && is_array($GLOBALS['TL_HOOKS']['glossarContent'])) {
      foreach ($GLOBALS['TL_HOOKS']['glossarContent'] as $type => $callback) {
        $this->import($callback[0]);
        $cb_output = $this->$callback[0]->$callback[1](\Input::get('items'),$strContent,$template,$objPage->language);
        if(!empty($cb_output))
          $arrGlossar[] = $cb_output;
      }
    }

    if(!empty($arrGlossar))
      $this->term = implode('|',$arrGlossar);
    $this->term = addslashes(str_replace('||','|',$this->term));

    $Glossar = \GlossarModel::findByLanguage($objPage->language);
    if(empty($Glossar))
      $Glossar = \GlossarModel::findByFallback(1);

    $arrGlossar = array();
    if(empty($Glossar))
      return $strContent;
    while($Glossar->next())
      $arrGlossar[] = $Glossar->id;

    /* replace content with tags to stop glossary replacement */
    $GlossarIgnoreTags = $this->replaceGlossarIgnoreTags($strContent);
    if(\Config::get('activateGlossarTags') == 1)
      $GlossarTags = $this->replaceGlossarTags($strContent);

    $Term = \SwGlossarModel::findBy(array("title IN ('".str_replace('|',"','",$this->term)."') AND pid IN ('".implode("','",$arrGlossar)."')"),array($Glossar->id),array('order'=>' CHAR_LENGTH(title) DESC'));
   
    $strContent = $this->replace($strContent,$Term,$Glossar);

    if(\Config::get('glossar_no_fallback') == 1 || $objPage->glossar_no_fallback == 1) {
      /* reinsert glossar hidden content */
      $strContent = $this->insertTagIgnoreContent($strContent,$GlossarIgnoreTags);
      if(\Config::get('activateGlossarTags') == 1)
        $strContent = $this->insertTagContent($strContent,$GlossarTags);

      return $strContent;
    }

    /* Replace the fallback languages */
    $Term = \SwGlossarModel::findBy(array("title IN ('".str_replace('|',"','",$objPage->fallback_glossar)."')"),array(),array('order'=>' CHAR_LENGTH(title) DESC'));
    $strContent = $this->replace($strContent,$Term);

    /* reinsert glossar hidden content */
    $strContent = $this->insertTagIgnoreContent($strContent,$GlossarIgnoreTags);
    if(\Config::get('activateGlossarTags') == 1)
      $strContent = $this->insertTagContent($strContent,$GlossarTags);
    
    return $strContent;
  }

  /* Replace content between the tags with placeholder */
  private function replaceGlossarTags(&$strContent) {
    $arrTagContent = array();
    $cIndex = 0;
    while (($intStart = strpos($strContent, '<!-- glossar::stop -->')) !== false) {
      if (($intEnd = strpos($strContent, '<!-- glossar::continue -->', $intStart)) !== false) {
        $intCurrent = $intStart;
        // Handle nested tags
        while (($intNested = strpos($strContent, '<!-- glossar::stop -->', $intCurrent + 22)) !== false && $intNested < $intEnd) {
          if (($intNewEnd = strpos($strContent, '<!-- glossar::continue -->', $intEnd + 26)) !== false) {
            $intEnd = $intNewEnd;
            $intCurrent = $intNested;
          }
          else break;
        }

        /* 
         * Save all cut content to reinsert it in insertTagContent 
         * to hide content from Glossar.
         */
        $arrTagContent[] = substr($strContent,$intStart,$intEnd+26-$intStart);
        $strContent = substr($strContent, 0, $intStart) .'###GLOSSAR_CONTENT_'.$cIndex.'###'. substr($strContent, $intEnd + 26);
        $cIndex++;
      }
      else break;
    }

    return $arrTagContent;
  }

  /* Replace content between the tags with placeholder */
  private function replaceGlossarIgnoreTags(&$strContent) {
    $arrTagContent = array();
    $cIndex = 0;
    while (($intStart = strpos($strContent, '<!-- glossar::ignore -->')) !== false) {
      if (($intEnd = strpos($strContent, '<!-- glossar::unignore -->', $intStart)) !== false) {
        $intCurrent = $intStart;
        // Handle nested tags
        while (($intNested = strpos($strContent, '<!-- glossar::ignore -->', $intCurrent + 22)) !== false && $intNested < $intEnd) {
          if (($intNewEnd = strpos($strContent, '<!-- glossar::unignore -->', $intEnd + 26)) !== false) {
            $intEnd = $intNewEnd;
            $intCurrent = $intNested;
          }
          else break;
        }

        /* 
         * Save all cut content to reinsert it in insertTagContent 
         * to hide content from Glossar.
         */
        $arrTagContent[] = substr($strContent,$intStart,$intEnd+26-$intStart);
        $strContent = substr($strContent, 0, $intStart) .'###GLOSSAR_IGNORE_CONTENT_'.$cIndex.'###'. substr($strContent, $intEnd + 26);
        $cIndex++;
      }
      else break;
    }

    return $arrTagContent;
  }

  /* replace placeholder with glossar-tag content */
  private function insertTagContent($strContent,$tags = array()) {
    if(!empty($tags))
      foreach($tags as $key => $tag)
        $strContent = str_replace('###GLOSSAR_CONTENT_'.$key.'###',$tag,$strContent);
    return $strContent;
  }

  /* replace placeholder with glossar-tag content */
  private function insertTagIgnoreContent($strContent,$tags = array()) {
    if(!empty($tags))
      foreach($tags as $key => $tag)
        $strContent = str_replace('###GLOSSAR_IGNORE_CONTENT_'.$key.'###',$tag,$strContent);
    return $strContent;
  }

  /* replace found tags with links and abbr */
  private function replace($strContent,$Term,$Glossar = null) {
    $this->term_glossar = $Glossar;
    if(!$strContent || !$Term)
      return $strContent;

    $IgnoreTags = $this->replaceGlossarIgnoreTags($strContent);

    while($Term->next()) {
      $this->term = $Term;

      if(($maxWidth = \Config::get('glossarMaxWidth')))
        $this->term->maxWidth = $maxWidth;
      if(($maxHeigth = \Config::get('glossarMaxHeight')))
        $this->term->maxWidth = $maxHeight;

      if(!$this->term->maxWidth)
        $this->term->maxWidth = $GLOBALS['glossar']['css']['maxWidth'];
      if(!$this->term->maxHeight)
        $this->term->maxHeight = $GLOBALS['glossar']['css']['maxHeight'];

      $Content = \GlossarContentModel::findPublishedByPidAndTable($Term->id,'tl_sw_glossar');

      $replaceFunction = 'replaceTitle2Link';
      if((!$Term->jumpTo && !$GLOBALS['TL_CONFIG']['jumpToGlossar']) || empty($Content))
        $replaceFunction = 'replaceTitle2Span';

      $ignoredTags = array('a');
      if($GLOBALS['TL_CONFIG']['ignoreInTags'])
        $ignoredTags = explode(',',$GLOBALS['TL_CONFIG']['ignoreInTags']);
      if($this->term->ignoreInTags)
        $ignoredTags = explode(',',$this->term->ignoreInTags);

      if(\Config::get('strictSearch') !== null && $Term->strictSearch === null)
        $Term->strictSearch = \Config::get('strictSearch');

      if(empty($Term->type) || $Term->type == 'default' || $Term->type == 'glossar') {
        $IllegalPlural = '';

        $lastIstDot = false;
        if(substr($Term->title,-1) === '.') {
          $strContent = str_replace($Term->title,$Term->title.'X',$strContent);
          $Term->title .= 'X';
          $lastIstDot = true;
        }

        if(\Config::get('illegalChars'))
          $IllegalPlural = \Config::get('illegalChars');
        $IllegalPlural = html_entity_decode($IllegalPlural);

        $plural = preg_replace('/[.]+(?<!\\.)/is','\\.',$IllegalPlural.(!$Term->noPlural ? $GLOBALS['glossar']['illegal']:'')).'<';
        $preg_query = '/(?!(?:[^<]+>|[^>]+(<\/'.implode('>|<\/',$ignoredTags).'>)))('.($Term->strictSearch==1||$Term->strictSearch==3?'\b':'') . $Term->title . (!$Term->noPlural?'[^ '.$plural.']*':'') . ($Term->strictSearch==1?'\b':'').')/is';
        $no_preg_query = '/(?!(?:[^<]+>|[^>]+(<\/'.implode('>|<\/',$ignoredTags).'>)))(?:<(?:a|span|abbr) (?!class="glossar")[^>]*>)('.($Term->strictSearch==1||$Term->strictSearch==3?'\b':'') . $Term->title . (!$Term->noPlural?'[^ '.$plural.']*':'') . ($Term->strictSearch==1?'\b':'').')/is';
        // echo preg_match_all($no_preg_query,$strContent,$third2);
        if($Term->title && preg_match_all( $preg_query, $strContent, $third)) {
          $strContent = preg_replace_callback( $preg_query, array($this,$replaceFunction), $strContent);
          if($lastIstDot)
            $strContent = str_replace($Term->title,substr($Term->title,0,-1),$strContent);
        }
      }
      if($Term->type == 'abbr') {
        $lastIstDot = false;
        if(substr($Term->title,-1) === '.') {
          $strContent = str_replace($Term->title,$Term->title.'X',$strContent);
          $Term->title .= 'X';
          $lastIstDot = true;
        }
        $preg_query = '/(?!(?:[^<]+>|[^>]+(<\/'.implode('>|<\/',$ignoredTags).'>)))\b(' . $Term->title . ')\b/is';
        // echo $preg_query.'<br><br>';
        if($Term->title && preg_match_all($preg_query, $strContent, $third)) {
          $strContent = preg_replace_callback($preg_query, array($this,'replaceAbbr'), $strContent);
          if($lastIstDot)
            $strContent = str_replace($Term->title,substr($Term->title,0,-1),$strContent);
        }
      }
    }

    $strContent = $this->insertTagIgnoreContent($strContent,$IgnoreTags);
    return $strContent;
  }
  
  /* InitializeSystem */
  public function getGlossarTerm() {

    if(\Input::post('id'))
      $Term = \SwGlossarModel::findByPk(\Input::post('id'));

    if($Term === null && \Input::post('cloud') == '')
      return false;

    if(!$this->checkLizenz()) {
      $Log = new \GlossarLogModel();
      $Log->tstamp = time();
      $Log->user = session_id();
      $GAction = 'load';

      if(\Input::post('loaded')==1)
        $GAction = 'close';
      if(\Input::post('clicked')==1) {
        $GAction = 'follow';
        if(\Input::post('no_ref')==1)
          $GAction = 'span';
      }
      if(($id = \Input::post('cloud'))) {
        $GAction = 'cloud';
        $Term = new \stdClass();
        $Term->id = $id;
      }
      $Log->action = $GAction;
      $Log->term = $Term->id;
      $Log->page = $_SESSION['FE_DATA']['referer']['current'];
      $Log->language = $_SESSION['TL_LANGUAGE'];
      $Log->save();
    }

    if(\Input::post('cloud') != '')
      return false;

    $Content = \GlossarContentModel::findPublishedByPidAndTable($Term->id,'tl_sw_glossar');
      
    $termObj = new \FrontendTemplate('glossar_layer');
    $termObj->setData($Term->row());
    $termObj->class = 'ce_glossar_layer';

    if(!empty($Content)) {
      if($GLOBALS['TL_CONFIG']['jumpToGlossar']) {
        $link = \GlossarPageModel::findByPk($GLOBALS['TL_CONFIG']['jumpToGlossar']);
        $termObj->link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$termObj->alias);
      }
      if($termObj->jumpTo) {
        $link = \GlossarPageModel::findByPk($termObj->jumpTo);
        $termObj->link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$termObj->alias);
      }
    }
    
    echo json_encode(array('content'=>$this->replaceInsertTags($termObj->parse(),false)));
    die();
  }

  private function replaceAbbr($treffer) {
    $href = $data = '';
    if($this->term->source == 'page' && $this->term->jumpTo) {
      $Page = \PageModel::findByPk($this->term->jumpTo);
      $href = $this->generateFrontendUrl($Page->row());
    }
    if($this->term->source == 'external' && $this->term->url)
      $href = $this->term->url;

    $lang = '';
    if(!empty($this->term_glossar->language))
      $lang = ' lang="'.$this->term_glossar->language.'"';

    if($href) $data .= '<a class="glossar_abbr glossar_'.$this->term->pid.'" href="'.$href.'"'.($this->term->target?' target="_blank':'').' title="'.$this->term->explanation.'">';
    $data .= '<abbr'.$lang.' class="glossar glossar_'.$this->term->pid.'" title="'.$this->term->explanation.'">'.$treffer[2].'</abbr>';
    if($href) $data .= '</a>';
    return $data;
  }

  private function replaceTitle2Span($treffer) {

    $lang = '';
    if(!empty($this->term_glossar->language))
      $lang = ' lang="'.$this->term_glossar->language.'"';

    return '<a'.$lang.' class="glossar glossar_'.$this->term->pid.' glossar_no_content" data-maxwidth="'.($this->term->maxWidth ? $this->term->maxWidth : 0).'" data-maxheight="'.($this->term->maxHeight ? $this->term->maxHeight : 0).'" data-glossar="'.$this->term->id.'">'.$treffer[2].'</a>';
  }

  private function replaceTitle2Link($treffer) {
    if($GLOBALS['TL_CONFIG']['jumpToGlossar'])
      $link = \GlossarPageModel::findByPk($GLOBALS['TL_CONFIG']['jumpToGlossar']);
    if($this->term->jumpTo)
      $link = \GlossarPageModel::findByPk($this->term->jumpTo);
    if($link)
      $link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').standardize(\String::restoreBasicEntities($this->term->alias)));
    
    $lang = '';
    if(!empty($this->term_glossar->language))
      $lang = ' lang="'.$this->term_glossar->language.'"';
    
    return '<a'.$lang.' class="glossar glossar_'.$this->term->pid.'" data-maxwidth="'.($this->term->maxWidth ? $this->term->maxWidth : 0).'" data-maxheight="'.($this->term->maxHeight ? $this->term->maxHeight : 0).'" data-glossar="'.$this->term->id.'" href="'.$link.'">'.$treffer[2].'</a>';
  }
  
  public function getSearchablePages($arrPages, $intRoot=0, $blnIsSitemap=false) {
    
    $Glossar = \SwGlossarModel::findAll();

    if($Glossar === null)
      return false;

    while($Glossar->next()) {
      $url = $GLOBALS['TL_CONFIG']['jumpToGlossar'];
      if($Glossar->jumpTo) $url = $Glossar->jumpTo;

      $objParent = \GlossarPageModel::findWithDetails($url);
      $domain = ($objParent->rootUseSSL ? 'https://' : 'http://') . ($objParent->domain ?: \Environment::get('host')) . TL_PATH . '/';

      if(!empty($url)) {
        $link = \GlossarPageModel::findByPk($url);
        $arrPages[] = $domain.$this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$Glossar->alias);
      }
    }

    return $arrPages;
  }

  public function importGlossar() {

    $FirstID = $Stage = 0;

    $this->strTable = 'tl_'.\Input::get('do');
    $this->strModel = ucfirst(\Input::get('do')).'Model';

    $this->import('BackendUser', 'User');
    $class = $this->User->uploader;

    $FileData = $Import = array();

    // See #4086 and #7046
    if (!class_exists($class) || $class == 'DropZone') {
      $class = 'FileUpload';
    }

    $objUploader = new $class();

    if (\Input::post('FORM_SUBMIT') == 'tl_csv_import') {
      
      $arrUploaded = $objUploader->uploadTo('system/tmp');

      $arrFiles = array();

      foreach ($arrUploaded as $strFile) {
       
        $arrFiles[] = $strFile;
      }

      if(\Input::post('update_glossar') != '')
        $Stage = \Input::post('update_glossar')+1;
      $Session = \Session::getInstance();

      if(\Input::post('glossar_kill_all') == '1') {
        $this->Database->execute("TRUNCATE TABLE tl_glossar");
        $this->Database->execute("TRUNCATE TABLE tl_sw_glossar");
        $this->Database->execute("DELETE FROM tl_content WHERE ptable = 'tl_sw_glossar'");
      }

      switch(\Input::post('update_glossar')) {
        case 1:
          $FileData = $this->decode_array($Session->get('glossar_file'));
          $Import = $this->decode_array($Session->get('glossar_import'));
          $f = $FirstID = $Session->get('glossar_first_id');

          $arrFD = array();
          foreach($Import['update']['tl_glossar'] as $glossar => $value)
            if(in_array($glossar,\Input::post('update')))
              $arrFD[$glossar] = $value;

          $Import['update']['tl_glossar'] = $arrFD;
          $arrFD = null;

          // echo '<pre>'.print_r($_POST,1).'</pre>';
          // echo '<pre>'.print_r($FileData,1).'</pre>';
          // die();

          if(!empty($Import['insert']['tl_glossar']))
            foreach($Import['insert']['tl_glossar'] as $alias => &$gdata)
              $gdata['id'] = $f++;

          if(\Input::post('glossar_update_action') <= 1) {
            $Update = $this->updateGlossarData($FileData,$Import['update']['tl_glossar']);
            $Update['insert'] = array_merge($Update['insert']['tl_glossar'],(array)$Import['insert']['tl_glossar']);
          } else {
            $Update['insert'] = (array)$Import['update']['tl_glossar'];
            foreach($Update['insert'] as $glossar => $insert) {
              unset($Update['insert'][$glossar]['tl_sw_glossar']);
            }
          }

          $insertedTerms = $this->importTermData($FileData,$Update['insert']);
          $this->importContentData($FileData,$insertedTerms);
          
          unset($Import['update']['tl_glossar']);
          unset($Import['insert']['tl_glossar']);
        break;
        default:
          $FileData = $this->decode_array($this->readFile($arrFiles[0]));
          $Session->set('glossar_file',$FileData);
          $Import = $this->importGlossarData($FileData);
          if(!empty($Import['insert'])) {
            $FirstID = $this->insertData($Import['insert'],'tl_glossar');
            $TermIDs = $this->importTermData($FileData,$Import['insert'],$FirstID);
            $this->importContentData($FileData,$TermIDs);
          }
        break;
      }

      if(!empty($Import['update'])) {
        $Session->set('glossar_first_id',$FirstID);
        $Session->set('glossar_import',$Import);
      } else $Stage = 2;
    }
    else {
      $arrFiles = explode(',', $this->Session->get('uploaded_themes'));
    }
    
    $this->Template = new \BackendTemplate('be_glossar_import');
    $this->Template->setData(array(
      'import' => $Import,
      'stage' => $Stage,
      'update_action' => \Input::post('glossar_update_action'),
      'maxFileSize' => \Config::get('maxFileSize'),
      'messages' => \Message::generate(),
      'fields' => $objUploader->generateMarkup(),
      'action' => ampersand(\Environment::get('request'), true)
    ));
    return $this->Template->parse();
  }

  private function readFile($file) {
    $data = '';
    if (($handle = fopen(TL_ROOT.'/'.$file, "r")) !== false) {
      $data = json_decode(fgets($handle),1);
      fclose($handle);
    }

    return $data;
  }

  private function importGlossarData($data) {
    $SQL = $arrGlossar = array();

    if(empty($data))
      return array();

    foreach($data as $id => $glossar) {
      $arrGlossar[$id] = $glossar['tl_glossar']['alias'];
    }

    $data = $this->decode_array($data);

    $Glossar = \GlossarModel::findAllByAlias($arrGlossar);
    if(empty($Glossar)) {
      foreach($arrGlossar as $id => $glossar) {
        $SQL['insert'][$data[$id]['tl_glossar']['alias']] = array_merge(array('fallback'=>0),array_diff_key($data[$id]['tl_glossar'],array('id'=>0,'tl_sw_glossar'=>array())));
      }
    } else {
      while($Glossar->next()) {
        if(!empty($data[$Glossar->alias]))
          $SQL['update']['tl_glossar'][$Glossar->alias] = array_merge($data[$Glossar->alias]['tl_glossar'],array('id'=>$Glossar->id));
      }
      foreach($data as $id => $glossar) {
        if(empty($SQL['update']['tl_glossar'][$glossar['tl_glossar']['alias']]) && empty($SQL['insert']['tl_glossar'][$glossar['tl_glossar']['alias']]))
          $SQL['insert'][$data[$id]['tl_glossar']['alias']] = array_merge(array('fallback'=>0),$this->decode_array(array_diff_key($glossar['tl_glossar'],array('id'=>0,'tl_sw_glossar'=>array()))));
      }
    }

    return $SQL;
  }

  private function updateGlossarData($data,$updateData) {
    $arrDelete = $SQL = array();
    foreach($updateData as $alias => $glossar) {
      if(\Input::post('glossar_update_action') == 0)
        $SQL['insert']['tl_glossar'][$glossar['alias']] = array_diff_key(array_merge(array('fallback'=>0),$glossar,array('title'=>$glossar['title'].'(Kopie)','alias'=>$glossar['alias'].'_kopie')),array('id'=>0,'tl_sw_glossar'=>array()));
      else {
        $arrDelete[] = $glossar['alias'];
        $SQL['insert']['tl_glossar'][$glossar['alias']] = array_diff_key(array_merge(array('fallback'=>0),$glossar),array('id'=>0,'tl_sw_glossar'=>array()));
      }
    }

    if(!empty($arrDelete))
      if(\Input::post('glossar_update_action') == 1)
        $this->Database->prepare("DELETE tl_glossar.*, tl_sw_glossar.*,tl_content.* FROM tl_glossar LEFT JOIN tl_sw_glossar ON tl_glossar.id = tl_sw_glossar.pid LEFT JOIN tl_content ON (tl_sw_glossar.id = tl_content.id AND tl_content.ptable = 'tl_sw_glossar') WHERE tl_glossar.alias IN ('".implode("','",$arrDelete)."')")->execute();
    $id = $this->insertData($SQL['insert']['tl_glossar'],'tl_glossar');

    foreach($SQL['insert']['tl_glossar'] as $alias => &$gdata)
      $gdata['id'] = $id++;

    return $SQL;
  }

  private function importTermData($data,$import,$fid = false) {
    $SQL = $arrGlossar = array();

    if(empty($data))
      return array();

    /* Neue Begriffe hinzufuegen */
    if(\Input::post('glossar_update_action') == 2) {
      foreach($import as $glossar => $gdata) {
        $arrTerms = array();
        foreach($data[$glossar]['tl_glossar']['tl_sw_glossar'] as $key => $term)
          $arrTerms[$term['alias']] = $term['alias'];

        $allTerms = \SwGlossarModel::findAllByAlias($arrTerms,$gdata['id']);
        if(!empty($allTerms)) {
          $dbTerms = $allTerms->fetchAll();

          foreach($data[$glossar]['tl_glossar']['tl_sw_glossar'] as $a1key => $term) {
            $found = false;
            foreach($dbTerms as $dbKey => $dbData)
              if($dbData['alias'] === $term['alias'])
                $found = true;
            if($found === false)
              $SQL[$glossar][$term['alias']] = array_diff_key(array_merge(array('strictSearch'=>0,'date'=>time(),'noPlural'=>0,'teaser'=>'','type'=>'default','jumpTo'=>0,'alias'=>standardize($term['title'])),$term,array('pid'=>($gdata['id']?$gdata['id']:$fid))),array('id'=>0,'tl_content'=>array()));
          }
        }
      }
      /* Alle Begriffe ersetzen */
    } elseif(\Input::post('glossar_update_action') == 3) {
      $arrDelete = array();
      foreach($import as $glossar => $gdata)
        $arrDelete[] = $data[$glossar]['tl_glossar']['id'];
      $this->Database->prepare("DELETE tl_sw_glossar.*,tl_content.* FROM tl_sw_glossar LEFT JOIN tl_content ON (tl_sw_glossar.id = tl_content.id AND tl_content.ptable = 'tl_sw_glossar') WHERE tl_sw_glossar.pid IN ('".implode("','",$arrDelete)."')")->execute();
      
      foreach($import as $glossar => $gdata) {
        foreach($data[$glossar]['tl_glossar']['tl_sw_glossar'] as $key => $term)
          $SQL[$glossar][$term['alias']?$term['alias']:standardize($term['title'])] = array_diff_key(array_merge(array('strictSearch'=>0,'date'=>time(),'noPlural'=>0,'teaser'=>'','type'=>'default','jumpTo'=>0,'alias'=>standardize($term['title'])),$term,array('pid'=>($gdata['id']?$gdata['id']:$fid))),array('id'=>0,'tl_content'=>array()));
        $fid++;
      }
      /* alles überschreiben */
    } else {
      foreach($import as $glossar => $gdata) {
        foreach($data[$glossar]['tl_glossar']['tl_sw_glossar'] as $key => $term)
          $SQL[$glossar][$term['alias']?$term['alias']:standardize($term['title'])] = array_diff_key(array_merge(array('strictSearch'=>0,'date'=>time(),'noPlural'=>0,'teaser'=>'','type'=>'default','jumpTo'=>0,'alias'=>standardize($term['title'])),$term,array('pid'=>($gdata['id']?$gdata['id']:$fid))),array('id'=>0,'tl_content'=>array()));
        $fid++;
      }
    }

    $InsertID = array();
    foreach($SQL as $glossar => $terms)
      $SQL[$glossar]['insertId'] = $this->insertData($terms,'tl_sw_glossar');

    return $SQL;
  }

  private function importContentData($data,$update) {

    $SQL = array();
    foreach($update as $glossar => $gdata) {
      $PID = $gdata['insertId']-1;
      if(!empty($data[$glossar]['tl_glossar']['tl_sw_glossar'])) {
        foreach($data[$glossar]['tl_glossar']['tl_sw_glossar'] as $tKey => $term) {
          if(!empty($gdata[$term['alias']])) {
            $PID++;
            if(!empty($term['tl_content']))
              foreach($term['tl_content'] as $cKey => $content)
                $SQL[$glossar][$term['alias']] = array_diff_key(array_merge($content,array('pid'=>$PID)),array('id'=>0));
          }
        }
      }
    }

    $SQL = $this->decode_array($SQL);

    // echo '<pre>'.print_r($update,1).'</pre>';
    // echo '<pre>'.print_r($SQL,1).'</pre>';
    // echo '<pre>'.print_r($data,1).'</pre>';

    $InsertID = array();
    foreach($SQL as $glossar => $content)
      $InsertID[$glossar] = $this->insertData($content,'tl_content');

    return $InsertID;
  }

  private function insertData($insert,$table) {
    $Query = "INSERT INTO ".$table;
    $SQL= array();

    reset($insert);
    $first_key = key($insert);

    foreach($insert as $alias => $data) {
      if($alias === $first_key)
        $Query .= " (".implode(',',array_keys($data)).") VALUES ";
      $SQL[] = "('".implode("','",$data)."')";
    }
    // echo $Query.implode(',',$SQL);
    $Execute = $this->Database->prepare($Query.implode(',',$SQL))->execute();
    return $Execute->insertId;
  }

  public function exportGlossar() {

    $objGlossar = new \BackendTemplate('be_glossar_export');
    $objGlossar->setData(array(
      'lickey'          => true,
      'headline'        => 'Export',
      'glossarMessage'  => '',
      'glossarSubmit'   => 'Export',
      'glossarLabel'    => 'Format wählen',
      'glossarHelp'     => 'Bitte wählen Sie das Format aus, mit der der Exporter Ihre Einträge exportieren soll.',
      'action' => ampersand(\Environment::get('request'), true),
    ));

    $lickey = $this->checkLizenz();

    if($lickey) {
      $objGlossar->lickey = false;
      return $objGlossar->parse();
    }

    if(\Input::get('glossar_export') != '') {

      $JSON = array();
      $id = \Input::get('id');

      $Glossar = \GlossarModel::findAll();

      $arrGlossar = array();
      if(!empty($Glossar)) {
        while($Glossar->next()) {
          if(empty($id) || $Glossar->id == $id) {
            $arrGlossar[] = $Glossar->id;
            $JSON[$Glossar->alias] = array('tl_glossar'=>$Glossar->row());
          }
        }
        $Term = \SwGlossarModel::findByPids($arrGlossar);
        if(!empty($Term)) {
          $arrTerms = array();
          $term = null;
          while($Term->next()) {
            $arrTerms[] = $Term->id;
            foreach($JSON as $key => $glossar)
              if($glossar['tl_glossar']['id'] == $Term->pid)
                $JSON[$key]['tl_glossar']['tl_sw_glossar'][] = $Term->row();
          }

          $Content = \GlossarContentModel::findByPidsAndTable($arrTerms,'tl_sw_glossar',\Input::get('glossar_export'));

          if(!empty($Content)) {
            while($Content->next()) {
              foreach($JSON as $key => $glossar)
                foreach($glossar['tl_glossar']['tl_sw_glossar'] as $term => $tdata)
                  if($tdata['id'] == $Content->pid)
                    $JSON[$key]['tl_glossar']['tl_sw_glossar'][$term]['tl_content'][] = $Content->row();
            }
          }
        }
      }

      $JSON = $this->encode_array($this->cleanArray($JSON));

      $title = $Glossar->alias;
      if(!$id)
        $title = 'complete';

      header("Content-type: application/download");
      header('Content-Disposition: attachment; filename="glossar_'.$title.'.json"');
      echo json_encode($JSON);
      die();
    }

    return $objGlossar->parse();
  }

  private function checkLizenz() {
    $db = &$this->Database;
    $ext = $db->prepare("select * from `tl_repository_installs` where `extension`='SWGlossar'")->execute();
    return ($ext->lickey == '0' || $ext->lickey == 'free2use');
  }

  /* Eingabe (Nicer JSON) */
  public function encode_array($input) {
    foreach ($input as &$value) 
      if(is_array($value))
        $value = $this->encode_array($value);
      else $value = addslashes(htmlentities($value));

    return $input;
  }

  /* Ausgabe */
  public function decode_array($input) {
    foreach ($input as $key => &$value) 
      if(is_array($value))
        $value = $this->decode_array($value);
      else $value = html_entity_decode(stripslashes($value));

    return $input;
  }

  /* Nur das noetigste und keine Standardwerte speichern. */
  public function cleanArray($input) {
    foreach ($input as &$value) 
      if(is_array($value))
        $value = $this->cleanArray($value); 

    return array_filter($input, function($item) {
      return $item !== null && $item !== '' && $item !== '0' && !in_array($item,array('a:2:{i:0;s:0:"";i:1;s:0:"";}','a:2:{s:4:"unit";s:2:"h1";s:5:"value";s:0:"";}','com_default'));
    });
  }

  public function importTerms() {

  }

  public function exportTerms() {
    $objGlossar = new \BackendTemplate('be_glossar_export');
    $objGlossar->setData(array(
      'lickey'          => true,
      'headline'        => 'Export',
      'glossarMessage'  => '',
      'glossarSubmit'   => 'Export',
      'glossarLabel'    => 'Format wählen',
      'glossarHelp'     => 'Bitte wählen Sie das Format aus, mit der der Exporter Ihre Einträge exportieren soll.',
      'action' => ampersand(\Environment::get('request'), true),
    ));

    $db = &$this->Database;
    $ext = $db->prepare("select * from `tl_repository_installs` where `extension`='SWGlossar'")->execute();

    if($ext->lickey == false || $ext->lickey == 'free2use') {
      $objGlossar->lickey = false;
    }

    return $objGlossar->parse();
  }
}
