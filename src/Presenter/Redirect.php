<?php

namespace Lorry\Presenter;

use Lorry\Presenter;

abstract class Redirect extends Presenter {

	public abstract function getLocation();

	public function get() {
		$this->redirect($this->getLocation());
	}
}