<?php

require_once 'Inquisition/admin/components/Question/HintDelete.php';
require_once 'CME/admin/components/Question/include/CMEQuestionHelper.php';

/**
 * Delete confirmation page for question hints
 *
 * @package   CME
 * @copyright 2013-2014 silverorange
 * @license   http://www.opensource.org/licenses/mit-license.html MIT License
 */
abstract class CMEQuestionHintDelete extends InquisitionQuestionHintDelete
{
	// {{{ protected properties

	/**
	 * @var CMEQuestionHelper
	 */
	protected $helper;

	// }}}

	// helper methods
	// {{{ public function setInquisition()

	public function setInquisition(InquisitionInquisition $inquisition)
	{
		parent::setInquisition($inquisition);

		if (!$this->inquisition instanceof InquisitionInquisition) {
			// if we got here from the question index, load the inquisition
			// from the binding as we only have one inquisition per question
			$sql = sprintf(
				'select inquisition from InquisitionInquisitionQuestionBinding
				where question = %s',
				$this->app->db->quote($this->question->id)
			);

			$inquisition_id = SwatDB::queryOne($this->app->db, $sql);

			$this->inquisition = $this->loadInquisition($inquisition_id);
		}

		$this->helper = $this->getQuestionHelper();
		$this->helper->initInternal();
	}

	// }}}
	// {{{ abstract protected function getQuestionHelper()

	abstract protected function getQuestionHelper();

	// }}}

	// build phase
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		parent::buildNavBar();

		// put edit entry at the end
		$title = $this->navbar->popEntry();

		$this->helper->buildNavBar($this->navbar);

		$this->navbar->addEntry($title);
	}

	// }}}
}

?>
