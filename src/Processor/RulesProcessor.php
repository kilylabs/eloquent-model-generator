<?php

namespace Krlove\EloquentModelGenerator\Processor;

use Krlove\CodeGenerator\Model\ClassNameModel;
use Krlove\CodeGenerator\Model\DocBlockModel;
use Krlove\CodeGenerator\Model\PropertyModel;
use Krlove\CodeGenerator\Model\MethodModel;
use Krlove\CodeGenerator\Model\UseClassModel;
use Krlove\EloquentModelGenerator\Config;
use Krlove\EloquentModelGenerator\Helper\EmgHelper;
use Krlove\EloquentModelGenerator\Model\EloquentModel;
use Krlove\EloquentModelGenerator\Model\EloquentBaseModel;
use Illuminate\Database\DatabaseManager;
use Krlove\EloquentModelGenerator\TypeRegistry;
use Krlove\CodeGenerator\Model\VirtualPropertyModel;

/**
 * Class TableNameProcessor
 * @package Krlove\EloquentModelGenerator\Processor
 */
class RulesProcessor implements ProcessorInterface
{
    /**
     * @var EmgHelper
     */
    protected $helper;

    protected $databaseManager;

    /**
     * @var TypeRegistry
     */
    protected $typeRegistry;

    /**
     * TableNameProcessor constructor.
     * @param EmgHelper $helper
     */
    public function __construct(EmgHelper $helper,DatabaseManager $databaseManager, TypeRegistry $typeRegistry)
    {
        $this->helper = $helper;
        $this->databaseManager = $databaseManager;
        $this->typeRegistry = $typeRegistry;
    }

    /**
     * @inheritdoc
     */
    public function process(EloquentModel $model, Config $config)
    {
        $className     = $config->get('class_name');
        $baseClassName = $config->get('base_class_name');
        $tableName     = $config->get('table_name');

        $model->setName(new ClassNameModel($className, $this->helper->getShortClassName($baseClassName)));
        if(strpos(get_class($model),'Base')===false) {
            $model->addUses(new UseClassModel(ltrim($baseClassName, '\\')));
        }

        $schemaManager = $this->databaseManager->connection($config->get('connection'))->getDoctrineSchemaManager();
        $prefix        = $this->databaseManager->connection($config->get('connection'))->getTablePrefix();

        $tableDetails       = $schemaManager->listTableDetails($tableName);
        $primaryColumnNames = $tableDetails->getPrimaryKey() ? $tableDetails->getPrimaryKey()->getColumns() : [];

        $columnNames = $columnNamesDefault = [];
        foreach ($tableDetails->getColumns() as $column) {
            /*
            $model->addProperty(new VirtualPropertyModel(
                $column->getName(),
                $this->typeRegistry->resolveType($column->getType()->getName())
            ));
            */

            if (in_array($column->getName(), $primaryColumnNames)) {
                continue;
            }

            $rule = [];
            if($column->getNotnull()) {
                $rule[] = 'required';
            } 
            if($column->getType()->getName() == 'string') {
                $rule[] = 'max:'.$column->getLength();
            } elseif($column->getType()->getName() == 'text') {
                $rule[] = 'max:'.$column->getLength();
            } elseif($column->getType()->getName() == 'integer') {
                $rule[] = 'numeric';
            }
            $columnNames[$column->getName()] = implode('|',$rule);
            $columnNamesDefault[$column->getName()] = $column->getDefault();
        }

        if(strpos(get_class($model),'Base')!==false) {
            $rules = new PropertyModel('attributes');
            $rules->setAccess('protected')
                ->setValue(array_filter($columnNamesDefault))
                ->setDocBlock(new DocBlockModel('@var array'));
            $model->addProperty($rules);
        }

        $rules = new PropertyModel('rules');
        $rules->setAccess('protected')
            ->setValue(array_filter($columnNames))
            ->setDocBlock(new DocBlockModel('@var array'));

        $method = new MethodModel('rules');
        $method->setAccess('protected');
        if(strpos(get_class($model),'Base')===false) {
            $method->setBody('return array_merge(parent::rules(),'.$rules->renderValue().');');
        } else {
            $method->setBody('return '.$rules->renderValue().';');
        }
        $model->addMethod($method);

    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 10;
    }

    public function getIsForBase() {
        return true;
    }
}
