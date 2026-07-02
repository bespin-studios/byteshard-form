<?php

namespace byteShard\Action\Form;

use byteShard\Container;
use byteShard\ID\ContainerIDElement;
use byteShard\ID\ID;
use byteShard\ID\PopupIDElement;
use byteShard\ID\TabIDElement;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Session;

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
        $id           = $this->getActionInitDTO()->id;
        $cell         = Session::getCell(ID::refactor($this->cells[0], $id));
        $targetCellId = $cell->getNewId();
        $elements     = [];
        if ($targetCellId->isPopupId()) {
            $elements[] = new PopupIDElement($targetCellId->getPopupId());
        }
        if ($targetCellId->isTabId()) {
            $elements[] = new TabIDElement($targetCellId->getTabId());
        }
        $elements[]  = new ContainerIDElement($this->container);
        $containerId = ID::factory(...$elements)->getEncryptedId();
        $parameters  = [$containerId => ['reload' => true]];
        $result      = new Action\CellActionResult(Action\ActionTargetEnum::Cell);
        $result->addCellCommand($this->cells, 'reloadFormContainer', $parameters);
        return $result;
    }

}