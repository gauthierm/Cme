<?php

require_once 'SwatDB/SwatDB.php';
require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Swat/SwatDate.php';
require_once 'Swat/SwatTableStore.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'Admin/pages/AdminIndex.php';
require_once 'Admin/AdminTitleLinkCellRenderer.php';
require_once 'CME/CME.php';
require_once 'CME/dataobjects/CMEProviderWrapper.php';
require_once 'CME/dataobjects/CMEEvaluationReportWrapper.php';

/**
 * @package   CME
 * @copyright 2011-2014 silverorange
 * @license   http://www.opensource.org/licenses/mit-license.html MIT License
 */
class CMEEvaluationReportIndex extends AdminIndex
{
	// {{{ protected properties

	/**
	 * @var array
	 */
	protected $reports_by_quarter = array();

	/**
	 * @var CMEProviderWrapper
	 */
	protected $providers;

	/**
	 * @var SwatDate
	 */
	protected $start_date;

	// }}}
	// {{{ protected function getUiXml()

	protected function getUiXml()
	{
		return 'CME/admin/components/EvaluationReport/index.xml';
	}

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML($this->getUiXml());

		$this->initStartDate();
		$this->initProviders();
		$this->initReportsByQuarter();
		$this->initTableViewColumns();
	}

	// }}}
	// {{{ protected function initStartDate()

	protected function initStartDate()
	{
		$oldest_date_string = SwatDB::queryOne(
			$this->app->db,
			'select min(complete_date) from InquisitionResponse
			where complete_date is not null
				and inquisition in (select evaluation from CMECredit)'
		);

		$this->start_date = new SwatDate($oldest_date_string);
	}

	// }}}
	// {{{ protected function initProviders()

	protected function initProviders()
	{
		$this->providers = SwatDB::query(
			$this->app->db,
			'select * from CMEProvider order by title, id',
			SwatDBClassMap::get('CMEProviderWrapper')
		);
	}

	// }}}
	// {{{ protected function initReportsByQuarter()

	protected function initReportsByQuarter()
	{
		$sql = 'select * from EvaluationReport order by quarter';
		$reports = SwatDB::query(
			$this->app->db,
			$sql,
			SwatDBClassMap::get('CMEEvaluationReportWrapper')
		);

		$reports->attachSubDataObjects(
			'provider',
			$this->providers
		);

		foreach ($reports as $report) {
			$quarter = clone $report->quarter;
			$quarter->convertTZ($this->app->default_time_zone);
			$quarter = $quarter->formatLikeIntl('yyyy-qq');
			$provider = $report->provider->shortname;
			if (!isset($this->reports_by_quarter[$quarter])) {
				$this->reports_by_quarter[$quarter] = array();
			}
			$this->reports_by_quarter[$quarter][$provider] = $report;
		}
	}

	// }}}
	// {{{ protected function initTableViewColumns()

	protected function initTableViewColumns()
	{
		$view = $this->ui->getWidget('index_view');
		foreach ($this->providers as $provider) {
			$renderer = new AdminTitleLinkCellRenderer();
			$renderer->link = sprintf(
				'EvaluationReport/Download?type=%s&quarter=%%s',
				$provider->shortname
			);
			$renderer->stock_id = 'download';
			$renderer->text = $provider->title;

			$column = new SwatTableViewColumn();
			$column->id = 'provider_'.$provider->shortname;
			$column->addRenderer($renderer);
			$column->addMappingToRenderer(
				$renderer,
				'quarter',
				'link_value'
			);

			$column->addMappingToRenderer(
				$renderer,
				'is_'.$provider->shortname.'_sensitive',
				'sensitive'
			);

			$view->appendColumn($column);
		}
	}

	// }}}

	// build phase
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		$now = new SwatDate();
		$now->convertTZ($this->app->default_time_zone);

		$year = $this->start_date->getYear();

		$start_date = new SwatDate();
		$start_date->setTime(0, 0, 0);
		$start_date->setDate($year, 1, 1);
		$start_date->setTZ($this->app->default_time_zone);

		$end_date = clone $start_date;
		$end_date->addMonths(3);

		$display_end_date = clone $end_date;
		$display_end_date->subtractMonths(1);

		$store = new SwatTableStore();

		while ($end_date->before($now)) {
			for ($i = 1; $i <= 4; $i++) {

				$ds = new SwatDetailsStore();

				$quarter = $start_date->formatLikeIntl('yyyy-qq');

				$ds->date    = clone $start_date;
				$ds->year    = $year;
				$ds->quarter = $quarter;

				$ds->quarter_title = sprintf(
					CME::_('Q%s - %s to %s'),
					$i,
					$start_date->formatLikeIntl('MMMM yyyy'),
					$display_end_date->formatLikeIntl('MMMM yyyy')
				);

				foreach ($this->providers as $provider) {
					$ds->{'is_'.$provider->shortname.'_sensitive'} =
						(isset($this->reports_by_quarter[$quarter][$provider->shortname]));
				}

				$store->add($ds);

				$start_date->addMonths(3);
				$end_date->addMonths(3);
				$display_end_date->addMonths(3);
			}

			$year++;
		}

		return $store;
	}

	// }}}
}

?>
