<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Combo;
use byteShard\Combo\Option;
use byteShard\Exception;
use byteShard\Form;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
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
                    $cell_form_controls = $cell->getContentControlType();
                    $client_data        = $this->decryptData($id, $cell_form_controls);
                    foreach ($this->formItems as $item_to_reload) {
                        $encrypted_name = $cell->getEncryptedName($item_to_reload);
                        if ($encrypted_name !== null && array_key_exists($encrypted_name, $cell_form_controls)) {
                            switch ($cell_form_controls[$encrypted_name]['objectType']) {
                                case Form\Control\Combo::class:
                                    $combo = $cellContent->getComboOptions($item_to_reload, $client_data);

                                    $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$encrypted_name] = $combo;
                                    break;
                            }
                        }
                    }
                } else {
                    $controls = $cellContent->getFormControls();
                    foreach ($controls as $control) {
                        if (array_key_exists($control->getName(), $this->formItems)) {
                            if ($control instanceof Form\Control\Combo) {
                                $comboClass = $control->getComboClass();
                                if ($comboClass !== '') {
                                    $class = new $comboClass();
                                    /** @var Combo $class */
                                    $class->setParameters($control->getComboParameters());
                                    $comboContent = $class->getComboContents($cell->getContentClass());

                                    $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$this->getEncryptedName($cell, $control)] = $comboContent;
                                } else {
                                    $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$this->getEncryptedName($cell, $control)] = $this->getComboContent($control);
                                }
                            } else {
                                $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$this->getEncryptedName($cell, $control)] = $this->getComboContent($control);
                            }
                        } else {
                            $nested = $control->getNestedItems();
                            //TODO: recursion
                            foreach ($nested as $nestedControl) {
                                if (array_key_exists($nestedControl->getName(), $this->formItems)) {
                                    if ($nestedControl instanceof Form\Control\Combo) {
                                        $comboClass = $nestedControl->getComboClass();
                                        if ($comboClass !== '') {
                                            $class        = new $comboClass();
                                            $comboContent = $class->getComboContents($cell->getContentClass());

                                            $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$this->getEncryptedName($cell, $nestedControl)] = $comboContent;
                                        } else {
                                            $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$this->getEncryptedName($cell, $nestedControl)] = $this->getComboContent($nestedControl);
                                        }
                                    } else {
                                        $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$this->getEncryptedName($cell, $nestedControl)] = $this->getComboContent($nestedControl);
                                    }
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

    private function getEncryptedName(Cell $cell, FormObject $control)
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
                $combo = new \byteShard\Internal\Combo\Combo();
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
