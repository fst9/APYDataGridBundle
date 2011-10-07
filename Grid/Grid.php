<?php

/*
 * This file is part of the DataGridBundle.
 *
 * (c) Stanislav Turza <sorien@mail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Sorien\DataGridBundle\Grid;

use Sorien\DataGridBundle\Grid\Columns;
use Sorien\DataGridBundle\Grid\Rows;
use Sorien\DataGridBundle\Grid\Action\MassActionInterface;
use Sorien\DataGridBundle\Grid\Action\RowActionInterface;
use Sorien\DataGridBundle\Grid\Column\Column;
use Sorien\DataGridBundle\Grid\Column\MassActionColumn;
use Sorien\DataGridBundle\Grid\Column\ActionsColumn;
use Sorien\DataGridBundle\Grid\Source\Source;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class Grid
{
    const UNLIMITED = PHP_INT_MAX;

    /**
     * @var \Symfony\Component\HttpFoundation\Session;
     */
    private $session;

    /**
    * @var \Symfony\Component\HttpFoundation\Request
    */
    private $request;

    /**
    * @var \Symfony\Component\Routing\Router
    */
    private $router;
    private $route;
    private $container;
    private $routeUrl;
    private $id;
    private $hash;

    /**
    * @var \Sorien\DataGridBundle\Grid\Source\Source
    */
    private $source;

    private $totalCount;
    private $page;
    private $limit;
    private $limits;

    /**
    * @var \Sorien\DataGridBundle\Grid\Column\Columns
    */
    private $columns;

    /**
    * @var \Sorien\DataGridBundle\Grid\DataSorien\DataGridBundle\Grid\Rows
    */
    private $rows;

    private $massActions;
    private $rowActions;

    private $showFilters;
    private $showTitles;

    /**
     * @param \Source\Source $source Data Source
     * @param $container
     * @param $route string if null current route will be used 
     * @param string $id set if you are using more then one grid inside controller
     */
    public function __construct($container, $source = null, $id = '')
    {
        if(!is_null($source) && !($source instanceof Source))
        {
            throw new \InvalidArgumentException(sprintf('Supplied Source have to extend Source class and not %s', get_class($source)));
        }

        $this->container = $container;

        $this->router = $container->get('router');
        $this->request = $container->get('request');
        $this->session = $this->request->getSession();

        $this->hash = md5($this->request->get('_controller').$id);
        $this->id = $id;

        $this->setLimits(array(20 => '20', 50 => '50', 100 => '100'));
        $this->page = 0;
        $this->showTitles = $this->showFilters = true;

        $this->columns = new Columns();
        $this->massActions = array();
        $this->rowActions = array();

        if (!is_null($source))
        {
            $this->setSource($source);
        }
    }
    
    function addColumn($column, $position = 0)
    {
        $this->columns->addColumn($column, $position);
        
        return $this;
    }
    
    function addMassAction(MassActionInterface $action)
    {
        if ($this->source instanceof Source)
        {
            throw new \RuntimeException('The actions have to be defined before the source.');
        }
        $this->massActions[] = $action;
        
        return $this;
    }
    
    function addRowAction(RowActionInterface $action)
    {
        $this->rowActions[$action->getColumn()][] = $action;
        
        return $this;
    }

    public function setSource($source)
    {
        if(!is_null($this->source))
        {
            throw new \InvalidArgumentException('Source can be set just once.');
        }

        if (!($source instanceof Source))
        {
            throw new \InvalidArgumentException('Supplied Source have to extend Source class.');
        }

        $this->source = $source;

        $this->source->initialise($this->container);

        //get cols from source
        $this->source->getColumns($this->columns);

        //store column data
        $this->fetchAndSaveColumnData();

        //execute massActions
        $this->executeMassActions();
        
        //store grid data
        $this->fetchAndSaveGridData();

        return $this;
    }

    /**
     * Retrieve Column Data from Session and Request
     *
     * @param string $column
     * @param bool $onlyFromRequest
     * @return null|string
     */
    private function getData($column, $fromRequest = true, $fromSession = true)
    {
        $result = null;

        if ($fromSession && is_array($data = $this->session->get($this->getHash())))
        {
            if (isset($data[$column]))
            {
                $result = $data[$column];
            }
        }

        if ($fromRequest && is_array($data = $this->request->get($this->getHash())))
        {
            if (isset($data[$column]))
            {
                $result = $data[$column];
            }
        }

        return $result;
    }

    /**
     * Set and Store Columns data
     *
     * @return void
     */
    private function fetchAndSaveColumnData()
    {
        $storage = $this->session->get($this->getHash());

        foreach ($this->columns as $column)
        {
            $column->setData($this->getData($column->getId()));

            if (($data = $column->getData()) !== null)
            {
                $storage[$column->getId()] = $data;
            }
            else
            {
                unset($storage[$column->getId()]);
            }
        }

        if (!empty($storage))
        {
            $this->session->set($this->getHash(), $storage);
        }
    }

    /**
     * Set and Store Initial Grid data
     *
     * @return void
     */
    private function fetchAndSaveGridData()
    {
        $storage = $this->session->get($this->getHash());
        
        //set internal data
        $limit = $this->getData('_limit');
        if (!is_null($limit))
        {
            $this->limit = $limit;
        }

        $page = $this->getData('_page');
        if (!is_null($page) && $page >= 0)
        {
            $this->page = $page;
        }

        $order = $this->getData('_order');
        if (!is_null($order))
        {
            list($columnId, $columnOrder) = explode('|', $order);

            $column = $this->columns->getColumnById($columnId);
            if (!is_null($column))
            {
                $column->setOrder($columnOrder);
            }

            $storage['_order'] = $order;
        }

        if ($this->limit != $this->getData('_limit', false))
        {
            $storage['_limit'] = $this->limit;
        }

        if ($this->page >= 0)
        {
            $storage['_page'] = $this->page;
        }

        // save data to sessions if needed
        if (!empty($storage))
        {
            $this->session->set($this->getHash(), $storage);
        }
    }

    public function executeMassActions()
    {        
        $id = $this->getData('__action_id', true, false);
        $data = $this->getData('__action', true, false);

        if ($id > -1 && is_array($data))
        {
            if (array_key_exists($id, $this->massActions)) {
                $action = $this->massActions[$id];
                
                if (is_callable($action->getCallback()))
                {
                    call_user_func($action->getCallback(), array_keys($data), false, $this->session);
                }
                else {
                    throw new \RuntimeException(sprintf('Callback %s is not callable.', $action->getCallback()));
                }
            }
            else {
                throw new \OutOfBoundsException(sprintf('Action %s is not defined.', $id));
            }
        }
    }

    /**
     * Prepare Grid for Drawing
     *
     * @return Grid
     */
    public function prepare()
    {        
        $this->rows = $this->source->execute($this->columns->getIterator(true), $this->page, $this->limit);

        if(!$this->rows instanceof Rows)
        {
            throw new \Exception('Source have to return Rows object.');
        }
        
        //add row actions column
        if (count($this->rowActions) > 0)
        {
            foreach ($this->rowActions as $column => $rowActions) {
                if ($rowAction = $this->columns->hasColumnById($column, true)) {
                    $rowAction->setRowActions($rowActions);
                }
                else {
                    $this->columns->addColumn(new ActionsColumn($column, 'Actions', $rowActions));
                }
            }
        }

        //add mass actions column
        if (count($this->massActions) > 0)
        {
            $this->columns->addColumn(new MassActionColumn($this->getHash()), 1);
        }
        
        $primaryColumnId = $this->columns->getPrimaryColumn()->getId();

        foreach ($this->rows as $row)
        {
            foreach ($this->columns as $column)
            {
                $row->setPrimaryField($primaryColumnId);
            }
        }

        //@todo refactor autohide titles when no title is set
        if (!$this->showTitles)
        {
            $this->showTitles = false;
            foreach ($this->columns as $column)
            {
                if (!$this->showTitles) break;

                if ($column->getTitle() != '')
                {
                    $this->showTitles = true;
                    break;
                }
            }
        }

        //get size
        if(!is_int($this->totalCount = $this->source->getTotalCount($this->columns)))
        {
            throw new \Exception(sprintf('Source function getTotalCount need to return integer result, returned: %s', gettype($this->totalCount)));
        }
        
        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function setColumns($columns)
    {
        if(!$columns instanceof Columns)
        {
            throw new \InvalidArgumentException('Supplied object have to extend Columns class.');
        }

        $this->columns = $columns;
        $this->fetchAndSaveColumnData();

        return $this;
    }

    public function getRows()
    {
        return $this->rows;
    }

    public function getMassActions()
    {
        return $this->massActions;
    }
    
    public function getRowActions()
    {
        return $this->rowActions;
    }
    
    public function getRouteUrl()
    {
        if ($this->routeUrl == '')
        {
            $this->routeUrl = $this->router->generate($this->request->get('_route'));
        }

        return $this->routeUrl;
    }

    public function isReadyForRedirect()
    {
        $data = $this->request->get($this->getHash());
        return !empty($data);
    }
    
    public function getHash()
    {
        return 'grid_'.$this->hash;
    }

    /**
     * @param array|int $limits
     * @return \Sorien\DataGridBundle\Grid\Grid
     */
    public function setLimits($limits)
    {
        if (is_array($limits))
        {
            $this->limits = $limits;
            $this->limit = key($this->limits);
        }

        if (is_numeric($limits))
        {
            $this->limits = array($limits => $limits);
            $this->limit = $limits;
        }

        return $this;
    }

    public function getLimits()
    {
        return $this->limits;
    }

    public function getCurrentLimit()
    {
        return $this->limit;
    }

    public function setPage($page)
    {
        if ($page > 0) {
            $this->page = intVal($page) - 1;
            return $this;
        }
        else {
            throw new \InvalidArgumentException('Page should be 1 or more.');
        }
    }

    public function getPage()
    {
        return $this->page;
    }

    public function getPageCount()
    {
        return ceil($this->getTotalCount() / $this->getCurrentLimit());
    }

    public function getTotalCount()
    {
        return $this->totalCount;
    }

    /**
     * @return bool
     * @todo fix according as isFilterSectionVisible
     */
    public function isTitleSectionVisible()
    {
        return $this->showTitles ;
    }

    /**
     * @return bool
     */
    public function isFilterSectionVisible()
    {
        if ($this->showFilters == true)
        {
            foreach ($this->columns as $column)
            {
                if ($column->isFilterable())
                {
                    return true;
                }
            }
        }

        return false;
    }

    public function isPagerSectionVisible()
    {
        $limits = sizeof($this->getLimits());
        return $limits > 1 || ($limits == 0 && $this->getCurrentLimit() < $this->totalCount);
    }

    public function hideFilters()
    {
        $this->showFilters = false;
    }

    public function hideTitles()
    {
        $this->showTitles = false;
    }

    /**
     * @param \Sorien\DataGridBundle\Grid\Column\Column $extension
     * @return void
     */
    public function addColumnExtension($extension)
    {
        $this->columns->addExtension($extension);
    }

    public function setId($id)
    {
        $this->id = $id;
        
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }
    
    public function deleteAction($ids) {
        $this->source->delete($ids);
    }
}