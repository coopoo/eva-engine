<?php
/**
 * EvaEngine
 *
 * @link      https://github.com/AlloVince/eva-engine
 * @copyright Copyright (c) 2012 AlloVince (http://avnpc.com/)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Eva_Api.php
 * @author    AlloVince
 */

namespace Eva\Mvc\Item;


use Eva\Mvc\Model\AbstractModelService,
    Eva\Paginator\Paginator,
    Zend\Mvc\Exception,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface,
    Zend\Stdlib\Hydrator\ClassMethods;
use ArrayObject;
use Zend\Stdlib\Hydrator\ArraySerializable;
use Zend\Stdlib\Hydrator\HydratorInterface;
use ArrayIterator;
use ArrayAccess;
use Iterator;

/**
 * Mvc Abstract Model for item / itemlist / paginator
 *
 * @category   Eva
 * @package    Eva_Mvc
 * @subpackage Model
 * @copyright  Copyright (c) 2012 AlloVince (http://avnpc.com/)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class AbstractItem implements ArrayAccess, Iterator, ServiceLocatorAwareInterface
{

    /**
     * @var null|int
     */
    protected $count = null;


    /**
     * @var Eva\Mvc\Model\AbstractModelService
     */
    protected $model;

    /**
     * @var Iterator|IteratorAggregate
     */
    protected $dataSource = null;

    /**
     * @var DbTable | Webservice
     */
    protected $dataSourceType = 'DbTable';

    protected $dataSourceClass = '';

    protected $relationships = array();

    protected $initialized = false;

    /**
     * @var HydratorInterface
     */
    protected $hydrator = null;

    /**
     * @var null
     */
    protected $objectPrototype = null;

    /**
    * @var ServiceLocatorInterface
    */
    protected $serviceLocator;

    protected $paginator;

    /**
    * Set the service locator.
    *
    * @param ServiceLocatorInterface $serviceLocator
    * @return AbstractHelper
    */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }


    /**
     * Set the row object prototype
     *
     * @param  object $objectPrototype
     * @return ResultSet
     */
    public function setObjectPrototype($objectPrototype)
    {
        if (!is_object($objectPrototype)) {
            throw new Exception\InvalidArgumentException(
                'An object must be set as the object prototype, a ' . gettype($objectPrototype) . ' was provided.'
            );
        }
        $this->objectPrototype = $objectPrototype;
        return $this;
    }

    /**
     * Set the hydrator to use for each row object
     *
     * @param HydratorInterface $hydrator
     * @return HydratingResultSet
     */
    public function setHydrator(HydratorInterface $hydrator)
    {
        $this->hydrator = $hydrator;
        return $this;
    }

    /**
     * Get the hydrator to use for each row object
     *
     * @return HydratorInterface
     */
    public function getHydrator()
    {
        return $this->hydrator;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setModel($model)
    {
        if(!$model instanceof AbstractModelService){
            throw new Exception\MissingLocatorException(printf('Model Service Locator not set by class %s',
            get_class($this)));
        }
        $this->model = $model;
        $this->initialize();
        return $this;
    }

    public function dbTable()
    {
        $tableClassName = $this->dataSourceClass;
        $serviceManager = $this->getServiceLocator();
        if($serviceManager->has($tableClassName)){
            return $serviceManager->get($tableClassName);
        }

        $serviceManager->setFactory($tableClassName, function(ServiceLocatorInterface $serviceLocator) use ($tableClassName){
            return new $tableClassName($serviceLocator->get('Zend\Db\Adapter\Adapter'));
        });

        return $serviceManager->get($tableClassName);
    }

    public function webService()
    {
    
    }

    public function getDataClass()
    {
        if($this->dataSourceType == 'WebService'){
            return $this->getWebService();
        }

        return $this->dbTable();
    }

    /**
     * Get the data source used to create the result set
     *
     * @return null|Iterator
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    public function mergeDataSource(array $dataSource)
    {
        foreach($dataSource as $key => $value){
            if($value !== null){
                $this->dataSource[$key] = $value;
            }
        }

        return $this;
    }

    public function setDataSource($dataSource)
    {
        if (is_array($dataSource)) {
            // its safe to get numbers from an array
            $first = current($dataSource);
            reset($dataSource);
            $this->count = count($dataSource);
            $this->dataSource = new ArrayIterator($dataSource);
        } elseif ($dataSource instanceof IteratorAggregate) {
            $this->dataSource = $dataSource->getIterator();
        } elseif ($dataSource instanceof Iterator) {
            $this->dataSource = $dataSource;
        } else {
            throw new Exception\InvalidArgumentException('DataSource provided is not an array, nor does it implement Iterator or IteratorAggregate');
        }
        return $this;
    }

    public function hasRelationships()
    {
        $hasRelationships = false;
        foreach($this->relationships as $key => $relationship){
            if(isset($relationship['dataSource']) && $relationship['dataSource']){
                $hasRelationships = true;
                break;
            }
        }
        return $hasRelationships;
    }

    public function getRelationships()
    {
        $relationships = new ArrayObject();

        $model = $this->getModel();
        foreach($this->relationships as $key => $relationship){
            if(isset($relationship['dataSource']) && $relationship['dataSource'] && $relationship['targetEntity']){
                $relItem = $this->join($key);
                $relItem->mergeDataSource($relationship['dataSource']);
                $relationships[$key] = $relItem;

                /*
                $relItem = $model->getItem($relationship['targetEntity']); 
                $relItem->setDataSource($relationship['dataSource']);
                $relationships[$key] = $relItem;
                */
            }
        }
        return $relationships;
    }

    public function addRelationship($key, array $relationship)
    {
    
    }

    public function removeRelationship($key)
    {
    
    }


    /**
    * Cast result set to array of arrays
    *
    * @return array
    * @throws Exception\RuntimeException if any row is not castable to an array
    */
    public function toArray(array $map = array())
    {
        if(0 === count($this->dataSource)){
            return array();
        }

        if(isset($this->dataSource[0])){
            foreach($this->dataSource as $key => $subDataSource){
                if(method_exists($subDataSource, 'toArray')){
                    $this->dataSource[$key] = $subDataSource->toArray($map);
                }
            }
            return $this->singleToArray(array());
        } else {
            return $this->singleToArray($map);
        }
    }

    protected function singleToArray($map)
    {
        if($map){
            if(is_array(current($map))){
                $self = isset($map['self']) ? $map['self'] : array();
                $join = isset($map['join']) ? $map['join'] : array();
                $proxy = isset($map['proxy']) ? $map['proxy'] : array();
                $return = $this->dump($self, $join, $proxy);
            } else {
                $this->self($map);
            }
        }

        $return = (array) $this->dataSource;
        return $return;
    }

    protected function dump(array $self, array $join, array $proxy)
    {
        $self = $this->self($self);

        //If item complete empty will not join
        if(0 === count($self->dataSource)){
            return $self;
        }

        foreach($join as $key => $map){
            if(isset($this->relationships[$key])){
                if(isset($this->relationships[$key]['mappedBy'])){
                    $mapKey = $this->relationships[$key]['mappedBy'];
                    $self->$mapKey = $this->join($key)->toArray($map);
                } else {
                    $self->$key = $this->join($key)->toArray($map);
                }
            }
        }

        return $self;
    }


    public function collections(array $params)
    {
        $dataClass = $this->getDataClass();
        if($params && method_exists($dataClass, 'setParameters')){
            $params = new \Zend\Stdlib\Parameters($params);
            $dataClass->setParameters($params);
        }
        $items = $dataClass->find('all');
        foreach($items as $key => $dataSource){
            $item = clone $this;
            $item->setDataSource((array) $dataSource);
            $this->dataSource[] = $item;
        }
        return $this;
    }


    public function self(array $map = array())
    {
        $columns = array();
        $functions = array();
        $selectAll = false;

        if(!$map){
            return $this;
        }

        if($map && in_array('*', $map)){
            $selectAll = true;
            if($map) {
                unset($map[array_search('*', $map)]);
            }
        }

        foreach($map as $key => $value){
            if(false === strrpos($value, '()')){
                $columns[] = $value;
            } else {
                $functions[] = str_replace('()', '', $value);
            }
        } 

        $dataSource = array();
        if(true === $selectAll || $columns){
            $dataClass = $this->getDataClass();
            if(false === $selectAll){
                $dataClass->columns($columns);
            }
            $where = $this->getPrimaryArray();
            $dataSource = $dataClass->where($where)->find('one');

            //Not find in DB
            if(!$dataSource){
                $this->setDataSource(array());
                return $this;
            }
        }

        //Auto complete
        if($functions){
            foreach($functions as $key => $function){
                if(true === method_exists($this, $function)){
                    $this->$function();
                }
            }
        }

        //Merge to original DataSource
        $originalDataSource = $this->getDataSource();
        if($dataSource){
            foreach($dataSource as $key => $value){
                if($value !== null){
                    $originalDataSource[$key] = $value;            
                }
            }
        }
        $dataSource = $originalDataSource;

        if(!$dataSource){
            $this->setDataSource(array());
        } else {
            $this->setDataSource((array) $dataSource);
        }
        return $this;
    }



    public function join($key)
    { 
        $model = $this->getModel();
        if(!isset($this->relationships[$key]) || !$this->relationships[$key]){
            return new ArrayAccess();
        }

        $relationship = $this->relationships[$key];

        //Important : here must use clone to create many entities
        $relItem = clone $model->getItem($relationship['targetEntity']); 
        //Important : Joined item should have no dataSource
        $relItem->setDataSource(array());

        $joinFuncName = 'join' . ucfirst($key);
        if(method_exists($this, $joinFuncName)){
            $this->$joinFuncName($relItem);
        } else {
            $joinFuncName = 'join' . $relationship['relationship'];

            if(!method_exists($this, $joinFuncName)){
                throw new Exception\InvalidArgumentException(printf(
                    'Undefined relationship when join %s in class %s',
                    $key,
                    get_class($this)
                ));
            }
            $this->$joinFuncName($key, $relItem, $relationship);
        }
        return $relItem;
    }

    protected function joinOneToOne($key, $relItem, $relationship)
    {
        $joinColumn = $relationship['joinColumn'];
        $referencedColumn = $relationship['referencedColumn'];
        $relItem->$joinColumn = $this->$referencedColumn;
        //p(sprintf('joinOneToOne Joined Class %s : joinColumn %s => %s joined %s => %s', get_class($relItem), $joinColumn, $relItem->$joinColumn , $referencedColumn, $this->$referencedColumn));
        return $this;
    }

    protected function joinOneToMany($key, $relItem, $relationship)
    {
        $joinColumn = $relationship['joinColumn'];
        $referencedColumn = $relationship['referencedColumn'];
        $params = array(
            $joinColumn => $this->$referencedColumn,
        );

        if(isset($relationship['joinParameters']) && is_array($relationship['joinParameters'])){
            $params = array_merge($params, $relationship['joinParameters']);
        }
        //p(sprintf('joinOneToMany Joined Class %s : joinColumn %s => %s joined %s => %s', get_class($relItem), $joinColumn, $relItem->$joinColumn , $referencedColumn, $this->$referencedColumn));
        return $relItem->collections($params);
    }

    protected function joinManyToOne($key, $relItem, $relationship)
    {
        $joinColumn = $relationship['joinColumn'];
        $referencedColumn = $relationship['referencedColumn'];
        $relItem->$joinColumn = $this->$referencedColumn;
        //p(sprintf('joinManyToOne Joined Class %s : joinColumn %s => %s joined %s => %s', get_class($relItem), $joinColumn, $relItem->$joinColumn , $referencedColumn, $this->$referencedColumn));
        return $this;
    }

    protected function joinManyToMany($key, $relItem, $relationship)
    {
        //p(@sprintf('joinManyToMany Joined Class %s : joinColumn %s => %s joined %s => %s', get_class($relItem), $joinColumn, $relItem->$joinColumn , $referencedColumn, $this->$referencedColumn));
    }

    public function proxy()
    {
        //call proxyRelationship;
    }

    public function create()
    {
        $dataClass = $this->getDataClass();
        $data = $this->toArray(
            isset($this->map['create']) ? $this->map['create'] : array()
        );
        $primaryKey = $dataClass->getPrimaryKey();
        if($dataClass->create($data)){
            $this->$primaryKey = $dataClass->getLastInsertValue();
        }
        return $this->$primaryKey;
    }

    public function save()
    {
        $dataClass = $this->getDataClass();
        $data = $this->toArray(
            isset($this->map['save']) ? $this->map['save'] : array()
        );
        $where = $this->getPrimaryArray();
        $dataClass->where($where)->save($data);
        return true;
    }

    public function remove()
    {
        $dataClass = $this->getDataClass();
        $where = $this->getPrimaryArray();
        $dataClass->where($where)->remove();
        return true;
    }

    protected function getPrimaryArray()
    {
        $dataClass = $this->getDataClass();
        $primaryKey = $dataClass->getPrimaryKey();
        if(is_string($primaryKey)){
            if(!$this->$primaryKey){
                throw new Exception\InvalidArgumentException(sprintf('Primary Key not set in item %s', get_class($this)));
            }
            $where = array($primaryKey => $this->$primaryKey);
        } elseif(is_array($primaryKey)) {
            $where = array();
            foreach($primaryKey as $key){
                if(!$this->$key){
                    throw new Exception\InvalidArgumentException(sprintf('Primary Key not set in item %s', get_class($this)));
                }
                $where[$key] = $this->$key;
            }
        } else {
            throw new Exception\InvalidArgumentException(sprintf('Primary Key not found or not correct in class %s', get_class($dataClass)));
        }
        return $where;
    }

    public function __get($name) 
    {
        if(isset($this->dataSource[$name])){
            return $this->dataSource[$name];
        }
        return null;
    }

    public function __set($name, $value)
    {
        $this->dataSource[$name] = $value;
        return $this;
    }


    public function initialize()
    {
        if(true === $this->initialized){
            return $this;
        }

        $dataSource = $this->dataSource;

        //Auto set datasource from model if they are connected
        if(!$dataSource && $this->model && $this->model->getItemClass() == get_class($this)){
            $dataSource = $this->model->getDataSource();
        }

        if($dataSource){
            foreach($dataSource as $key => $data){
                if(is_array($data)){
                    $this->relationships[$key]['dataSource'] = $data;
                    unset($dataSource[$key]);
                }
            }
        }

        if(!$dataSource){
            $dataSource = array();
        }

        $this->setDataSource($dataSource);

        $this->initialized = true;
        return $this;
    }


    public function setPaginator($paginator)
    {
        $this->paginator = $paginator;
    }

    public function getPaginator(array $paginatorOptions = array())
    {
        $defaultPaginatorOptions = array(
            'itemCountPerPage' => 10,
            'pageRange' => 5,
            'pageNumber' => 1,
        );

        $dataClass = $this->getDataClass();
        $count = $dataClass->getCount();
        if(!$count) {
            return $this->paginator = null;
        }

        $dbPaginatorOptions = $dataClass->getPaginatorOptions();
        $paginatorOptions = array_merge($defaultPaginatorOptions, $dbPaginatorOptions, $paginatorOptions);

        $count = (int) $count;
        $diConfig = array(
            'instance' => array(
                'Zend\Paginator\Adapter\DbSelect' => array(
                    'parameters' => array(
                        'rowCount' => $count,
                        'select' => $dataClass->getSelect(),
                        'adapterOrSqlObject' => $dataClass->getSql(),
                    )
                ),
                'Eva\Paginator\Paginator' => array(
                    'parameters' => array(
                        'rowCount' => $count,
                        'adapter' => 'Zend\Paginator\Adapter\DbSelect',
                    ),
                ),
            )
        );


        foreach ($paginatorOptions as $key => $value) {
            if(false === in_array($key, array('itemCountPerPage', 'pageNumber', 'pageRange'))){
                continue;
            }
            $diConfig['instance']['Eva\Paginator\Paginator']['parameters'][$key] = $paginatorOptions[$key];
        }

        $di = new \Zend\Di\Di();
        $di->configure(new \Zend\Di\Config($diConfig));
        $paginator = $di->get('Eva\Paginator\Paginator');
        return $this->paginator = $paginator;
    }


    /**
    * Iterator: move pointer to next item
    *
    * @return void
    */
    public function next()
    {
        $this->dataSource->next();
    }

    /**
    * Iterator: retrieve current key
    *
    * @return mixed
    */
    public function key()
    {
        return $this->dataSource->key();
    }

    /**
    * Iterator: get current item
    *
    * @return array
    */
    public function current()
    {
        return $this->dataSource->current();
    }

    /**
    * Iterator: is pointer valid?
    *
    * @return bool
    */
    public function valid()
    {
        return $this->dataSource->valid();
    }

    /**
    * Iterator: rewind
    *
    * @return void
    */
    public function rewind()
    {
        $this->dataSource->rewind();
        // return void
    }

    /**
     * Countable: return count of rows
     *
     * @return int
     */
    public function count()
    {
        if ($this->count !== null) {
            return $this->count;
        }
        $this->count = count($this->dataSource);
        return $this->count;
    }

    public function offsetExists($index) {
        return isset($this->dataSource[$index]);
    }
 
    public function offsetGet($index) {
        if($this->offsetExists($index)) {
            return $this->dataSource[$index];
        }
        return false;
    }
 
    public function offsetSet($index, $value) {
        if($index) {
            $this->dataSource[$index] = $value;
        } else {
            $this->dataSource[] = $value;
        }
        return true;
 
    }
 
    public function offsetUnset($index) {
        unset($this->dataSource[$index]);
        return true;
    }
}
