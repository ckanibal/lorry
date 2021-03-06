<?php

namespace Lorry\Service;

class LocalisationService {

	/**
	 *
	 * @var \Lorry\Service\SessionService
	 */
	protected $session;

	public final function setSessionService(SessionService $session) {
		$this->session = $session;
	}

	public final function getAvailableLanguages() {
		return array('en-US', 'de-DE');
	}

	public final function verifyLanguage($language) {
		if(in_array($language, $this->getAvailableLanguages())) {
			return $language;
		}
		return false;
	}

	private $display_language = false;

	public function getDisplayLanguage() {
		if($this->display_language) {
			return $this->display_language;
		}

		$available = $this->getAvailableLanguages();

		if($this->session->authenticated()) {
			$language = $this->session->getUser()->getLanguage();
			if($this->setDisplayLanguage($language)) {
				return $language;
			}
		}

		if(isset($_COOKIE['lorry_language'])) {
			$language = $_COOKIE['lorry_language'];
			if($this->setDisplayLanguage($language)) {
				return $language;
			} else {
				$this->resetDisplayLanguage();
			}
		}

		if(function_exists('http_negotiate_language')) {
			$language = http_negotiate_language($available);
		} else {
			$language = $available[0];
			$accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			foreach($available as $i => $current) {
				if(strpos($accept, $current) === 0) {
					$language = $available[$i];
				}
			}
		}

		$this->setDisplayLanguage($language);
		return $this->display_language;
	}

	public final function resetDisplayLanguage() {
		setcookie('lorry_language', '', 0, '/');
		return true;
	}

	public final function setDisplayLanguage($language) {
		if($this->verifyLanguage($language)) {
			$this->display_language = $language;
			if(!isset($_COOKIE['lorry_language']) || $_COOKIE['lorry_language'] != $language) {
				setcookie('lorry_language', $language, time() + 60 * 60 * 24 * 365, '/');
			}
			return true;
		}
		return false;
	}

	private $localized = false;
	
	public final function localize() {
		if($this->localized) {
			return false;
		}
			
		$requested = $this->getDisplayLanguage();
		header('Content-Language: '.$requested);
		
		$this->localized = true;
		
		return $this->silentLocalize($requested);
	}

	private $current_language = false;
	
	public final function silentLocalize($language) {
		if($language == $this->current_language) {
			return true;
		}
		if(!$this->verifyLanguage($language)) {
			return false;
		}
			
		$language = str_replace('-', '_', $language);
		putenv('LC_ALL='.$language);
		setlocale(LC_ALL, $language.'.UTF-8');

		$textdomain = 'lorry-'.$language;
		bindtextdomain($textdomain, '../app/locale');
		bind_textdomain_codeset($textdomain, 'UTF-8');
		textdomain($textdomain);
		
		$this->current_language = $language;
		
		return true;
	}

	public final function resetLocalize() {
		return $this->silentLocalize($this->getDisplayLanguage());
	}
	
	const FORMAT_DATETIME = 1;
	const FORMAT_DATE = 2;
	const FORMAT_TIME = 3;

	public final function getFormat($format) {
		switch($format) {
			case self::FORMAT_DATETIME:
				return gettext('d-m-Y H:i');
				break;
			case self::FORMAT_DATE:
				return gettext('d-m-Y');
				break;
			case self::FORMAT_TIME:
				return gettext('H:i');
				break;
		}
	}

	public final function namedMonth($month) {
		switch($month) {
			case 1:
				return gettext('January');
			case 2:
				return gettext('February');
			case 3:
				return gettext('March');
			case 4:
				return gettext('April');
			case 5:
				return gettext('May');
			case 6:
				return gettext('June');
			case 7:
				return gettext('July');
			case 8:
				return gettext('August');
			case 9:
				return gettext('September');
			case 10:
				return gettext('October');
			case 11:
				return gettext('November');
			case 12:
				return gettext('December');
		}
	}

	public final function countedNumber($number) {
		switch($number) {
			case 1:
				return gettext('1st');
			case 2:
				return gettext('2nd');
			case 3:
				return gettext('3rd');
			default:
				return strtr(gettext('%n%th'), array('%n%' => $number));
		}
	}

}
