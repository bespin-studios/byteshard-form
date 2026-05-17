<?php

namespace byteShard\Action\Form;

use byteShard\Container;
use byteShard\ID\ContainerIDElement;
use byteShard\ID\ID;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

class ReloadFormContainer extends Action
{
    private array  $cells;
    private string $container = '';

    public function __construct(string $cell, string $containerClass)
    {
        $this->cells = parent::getUniqueCellNameArray($cell);
        if (is_subclass_of($containerClass, Container::class)) {
            $this->container = $containerClass;
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $containerId = ID::factory(new ContainerIDElement($this->container))->getEncryptedId();
        $parameters  = [$containerId => ['reload' => true]];
        $result      = new Action\CellActionResult(Action\ActionTargetEnum::Cell);
        $result->addCellCommand($this->cells, 'reloadFormContainer', $parameters);
        return $result;
    }

}