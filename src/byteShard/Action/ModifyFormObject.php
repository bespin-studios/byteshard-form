<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Combo\Option;
use byteShard\DynamicCellContent;
use byteShard\Form\Control\Radio;
use byteShard\ID\ID;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Action\ClientExecutionInterface;
use byteShard\Internal\Debug;
use byteShard\Internal\Form\FormObject;

/**
 * Class ModifyFormObject
 * @package byteShard\Action
 */
abstract class ModifyFormObject extends Action implements ClientExecutionInterface
{
    use Action\ClientExecutionTrait;

    private string            $cell;
    private array             $formItems         = [];
    private array             $options           = [];
    protected string          $modification;
    protected bool|int|string $modificationValue = true;
    protected string          $clientModification;

    /**
     * ModifyFormObject constructor.
     * @param string $cell
     * @param string ...$formControls
     */
    public function __construct(string $cell, string ...$formControls)
    {
        $this->cell = Cell::getContentCellName($cell);
        foreach ($formControls as $formControl) {
            if ($formControl !== '') {
                $this->formItems[$formControl] = $formControl;
            }
        }
    }

    public function getClientExecutionMethod(): string
    {
        return $this->clientModification;
    }

    /**
     * @param ID $containerId
     * @return array<string> form object ids
     */
    public function getClientExecutionItems(ID $containerId): array
    {
        // because cell nonce of a target cell might change because of asynchronous load order or reloading of a target cell, we can only have client execution on the same cell
        $currentCell = $containerId->getEncodedCellId(false);
        $cells       = $this->getCells([$this->cell], $containerId);
        $result      = [];
        foreach ($cells as $cell) {
            if ($currentCell === $cell->getEncodedId()) {
                foreach ($this->formItems as $formItem) {
                    $result[] = FormObject\Proxy::getEncryptedClientName($formItem, $cell->getNonce());
                }
            } else {
                Debug::error(get_called_class().': client execution is currently only possible in the same cell');
            }
        }
        return $result;
    }

    /**
     * restrict the action to only run on the defined options
     * this only works if the action is attached to a combo
     * @API
     */
    public function setComboOptions(Option|string|int ...$options): static
    {
        foreach ($options as $option) {
            if ($option instanceof Option) {
                $option_value                 = $option->getValue();
                $this->options[$option_value] = $option_value;
            } else {
                /**@var int|string $options */
                $this->options[(string)$option] = (string)$option;
            }
        }
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $modify = true;
        if (!empty($this->options)) {
            $eventId    = $this->getActionInitDTO()->eventId;
            $selectedId = (string)$this->getClientData()->{$eventId};
            if (!array_key_exists($selectedId, $this->options)) {
                $modify = false;
            }
        }
        $action = new Action\CellActionResult(Action\ActionTargetEnum::Cell);
        if ($modify === false) {
            return $action;
        }

        $cells = $this->getCells([$this->cell]);
        if ($cells === null) {
            return $action;
        }
        $cell     = $cells[0];
        $controls = $cell->getContentControlType();

        $param = [];
        foreach ($this->formItems as $formItem) {
            $param[] = $this->buildModificationParam($cell, $controls, $formItem);
        }
        if (!empty($param)) {
            $action->addCellCommand([$this->cell], 'modifyObjects', array_merge_recursive(...$param));
        }
        return $action;
    }

    private function buildModificationParam(Cell $cell, array $controls, string $formItem): array
    {
        [$encryptedFormItem, $radioValue] = $this->parseFormItem($cell, $formItem);
        if ($encryptedFormItem === null || !isset($controls[$encryptedFormItem])) {
            return [];
        }

        $modification = $this->getModification($cell->getNonce());
        if ($modification === null) {
            return [];
        }
        if ($controls[$encryptedFormItem]['objectType'] === Radio::class) {
            if ($radioValue !== null) {
                return $this->buildRadioSpecificParam($controls[$encryptedFormItem], $encryptedFormItem, $radioValue, $modification);
            }
            return ['radio' => [$encryptedFormItem => ['all_radio_options' => [$this->modification => $modification]]]];
        }
        return [$encryptedFormItem => [$this->modification => $modification]];
    }

    private function parseFormItem(Cell $cell, string $formItem): array
    {
        [$controlName, $radioValue] = str_contains($formItem, '::')
            ? explode('::', $formItem, 2)
            : [$formItem, null];
        return [$cell->getEncryptedName($controlName), $radioValue];
    }

    private function buildRadioSpecificParam(array $control, string $encryptedFormItem, string $radioValue, mixed $modification): array
    {
        $encryptedRadioValue = array_search($radioValue, $control['radio_value'], true);
        if ($encryptedRadioValue === false) {
            return [];
        }
        return ['radio' => [$encryptedFormItem => [$encryptedRadioValue => [$this->modification => $modification]]]];
    }

    private function getModification(string $cellNonce): mixed
    {
        if ($this instanceof Form\SelectComboOption) {
            return $this->getComboOptionToSelect($cellNonce);
        }
        if ($this instanceof SetRequiredFormObject) {
            return true;
        }
        if ($this instanceof UnsetRequiredFormObject) {
            return false;
        }
        return $this->modification;
    }
}
