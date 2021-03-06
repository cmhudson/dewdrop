<?php

namespace Dewdrop\View;


class DewdropListTable extends \WP_List_Table {

    public $moduleName;
    protected $primaryKey;
    protected $linkColumns = array();
    protected $model;
    protected $stmt;
    public $items;

    protected $columns;

    protected $singularTitle;
    protected $pluralTitle;

    /**
     * @param \Dewdrop\Db\Table $model
     * @param \Dewdrop\Db\Select $stmt
     * @param null $columns
     * @param null $module
     * @internal param $moduleName
     */
    public function __construct($model = null,$stmt = null,$columns = null,$module = null)
    {
        $this->setModel($model);
        $this->setStatement($stmt);
        $this->setColumns($columns);
        $this->setModule($model);

    }

    public function setup()
    {
        $model = null;
        if ($this->model) {
            $model = $this->model;
        }

        if (! $this->stmt && $model && method_exists($model,'selectAdminListing')) {
            $this->stmt = $model->selectAdminListing();
        }

        if ($model) {
            $this->singularTitle = $model->getSingularTitle();
            $this->pluralTitle   = $model->getPluralTitle();

            $keys = $model->getPrimaryKey();
            $this->setPrimaryKey($keys[0]);
        }

        if (! $this->moduleName) {
            $this->moduleName = $this->singularTitle;
        }

        if (! $this->columns && $model) {
            $columns = array();
            foreach ($model->getRowColumns() as $column) {
                //var_dump($model->field($column));
                $field = $model->field($column);
                $columns[$column] = $field->getLabel();
            }

            $this->setColumns($columns);
        }

        parent::__construct( array(
            'singular'  => $this->singularTitle,     //singular name of the listed records
            'plural'    => $this->pluralTitle,    //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );

        $this->prepare_items();
    }

    public function setModule($module)
    {
        $this->moduleName = $module;
    }

    public function setModel($model)
    {
        $this->model = $model;
    }

    public function setStatement($stmt)
    {
        $this->stmt = $stmt;
    }

    public function setPrimaryKey($key)
    {
        $this->primaryKey = $key;
    }

    public function setColumns($columns)
    {
        $this->columns = $columns;
    }

    public function setLinkColumn($fieldKey)
    {
        if (! in_array($fieldKey,$this->linkColumns)) {
            $this->linkColumns[] = $fieldKey;
        }
    }

    function column_default($item, $column_name){

        if ($item[$column_name]) {

            if (in_array($column_name,$this->linkColumns)) {
                return $this->renderEditLinkCell($item,$column_name);
            }

            return $item[$column_name];
        }
        return '';

    }

    protected function renderEditLinkCell($item,$column_name)
    {
        $value = $item[$column_name];
        $id    = $item[$this->primaryKey];
        return sprintf(
            '<a href="?page=%s/Edit&%s=%s" >%s</a>',
            $this->moduleName,
            $this->primaryKey,
            $id,
            $value
        );
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item[$this->primaryKey]                //The value of the checkbox should be the record's id
        );
    }

    function get_columns(){
        return $this->columns;
    }

    function get_sortable_columns() {
        $columns = $this->get_columns();

        $sortable = array();
        foreach ($columns as $slug => $label) {
            $sorted = false;
            if ($slug == $this->primaryKey) {
                $sorted = true;
            }
            $sortable[$slug] = array($slug,false);
        }

        return $sortable;

        /*$sortable_columns = array(
            'title'     => array('title',false),     //true means it's already sorted
            'rating'    => array('rating',false),
            'director'  => array('director',false)
        );
        return $sortable_columns;*/
    }

    function get_bulk_actions() {
        $actions = array(
            'delete'    => 'Delete'
        );
        return $actions;
    }

    function process_bulk_action() {
        //Detect when a bulk action is being triggered...
        if( 'delete'===$this->current_action() ) {
            wp_die('Items deleted (or they would be if we had items to delete)!');
        }
    }

    public function prepare_items()
    {
        $columns  = $this->get_columns();
        if (! $this->linkColumns) {
            foreach ($columns as $key => $label) {
                if ($key == 'name' || $key == 'title') {
                    $this->linkColumns[] = $key;
                }
            }

        }
        $hidden   = array();
        $sortable = $this->get_sortable_columns();


        $per_page = 7;
        /**
         * build an array to be used by the class for column
         * headers.
         */
        $this->_column_headers = array($columns, $hidden, $sortable);


        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        $this->process_bulk_action();

        $current_page = $this->get_pagenum();

        // query goes here
        $stmt   = $this->stmt;

        // get total item count
        $total_items = count($this->model->getAdapter()->fetchAll($stmt));
        $total_pages = ceil($total_items/$per_page);

        // add pagination to query
        if (! $current_page || $current_page == 1) {
            $stmt->limit($per_page);
        } else {
            $offset = ($current_page-1) * $per_page;
            $stmt->limit($per_page,$offset);
        }

        // sort query
        $order = $_REQUEST['order'];

        $orderby = $_REQUEST['orderby'];
        if ( in_array($orderby,array_keys($columns))) {
            $stmt->reset('order');
            $stmt->order($orderby . ' ' . $order);
        }

        // query here
        $rs     = $this->model->getAdapter()->fetchAll($stmt);

        $data   = array();



        foreach ($rs as $row) {

            foreach ($this->get_columns() as $id => $title) {
                $data[$row[$this->primaryKey]][$id] = $row[$id];
            }

        }

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => $total_pages   //WE have to calculate the total number of pages
        ) );
    }


}
