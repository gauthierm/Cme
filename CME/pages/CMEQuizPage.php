<?php

require_once 'Swat/SwatYUI.php';
require_once 'SwatI18N/SwatI18NLocale.php';
require_once 'Swat/SwatDisplayableContainer.php';
require_once 'Swat/SwatContentBlock.php';
require_once 'Swat/SwatMessageDisplay.php';
require_once 'Site/pages/SiteDBEditPage.php';
require_once 'Inquisition/dataobjects/InquisitionInquisitionWrapper.php';
require_once 'Inquisition/dataobjects/InquisitionQuestionWrapper.php';
require_once 'Inquisition/dataobjects/InquisitionQuestionOptionWrapper.php';
require_once 'CME/CMECreditCompleteMailMessage.php';
require_once 'CME/dataobjects/CMEQuiz.php';

/**
 * @package   CME
 * @copyright 2011-2014 silverorange
 * @license   http://www.opensource.org/licenses/mit-license.html MIT License
 */
abstract class CMEQuizPage extends SiteDBEditPage
{
	// {{{ protected properties

	/**
	 * @var CMECredit
	 */
	protected $credit;

	/**
	 * @var InquisitionInquisition
	 */
	protected $quiz;

	/**
	 * @var InquisitionResponse
	 */
	protected $response;

	/**
	 * Saved references to question controls for processing because they are
	 * not part of the SwatUI.
	 *
	 * @var array
	 */
	protected $question_views = array();

	/**
	 * Array of response values indexed by question id for restoring form state
	 * from partially completed quiz
	 *
	 * @var array
	 */
	protected $response_values_by_binding_id = array();

	/**
	 * @var SwatMessageDisplay
	 */
	protected $response_message_display;

	// }}}
	// {{{ protected function getUiXml()

	protected function getUiXml()
	{
		return 'CME/pages/cme-quiz.xml';
	}

	// }}}
	// {{{ protected function getCacheKey()

	protected function getCacheKey()
	{
		return 'cme-quiz-page-'.$this->credit->id;
	}

	// }}}
	// {{{ protected function getArgumentMap()

	protected function getArgumentMap()
	{
		return array(
			'credit' => array(0, null),
		);
	}

	// }}}
	// {{{ abstract protected function getEvaluationURI()

	abstract protected function getEvaluationURI();

	// }}}
	// {{{ abstract protected function getCertificateURI()

	abstract protected function getCertificateURI();

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->initCredit();
		$this->initQuiz();

		// if there is no quiz, go to evaluation page
		if (!$this->quiz instanceof CMEQuiz) {
			if ($response->complete_date === null) {
				$this->relocateToEvaluation();
			}
		}

		$this->initResponse();

		if (!$this->isComplete()) {
			foreach ($this->quiz->question_bindings as $question_binding) {
				$this->addQuestionToUi($question_binding);
			}
		}
	}

	// }}}
	// {{{ protected function initCredit()

	protected function initCredit()
	{
		$credit_id = $this->getArgument('credit');

		$sql = sprintf(
			'select CMECredit.* from CMECredit
				inner join CMEFrontMatter
					on CMECredit.front_matter = CMEFrontMatter.id
			where CMECredit.id = %s and CMEFrontMatter.enabled = %s',
			$this->app->db->quote($credit_id, 'integer'),
			$this->app->db->quote(true, 'boolean')
		);

		$this->credit = SwatBD::query(
			$this->app->db,
			$sql,
			SwatDBClassMap::get('CMECreditWrapper')
		)->getFirst();

		if (!$this->credit instanceof CMECredit) {
			throw new SiteNotFoundException(
				sprintf(
					'CME credit %s not found.',
					$credit_id
				)
			);
		}
	}

	// }}}
	// {{{ protected function initQuiz()

	protected function initQuiz()
	{
		$this->quiz = $this->app->getCacheValue($this->getCacheKey());

		if ($this->quiz === false) {
			$this->quiz = $this->credit->quiz;

			if (!$this->quiz instanceof CMEQuiz) {
				throw new SiteNotFoundException(
					'Quiz not found for CME credit.'
				);
			}

			$questions = $this->quiz->question_bindings->loadAllSubDataObjects(
				'question',
				$this->app->db,
				'select * from InquisitionQuestion where id in (%s)',
				SwatDBClassMap::get('InquisitionQuestionWrapper')
			);

			// efficiently load correct options
			$questions->loadAllSubDataObjects(
				'correct_option',
				$this->app->db,
				'select * from InquisitionQuestionOption where id in (%s)',
				SwatDBClassMap::get('InquisitionQuestionOptionWrapper')
			);

			// efficiently load question options
			$questions->loadAllSubRecordsets(
				'options',
				SwatDBClassMap::get('InquisitionQuestionOptionWrapper'),
				'InquisitionQuestionOption',
				'question',
				'',
				'displayorder, id'
			);

			$this->addCacheValue($this->quiz, $this->getCacheKey());
		} else {
			$this->quiz->setDatabase($this->app->db);
		}
	}

	// }}}
	// {{{ protected function initResponse()

	protected function initResponse()
	{
		$this->response = $this->quiz->getResponseByAccount(
			$this->app->session->account
		);

		if ($this->response !== null) {
			// efficiently load question options for response values
			$this->response->values->loadAllSubDataObjects(
				'question_option',
				$this->app->db,
				'select * from InquisitionQuestionOption where id in (%s)',
				SwatDBClassMap::get('InquisitionQuestionOptionWrapper')
			);

			// efficiently load question bindings for response values
			$question_bindings = $this->response->values->loadAllSubDataObjects(
				'question_binding',
				$this->app->db,
				'select * from InquisitionInquisitionQuestionBinding
					where id in (%s)',
				SwatDBClassMap::get(
					'InquisitionInquisitionQuestionBindingWrapper'
				)
			);

			// efficiently load questions for question bindings
			if ($question_bindings instanceof
				InquisitionInquisitionQuestionBindingWrapper) {
				$question_bindings->loadAllSubDataObjects(
					'question',
					$this->app->db,
					'select * from InquisitionQuestion where id in (%s)',
					SwatDBClassMap::get('InquisitionQuestionWrapper')
				);
			}

			// index responses by question binding id
			foreach ($this->response->values as $value) {
				$binding_id = $value->question_binding->id;
				$this->response_values_by_binding_id[$binding_id] = $value;
			}
		}
	}

	// }}}
	// {{{ protected function addQuestionToUi()

	protected function addQuestionToUi(
		InquisitionInquisitionQuestionBinding $question_binding)
	{
		$container = new SwatDisplayableContainer();
		$container->classes[] = 'question';

		$content_container = new SwatDisplayableContainer();
		$content_container->classes[] = 'question-bodytext';

		$content_block = new SwatContentBlock();
		$content_block->content = $question_binding->question->bodytext;
		$content_block->content_type = 'text/xml';

		$content_container->addChild($content_block);
		$container->addChild($content_container);

		$response_value = $this->getResponseValue($question_binding);
		$view = $question_binding->getView();
		$this->question_views[$question_binding->id] = $view;

		$form_field = new SwatFormField();
		$form_field->display_messages = false;
		$form_field->addChild($view->getWidget($response_value));

		$container->addChild($form_field);

		// add to UI
		$this->ui->getWidget('question_container')->add($container);
	}

	// }}}
	// {{{ protected function getResponseValue()

	protected function getResponseValue(
		InquisitionInquisitionQuestionBinding $question_binding)
	{
		$value = null;

		// get response value if it exists
		if ($this->response !== null) {
			$binding_id = $question_binding->id;

			if (isset($this->response_values_by_binding_id[$binding_id])) {
				$value = $this->response_values_by_binding_id[$binding_id];
			}
		}

		return $value;
	}

	// }}}
	// {{{ protected function isComplete()

	protected function isComplete()
	{
		return ($this->response !== null &&
			$this->response->complete_date !== null);
	}

	// }}}

	// process phase
	// {{{ protected function saveData()

	protected function saveData(SwatForm $form)
	{
		switch ($form->id) {
		case 'quiz_form' :
			$this->saveQuizData($form);
			break;
		case 'reset_form' :
			$this->resetQuiz($form);
			break;
		}
	}

	// }}}
	// {{{ protected function saveQuizData()

	protected function saveQuizData(SwatForm $form)
	{
		if ($this->response === null) {
			$class_name = SwatDBClassMap::get('InquisitionResponse');
			$this->response = new $class_name();

			$this->response->account     = $this->app->session->account->id;
			$this->response->inquisition = $this->quiz->id;
			$this->response->createdate  = new SwatDate();
			$this->response->createdate->toUTC();
		} else {
			// delete old response values
			$this->response->values->delete();
		}

		// set complete date
		$this->response->complete_date = new SwatDate();
		$this->response->complete_date->toUTC();

		// save response
		$this->response->setDatabase($this->app->db);
		$this->response->save();

		// set new response values
		$wrapper = SwatDBClassMap::get('InquisitionResponseValueWrapper');
		$response_values = new $wrapper();

		foreach ($this->quiz->question_bindings as $question_binding) {
			$view = $this->question_views[$question_binding->id];

			$response_value = $view->getResponseValue();
			$response_value->response = $this->response->id;

			$response_values[] = $response_value;
		}

		$this->response->values = $response_values;

		// save response values
		$this->response->values->save();

		// clear CME hours cache for this account
		$key = 'cme-hours-'.$this->app->session->account->id;
		$this->app->deleteCacheValue($key, 'cme-hours');

		$this->sendCompletionEmail();
	}

	// }}}
	// {{{ protected function resetQuiz()

	protected function resetQuiz(SwatForm $form)
	{
		// response can be null when refreshing the quiz page immediately after
		// resetting a quiz, or resetting it in another window, and attempting
		// to reset a second time.
		if (!$this->credit->resettable || $this->response === null) {
			return;
		}

		$now = new SwatDate();
		$now->toUTC();

		$sql = sprintf(
			'update InquisitionResponse set
			reset_date = %s where id = %s',
			$this->app->db->quote($now->getDate(), 'date'),
			$this->app->db->quote($this->response->id, 'integer')
		);

		SwatDB::exec($this->app->db, $sql);
	}

	// }}}
	// {{{ protected function sendCompletionEmail()

	protected function sendCompletionEmail()
	{
		try {
			$message = new CMECreditCompleteMailMessage(
				$this->app,
				$this->app->session->account,
				$this->credit,
				$this->response
			);
			$message->send();
		} catch (SiteMailException $e) {
			$e->processAndContinue();
		}
	}

	// }}}
	// {{{ abstract protected function relocate()

	abstract protected function relocate(SwatForm $form);

	// }}}
	// {{{  protected function relocateToCertificate()

	 protected function relocateToCertificate()
	 {
		 $this->app->relocate($this->getCertificateURI());
	 }

	// }}}
	// {{{  protected function relocateToEvaluation()

	 protected function relocateToEvaluation()
	 {
		 $this->app->relocate($this->getEvaluationURI());
	 }

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		if ($this->isComplete()) {
			$this->buildQuizResponse();
		} else {

			ob_start();
			$this->displayQuizDetailsIncomplete();
			$content_block = $this->ui->getWidget('quiz_content');
			$content_block->content = ob_get_clean();
			$content_block->content_type = 'text/xml';

		}
	}

	// }}}
	// {{{ protected function buildQuizResponse()

	protected function buildQuizResponse()
	{
		// quiz description
		$description = $this->getQuizDescription();
		if ($description != '') {
			ob_start();
			echo '<div class="quiz-description">';
			echo $description;
			echo '</div>';
			$content_block = $this->ui->getWidget('quiz_response_description');
			$content_block->content = ob_get_clean();
			$content_block->content_type = 'text/xml';
		}

		// messages
		$this->buildQuizResponseMessages();

		// answers
		if ($this->credit->resettable && !$this->response->isPassed()) {
			$this->ui->getWidget('reset_form')->visible = true;
		} else {
			ob_start();
			$this->displayQuizResponse();
			$content_block = $this->ui->getWidget('quiz_response');
			$content_block->content = ob_get_clean();
			$content_block->content_type = 'text/xml';
		}

		$this->ui->getWidget('quiz_frame')->visible = false;
		$this->ui->getWidget('quiz_response_container')->visible = true;
	}

	// }}}
	// {{{ protected function buildQuizResponseMessages()

	protected function buildQuizResponseMessages()
	{
		$locale  = SwatI18NLocale::get();
		$correct = $this->response->getCorrectCount();
		$total   = count($this->quiz->question_bindings);
		$grade   = $this->response->getGrade();

		ob_start();

		// quiz grade
		echo '<p class="quiz-response-grade">';

		printf(
			CME::ngettext(
				'You got %s out of %s answer correct for a grade of %s%%.',
				'You got %s out of %s answers correct for a grade of %s%%.',
				$total
			),
			SwatString::minimizeEntities($locale->formatNumber($correct)),
			SwatString::minimizeEntities($locale->formatNumber($total)),
			SwatString::minimizeEntities(
				$locale->formatNumber(round($grade * 1000) / 10)
			)
		);

		echo '</p>';

		if (!$this->credit->resettable) {
			echo '<p class="quiz-response-status">';
			echo SwatString::minimizeEntities(
				CME::_(
					'Once you have taken the quiz, it may not be taken again.'
				)
			);
			echo '</p>';
		}

		if ($this->response->isPassed()) {

			$account = $this->app->session->account;
			if ($account->isEvaluationComplete($this->credit->front_matter)) {
				echo '<p>'
				echo SwatString::minimizeEntities(
					CME::_('You’ve already completed the evaluation.')
				);
				echo '</p>';

				$certificate_link = new SwatHtmlTag('a');
				$certificate_link->class = 'button';
				$certificate_link->href = $this->getCertificateURI();
				$certificate_link->setContent(CME::_('Print Certificate'));
				$certificate_link->display();
			} else {
				$evaluation_link = new SwatHtmlTag('a');
				$evaluation_link->class = 'button';
				$evaluation_link->href = $this->getEvaluationURI();
				$evaluation_link->setContent(CME::_('Complete Evaluation'));
				$evaluation_link->display();
			}

		} else {

			// quiz failed message
			$p_tag = new SwatHtmlTag('p');
			$p_tag->class = 'quiz-response-failed';
			$p_tag->setContent(
				sprintf(
					CME::_(
						'A grade of %s%% is required to qualify for CME '.
						'credits.'
					),
					$locale->formatNumber(
						$this->credit->passing_grade * 100
					)
				)
			);

			$p_tag->display();
		}

		$content_block = $this->ui->getWidget('quiz_response_description');
		$content_block->content.= ob_get_clean();
		$content_block->content_type = 'text/xml';
	}

	// }}}
	// {{{ protected function displayQuizDetailsIncomplete()

	protected function displayQuizDetailsIncomplete()
	{
		$locale = SwatI18NLocale::get();

		// quiz description
		$description = $this->getQuizDescription();
		if ($description != '') {
			echo '<div class="quiz-description">';
			echo $description;
			echo '</div>';
		}

		// passing grade
		echo '<div class="quiz-passing-grade">';

		$grade_span = new SwatHtmlTag('span');
		$grade_span->setContent(
			$locale->formatNumber($this->credit->passing_grade * 100).'%'
		);

		printf(
			CME::_(
				'A grade of %s is required',
				$grade_span
			)
		);

		echo '</div>';

		// number of questions and time estimate
		echo '<div class="quiz-intro-status">';

		$total_questions = count($this->quiz->question_bindings);

		if ($total_questions > 30) {
			$time_estimate = round($total_questions * 2 / 30) / 2;
			$time_estimate = sprintf(
				CME::ngettext(
					'one hour',
					'%s hours',
					$time_estimate
				),
				$locale->formatNumber($time_estimate)
			);
		} else {
			$time_estimate = ceil($total_questions * 2 / 10) * 10;
			$time_estimate = sprintf(
				CME::_('%s minutes'),
				$locale->formatNumber($time_estimate)
			);
		}

		printf(
			CME::_('%s questions, about %s.'),
			$locale->formatNumber($total_questions),
			$time_estimate
		);

		echo '</div>';
	}

	// }}}
	// {{{ protected function buildContent()

	protected function buildContent()
	{
		parent::buildContent();

		if (!$this->isComplete()) {
			$this->layout->startCapture('content');
			Swat::displayInlineJavaScript($this->getInlineJavaScript());
			$this->layout->endCapture();
		}
	}

	// }}}
	// {{{ protected function buildTitle()

	protected function buildTitle()
	{
		$this->layout->data->title = sprintf(
			CME::_('%s Quiz'),
			SwatString::minimizeEntities(
				$this->credit->front_matter->provider->title
			)
		);

		$this->layout->data->html_title = sprintf(
			CME::_('%s Quiz - %s'),
			$this->credit->front_matter->provider->title,
			$this->app->getHtmlTitle()
		);
	}

	// }}}
	// {{{ protected function getQuizDescription()

	protected function getQuizDescription()
	{
		return '';
	}

	// }}}
	// {{{ protected function getInlineJavaScript()

	protected function getInlineJavaScript()
	{
		static $shown = false;

		if (!$shown) {
			$javascript = $this->getInlineJavaScriptTranslations();
			$shown = true;
		} else {
			$javascript = '';
		}

		$quiz_uri = $this->app->getBaseHref(true).$this->source;
		$response_server = $quiz_uri.'/response';

		// get current question (first unanswered after last answered)
		$count = 0;
		$current_question = 0;
		foreach ($this->quiz->question_bindings as $question_binding) {
			$binding_id = $question_binding->id;

			if (isset($this->response_values_by_binding_id[$binding_id])) {
				$current_question = $count + 1;
			}
			$count++;
		}

		// limit current question in case last question is present in the
		// response
		$current_question = min(
			$current_question,
			count($this->quiz->question_bindings) - 1
		);

		$javascript.= sprintf(
			"var quiz_page = new %s('quiz_container', %s, %s);\n",
			$this->getJavaScriptClassName(),
			SwatString::quoteJavaScriptString($response_server),
			$current_question
		);

		return $javascript;
	}

	// }}}
	// {{{ protected function getInlineJavaScriptTranslations()

	protected function getInlineJavaScriptTranslations()
	{
		$strings = array(
			'start_text'                  => CME::_('Start Quiz'),
			'continue_text'               => CME::_('Continue Quiz'),
			'next_text'                   => CME::_('Next Question'),
			'previous_text'               => CME::_('Previous Question'),
			'quiz_status_text'            => CME::_('Question %s of %s'),
			'submit_text'                 => CME::_('Submit Quiz'),
			'review_text'                 => CME::_('Review Answers'),
			'intro_text'                  => CME::_('Return to Introduction'),
			'close_text'                  => CME::_('Close'),
			'question_title_text'         => CME::_('Question %s'),
			'change_text'                 => CME::_('Change'),
			'answer_text'                 => CME::_('Answer'),
			'intro_status_review_text'    => CME::_(
				'%s of %s questions completed'
			),
			'intro_status_start_text'     => CME::_('%s questions, about %s'),
			'intro_status_continue_text'  => CME::_(
				'%s of %s questions completed, about %s remaining'
			),
			'review_status_text_0'        => CME::_(
				'All questions are answered.'
			),
			'review_status_text_1'        => CME::_('%s is not answered'),
			'review_status_text_2_to_5'   => CME::_('%s are unanswered.'),
			'review_status_text_many'     => CME::_(
				'%s questions are unanswered.'
			),
			'review_status_required_text' => CME::_(
				'All questions must be answered before the quiz can be '.
				'submitted.'
			),
			'time_hours_text_1'           => CME::_('one hour'),
			'time_hours_text_many'        => CME::_('%s hours'),
			'time_minutes_text_many'      => CME::_('%s minutes'),
		);

		$javascript = '';
		foreach ($strings as $key => $text) {
			$javscript.= sprintf(
				"CMEQuizPage.%s = %s;\n",
				$key,
				SwatString::quoteJavaScriptString($text)
			);
		}
		return $javascript;
	}

	// }}}
	// {{{ protected function getJavaScriptClassName()

	protected function getJavaScriptClassName()
	{
		return 'CMEQuizPage';
	}

	// }}}
	// {{{ protected function load()

	protected function load(SwatForm $form)
	{
	}

	// }}}
	// {{{ protected function displayQuizResponse()

	protected function displayQuizResponse()
	{
		// build lookup array for response values
		$response_values = array();
		foreach ($this->response->values as $value) {
			$response_values[
				$value->getInternalValue('question_binding')
			] = $value;
		}

		echo '<ol class="quiz-response">';

		foreach ($this->quiz->question_bindings as $question_binding) {
			$question = $question_binding->question;

			$correct_option_id = $question->getInternalValue('correct_option');
			$response_option_id = null;
			$correct = false;

			// if there's a response, check it
			if (isset($response_values[$question_binding->id])) {
				$response_value = $response_values[$question_binding->id];
				$response_option_id =
					$response_value->getInternalValue('question_option');

				$correct = ($correct_option_id === $response_option_id);
			}

			$question_li = new SwatHtmlTag('li');
			$question_li->class = 'quiz-question';
			if ($correct) {
				$question_li->class.= ' quiz-question-correct';
			} else {
				$question_li->class.= ' quiz-question-incorrect';
			}
			$question_li->open();

			echo $question->bodytext;

			echo '<span class="quiz-response-question-icon"></span>';

			echo '<dl class="quiz-question-options">';

			$option = $question->correct_option;

			// your option
			if ($option !== null && $response_option_id !== null) {
				$dt_tag = new SwatHtmlTag('dt');
				$dt_tag->setContent(CME::_('Your Answer:'));
				$dt_tag->display();
				$response_option = $question->options[$response_option_id];
				$dd_tag = new SwatHtmlTag('dd');
				if ($option->id !== $response_option_id) {
					$dd_tag->class =
						'quiz-question-option quiz-question-option-incorrect';
				} else {
					$dd_tag->class =
						'quiz-question-option';
				}

				$dd_tag->setContent($response_option->title);
				$dd_tag->display();
			}

			// correct option (shown if your option is wrong)
			if ($option !== null && $option->id !== $response_option_id) {
				$dt_tag = new SwatHtmlTag('dt');
				$dt_tag->setContent(CME::_('Correct Answer:'))
				$dt_tag->display();
				$dd_tag = new SwatHtmlTag('dd');
				$dd_tag->class =
					'quiz-question-option quiz-question-option-correct';

				$dd_tag->setContent($option->title);
				$dd_tag->display();
			}

			echo '</dl>';

			$question_li->close();
		}

		echo '</ol>';
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();

		$yui = new SwatYUI(array(
			'dom',
			'event',
			'connection',
			'json',
			'animation',
		));
		$this->layout->addHtmlHeadEntrySet($yui->getHtmlHeadEntrySet());
		$this->layout->addHtmlHeadEntry(
			'packages/swat/javascript/swat-z-index-manager.js'
		);
		$this->layout->addHtmlHeadEntry(
			'packages/cme/javascript/cme-quiz-page.js'
		);

		if ($this->response_message_display !== null) {
			$this->layout->addHtmlHeadEntrySet(
				$this->response_message_display->getHtmlHeadEntrySet());
		}
	}

	// }}}
}

?>
