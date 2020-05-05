<?php

namespace Krlove\EloquentModelGenerator;

use Krlove\EloquentModelGenerator\Exception\GeneratorException;
use Krlove\EloquentModelGenerator\Model\EloquentModel;
use Krlove\EloquentModelGenerator\Model\EloquentBaseModel;
use Krlove\EloquentModelGenerator\Processor\ProcessorInterface;

/**
 * Class EloquentModelBuilder
 * @package Krlove\EloquentModelGenerator
 */
class EloquentModelBuilder
{
    /**
     * @var ProcessorInterface[]
     */
    protected $processors;

    /**
     * EloquentModelBuilder constructor.
     * @param ProcessorInterface[]|\IteratorAggregate $processors
     */
    public function __construct($processors)
    {
        if ($processors instanceof \IteratorAggregate) {
            $this->processors = iterator_to_array($processors);
        } else {
            $this->processors = $processors;
        }

    }

    /**
     * @param Config $config
     * @return EloquentModel
     * @throws GeneratorException
     */
    public function createModel(Config $config)
    {
        $model = new EloquentModel();

        $this->prepareProcessors();

        foreach ($this->processors as $processor) {
            $processor->process($model, $config);
        }

        return $model;
    }

    public function createBaseModel(Config $config)
    {
        $model = new EloquentBaseModel();

        $this->prepareBaseProcessors();

        foreach ($this->processors as $processor) {
            $processor->process($model, $config);
        }

        return $model;
    }

    /**
     * Sort processors by priority
     */
    protected function prepareProcessors($is_base=true)
    {
        $this->processors = array_filter($this->processors,function($item) {
            return $item->getIsForBase();
        });
    }

    protected function prepareBaseProcessors($is_base=true)
    {
        usort($this->processors, function (ProcessorInterface $one, ProcessorInterface $two) {
            if ($one->getPriority() == $two->getPriority()) {
                return 0;
            }

            return $one->getPriority() < $two->getPriority() ? 1 : -1;
        });
    }
}
