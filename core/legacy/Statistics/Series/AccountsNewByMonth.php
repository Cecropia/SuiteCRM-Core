<?php

namespace App\Legacy\Statistics\Series;

use App\Entity\Statistic;
use App\Legacy\Data\ListDataQueryHandler;
use App\Legacy\LegacyHandler;
use App\Legacy\LegacyScopeState;
use App\Legacy\Statistics\StatisticsHandlingTrait;
use App\Model\Statistics\ChartOptions;
use App\Service\ModuleNameMapperInterface;
use App\Service\StatisticsProviderInterface;
use BeanFactory;
use SugarBean;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AccountsNewByMonth extends LegacyHandler implements StatisticsProviderInterface
{
    use StatisticsHandlingTrait;

    public const KEY = 'accounts-new-by-month';

    /**
     * @var ListDataQueryHandler
     */
    private $queryHandler;

    /**
     * @var ModuleNameMapperInterface
     */
    private $moduleNameMapper;

    /**
     * LeadDaysOpen constructor.
     * @param string $projectDir
     * @param string $legacyDir
     * @param string $legacySessionName
     * @param string $defaultSessionName
     * @param LegacyScopeState $legacyScopeState
     * @param ListDataQueryHandler $queryHandler
     * @param ModuleNameMapperInterface $moduleNameMapper
     * @param SessionInterface $session
     */
    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        ListDataQueryHandler $queryHandler,
        ModuleNameMapperInterface $moduleNameMapper,
        SessionInterface $session
    ) {
        parent::__construct($projectDir, $legacyDir, $legacySessionName, $defaultSessionName, $legacyScopeState, $session);
        $this->queryHandler = $queryHandler;
        $this->moduleNameMapper = $moduleNameMapper;
    }

    /**
     * @inheritDoc
     */
    public function getHandlerKey(): string
    {
        return $this->getKey();
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function getData(array $query): Statistic
    {
        [$module, $id, $criteria, $sort] = $this->extractContext($query);

        if (empty($module) || $module !== 'accounts') {
            return $this->getEmptySeriesResponse(self::KEY);
        }

        $this->init();
        $this->startLegacyApp();

        $legacyName = $this->moduleNameMapper->toLegacy($module);

        $bean = $this->getBean($legacyName);

        if (!$bean instanceof SugarBean) {
            return $this->getEmptySeriesResponse(self::KEY);
        }

        $query = $this->queryHandler->getQuery($bean, $criteria, $sort);
        $query = $this->generateQuery($query);

        $result = $this->runQuery($query, $bean);

        $nameField = 'month';
        $valueField = 'value';
        $groupingFields = 'name';
        $months = $this->getMonths();

        $series = $this->buildMultiSeries($result, $groupingFields, $nameField, $valueField, $months);

        $chartOptions = new ChartOptions();
        $chartOptions->yAxisTickFormatting = true;
        $chartOptions->xAxisTicks = $months;

        $statistic = $this->buildSeriesResponse(self::KEY, 'int', $series, $chartOptions);

        $this->close();

        return $statistic;
    }

    /**
     * @param string $legacyName
     * @return bool|SugarBean
     */
    protected function getBean(string $legacyName)
    {
        return BeanFactory::newBean($legacyName);
    }

    /**
     * @return array
     */
    protected function getMonths(): array
    {
        return [1,2,3,4,5,6,7,8,9,10,11,12];
    }

    /**
     * @param array $query
     * @param $bean
     * @return array
     */
    protected function runQuery(array $query, $bean): array
    {
        // send limit -2 to not add a limit
        return $this->queryHandler->runQuery($bean, $query);
    }

    /**
     * @param array $query
     * @return array
     */
    protected function generateQuery(array $query): array
    {
        $query['select'] = 'SELECT COUNT(accounts.name) as value, EXTRACT(MONTH FROM accounts.date_entered) as month, accounts.account_type as name';
        $query['where'] .= ' AND accounts.account_type is not null ';
        $query['order_by'] = '';
        $query['group_by'] = ' GROUP BY EXTRACT(MONTH FROM accounts.date_entered), accounts.account_type';

        return $query;
    }
}
