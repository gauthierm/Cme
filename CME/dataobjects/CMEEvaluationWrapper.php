<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once 'Inquisition/dataobjects/InquisitionInquisitionWrapper.php';
require_once 'CME/dataobjects/CMEEvaluation.php';

/**
 * A recordset wrapper class for CMEEEvaluation objects
 *
 * @package   CME
 * @copyright 2011-2014 silverorange
 * @see       CMEEvaluation
 */
class CMEEvaluationWrapper extends InquisitionInquisitionWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class = 'CMEEvaluation';
		$this->index_field = 'id';
	}

	// }}}
}

?>
