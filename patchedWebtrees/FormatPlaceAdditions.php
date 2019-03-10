<?php
namespace Cissee\WebtreesExt;

class FormatPlaceAdditions {

	private $htmlAfterNames;
	private $latiLong;
	private $latiLongTooltip;
	private $htmlAfterLatiLong;
	private $htmlAfterNotes;
	private $script;
	
	/**
	 * @return string html
	 */	 	
	public function getHtmlAfterNames() {
		return $this->htmlAfterNames;
	}
	
	/**
	 * @return array|null (array of integer)
	 */	 	
	public function getLatiLong() {
		return $this->latiLong;
	}
	
	/**
	 * @return string|null
	 */	 	
	public function getLatiLongTooltip() {
		return $this->latiLongTooltip;
	}
	
	/**
	 * @return string html
	 */	 	
	public function getHtmlAfterLatiLong() {
		return $this->htmlAfterLatiLong;
	}

	/**
	 * @return string html
	 */	 	
	public function getHtmlAfterNotes() {
		return $this->htmlAfterNotes;
	}
	
	/**
	 * @return string script
	 */	 	
	public function getScript() {
		return $this->script;
	}
	
	public function __construct($htmlAfterNames = '', $latiLong = null, $latiLongTooltip = null, $htmlAfterLatiLong = '', $htmlAfterNotes = '', $script = '') {
		$this->htmlAfterNames = $htmlAfterNames;
		$this->latiLong = $latiLong;
		$this->latiLongTooltip = $latiLongTooltip;
		$this->htmlAfterLatiLong = $htmlAfterLatiLong;
		$this->htmlAfterNotes = $htmlAfterNotes;
		$this->script = $script;
	}
}
