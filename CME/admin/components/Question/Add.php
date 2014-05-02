<?php

require_once 'Inquisition/admin/components/Question/Add.php';
require_once 'CME/CME.php';
require_once 'CME/dataobjects/CMECreditWrapper.php';

/**
 * Question edit page for inquisitions
 *
 * @package   CME
 * @copyright 2011-2014 silverorange
 * @license   http://www.opensource.org/licenses/mit-license.html MIT License
 */
class CMEQuestionAdd extends InquisitionQuestionAdd
{
	// {{{ protected properties

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var CMECredit
	 */
	protected $credit;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		// for evaluations, hide correct option column
		$sql = sprintf(
			'select count(1) from CMECredit
			where evaluation = %s',
			$this->app->db->quote($this->inquisition->id, 'integer')
		);

		$is_evaluation = (SwatDB::queryOne($this->app->db, $sql) > 0);

		if ($is_evaluation) {
			$view = $this->ui->getWidget('question_option_table_view');
			$correct_column = $view->getColumn('correct_option');
			$correct_column->visible = false;
		}

		$this->initCredit();
		$this->initType();
	}

	// }}}
	// {{{ protected function initCredit()

	protected function initCredit()
	{
		$sql = sprintf(
			'select * from CMECredit where
			evaluation = %1$s or quiz = %1$s',
			$this->app->db->quote($this->inquisition->id, 'integer')
		);

		$this->credit = SwatDB::query(
			$this->app->db,
			$sql,
			SwatDBClassMap::get('CMECreditWrapper')
		)->getFirst();
	}

	// }}}
	// {{{ protected function initType()

	protected function initType()
	{
		$evaluation_id = $this->credit->getInternalValue('evaluation');
		$quiz_id       = $this->credit->getInternalValue('quiz');

		if ($this->inquisition->id === $evaluation_id) {
			$this->type = 'evaluation';
		} elseif ($this->inquisition->id === $quiz_id) {
			$this->type = 'quiz';
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		parent::buildNavBar();

		if ($this->credit instanceof CMECredit) {
			$entries = $this->navbar->popEntries(3);
			array_shift($entries);
			$entries[0]->title = $this->getQuizTitle();
			$this->navbar->addEntries($entries);
		}
	}

	// }}}
	// {{{ protected function getQuizTitle()

	protected function getQuizTitle()
	{
		switch ($this->type) {
		case 'evaluation':
			return sprintf(
				CME::_('% Evaluation'),
				$this->credit->provider->title
			);

		default:
		case 'quiz':
			return sprintf(
				CME::_('%s Quiz'),
				$this->credit->provider->title
			);
		}
	}

	// }}}
}

?>
