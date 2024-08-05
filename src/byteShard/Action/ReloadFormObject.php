<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Combo\Option;
use byteShard\Exception;
use byteShard\Form;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Combo\Combo;
use byteShard\Internal\Form\FormObject;
use Closure;

/**
 * Class ReloadFormObject
 * @package byteShard\Action
 */
class ReloadFormObject extends Action
{
    private string $cell;
    private array  $formItems = [];

    /**
     * ReloadFormObject constructor.
     * use method defineComboOptions($formControlId, $clientData)
     *
     * @param string $cell
     * @param string ...$formItems
     * @throws Exception
     */
    public function __construct(string $cell, string ...$formItems)
    {
        parent::__construct();
        $this->cell = Cell::getContentClassName($cell, 'Form', __METHOD__);
        foreach ($formItems as $formItem) {
            if ($formItem !== '') {
                $this->formItems[$formItem] = $formItem;
            }
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $id    = $this->getLegacyId();
        $cells = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            $className   = $cell->getContentClass();
            $cellContent = new $className($cell);
            if ($cellContent instanceof Form) {
                if ($cellContent->hasComboContent() === true) {
                    // deprecated
                    $cellFormControls = $cell->getContentControlType();
                    $clientData       = $this->decryptData($id, $cellFormControls);
                    foreach ($this->formItems as $itemToReload) {
                        $encryptedName = $cell->getEncryptedName($itemToReload);
                        if ($encryptedName !== null && array_key_exists($encryptedName, $cellFormControls) && $cellFormControls[$encryptedName]['objectType'] === Form\Control\Combo::class) {
                            $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$encryptedName] = $cellContent->getComboOptions($itemToReload, $clientData);
                        }
                    }
                } else {
                    $controls = $cellContent->getFormControls();
                    foreach ($controls as $control) {
                        $this->processControl($control, $cell, $action);
                    }
                }
            }
        }
        $action['state'] = 2;
        return new Action\ActionResultMigrationHelper($action);
    }

    private function processControl($control, $cell, &$action): void
    {
        if (array_key_exists($control->getName(), $this->formItems)) {
            $encryptedName = $this->getEncryptedName($cell, $control);
            if ($encryptedName !== null) {
                if ($control instanceof Form\Control\Combo && $control->getComboClass() !== '') {
                    $action['LCell'][$cell->containerId()][$cell->cellId()]['reloadFormObject'][$encryptedName] = $control->getUrl($cell);
                } else {
                    $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$encryptedName] = $this->getComboContent($control);
                }
            }
        }
        $nested = $control->getNestedItems();
        foreach ($nested as $nestedControl) {
            $this->processControl($nestedControl, $cell, $action);
        }
    }

    private function getEncryptedName(Cell $cell, FormObject $control): ?string
    {
        return $cell->getEncryptedName($control->getName());
    }

    private function getComboContent(FormObject $control): string
    {
        $binding = $control->getDataBinding();
        //TODO: streamline combo so that array/object-array can be passed directly
        if ($binding instanceof Closure) {
            $opts = $binding();
            if (is_array($opts)) {
                $options = [];
                foreach ($opts as $opt) {
                    if ($opt instanceof Option) {
                        $options[] = $opt;
                    } else {
                        $opt = (array)$opt;
                        $opt = array_change_key_case($opt);
                        if (isset($opt['value'])) {
                            $options[] = new Option($opt['value'], $opt['text'] ?? null, $opt['selected'] ?? false, $opt['image'] ?? null);
                        }
                    }
                }
                $combo = new Combo();
                $combo->setOptions(...$options);
                return $combo->getXML();
            }
        } else {
            print $control->getName();
        }
        return '';
    }

    /**
     * @param $id
     * @param $form_controls
     * @return array
     */
    private function decryptData($id, $form_controls): array
    {
        $data = [];
        if (is_array($id)) {
            foreach ($id as $object_name => $value) {
                if (array_key_exists($object_name, $form_controls)) {
                    $data[$form_controls[$object_name]['name']]          = $form_controls[$object_name];
                    $data[$form_controls[$object_name]['name']]['value'] = $value;
                }
            }
        }
        return $data;
    }
}
