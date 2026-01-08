<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Form;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Debug;

class SetComboOptions extends Action
{
    private array  $comboItems = [];
    private string $cell;

    public function __construct(string $cell, string ...$comboItems)
    {
        $this->cell = Cell::getContentCellName($cell);
        if (!Cell::isFormContent($cell)) {
            Debug::error(__METHOD__.' Action can only be used in Form');
        }
        foreach ($comboItems as $comboItem) {
            if ($comboItem !== '') {
                $this->comboItems[$comboItem] = $comboItem;
            }
        }
    }

    protected function runAction(): ActionResultInterface
    {
        return new Action\ActionResultMigrationHelper([]);
    }
}