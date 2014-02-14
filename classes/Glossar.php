<?php

/*
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 */

namespace sioweb\contao\extensions\glossar;
use Contao;

/**
* @file Glossar.php
* @class Glossar
* @author Sascha Weidner
* @version 3.0.0
* @package sioweb.contao.extensions.glossar
* @copyright Sioweb - Sascha Weidner
*/

class Glossar extends \Frontend
{ 

	private $Glossar;
	public function searchGlossarTerms($strContent, $strTemplate)
	{
		if(!$strContent)
			return $strContent;
		$Glossar = SWGlossarModel::findAll(array('order'=>' CHAR_LENGTH(title) DESC'));
		/* Gefundene Begriffe durch Links zum Glossar ersetzen */
		if($Glossar)
			while($Glossar->next())
			{
				$this->glossar = $Glossar;
				if(!$this->glossar->maxWidth)
					$this->glossar->maxWidth = $GLOBALS['glossar']['css']['maxWidth'];
				if(!$this->glossar->maxHeight)
					$this->glossar->maxHeight = $GLOBALS['glossar']['css']['maxHeight'];
				
				$third = array();
				if($Glossar->title && $Glossar->jumpTo && preg_match_all( "/(?!(?:[^<]+>|[^>]+<\/a>))\b(" . $Glossar->title . (!$Glossar->noPlural ?"[^ ".$GLOBALS['glossar']['illegal']."]*": '').")/is", $strContent, $third))
					$strContent = preg_replace_callback ( "/(?!(?:[^<]+>|[^>]+<\/a>))\b(" . $Glossar->title . (!$Glossar->noPlural ?"[^ ".$GLOBALS['glossar']['illegal']."]*": '').")/is", array($this,'replaceTitle'), $strContent );
				
			}
		return $strContent;
	}
	
	public function ajaxGlossar()
	{
		if($_POST['id'])
			$Glossar = SWGlossarModel::findByPk($_POST['id']);
		if($Glossar === null)
			return false;
			
		$glossarObj = new \FrontendTemplate('glossar_layer');
		$glossarObj->setData($Glossar->row());
		$glossarObj->class = 'ce_glossar_layer';
		$link = \PageModel::findByPk($glossarObj->jumpTo);
		$glossarObj->link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').$glossarObj->alias);
		
		
		return $glossarObj->parse();
		exit;  
	}

	private function replaceTitle($treffer)
	{
		$link = \PageModel::findByPk($this->glossar->jumpTo);
		if($link)
			$link = $this->generateFrontendUrl($link->row(), (($GLOBALS['TL_CONFIG']['useAutoItem'] && !$GLOBALS['TL_CONFIG']['disableAlias']) ?  '/' : '/items/').standardize(\String::restoreBasicEntities($this->glossar->alias)));
		#echo '<pre>'.print_r($treffer,1).'</pre>';
		return '<a class="glossar" data-maxwidth="'.($this->glossar->maxWidth ? $this->glossar->maxWidth : 0).'" data-maxheight="'.($this->glossar->maxHeight ? $this->glossar->maxHeight : 0).'" data-glossar="'.$this->glossar->id.'" href="'.$link.'">'.$treffer[1].'</a>';
	}
}