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
use byteShard\Internal\ContentClassFactory;
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
        $this->cell      = Cell::getContentClassName($cell, 'Form', __METHOD__);
        foreach ($formItems as $formItem) {
            if ($formItem !== '') {
                $this->formItems[$formItem] = $formItem;
            }
        }
    }

    protected function runAction(): ActionResultInterface
    {
        $action = new Action\CellActionResult();
        $cells  = $this->getCells([$this->cell]);
        if (empty($cells)) {
            return $action;
        }
        $cell        = $cells[0];
        $cellContent = ContentClassFactory::cellContent($cell->getContentClass(), '', $cell);

        if (!($cellContent instanceof Form)) {
            return $action;
        }

        [$reloadParams, $setDataParams] = $this->collectControlParameters($cellContent->getFormControls(), $cell);

        if (!empty($reloadParams)) {
            $action->addCellCommand([$this->cell], 'reloadFormObject', $reloadParams);
        }

        if (!empty($setDataParams)) {
            $action->addCellCommand([$this->cell], 'setObjectData', $setDataParams);
        }

        return $action;
    }

    private function collectControlParameters(array $controls, Cell $cell): array
    {
        $reloadParams  = [];
        $setDataParams = [];

        foreach ($controls as $control) {
            $this->processControl($control, $cell, $reloadParams, $setDataParams);
        }

        return [$reloadParams, $setDataParams];
    }

    private function processControl($control, Cell $cell, array &$reloadParams, array &$setDataParams): void
    {
        if (array_key_exists($control->getName(), $this->formItems)) {
            $encryptedName = $cell->getEncryptedName($control->getName());

            if ($encryptedName !== null) {
                if ($control instanceof Form\Control\Combo && $control->getComboClass() !== '') {
                    $reloadParams[$encryptedName] = $control->getUrl($cell);
                } else {
                    $setDataParams[$encryptedName] = $this->getComboContent($control);
                }
            }
        }

        foreach ($control->getNestedItems() as $nestedControl) {
            $this->processControl($nestedControl, $cell, $reloadParams, $setDataParams);
        }
    }

    private function getComboContent(FormObject $control): string
    {
        $binding = $control->getDataBinding();

        if (!($binding instanceof Closure)) {
            return '';
        }

        $opts = $binding();
        if (!is_array($opts)) {
            return '';
        }

        $options = array_filter(array_map(fn($opt) => $this->normalizeOption($opt), $opts));

        $combo = new Combo();
        $combo->setOptions(...$options);
        return $combo->getXML();
    }

    private function normalizeOption(mixed $opt): ?Option
    {
        if ($opt instanceof Option) {
            return $opt;
        }

        $opt = (array)$opt;
        $opt = array_change_key_case($opt);

        if (!isset($opt['value'])) {
            return null;
        }

        return new Option($opt['value'], $opt['text'] ?? null, $opt['selected'] ?? false, $opt['image'] ?? null
        );
    }
}
