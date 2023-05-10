<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Exception;
use byteShard\Form\Control\Calendar;
use byteShard\Form\Control\Checkbox;
use byteShard\Form\Control\Radio;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use Closure;
use DateTime;
use ReflectionClass;
use ReflectionException;

/**
 * Class SetFormObjectValue
 * @package byteShard\Action
 */
class SetFormObjectValue extends Action
{
    private string  $cell;
    private Closure $closure;
    private array   $formControls = [];
    private string  $method;
    private mixed   $methodParameter;
    private mixed   $newValue;
    private ?object $objectMap;

    /**
     * SetFormObjectValue constructor.
     * @param string $cell
     * @param string ...$objects
     */
    public function __construct(string $cell, string ...$objects)
    {
        parent::__construct();
        $this->cell = Cell::getContentCellName($cell);
        foreach ($objects as $object) {
            if ($object !== '') {
                $this->formControls[$object] = $object;
            }
        }
        $this->addUniqueID($this->cell, $this->formControls);
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
        // TODO: does this even work?
        // closures cannot be stored in the session...
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

    private function getFormControls(Cell $cell): array
    {
        $result   = [];
        $controls = $cell->getContentControlType();
        foreach ($this->formControls as $formControl) {
            $radioValue = null;
            if (str_contains($formControl, '::')) {
                [$formControl, $radioValue] = explode('::', $formControl);
            }
            foreach ($controls as $encryptedId => $control) {
                if ($control['name'] === $formControl) {
                    $result[$encryptedId] = [
                        'name'       => $control['name'],
                        'objectType' => $control['objectType'],
                        'radioValue' => 'all_radio_options'
                    ];
                    if ($radioValue !== null && $control['objectType'] === Radio::class) {
                        $encryptedRadioValue                = array_search($radioValue, $control['radio_value'], true);
                        $result[$encryptedId]['radioValue'] = $encryptedRadioValue !== false ? $encryptedRadioValue : null;
                    }
                }
            }
        }
        return $result;
    }

    /** @throws \Exception */
    private function getNewValue(string $formControl, string $objectType, ?string $radioValue, mixed $newValueByCallback): null|string|bool|array
    {
        $newValue = null;
        if (isset($this->objectMap)) {
            if (property_exists($this->objectMap, $formControl)) {
                $newValue = $this->objectMap->{$formControl};
            }
        } else {
            $newValue = $this->newValue ?? $newValueByCallback;
        }
        if ($newValue !== null) {
            switch ($objectType) {
                case Radio::class:
                    if ($radioValue !== null) {
                        $newValue = ($newValue) ? [$radioValue => true] : [$radioValue => false];
                    }
                    break;
                case Checkbox::class:
                    $newValue = (bool)$newValue;
                    break;
                case Calendar::class:
                    //TODO: get Server Date Format from Session, modify value
                    if ($newValue instanceof DateTime) {
                        $newValue = $newValue->format('Y-m-d');
                    } elseif (empty($newValue)) {
                        $newValue = '';
                    } else {
                        throw new \Exception('DateTime expected in setFormObjectValue for object of type calendar');
                    }
                    break;
            }
        }
        return $newValue;
    }

    /** @throws \Exception|Exception */
    protected function runAction(): ActionResultInterface
    {
        $id                 = $this->getLegacyId();
        $action             = [];
        $cells              = $this->getCells([$this->cell]);
        $newValueByCallback = null;
        if (isset($this->closure)) {
            $newValueByCallback = $this->closure->__invoke($id);
        }
        foreach ($cells as $cell) {
            if ($newValueByCallback === null && isset($this->method)) {
                $newValueByCallback = $this->getNewValueByCallbackMethod($cell, $id);
            }
            $controls = $this->getFormControls($cell);
            foreach ($controls as $encryptedId => $control) {
                $newValue = $this->getNewValue($control['name'], $control['objectType'], $control['radioValue'], $newValueByCallback);
                if ($newValue !== null) {
                    if ($control['objectType'] === Radio::class) {
                        $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData']['radio'][$encryptedId] = $newValue;
                    } else {
                        $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectData'][$encryptedId] = $newValue;
                    }
                }
            }
        }
        return new Action\ActionResultMigrationHelper($action);
    }

    /**
     * @param Cell $cell
     * @param $id
     * @return string|null
     * @throws Exception
     */
    private function getNewValueByCallbackMethod(Cell $cell, &$id): ?string
    {
        $result           = [];
        $contentClassName = $cell->getContentClass();
        if (class_exists($contentClassName) && method_exists($contentClassName, $this->method)) {
            try {
                $argumentTest     = new ReflectionClass($contentClassName);
                $reflectionMethod = $argumentTest->getMethod($this->method);
            } catch (ReflectionException $exception) {
                throw new Exception($exception->getMessage());
            }
            $numberOfParameters = $reflectionMethod->getNumberOfParameters();
            if (in_array($numberOfParameters, [0, 1, 2])) {
                $call = new $contentClassName($cell);
                $call->setProcessedClientData($this->getClientData());
                $call->setClientTimeZone($this->getClientTimeZone());
                if ($numberOfParameters === 2) {
                    $result = $call->{$this->method}($id, $this->methodParameter);
                } elseif ($numberOfParameters === 1) {
                    $result = $call->{$this->method}($id);
                } else {
                    $result = $call->{$this->method}();
                }
                if (is_array($result) && array_key_exists('run_nested', $result) && is_bool($result['run_nested'])) {
                    $this->setRunNested($result['run_nested']);
                    unset($result['run_nested']);
                }
            } else {
                $e = new Exception('Any method that will be called by Action\\SetFormObjectValue needs to have exactly one or two parameters');
                $e->setLocaleToken('byteShard.action.logicException.getNewValueByCallbackMethod.wrongParameterCount');
                throw $e;
            }
        }
        if (is_array($result) && array_key_exists('newValue', $result)) {
            return $result['newValue'];
        } elseif (is_string($result)) {
            return $result;
        }
        return null;
    }
}
