<?php

require_once 'Inquisition/dataobjects/InquisitionResponseWrapper.php';
require_once 'CME/dataobjects/QuizResponse.php';

/**
 * Special wrapper needed because {@link SwatDBClassMap} is not used for
 * quizzes
 *
 * This is because quiz and evaluation responses share the same database table.
 *
 * @package   CME
 * @copyright 2012-2014 silverorange
 * @see       CMEQuizResponse
 */
class CMEQuizResponseWrapper extends InquisitionResponseWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class = 'CMEQuizResponse';
	}

	// }}}
}

?>
