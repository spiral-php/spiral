<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Components\DBAL\Builders\Common;

use Spiral\Components\Cache\StoreInterface;
use Spiral\Components\DBAL\DBALException;
use Spiral\Components\DBAL\QueryBuilder;
use Spiral\Components\DBAL\QueryCompiler;
use Spiral\Components\DBAL\QueryResult;
use Spiral\Support\Pagination\PaginableInterface;
use Spiral\Support\Pagination\PaginatorTrait;

/**
 * BasicSelectQuery provides basic functionality for any select query without query building,
 * unions and ability to specify source tables. This class used as parent for DBAL\SelectQuery and
 * for ORM\Selector.
 *
 * @method int avg($identifier) Perform aggregation based on column or expression value.
 * @method int min($identifier) Perform aggregation based on column or expression value.
 * @method int max($identifier) Perform aggregation based on column or expression value.
 * @method int sum($identifier) Perform aggregation based on column or expression value.
 */
abstract class AbstractSelectQuery extends QueryBuilder implements
    \IteratorAggregate,
    \JsonSerializable,
    PaginableInterface
{
    /**
     * Select builder uses where, join traits and can be paginated.
     */
    use WhereTrait, JoinTrait, HavingTrait, PaginatorTrait;

    /**
     * Flag to indicate that query is distinct.
     *
     * @var bool
     */
    protected $distinct = false;

    /**
     * Columns or expressions to be fetched from database, can include aliases (AS).
     *
     * @var array
     */
    protected $columns = ['*'];

    /**
     * Array of columns or/and expressions to be used to generate ORDER BY statement. Every orderBy
     * token should include correct identifier (or expression) and sorting direction (ASC, DESC).
     *
     * @var array
     */
    protected $orderBy = [];

    /**
     * Column names or expressions to group by.
     *
     * @var array
     */
    protected $groupBy = [];

    /**
     * Cache lifetime. Can be set at any moment and will change behaviour os run() method, if set -
     * query will be performed using Database->cached() function.
     *
     * @var int
     */
    protected $cacheLifetime = 0;

    /**
     * Cache key to be used. Empty if DBAL should generate key automatically.
     *
     * @var string
     */
    protected $cacheKey = '';

    /**
     * Cache store to be used for caching. Default cache store will be used if nothing was specified.
     *
     * @var StoreInterface
     */
    protected $cacheStore = null;

    /**
     * Specify that query result should be cached for specified amount of seconds. Attention, this
     * method will apply caching to every result generated by SelectBuilder including count() and
     * aggregation methods().
     *
     * @param int            $lifetime Cache lifetime in seconds.
     * @param string         $key      Cache key to be used, if none provided spiral will generate
     *                                 key based on generated SQL.
     * @param StoreInterface $store    Cache store to be used, default store will be used if nothing
     *                                 was specified.
     * @return static
     */
    public function cache($lifetime, $key = '', StoreInterface $store = null)
    {
        $this->cacheLifetime = $lifetime;
        $this->cacheKey = $key;
        $this->cacheStore = $store;

        return $this;
    }

    /**
     * Set distinct flag to true/false. Applying distinct to select query will return only unique
     * records from database.
     *
     * @param bool $distinct
     * @return static
     */
    public function distinct($distinct = true)
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * Specify grouping identifier or expression for select query.
     *
     * @param string $identifier
     * @return static
     */
    public function groupBy($identifier)
    {
        $this->groupBy[] = $identifier;

        return $this;
    }

    /**
     * Add results ordering. Order should be specified by identifier or expression and sorting direction.
     * Multiple orderBy() methods can be applied to one query. In case of unions order by will be
     * applied to united result.
     *
     * Method can accept array parameters:
     * $select->orderBy([
     *      'id'   => 'DESC',
     *      'name' => 'ASC'
     * ]);
     *
     * @param string|array $identifier Column or expression of SqlFragment.
     * @param string       $direction  Sorting direction, ASC|DESC.
     * @return static
     */
    public function orderBy($identifier, $direction = 'ASC')
    {
        if (is_array($identifier))
        {
            foreach ($identifier as $expression => $direction)
            {
                $this->orderBy[] = [$expression, $direction];
            }
        }
        else
        {
            $this->orderBy[] = [$identifier, $direction];
        }

        return $this;
    }

    /**
     * Get ordered list of builder parameters.
     *
     * @param QueryCompiler $compiler
     * @return array
     */
    public function getParameters(QueryCompiler $compiler = null)
    {
        $compiler = !empty($compiler) ? $compiler : $this->compiler;

        return $this->expandParameters($compiler->prepareParameters(
            QueryCompiler::SELECT_QUERY,
            $this->whereParameters,
            $this->onParameters,
            $this->havingParameters
        ));
    }

    /**
     * Counts the number of results for this query. Limit and offset values will be ignored. Attention,
     * method results will be cached (if requested), which means that attached paginator can work
     * incorrectly. Attention, you can't really use count() methods with united queries (at least
     * without tweaking every united query).
     *
     * Attention: count() method can and will return wrong results if you trying to count complex
     * sql query with joins and etc.
     *
     * @param string $column
     * @return int
     */
    public function count($column = '*')
    {
        $backup = [$this->columns, $this->orderBy, $this->groupBy, $this->limit, $this->offset];
        $this->columns = ["COUNT({$column})"];

        //Can not be used with COUNT()
        $this->orderBy = $this->groupBy = [];
        $this->limit = $this->offset = 0;

        $result = $this->run(false)->fetchColumn();
        list($this->columns, $this->orderBy, $this->groupBy, $this->limit, $this->offset) = $backup;

        return (int)$result;
    }

    /**
     * Perform one of SELECT aggregation methods. Supported methods: AVG, MIN, MAX, SUM. Attention,
     * you can't use aggregation methods with united queries without explicitly specifying aggregation
     * as column in every nested query.
     *
     * @param string $method
     * @param array  $arguments
     * @return int
     * @throws DBALException
     */
    public function __call($method, $arguments)
    {
        $columns = $this->columns;

        if (!in_array($method = strtoupper($method), ['AVG', 'MIN', 'MAX', 'SUM']))
        {
            throw new DBALException("Unknown aggregation method '{$method}'.");
        }

        if (!isset($arguments[0]) || count($arguments) > 1)
        {
            throw new DBALException("Aggregation methods can support exactly one column.");
        }

        $this->columns = ["{$method}({$arguments[0]})"];

        $result = $this->run(false)->fetchColumn();
        $this->columns = $columns;

        return (int)$result;
    }

    /**
     * Run QueryBuilder statement against parent database. Method will be overloaded by child builder
     * to return correct value.
     *
     * @param bool $paginate True is pagination should be applied.
     * @return QueryResult
     */
    public function run($paginate = true)
    {
        $backup = [$this->limit, $this->offset];

        if ($paginate)
        {
            $this->doPagination();
        }
        else
        {
            //We have to flush limit and offset values when pagination is not required.
            $this->limit = $this->offset = 0;
        }

        if (!empty($this->cacheLifetime))
        {
            $result = $this->database->cached(
                $this->cacheLifetime,
                $this->sqlStatement(),
                $this->getParameters(),
                $this->cacheKey,
                $this->cacheStore
            );
        }
        else
        {
            $result = $this->database->query($this->sqlStatement(), $this->getParameters());
        }

        //Restoring limit and offset values
        list($this->limit, $this->offset) = $backup;

        return $result;
    }

    /**
     * Retrieve an external iterator, SelectBuilder will return QueryResult as iterator.
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return QueryResult
     */
    public function getIterator()
    {
        return $this->run();
    }

    /**
     * Iterate thought result chunks defined by limit value.
     *
     * Example:
     * $select->chunked(100, function(QueryResult $result, $count)
     * {
     *      dump($result);
     * });
     *
     * Return false from inner function to stop chunking.
     *
     * @param int      $limit
     * @param callable $callback
     */
    public function chunked($limit, callable $callback)
    {
        //Count items
        $count = $this->count();
        $offset = 0;

        $this->limit($limit);
        while ($offset + $limit <= $count)
        {
            $result = call_user_func(
                $callback,
                $this->offset($offset)->getIterator(),
                $offset,
                $count
            );

            if ($result === false)
            {
                //Stop iteration
                return;
            }

            $offset += $limit;
        }
    }

    /**
     * (PHP 5 > 5.4.0)
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->getIterator()->jsonSerialize();
    }
}