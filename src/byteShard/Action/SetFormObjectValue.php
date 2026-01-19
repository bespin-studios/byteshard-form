<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Form\Control\Calendar;
use byteShard\Form\Control\Checkbox;
use byteShard\Form\Control\Radio;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\Traits\Action\MethodCallback;
use Closure;
use DateTime;

/**
 * Class SetFormObjectValue
 * @package byteShard\Action
 */
class SetFormObjectValue extends Action
{
    use MethodCallback;

    private string               $cell;
    private ?Closure             $closure      = null;
    private array                $formControls = [];
    private ?string              $method       = null;
    private mixed                $methodParameter;
    private null|string|DateTime $newValue     = null;
    private ?object              $objectMap    = null;

    /**
     * SetFormObjectValue constructor.
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
     * @API
     * @param string|DateTime $value will be applied to all defined form objects
     * @return $this
     */
    public function setNewValue(string|DateTime $value): self
    {
        $this->newValue = $value;
        return $this;
    }

    /**
     * @API
     * @param Object $objectMap Object Properties will be matched to Form\Control\Objects
     * @return $this
     */
    public function setNewValuesObject(object $objectMap): self
    {
        $this->objectMap = $objectMap;
        return $this;
    }

    /**
     * @API
     * @param Closure $closure the return value of this anonymous function will be applied to all defined form objects
     * @return $this
     */
    public function setClosure(Closure $closure): self
    {
        $this->closure = $closure;
        return $this;
    }

    /**
     * The method must be declared as public in the target cell.
     * It will be called with up to two parameters.
     * The first one is always the client data.
     * If a second parameter is defined, whatever is defined as $parameter will be passed to the callback method
     * The callback method must return an array with the key 'newValue'. This will be applied to all defined form objects
     * The callback method can also return the array key 'run_nested' with a boolean value
     *
     * @API
     * @param string $method
     * @param mixed $parameter
     * @return $this
     */
    public function setCallbackMethod(string $method, mixed $parameter = null): self
    {
        $this->method = $method;
        if ($parameter !== null) {
            $this->methodParameter = $parameter;
        }
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

        $parameters = $this->getParameters($cell, $result);
        if (!empty($parameters)) {
            $action->addCellCommand([$this->cell], 'setObjectData', $parameters);
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

    private function getParameters(Cell $cell, object|string $valueMap): array
    {
        $parameters = [];
        foreach ($this->getFormControls($cell) as $encryptedId => $control) {
            $value = is_string($valueMap) || $valueMap instanceof DateTime
                ? $valueMap
                : ($valueMap->{$control['name']} ?? null);

            if ($value !== null) {
                $clientValue = $this->getClientValue($control['objectType'], $value, $control['radioValue']);
                if ($control['objectType'] === Radio::class) {
                    $parameters['radio'][$encryptedId] = $clientValue;
                } else {
                    $parameters[$encryptedId] = $clientValue;
                }
            }
        }
        return $parameters;
    }

    private function getFormControls(Cell $cell): array
    {
        $result   = [];
        $controls = $cell->getContentControlType();
        foreach ($this->formControls as $formControl) {
            [$controlName, $radioValue] = $this->parseFormControl($formControl);

            foreach ($controls as $encryptedId => $control) {
                if ($control['name'] === $controlName) {
                    $result[$encryptedId] = [
                        'name'       => $control['name'],
                        'objectType' => $control['objectType'],
                        'radioValue' => $this->resolveRadioValue($control, $radioValue),
                    ];
                }
            }
        }
        return $result;
    }

    private function parseFormControl(string $formControl): array
    {
        return str_contains($formControl, '::')
            ? explode('::', $formControl, 2)
            : [$formControl, null];
    }

    private function resolveRadioValue(array $control, ?string $radioValue): ?string
    {
        if ($radioValue === null || $control['objectType'] !== Radio::class) {
            return 'all_radio_options';
        }

        $encryptedRadioValue = array_search($radioValue, $control['radio_value'], true);
        return $encryptedRadioValue !== false ? $encryptedRadioValue : null;
    }

    private function getClientValue(string $objectType, mixed $objectValue, ?string $radioValue = null): mixed
    {
        if ($objectValue === null) {
            return null;
        }

        return match ($objectType) {
            Radio::class    => $radioValue !== null ? [$radioValue => (bool)$objectValue] : $objectValue,
            Checkbox::class => (bool)$objectValue,
            Calendar::class => $this->formatCalendarValue($objectValue),
            default         => $objectValue
        };
    }

    private function formatCalendarValue(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }
        throw new \Exception('DateTime expected in setFormObjectValue for object of type calendar');
    }
}
