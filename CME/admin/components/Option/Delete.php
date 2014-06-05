<?php

require_once 'Inquisition/admin/components/Option/Delete.php';
require_once 'CME/admin/components/Option/include/CMEOptionHelper.php';

/**
 * @package   CME
 * @copyright 2014 silverorange
 * @license   http://www.opensource.org/licenses/mit-license.html MIT License
 */
abstract class CMEOptionDelete extends InquisitionOptionDelete
{
	// {{{ protected properties

	/**
	 * @var CMEOptionHelper
	 */
	protected $helper;

	// }}}

	// init phase
	// {{{ public function setInquisition()

	public function setInquisition(InquisitionInquisition $inquisition)
	{
		parent::setInquisition($inquisition);

		$this->helper = $this->getOptionHelper();
		$this->helper->initInternal();
	}

	// }}}
	// {{{ abstract protected function getOptionHelper()

	abstract protected function getOptionHelper();

	// }}}

	// build phase
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		parent::buildNavBar();

		$this->helper->buildNavBar($this->navbar);
	}

	// }}}
}

?>