<?php

require_once 'Inquisition/admin/components/Question/Delete.php';
require_once 'CME/admin/components/Question/include/CMEQuestionHelper.php';

/**
 * Delete confirmation page for question images
 *
 * @package   CME
 * @copyright 2012-2014 silverorange
 * @license   http://www.opensource.org/licenses/mit-license.html MIT License
 */
abstract class CMEQuestionDelete extends InquisitionQuestionDelete
{
	// {{{ protected properties

	/**
	 * @var CMEQuestionHelper
	 */
	protected $helper;

	// }}}

	// helper methods
	// {{{ public function setId()

	public function setId($id)
	{
		parent::setId($id);

		$this->helper = $this->getQuestionHelper();
		$this->helper->initInternal($this->inquisition);
	}

	// }}}
	// {{{ abstract protected function getQuestionHelper()

	abstract protected function getQuestionHelper();

	// }}}

	// process phase
	// {{{ protected function relocate()

	protected function relocate()
	{
		$uri = $this->helper->getRelocateURI();

		if ($uri == '') {
			parent::relocate();
		} else {
			$this->app->relocate($uri);
		}
	}

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
