<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Form;

use byteShard\Cell;
use byteShard\Form;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Form\FormObject;

/**
 * Class AddControl
 * @API
 * @package byteShard\Action
 */
class AddControl extends Action
{
    /** @var FormObject[] */
    private array  $formControls;
    private string $cell;
    private string $position = '';
    private int    $offset   = 0;

    /**
     * AddControl constructor.
     * @param string $cell
     * @param FormObject ...$formControls
     */
    public function __construct(string $cell, FormObject ...$formControls)
    {
        $this->cell         = Cell::getContentCellName($cell);
        $this->formControls = $formControls;
    }

    /**
     * @API
     */
    public function positionAfter(string $formObject): self
    {
        $this->position = $formObject;
        return $this;
    }

    /**
     * @API
     */
    public function setPositionOffset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $cells = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            $className = $cell->getContentClass();
            $form      = new $className($cell);
            if ($form instanceof Form) {
                $action['LCell'][$cell->containerId()][$cell->cellId()]['addItem'] = [
                    'items'    => json_decode($form->getControlsForAction(...$this->formControls)),
                    'position' => $cell->getEncryptedName($this->position),
                    'offset'   => $this->offset
                ];

                $objectProperties = $this->getObjectProperties();
                foreach ($this->formControls as $formObject) {
                    $proxy            = new FormObject\Proxy($formObject, $cell, $cell->getAccessType(), null, $cell->getNonce());
                    $objectProperties = array_merge($objectProperties, $proxy->getObjectProperties());
                }
                //TODO: create a setter in the actionResultInterface, merge object properties and return a merged string
                $action['LCell'][$cell->containerId()][$cell->cellId()]['op'] = Form::getObjectProperties($objectProperties);
            }
        }
        $action['state'] = 2;
        return new Action\ActionResultMigrationHelper($action);
    }
}
