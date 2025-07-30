<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Combo\Option;
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
     * @param string ...$formItems
     */
    public function __construct(string $cell, string ...$formItems)
    {
        parent::__construct();
        $this->cell = Cell::getContentCellName($cell);
        if (!Cell::isFormContent($cell)) {
            Debug::error(__METHOD__.' Action can only be used in Form');
        }
        foreach ($formItems as $formItem) {
            if ($formItem !== '') {
                $this->formItems[$formItem] = $formItem;
            }
        }
        $this->addUniqueID($this->cell, $this->formItems);
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
    public function setComboOptions(Option|string|int ...$options)
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
    }

    protected function runAction(): ActionResultInterface
    {
        $id     = $this->getLegacyId();
        $modify = true;
        if (!empty($this->options)) {
            $selectedId = $id[key($id)];
            if (!array_key_exists($selectedId, $this->options)) {
                $modify = false;
            }
        }
        if ($modify === true) {
            $cells = $this->getCells([$this->cell]);
            foreach ($cells as $cell) {
                $controls = $cell->getContentControlType();
                foreach ($this->formItems as $formItem) {
                    if (str_contains($formItem, '::')) {
                        $item              = explode('::', $formItem);
                        $encryptedFormItem = $cell->getEncryptedName($item[0]);
                        if (isset($controls[$encryptedFormItem])) {
                            if ($controls[$encryptedFormItem]['objectType'] === Radio::class) {
                                $encryptedItemValue = array_search($item[1], $controls[$encryptedFormItem]['radio_value'], true);
                                if ($encryptedItemValue !== false) {
                                    $modification = $this->getModification($cell->getNonce());
                                    if ($modification !== null) {
                                        $action['LCell'][$cell->containerId()][$cell->cellId()]['modifyObjects']['radio'][$encryptedFormItem][$encryptedItemValue][$this->modification] = $modification;
                                    }
                                }
                            }
                        }
                    } else {
                        $encryptedFormItem = $cell->getEncryptedName($formItem);
                        if ($encryptedFormItem !== null) {
                            $modification = $this->getModification($cell->getNonce());
                            if ($modification !== null) {
                                if (isset($controls[$encryptedFormItem]) && $controls[$encryptedFormItem]['objectType'] === Radio::class) {
                                    $action['LCell'][$cell->containerId()][$cell->cellId()]['modifyObjects']['radio'][$encryptedFormItem]['all_radio_options'][$this->modification] = $modification;
                                } else {
                                    $action['LCell'][$cell->containerId()][$cell->cellId()]['modifyObjects'][$encryptedFormItem][$this->modification] = $modification;
                                }
                            }
                        }
                    }
                }
            }
        }
        $action['state'] = 2;
        return new Action\ActionResultMigrationHelper($action);
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
