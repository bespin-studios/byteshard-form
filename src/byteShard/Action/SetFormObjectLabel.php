<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Exception;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use Closure;
use ReflectionClass;
use ReflectionException;

/**
 * Class SetFormObjectLabel
 * @package byteShard\Action
 */
class SetFormObjectLabel extends Action
{
    private string  $cell;
    private array   $formControls = [];
    private ?object $objectMap    = null;
    private ?string $newValue     = null;

    private Closure $closure;
    private string  $method;
    private mixed   $methodParameter;


    /**
     * SetFormObjectLabel constructor.
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
        $id     = $this->getLegacyId();
        $action = [];
        $cells  = $this->getCells([$this->cell]);
        foreach ($cells as $cell) {
            if ($this->objectMap !== null) {
                foreach ($this->formControls as $formControlName) {
                    if (property_exists($this->objectMap, $formControlName)) {
                        $newValue                                                                                       = $this->objectMap->{$formControlName};
                        $encryptedClientName                                                                            = $cell->getEncryptedName($formControlName);
                        $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectLabel'][$encryptedClientName] = $newValue;
                    }
                }
            } else {
                $newValue = null;
                if ($this->newValue !== null) {
                    $newValue = $this->newValue;
                } elseif (isset($this->closure)) {
                    $newValue = $this->closure->__invoke($id);
                } elseif (isset($this->method)) {
                    $newValue = $this->getNewValueByCallbackMethod($cell, $id);
                }
                if ($newValue !== null) {
                    foreach ($this->formControls as $formControlName) {
                        $encryptedClientName = $cell->getEncryptedName($formControlName);
                        if ($encryptedClientName !== null) {
                            $action['LCell'][$cell->containerId()][$cell->cellId()]['setObjectLabel'][$encryptedClientName] = $newValue;
                        }
                    }
                }
            }
        }
        return new Action\ActionResultMigrationHelper($action);
    }

    /**
     * @param Cell $cell
     * @param $id
     * @return null|string|array
     * @throws Exception|ReflectionException
     */
    private function getNewValueByCallbackMethod(Cell $cell, &$id): null|string|array
    {
        $result           = [];
        $contentClassName = $cell->getContentClass();
        if (class_exists($contentClassName) && method_exists($contentClassName, $this->method)) {
            $argumentTest     = new ReflectionClass($contentClassName);
            $reflectionMethod = $argumentTest->getMethod($this->method);
            if ($reflectionMethod->getNumberOfParameters() === 2) {
                $call   = new $contentClassName($cell);
                $result = $call->{$this->method}($id, $this->methodParameter);
                if (is_array($result) && array_key_exists('run_nested', $result) && is_bool($result['run_nested'])) {
                    $this->setRunNested($result['run_nested']);
                    unset($result['run_nested']);
                }
            } elseif ($reflectionMethod->getNumberOfParameters() === 1) {
                $call   = new $contentClassName($cell);
                $result = $call->{$this->method}($id);
                if (is_array($result) && array_key_exists('run_nested', $result) && is_bool($result['run_nested'])) {
                    $this->setRunNested($result['run_nested']);
                    unset($result['run_nested']);
                }
            } else {
                $e = new Exception('Any method that will be called by Action\\SetFormObjectValue needs to have exactly one or two parameters');
                $e->setLocaleToken('byteShard.action.exception.getNewValueByCallbackMethod.wrongParameterCount');
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
