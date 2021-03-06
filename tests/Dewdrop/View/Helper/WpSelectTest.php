<?php

namespace Dewdrop\View\Helper;

use Dewdrop\Test\BaseTestCase;
use Dewdrop\View\View;

class WpSelectTest extends BaseTestCase
{
    private $view;

    public function setUp()
    {
        $this->view = new View();
    }

    public function testCanRenderSelectWithExplicitlyPassedArguments()
    {
        $out = $this->view->wpSelect(
            'my_select',
            array(
                1 => 'Option 1',
                2 => 'Option 2'
            ),
            1
        );

        $this->assertMatchesDomQuery('select[name="my_select"]', $out);
        $this->assertMatchesDomQuery('option[value="1"]', $out);
        $this->assertMatchesDomQuery('option[value="2"]', $out);

        $results = $this->queryDom('option[selected="selected"]', $out);
        $this->assertEquals(1, $results[0]->getAttribute('value'));
    }

    public function testCanRenderSelectWithArgumentsPassedAsArray()
    {
        $out = $this->view->wpSelect(
            array(
                'name'    => 'my_select',
                'options' => array(
                    1 => 'Option 1',
                    2 => 'Option 2'
                ),
                'value'   => 1
            )
        );

        $this->assertMatchesDomQuery('select[name="my_select"]', $out);
        $this->assertMatchesDomQuery('option[value="1"]', $out);
        $this->assertMatchesDomQuery('option[value="2"]', $out);

        $results = $this->queryDom('option[selected="selected"]', $out);
        $this->assertEquals(1, $results[0]->getAttribute('value'));
    }

    public function testIdEqualsNameWhenIdIsntSpecifiedManually()
    {
        $out = $this->view->wpSelect(
            'my_select',
            array(
                1 => 'Option 1',
                2 => 'Option 2'
            ),
            1
        );

        $this->assertMatchesDomQuery('select[name="my_select"]', $out);
        $this->assertMatchesDomQuery('select[id="my_select"]', $out);
    }

    public function testCanExplicitlySetIdToBeDifferentThanName()
    {
        $out = $this->view->wpSelect(
            array(
                'name'    => 'my_select_name',
                'id'      => 'my_select_id',
                'options' => array(),
                'value'   => null
            )
        );

        $this->assertMatchesDomQuery('select[name="my_select_name"]', $out);
        $this->assertMatchesDomQuery('select[id="my_select_id"]', $out);
    }

    public function testValuesAreCastToStringPriorToComparison()
    {
        $out = $this->view->wpSelect(
            array(
                'name'    => 'my_select_name',
                'options' => array(1 => 'Sole Option'),
                'value'   => '1'
            )
        );

        $this->assertMatchesDomQuery('option[selected="selected"][value="1"]', $out);
    }

    public function testCanRenderUsingAFieldObject()
    {
        $wpdb = new \wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $db   = new \Dewdrop\Db\Adapter($wpdb);

        require_once __DIR__ . '/table/DewdropTestFruits.php';
        $table = new \DewdropViewHelperTest\DewdropTestFruits($db);
        $row   = $table->createRow();

        $row->field('name')->getOptionPairs()
            ->setTableName('dewdrop_test_animals');

        $this->view->wpSelect($row->field('name'));
    }

    /**
     * @expectedException \Dewdrop\Exception
     */
    public function testOmittingNameArgumentThrowsException()
    {
        $this->view->wpSelect(
            array(
                'options' => array(),
                'value'   => null
            )
        );
    }

    /**
     * @expectedException \Dewdrop\Exception
     */
    public function testOmittingOptionsArgumentThrowsException()
    {
        $this->view->wpSelect(
            array(
                'name'  => 'test',
                'value' => null
            )
        );
    }

    /**
     * @expectedException \Dewdrop\Exception
     */
    public function testOmittingValueArgumentThrowsException()
    {
        $this->view->wpSelect(
            array(
                'options' => array(),
                'name'    => 'test'
            )
        );
    }
}
