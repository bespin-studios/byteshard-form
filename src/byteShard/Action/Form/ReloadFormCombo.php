<?php

namespace byteShard\Action\Form;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Action\CellActionResult;

class ReloadFormCombo extends Action
{
    private string $cell;
    private array  $formItems = [];

    public function __construct(string $cell, string ...$formComboId)
    {
        $this->cell = Cell::getContentClassName($cell, 'Form', __METHOD__);
        foreach ($formComboId as $formItem) {
            if ($formItem !== '') {
                $this->formItems[$formItem] = $formItem;
            }
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $foo    = [];
        $result = new CellActionResult('layout');
        return $result->addCellCommand([$this->cell], 'reloadFormObject', $foo);
    }
}