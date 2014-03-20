<?php

namespace Dewdrop\View\Helper;

use Dewdrop\Db\Field;
use Dewdrop\Exception;
use Dewdrop\View\DewdropListTable;

class WpListTable extends AbstractHelper
{

    /**
     * @var \Dewdrop\View\DewdropListTable
     */
    public $listTable;

    /*public function __construct($view)
    {
        /** @var $view \Dewdrop\View\View *
        parent::__construct($view);

        $this->listTable = new DewdropListTable(null);
        var_dump($this->listTable);exit;

    }*/

    public function direct($model = null, $stmt = null, $columns = null, $module = null)
    {
        //var_dump(func_get_args());
        if (! $this->listTable) {
            $this->listTable = new DewdropListTable();
        }
        $this->listTable->setModel($model);
        $this->listTable->setStatement($stmt);
        if ($columns) {
            $this->listTable->setColumns($columns);
        }
        if ($module) {
            $this->listTable->setModule($module);
        }

        return $this;
    }

    public function display()
    {
        $this->listTable->setup();

        $this->listTable->display();
    }

    public function __call($method, $args)
    {
        if (method_exists($this->listTable,$method)) {
            return call_user_func_array(array($this->listTable, $method), $args);
        }
        return null;
    }

    /*public function setColumns(array $columns)
    {
        $this->listTable->setColumns($columns);
    }

    public function setStatement($stmt)
    {
        $this->listTable->setStatement($stmt);
    }

    public function setModel($model)
    {
        $this->listTable->setModel($model);
    }*/
}
