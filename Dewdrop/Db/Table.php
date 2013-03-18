<?php

namespace Dewdrop\Db;

use Dewdrop\Paths;
use Dewdrop\Exception;
use Dewdrop\Db\Row;
use Dewdrop\Db\Field;

/**
 * The table class provides a gateway to the a single DB table by providing
 * utility methods for querying the table and finding specific rows within
 * it.
 *
 * @category   Dewdrop
 * @package    Db
 */
abstract class Table
{
    /**
     * Any field objects that have been generated for this table.
     *
     * @var array
     */
    private $fields = array();

    /**
     * Callbacks assigned during the init() method of your table sub-class,
     * which will be used to tweak the default field settings away from the
     * defaults inferred from DB metadata.
     *
     * @var array
     */
    private $fieldCustomizationCallbacks = array();

    /**
     * The default row class for this table object.  If you'd like to use
     * a custom row class for your model, you can set it in your init()
     * method.
     *
     * @var string
     */
    private $rowClass = '\Dewdrop\Db\Row';

    /**
     * @var \Dewdrop\Db\Adapter
     */
    private $db;

    /**
     * @var \Dewdrop\Paths
     */
    private $paths;

    /**
     * The name of the DB table represented by this table class.
     *
     * @var string
     */
    private $tableName;

    /**
     * The metadata generated by the db-metadata CLI command or the dbdeploy
     * CLI command for this table.  This is used to provide your plugin with
     * information about the columns and constraints on the underlying DB
     * table.
     *
     * @var array
     */
    private $metadata;

    /**
     * A pluralized version of this table's title.  If not manually specified,
     * the title will be inflected from teh table name.
     *
     * @var string
     */
    private $pluralTitle;

    /**
     * A singularized version of this table's title.  If not manually specified,
     * the title will be inflected from teh table name.
     *
     * @var string
     */
    private $singularTitle;

    /**
     * @param \Dewdrop\Db\Adapter $db
     * @param \Dewdrop\Paths $paths
     */
    public function __construct(Adapter $db, Paths $paths = null)
    {
        $this->db    = $db;
        $this->paths = ($paths ?: new Paths());

        $this->init();

        if (!$this->tableName) {
            throw new Exception('You must call setTableName() in your init() method.');
        }
    }

    /**
     * This method should be used by sub-classes to set the table name,
     * create field customization callbacks, etc.
     *
     * @return void
     */
    abstract public function init();

    /**
     * Retrieve the field object associated with the specified name.
     *
     * @param string $name
     * @return \Dewdrop\Db\Field
     */
    public function field($name)
    {
        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        }

        $metadata = $this->getMetadata('columns', $name);

        if (!$metadata) {
            throw new Exception("Attempting to retrieve unknown column \"{$name}\"");
        }

        $field = new Field($this, $name, $metadata);

        if (isset($this->fieldCustomizationCallbacks[$name])) {
            call_user_func($this->fieldCustomizationCallbacks[$name], $field);
        }

        return $field;
    }

    /**
     * Assign a callback that will allow you to further customize a field
     * object whenever that object is requested using the table's field()
     * method.
     *
     * @param string $name
     * @param mixed $callback
     * @return \Dewdrop\Db\Table
     */
    public function customizeField($name, $callback)
    {
        $meta = $this->getMetadata('columns');

        if (!isset($meta[$name])) {
            throw new Exception("Setting customization callback for unknown column \"{$name}\"");
        }

        $this->fieldCustomizationCallbacks[$name] = $callback;

        return $this;
    }

    /**
     * Assign a DB table name to this model.
     *
     * @param string $tableName
     * @returns \Dewdrop\Db\Table
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;

        return $this;
    }

    /**
     * Get the DB table name assigned to this model.
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Override the default singular title.
     *
     * @param string $singularTitle
     * @return \Dewdrop\Db\Table
     */
    public function setSingularTitle($singularTitle)
    {
        $this->singularTitle = $singularTitle;

        return $this;
    }

    /**
     * Get a singular title (e.g. "Fruit", not "Fruits") for this model.
     *
     * If no title is set, we'll pull the inflected version from the table's
     * metadata.
     *
     * @return string
     */
    public function getSingularTitle()
    {
        if (!$this->singurlarTitle) {
            $this->singularTitle = $this->getMetadata('titles', 'singular');
        }

        return $this->singularTitle;
    }

    /**
     * Manually override the inflected plural title for this model.
     *
     * @param string $pluralTitle
     * @return \Dewdrop\Db\Table
     */
    public function setPluralTitle($pluralTitle)
    {
        $this->pluralTitle = $pluralTitle;

        return $this;
    }

    /**
     * Get a singular title (e.g. "Fruits", not "Fruit") for this model.
     *
     * If no title is set, we'll pull the inflected version from the table's
     * metadata.
     *
     * @return string
     */
    public function getPluralTitle()
    {
        if (!$this->pluralTitle) {
            $this->pluralTitle = $this->getMetadata('titles', 'plural');
        }

        return $this->pluralTitle;
    }

    /**
     * Load this table's metadata from the file generated by the db-metadata
     * CLI command.  The metadata currently has two sections:
     *
     * - titles: Default singular and plural titles for the model.
     * - columns: The columns in the table with types, constraints, etc.
     *
     * You can retrieve the entirety of the metadata information by providing
     * null values to both arguments.  You can retrieve an entire section of
     * the metdata by only specifying the first argument.  Or, you can specify
     * values for both arguments to retrieve a specific member of a specific
     * section.
     *
     * For example, to get metadata only for the "name" column, you would call:
     *
     * <code>
     * $this->getMetadata('columns', 'name');
     * </code>
     *
     * @return array
     */
    public function getMetadata($section = null, $index = null)
    {
        if (!$this->metadata) {
            $metadataPath = "{$this->paths->getModels()}/metadata/{$this->tableName}.php";

            if (!file_exists($metadataPath)) {
                throw new Exception(
                    'Table metadata not found.  '
                    . 'Run "db-metadata" command to generate it.'
                );
            }

            $this->metadata = require $metadataPath;

            if (!is_array($this->metadata)) {
                throw new Exception(
                    'Failed to retrieve table metadata not found.  '
                    . 'Run "db-metadata" command to generate it.'
                );
            }
        }

        if ($section && $index && isset($this->metadata[$section]) && isset($this->metadata[$index])) {
            return $this->metadata[$section][$index];
        } elseif ($section && isset($this->metadata[$section])) {
            return $this->metadata[$section];
        } else {
            return $this->metadata;
        }
    }

    /**
     * Get the names of the columns in the primary key.  This will always
     * return an array of column names, even if there is only one column
     * in the table's primary key.
     *
     * @return array
     */
    public function getPrimaryKey()
    {
        $columns = array();

        foreach ($this->getMetadata('columns') as $column => $metadata) {
            if ($metadata['PRIMARY']) {
                $position  = $metadata['PRIMARY_POSITION'];

                $columns[$position] = $column;
            }
        }

        ksort($columns);

        return array_values($columns);
    }

    /**
     * Get the DB adapter associated with this table object.
     *
     * @return \Dewdrop\Db\Adapter
     */
    public function getAdapter()
    {
        return $this->db;
    }

    /**
     * Create a new \Dewdrop\Db\Select object.
     *
     * @return \Dewdrop\Db\Select
     */
    public function select()
    {
        return $this->db->select();
    }

    /**
     * Insert a new row.
     *
     * Data should be supplied as key value pairs, with the keys representing
     * the column names.
     *
     * @param array $data
     * @return integer Number of affected rows.
     */
    public function insert(array $data)
    {
        return $this->db->insert($this->tableName, $data);
    }

    /**
     * Update an existing row.
     *
     * Data should be supplied as key value pairs, with the keys representing
     * the column names.  The where clause should be an already assembled
     * and quoted string.  It should not be prefixed with the "WHERE" keyword.
     *
     * @param array $data
     * @param string $where
     */
    public function update(array $data, $where)
    {
        return $this->db->update($this->tableName, $data, $where);
    }

    /**
     * Find a single row based upon primary key value.
     *
     * @return \Dewdrop\Db\Row
     */
    public function find()
    {
        return $this->fetchRow($this->assembleFindSql(func_get_args()));
    }

    /**
     * Find the data needed to refresh a row object's data based upon its
     * primary key value.
     *
     * @param array $args
     * @return array
     */
    public function findRowRefreshData(array $args)
    {
        return $this->db->fetchRow(
            $this->assembleFindSql($args),
            ARRAY_A
        );
    }

    /**
     * Create a new row object, assigning the provided data as its initial
     * state.
     *
     * @param array $data
     * @return \Dewdrop\Db\Row
     */
    public function createRow(array $data = array())
    {
        $className = $this->rowClass;
        return new $className($this, $data);
    }

    /**
     * Fetch a single row by running the provided SQL.
     *
     * @param string|\Dewdrop\Db\Select $sql
     * @return \Dewdrop\Db\Row
     */
    public function fetchRow($sql)
    {
        $className = $this->rowClass;
        $data      = $this->db->fetchRow($sql, ARRAY_A);

        return new $className($this, $data);
    }

    /**
     * Assemble SQL for finding a row by its primary key.
     *
     * @param array $args The primary key values
     * @return string
     */
    private function assembleFindSql(array $args)
    {
        $pkey = $this->getPrimaryKey();

        foreach ($pkey as $index => $column) {
            if (!isset($args[$index])) {
                $pkeyColumnCount = count($pkey);
                throw new Exception("You must specify a value for all {$pkeyColumnCount} primary key columns");
            }

            $column  = $this->db->quoteIdentifier($column);
            $where[] = $this->db->quoteInto("{$column} = ?", $args[$index]);
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s',
            $this->db->quoteIdentifier($this->tableName),
            implode(' AND ', $where)
        );

        return $sql;
    }
}
