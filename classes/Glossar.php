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

  private $glossar = array();

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
        $cb_output = $this->$callback[0]->$callback[1](\Input::get('items'),$strContent,$template);
        if(!empty($cb_output))
          $arrGlossar[] = $cb_output;
      }
    }

    if(!empty($arrGlossar))
      $this->glossar = implode('|',$arrGlossar);
    $this->glossar = str_replace('||','|',$this->glossar);

    $Glossar = \GlossarModel::findByLanguage($objPage->language);
    if(empty($Glossar))
      $Glossar = \GlossarModel::findByFallback(1);

    $Term = \SWGlossarModel::findBy(array("title IN ('".str_replace('|',"','",$this->glossar)."') AND pid = ?"),array($Glossar->id),array('order'=>' CHAR_LENGTH(title) DESC'));
   
    $strContent = $this->replace($strContent,$Term);

    if(\Config::get('glossar_no_fallback') == 1 || $objPage->glossar_no_fallback == 1)
      return $strContent;

    /* Replace the fallback languages */
    $Term = \SWGlossarModel::findBy(array("title IN ('".str_replace('|',"','",$objPage->fallback_glossar)."')"),array(),array('order'=>' CHAR_LENGTH(title) DESC'));
    $strContent = $this->replace($strContent,$Term);
    
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

      $Content = \ContentModel::findPublishedByPidAndTable($Glossar->id,'tl_sw_glossar');

      $replaceFunction = 'replaceTitle2Link';
      if((!$Glossar->jumpTo && !$GLOBALS['TL_CONFIG']['jumpToGlossar']) || empty($Content))
        $replaceFunction = 'replaceTitle2Span';

      $ignoredTags = array('a');
      if($GLOBALS['TL_CONFIG']['ignoreInTags'])
        $ignoredTags = explode(',',$GLOBALS['TL_CONFIG']['ignoreInTags']);
      if($this->glossar->ignoreInTags)
        $ignoredTags = explode(',',$this->glossar->ignoreInTags);

      if(\Config::get('strictSearch') !== null && $Glossar->strictSearch === null)
        $Glossar->strictSearch = \Config::get('strictSearch');

      if(empty($Glossar->type) || $Glossar->type == 'default' || $Glossar->type == 'glossar') {
        $IllegalPlural = '';
        if(\Config::get('illegalChars'))
          $IllegalPlural = \Config::get('illegalChars');
        $IllegalPlural = html_entity_decode($IllegalPlural);

        $plural = preg_replace('/[.]+(?<!\\.)/is','\\.',$IllegalPlural.(!$Glossar->noPlural ? $GLOBALS['glossar']['illegal']:'')).'<';
        $preg_query = '/(?!(?:[^<]+>|[^>]+(<\/'.implode('>|<\/',$ignoredTags).'>)))('.($Glossar->strictSearch==1||$Glossar->strictSearch==3?'\b':'') . $Glossar->title . (!$Glossar->noPlural?'[^ '.$plural.']*':'') . ($Glossar->strictSearch==1?'\b':'').')/is';
        // $preg_query = '/(?!(?:[^<]+>|[^>]+(<\/'.implode('>|<\/',$ignoredTags).'>)))(?:<(?:a|span|abbr) (?!class="glossar")[^>]*>)?('.($Glossar->strictSearch==1||$Glossar->strictSearch==3?'\b':'') . $Glossar->title . (!$Glossar->noPlural?'[^ '.$plural.']*':'') . ($Glossar->strictSearch==1?'\b':'').')/is';
        // echo $preg_query.'<br>';
        if($Glossar->title && preg_match_all( $preg_query, $strContent, $third)) {
          $strContent = preg_replace_callback( $preg_query, array($this,$replaceFunction), $strContent);
        }
      }
      $preg_query = '/(?!(?:[^<]+>|[^>]+(<\/'.implode('>|<\/',$ignoredTags).'>)))\b(' . $Glossar->title . ')\b/is';
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

    // $Log = new \GlossarLogModel();
    // $Log->setData(array(
    //   'action' => 'load',
    // ));

    $Content = \ContentModel::findPublishedByPidAndTable($Glossar->id,'tl_sw_glossar');
      
    $glossarObj = new \FrontendTemplate('glossar_layer');
    $glossarObj->setData($Glossar->row());
    $glossarObj->class = 'ce_glossar_layer';

    if(!empty($Content)) {
      if($GLOBALS['TL_CONFIG']['jumpToGlossar']) {
        $link = \PageModel::findByPk($GLOBALS['TL_CONFIG']['jumpToGlossar']);
        $glossarObj->link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$glossarObj->alias);
      }
      if($glossarObj->jumpTo) {
        $link = \PageModel::findByPk($glossarObj->jumpTo);
        $glossarObj->link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$glossarObj->alias);
      }
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

        $allTerms = \SWGlossarModel::findAllByAlias($arrTerms,$gdata['id']);
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

    $db = &$this->Database;
    $ext = $db->prepare("select * from `tl_repository_installs` where `extension`='SWGlossar'")->execute();

    if($ext->lickey == false || $ext->lickey == 'free2use') {
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
        $Term = \SWGlossarModel::findByPids($arrGlossar);
        if(!empty($Term)) {
          $arrTerms = array();
          $term = null;
          while($Term->next()) {
            $arrTerms[] = $Term->id;
            foreach($JSON as $key => $glossar)
              if($glossar['tl_glossar']['id'] == $Term->pid)
                $JSON[$key]['tl_glossar']['tl_sw_glossar'][] = $Term->row();
          }

          $Content = \ContentModel::findByPidsAndTable($arrTerms,'tl_sw_glossar',\Input::get('glossar_export'));

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