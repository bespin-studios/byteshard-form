<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Traits\Action\MethodCallback;
use Closure;

/**
 * Class SetFormObjectLabel
 * @package byteShard\Action
 */
class SetFormObjectLabel extends Action
{
    use MethodCallback;

    private string  $cell;
    private array   $formControls = [];
    private ?object $objectMap    = null;
    private ?string $newValue     = null;

    private ?Closure $closure = null;
    private ?string  $method  = null;
    private mixed    $methodParameter;


    /**
     * SetFormObjectLabel constructor.
     * @param string $cell
     * @param string ...$formControls
     */
    public function __construct(string $cell, string ...$formControls)
    {
        $this->cell = Cell::getContentCellName($cell);
        foreach ($formControls as $formControl) {
            if ($formControl !== '') {
                $this->formControls[$formControl] = $formControl;
            }
        }
    }

    /**
     * @param string $value will be applied to all defined form objects
     * @return $this
     * @API
     */
    public function setNewValue(string $value): self
    {
        $this->newValue = $value;
        return $this;
    }

    /**
     * @param object|null $objectMap Object Properties will be matched to Form\Control\Objects
     * @return $this
     * @API
     */
    public function setNewValuesObject(?object $objectMap): self
    {
        $this->objectMap = $objectMap;
        return $this;
    }

    /**
     * @param Closure $closure the return value of this anonymous function will be applied to all defined form objects
     * @return $this
     * @API
     */
    public function setClosure(Closure $closure): self
    {
        $this->closure = $closure;
        return $this;
    }

    /**
     * The method must be declared as public in the target cell.
     * It will be called and the $parameters are unpacked
     * The callback method can return a string or a numeric or an array or an object
     * If the callback method returns an array or an object a property/key named newValue must exist and contain a string
     * In addition the array/object can contain a property/key named runNested with a boolean
     *
     * @param string $method
     * @param mixed $parameter
     * @return $this
     * @API
     */
    public function setCallbackMethod(string $method, mixed $parameter = null): self
    {
        $this->method          = $method;
        $this->methodParameter = $parameter;
        return $this;
    }

    protected function runAction(): ActionResultInterface
    {
        $action = new Action\CellActionResult(Action\ActionTargetEnum::Cell);
        $cells  = $this->getCells([$this->cell]);
        if (empty($cells)) {
            return $action;
        }
        $cell   = $cells[0];
        $result = $this->resolveValue($cell);

        if ($result === null) {
            return $action;
        }
        if (is_object($result)) {
            $parameters = $this->buildObjectMapParameters($cell, $result);
        } elseif (is_string($result)) {
            $parameters = $this->buildScalarParameters($cell, $result);
        }

        if (!empty($parameters)) {
            $action->addCellCommand([$this->cell], 'setObjectLabel', $parameters);
        }
        return $action;
    }

    private function resolveValue(Cell $cell): string|null|object
    {
        if ($this->objectMap !== null) {
            return $this->objectMap;
        }
        if ($this->newValue !== null) {
            return $this->newValue;
        }
        if (isset($this->closure)) {
            return ($this->closure)();
        }
        if ($this->method !== null) {
            return $this->getNewValueByCallbackMethod($cell, $this->method, $this->methodParameter);
        }
        return null;
    }

    private function buildObjectMapParameters(Cell $cell, object $objectMap): array
    {
        $parameters = [];
        foreach ($this->formControls as $formControlName) {
            if (property_exists($objectMap, $formControlName)) {
                $encryptedClientName = $cell->getEncryptedName($formControlName);
                if ($encryptedClientName !== null) {
                    $parameters[$encryptedClientName] = $objectMap->{$formControlName};
                }
            }
        }
        return $parameters;
    }

    private function buildScalarParameters(Cell $cell, string $value): array
    {
        $parameters = [];
        foreach ($this->formControls as $formControlName) {
            $encryptedClientName = $cell->getEncryptedName($formControlName);
            if ($encryptedClientName !== null) {
                $parameters[$encryptedClientName] = $value;
            }
        }
        return $parameters;
    }
}
