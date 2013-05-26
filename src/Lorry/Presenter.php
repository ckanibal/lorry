<?php

namespace Lorry;

use Lorry\Service\ConfigService;
use Lorry\Service\LocalisationService;
use Lorry\Service\SecurityService;
use Lorry\Service\SessionService;
use Twig_Environment;

abstract class Presenter implements PresenterInterface {

	/**
	 *
	 * @var \Lorry\Service\ConfigService
	 */
	protected $config;

	public final function setConfigService(ConfigService $config) {
		$this->config = $config;
	}

	/**
	 *
	 * @var \Lorry\Service\LocalisationService
	 */
	protected $localisation;

	public final function setLocalisationService(LocalisationService $localisation) {
		$this->localisation = $localisation;
	}

	/**
	 *
	 * @var \Lorry\Service\SecurityService
	 */
	protected $security;

	public final function setSecurityService(SecurityService $security) {
		$this->security = $security;
	}

	/**
	 *
	 * @var \Lorry\Service\SessionService
	 */
	protected $session;

	public final function setSessionService(SessionService $session) {
		$this->session = $session;
	}

	/**
	 *
	 * @var \Twig_Environment;
	 */
	private $twig;

	public final function setTwig(Twig_Environment $twig) {
		$this->twig = $twig;
	}

	/**
	 * Default handling for post requests.
	 */
	public function post() {
		call_user_func_array(array($this, 'get'), func_get_args());
	}

	protected $context = array();

	protected final function display($template) {
		$this->twig->display($template, $this->context);
	}

	protected final function error($selector, $message) {
		$this->alert($selector, $message, 'error');
	}

	protected final function warning($selector, $message) {
		$this->alert($selector, $message, 'warning');
	}

	protected final function success($selector, $message) {
		$this->alert($selector, $message, 'success');
	}

	private final function alert($selector, $message, $type) {
		if(!array_key_exists('alerts', $this->context)) {
			$this->context['alerts'] = array();
		}
		$this->context['alerts'][$selector] = array('message' => $message, 'type' => $type);
	}

	/**
	 * Sends a 301 Moved Permanently redirect.
	 * @param string $location
	 */
	protected final function redirect($location, $external = false) {
		if(!$external) {
			$location = $this->config->get('base').$location;
		}
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: '.$location);
	}

}