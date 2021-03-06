<?php

namespace Lorry;

use Analog\Analog;
use Lorry\Service\ConfigService;
use Lorry\Service\LocalisationService;
use Lorry\Service\PersistenceService;
use Lorry\Service\SecurityService;
use Lorry\Service\SessionService;
use Lorry\Service\MailService;
use Lorry\Exception\NotImplementedException;
use Twig_Loader_Filesystem;
use Twig_Environment;

class Environment {

	/**
	 * Handle an HTTP request.
	 */
	public function requestHandle() {
		$config = new ConfigService();

		$loglevel = $config->get('debug') ? \Analog::DEBUG : \Analog::INFO;
		\Analog::handler(\Analog\Handler\Threshold::init(
						\Analog\Handler\File::init('../app/logs/lorry.log'), $loglevel
		));

		try {
			$this->handle($config);
		} catch(\Exception $e) {

			$identifier = sha1(uniqid('lorry'));
			Analog::error($identifier.' - '.get_class($e).': '.$e->getMessage());

			header('HTTP/1.1 500 Internal Server Error');
			header('Content-Type: text/plain');

			if($config && $config->get('debug')) {
				echo 'An internal error occured: '.get_class($e).PHP_EOL.PHP_EOL;
				if($e->getMessage()) {
					echo 'Message: '.$e->getMessage().PHP_EOL.PHP_EOL;
				}
				echo 'Stack trace:'.PHP_EOL.$e->getTraceAsString().PHP_EOL.PHP_EOL;
				echo 'Lorry platform in debug mode.';
			} else {
				echo 'An internal error occured. We will be looking into this.'.PHP_EOL.PHP_EOL;
				echo 'Error was '.$identifier.' at '.date('Y-m-d H:i:s').'.'.PHP_EOL;
			}
		}
	}

	protected function handle(ConfigService $config) {

		$persistence = new PersistenceService();
		$persistence->setConfigService($config);

		ModelFactory::setConfigService($config);
		ModelFactory::setPersistenceService($persistence);

		$session = new SessionService();

		$localisation = new LocalisationService();
		$localisation->setSessionService($session);
		$localisation->localize();

		$loader = new Twig_Loader_Filesystem('../app/templates');
		$twig = new Twig_Environment($loader, array('cache' => '../app/cache/twig', 'debug' => $config->get('debug')));
		$twig->addExtension(new \Twig_Extension_Escaper(true));
		$twig->addExtension(new \Twig_Extensions_Extension_I18n());

		$twig->addGlobal('brand', htmlspecialchars($config->get('brand')));
		$twig->addGlobal('base', htmlspecialchars($config->get('base')));
		$twig->addGlobal('resources', htmlspecialchars($config->get('base').'/resources'));
		$twig->addGlobal('path', explode('/', trim(Router::getPath(), '/')));
		$twig->addGlobal('filename', htmlspecialchars(rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')));

		$twig->addGlobal('site_notice', $config->get('notice'));
		$twig->addGlobal('site_copyright', htmlspecialchars('© '.date('Y')));
		$twig->addGlobal('site_trademark', '<a class="text" href="http://clonk.de">'.gettext('"Clonk" is a registered trademark of Matthes Bender').'</a>');
		$twig->addGlobal('site_contact', $config->get('contact'));

		if($session->authenticated()) {
			$user = $session->getUser();
			$twig->addGlobal('user_login', true);
			$twig->addGlobal('user_name', $user->getUsername());
			$twig->addGlobal('user_profile', $config->get('base').'/users/'.$user->getUsername());
			$twig->addGlobal('user_administrator', $user->isAdministrator());
			$twig->addGlobal('user_moderator', $user->isModerator());
			$twig->addGlobal('state', $session->getState());
		}

		$security = new SecurityService();
		$security->setSessionService($session);

		$mail = new MailService();
		$mail->setConfigService($config);
		$mail->setLocalisationService($localisation);

		PresenterFactory::setConfigService($config);
		PresenterFactory::setLocalisationService($localisation);
		PresenterFactory::setSecurityService($security);
		PresenterFactory::setSessionService($session);
		PresenterFactory::setMailService($mail);
		PresenterFactory::setTwig($twig);

		EmailFactory::setConfigService($config);
		EmailFactory::setLocalisationService($localisation);
		EmailFactory::setSecurityService($security);
		EmailFactory::setSessionService($session);
		EmailFactory::setTwig($twig);

		// set production routes
		Router::setRoutes(array(
			'/' => 'Site\Front',
			'/addons' => 'Addon\Portal',
			'/addons/:alpha' => 'Addon\Game',
			'/addons/:alpha/:alpha' => 'Addon\Release',
			'/addons/:alpha/:alpha/:version' => 'Addon\Release',
			'/download/' => 'Redirect\Front',
			'/download/:alpha/:alpha' => 'Addon\Download',
			'/download/:alpha/:alpha/:version' => 'Addon\Download',
			'/publish' => 'Publish\Portal',
			'/publish/create' => 'Publish\Create',
			'/publish/addons/:number' => 'Publish\Edit',
			'/publish/addons/:number/:version' => 'Publish\Release',
			'/publish/addons/:number/:version/upload' => 'Publish\Upload',
			'/api/upload/remove' => 'Publish\Remove',
			'/users' => 'User\Table',
			'/users/:alpha' => 'User\Profile',
			'/users/:alpha/edit' => 'User\Edit',
			'/admin' => 'Manage\Administration',
			'/moderate' => 'Manage\Moderation',
			'/register' => 'Account\Register',
			'/login' => 'Account\Login',
			'/logout' => 'Account\Logout',
			'/settings' => 'Account\Settings',
			'/activate' => 'Account\Activate',
			'/identify' => 'Account\Identify',
			'/auth/gateway/:alpha' => 'Auth\Gateway',
			'/auth/callback/:alpha' => 'Auth\Callback',
			'/about' => 'Site\About',
			'/clonk' => 'Site\Clonk',
			'/contact' => 'Site\Contact'
		));

		// set debug routes
		if($config->get('debug')) {
			Router::addRoutes(array(
				'/debug/cachewarmer' => 'Debug\CacheWarmer'
			));
		}

		// determine the RESTful method
		$method = strtolower($_SERVER['REQUEST_METHOD']);

		try {
			// determine the controller
			$presenter = Router::route();

			// check if method is supported
			if(!method_exists($presenter, $method)) {
				throw new NotImplementedException(get_class($presenter).'->'.$method.'()');
			}

			// execute the RESTful method
			return $presenter->handle($method, Router::getMatches());
		} catch(Exception $exception) {
			$presenter = $exception->getPresenter();
			if(PresenterFactory::valid($presenter)) {
				return PresenterFactory::build($exception->getPresenter())->get($exception);
			}
		} catch(\Exception $exception) {
			return PresenterFactory::build('Error')->get($exception);
		}
	}

}
