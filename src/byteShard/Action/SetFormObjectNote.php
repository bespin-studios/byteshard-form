<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

/**
 * Class SetFormObjectNote
 * @package byteShard\Action
 */
class SetFormObjectNote extends Action
{
    private string $cell;
    private array  $formControls = [];

    public function __construct(string $cell, string ...$objects)
    {
        parent::__construct();
        $this->cell = Cell::getContentCellName($cell);
        foreach ($objects as $object) {
            if ($object !== '') {
                $this->formControls[$object] = $object;
            }
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $action = [];
        $cells  = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            $contentClassName = $cell->getContentClass();
            $call             = new $contentClassName($cell);
            $call->defineActionCallback();
            /*$cellContentId    = $cell->getLayoutContainerID();
            $cellId           = $cell->getID();
            $cellFormControls = $cell->getContentControlType();*/
        }
        /*if (is_array($this->formObjects)) {
            foreach ($this->formObjects as $cell_id => $object_id) {
                if (isset($id[$object_id])) {
                    $tmp_cell = null;
                    if ($_SESSION[MAIN] instanceof Session) {
                        $tmp_cell = $_SESSION[MAIN]->getCell($_SESSION[MAIN]->getIDByName($cell_id));
                    }
                    if ($tmp_cell instanceof Cell) {
                        $container_id = $tmp_cell->getLayoutContainerID();
                        if ($container_id instanceof Struct\Navigation_ID) {
                            $cell_id = $tmp_cell->getID();
                            if (isset($tmp_cell->content['form_fields']) && is_array($tmp_cell->content['form_fields'])) {
                                foreach ($tmp_cell->content['form_fields'] as $encrypted_name => $val) {
                                    if (isset($val['name']) && $val['name'] === $object_id) {
                                        if ($container_id instanceof Struct\Popup_ID) {
                                            if ($id[$object_id] === '' || $id[$object_id] === null) {
                                                $action['LCell'][$container_id->Popup_ID][$cell_id->ID]['clearNote'][$encrypted_name] = $id[$object_id];
                                            } else {
                                                $action['LCell'][$container_id->Popup_ID][$cell_id->ID]['setNote'][$encrypted_name] = $id[$object_id];
                                            }
                                        } else {
                                            if ($id[$object_id] === '' || $id[$object_id] === null) {
                                                $action['LCell'][$container_id->Tab_ID][$cell_id->ID]['clearNote'][$encrypted_name] = $id[$object_id];
                                            } else {
                                                $action['LCell'][$container_id->Tab_ID][$cell_id->ID]['setNote'][$encrypted_name] = $id[$object_id];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }*/
        return new Action\ActionResultMigrationHelper($action);
    }
}
