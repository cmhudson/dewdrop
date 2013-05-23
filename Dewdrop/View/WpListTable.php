<?php

namespace Admin\Beer;

//use Dewdrop\Db\Select;
//use Dewdrop\Db\Table;


class WpListTable extends \WP_List_Table {

    public $module_name;
    private $primary_key;
    private $linkColumns = array();
    private $model;
    private $stmt;
    public $items;

    private $columns;

    private $singularTitle;
    private $pluralTitle;

    /**
     * @param \Dewdrop\Db\Table $model
     * @param \Dewdrop\Db\Select $stmt
     * @param null $columns
     * @param null $module
     * @internal param $moduleName
     */
    public function __construct($model,$stmt = null,$columns = null,$module = null)
    {
        $this->model = $model;

        if ($stmt) {
            $this->stmt = $stmt;
        } else if (method_exists($model,'selectAdminListing')) {
            $this->stmt = $model->selectAdminListing();
        }

        $this->singularTitle = $model->getSingularTitle();
        $this->pluralTitle   = $model->getPluralTitle();

        if ($module) {
            $this->module_name = $module;
        } else {
            $this->module_name = $this->singularTitle;
        }


        $keys = $model->getPrimaryKey();
        $this->set_primary_key($keys[0]);

        if ($columns) {
            $this->setColumns($columns);
        } else {
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

    public function set_primary_key($key)
    {
        $this->primary_key = $key;
    }

    public function setColumns($columns)
    {
        $this->columns = $columns;
    }

    public function set_link_column($fieldKey)
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

    private function renderEditLinkCell($item,$column_name)
    {
        $value = $item[$column_name];
        $id    = $item[$this->primary_key];
        return sprintf(
            '<a href="?page=%s/Edit&%s=%s" >%s</a>',
            $this->module_name,
            $this->primary_key,
            $id,
            $value
        );
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item[$this->primary_key]                //The value of the checkbox should be the record's id
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
            if ($slug == $this->primary_key) {
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
                $data[$row[$this->primary_key]][$id] = $row[$id];
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
