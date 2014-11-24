<?php
namespace Nayjest\Grids\Components;

use Nayjest\Grids\Components\Base\IRenderableComponent;
use Nayjest\Grids\Components\Base\TComponent;
use Nayjest\Grids\Components\Base\TComponentView;
use Nayjest\Grids\ArrayDataRow;
use Nayjest\Grids\DataProvider;
use Nayjest\Grids\DataRow;
use Nayjest\Grids\FieldConfig;
use Nayjest\Grids\IdFieldConfig;
use Nayjest\Grids\Grid;
use Illuminate\Support\Facades\Event;
use Exception;

class TotalsRow extends ArrayDataRow implements IRenderableComponent
{
    use TComponent {
        TComponent::initialize as protected initializeComponent;
    }
    use TComponentView;

    const OPERTATION_SUM = 'sum';
    const OPERATION_AVG = 'avg';
    //const OPERATION_MAX = 'max';
    //const OPERATION_MIN = 'min';

    /** @var \Illuminate\Support\Collection|FieldConfig[] */
    protected $fields;

    protected $field_names;

    protected $field_operations = [];

    protected $rows_processed = 0;

    public function __construct(array $field_names = [])
    {
        $this->template = '*.components.totals';
        $this->name = 'totals';

        $this->field_names = $field_names;
        $this->id = 'Totals';
        $this->src = [];
        foreach ($this->field_names as $name) {
            $this->src[$name] = 0;
        }

    }

    protected function provideFields()
    {
        $field_names = $this->field_names;
        $this->fields = $this->grid->getConfig()->getColumns()->filter(
            function (FieldConfig $field) use ($field_names) {
                return in_array($field->getName(), $field_names);
            }
        );
    }

    protected function listen(DataProvider $dp)
    {
        Event::listen(
            DataProvider::EVENT_FETCH_ROW,
            function (DataRow $row, DataProvider $provider) use ($dp) {
                if ($dp !== $provider) return;
                $this->rows_processed++;
                foreach ($this->fields as $field) {
                    $name = $field->getName();
                    $operation = $this->getFieldOperation($name);
                    switch($operation) {
                        case self::OPERTATION_SUM:
                            $this->src[$name] += $row->getCellValue($field);
                            break;
                        case self::OPERATION_AVG:
                            $this->src["{$name}_sum"] += $row->getCellValue($field);
                            $this->src[$name] = $this->src["{$name}_sum"] / $this->rows_processed;
                            break;
                        default:
                            throw new Exception("TotalsRow:Unknown aggregation operation.");
                    }

                }

            }
        );
    }

    public function initialize(Grid $grid)
    {
        $this->initializeComponent($grid);
        $this->provideFields();
        $this->listen(
            $this->grid->getConfig()->getDataProvider()
        );
    }

    /**
     * @param FieldConfig $field
     * @return bool
     */
    public function uses(FieldConfig $field)
    {
        return in_array($field, $this->fields->toArray()) or $field instanceof IdFieldConfig;
    }

    public function getCellValue($field)
    {
        if (!$field instanceof FieldConfig) {
            $field = $this->grid->getConfig()->getColumn($field);
        }
        if ($this->uses($field) and !$field instanceof IdFieldConfig) {
            return parent::getCellValue($field);
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getRowsProcessed()
    {
        return $this->rows_processed;
    }

    /**
     * @param array $field_names
     */
    public function setFieldNames($field_names)
    {
        $this->field_names = $field_names;
    }

    /**
     * @return array
     */
    public function getFieldNames()
    {
        return $this->field_names;
    }

    /**
     * @param array $field_operations
     */
    public function setFieldOperations(array $field_operations)
    {
        $this->field_operations = $field_operations;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getFieldOperations()
    {
        return $this->field_operations;
    }

    /**
     * @return mixed
     */
    public function getFieldOperation($field_name)
    {
        return isset($this->field_operations[$field_name])?$this->field_operations[$field_name]:self::OPERTATION_SUM;
    }


}