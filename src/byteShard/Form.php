<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Enum\ContentFormat;
use byteShard\Enum\ContentType;
use byteShard\Form\Control;
use byteShard\Form\FormInterface;
use byteShard\Form\FormSettingsInterface;
use byteShard\Form\Settings;
use byteShard\Internal\CellContent;
use byteShard\Internal\ClientData\ProcessedClientData;
use byteShard\Internal\ClientData\ProcessedClientDataInterface;
use byteShard\Internal\Form\CollectionInterface;
use byteShard\Internal\Form\DateValueInterface;
use byteShard\Internal\Form\FormObject;
use byteShard\Internal\Form\FormObject\Proxy;
use byteShard\Internal\Form\Nested;
use byteShard\Internal\Form\ValueInterface;
use byteShard\Internal\SimpleXML;
use byteShard\Internal\Struct\ClientCell;
use byteShard\Internal\Struct\ClientCellEvent;
use byteShard\Internal\Struct\ClientCellProperties;
use byteShard\Internal\Struct\ContentComponent;
use Closure;
use DateTime;
use SimpleXMLElement;

/**
 * Class Form
 * @package byteShard
 */
abstract class Form extends CellContent implements FormInterface
{
    protected string $cellContentType = 'DHTMLXForm';

    /**
     * @var FormObject[]
     */
    private array $formObjects          = [];
    private array $formObjectParameters = [];

    private ?FormSettingsInterface $formSettings = null;
    private array     $eventArray   = [];
    private string    $query        = '';


    private ?object $data_binding;

    /**
     * @var Proxy[]
     */
    private array   $internalFormObjects = [];
    private ?string $pollId              = null;

    // Events
    private bool  $event_on_button_click          = false;
    private bool  $event_on_change                = false;
    private bool  $event_on_input_change          = false;
    private bool  $event_on_close_button_click    = false;
    private bool  $event_on_info                  = false;
    private bool  $event_on_poll                  = false;
    private bool  $event_on_show_help             = false;
    private bool  $event_on_upload_file           = false;
    private bool  $event_on_blur                  = false;
    private bool  $eventOnUnrestrictedButtonClick = false;
    private bool  $has_dependency_validation      = false;
    private bool  $use_single_file_mode           = false;
    private array $clientEvents                   = [];
    private array $inputChangeObjects             = [];
    private array $combosWithNewEntries           = [];

    // Parameters
    private bool $liveValidation = false;
    private bool $lock           = false;
    /**
     * @var array store ids of form controls which support input help
     */
    private array $inputHelpObjects = [];
    /**
     * @var bool override dhtmlx controls: set placeholder attribute
     */
    private bool   $has_placeholders     = false;
    private string $alignContent         = '';
    private string $className            = '';
    private array  $objectProperties     = [];
    private array  $selectedComboOptions = [];
    private array  $asynchronousControls = [];

    /**
     * Form constructor.
     * @param Cell $cell
     * @throws Exception
     */
    public function __construct(Cell $cell)
    {
        parent::__construct($cell);
    }

    /**
     * @return void
     * @API
     */
    public function alignContent(): void
    {
        $this->alignContent = 'center';
    }

    /**
     * set an addition css class on the forms div
     * @param string $className
     * @return $this
     * @API
     */
    public function setFormClass(string $className): self
    {
        $this->className = $className;
        return $this;
    }

    /**
     * @param string $encryptedName
     * @return string
     * @API
     */
    public function getControlName(string $encryptedName): string
    {
        $controls = $this->cell->getContentControlType();
        if (array_key_exists($encryptedName, $controls)) {
            return $controls[$encryptedName]['name'];
        }
        return '';
    }

    /**
     * @session none
     * @param string $query
     * @return $this
     * @API
     */
    protected function setQuery(string $query): self
    {
        trigger_error('setQuery is deprecated. Use defineDataBinding instead.', E_USER_DEPRECATED);
        $this->query = $query;
        return $this;
    }

    /**
     * @session none
     * @throws Exception
     * @deprecated
     */
    private function queryData(): void
    {
        if ($this->query !== '') {
            trigger_error('Setting Values using setQuery is deprecated. Use defineDataBinding instead.', E_USER_DEPRECATED);
            $this->data_binding = Database::getSingle($this->query);
        }
    }

    /**
     * @throws Exception
     * @throws \Exception
     * @internal
     */
    public function getCellContent(bool $resetNonce = true): ?ClientCell
    {
        parent::getCellContent($resetNonce);
        $this->cell->clearContentObjectTypes();
        $nonce = $this->cell->getNonce();
        switch ($this->getAccessType()) {
            case Enum\AccessType::NONE:
                unset($this->formObjects);
                $this->addFormObject(new Form\Control\Label(Locale::get('byteShard.form.cell.Label.noPermission.Label'), 'noPermission'));
                $this->evaluate($nonce);
                $this->lock = true;
                break;
            case Enum\AccessType::R:
                $this->defineCellContent();
                if ($this->hasFallbackContent()) {
                    return $this->getFallbackContent()->getCellContent(false);
                }
                $this->queryData();
                $this->data_binding = $this->defineDataBinding();
                $this->evaluate($nonce);
                $this->lock = true;
                break;
            case Enum\AccessType::RW:
                $this->setRequestTimestamp();
                $this->defineCellContent();
                if ($this->hasFallbackContent()) {
                    return $this->getFallbackContent()->getCellContent(false);
                }
                $this->queryData();
                $this->data_binding = $this->defineDataBinding();
                $this->evaluate($nonce);
                break;
        }
        $components = parent::getComponents();
        $this->evaluateContentEvents();
        session_write_close();
        switch ($this->cell->getContentFormat()) {
            case 'JSON':
                $components[] = new ContentComponent(
                    type   : ContentType::DhtmlxForm,
                    content: $this->getJSON(),
                    events : $this->getCellEvents(),
                    setup  : $this->getPreParameters($nonce),
                    update : $this->getPostParameters(),
                    format : ContentFormat::JSON
                );
                break;
            case 'XML':
                $components[] = new ContentComponent(
                    type   : ContentType::DhtmlxForm,
                    content: $this->getXML(),
                    events : $this->getCellEvents(),
                    setup  : $this->getPreParameters($nonce),
                    update : $this->getPostParameters(),
                    format : ContentFormat::XML
                );
                break;
        }
        return new ClientCell(
            new ClientCellProperties(
                nonce                  : $nonce,
                cellHeader             : $this->getCellHeader(),
                pollId                 : $this->pollId,
                hasAsynchronousElements: !empty($this->asynchronousControls)
            ),
            ...$components,
        );
    }

    /**
     * @return object|null
     * @throws Exception
     */
    public function defineDataBinding(): ?object
    {
        // TODO: this method should actually be called in each cell next to defineCellContent so slower data bindings don't have to be executed every time
        // furthermore this should reduce class dependencies because Database::getArray / getSingle would be called in the cell class instead of this class
        // TODO find what Lars intended with this function and implement
        if ($this->query !== '') {
            trigger_error('Setting Values using setQuery is deprecated. Use defineDataBinding instead.', E_USER_DEPRECATED);
            return Database::getSingle($this->query);
        }
        return null;
    }

    /**
     * @param array $file
     * @return void
     * @API
     */
    public function byteShardFileUploadHandler(array $file): void
    {
        $this->cell->setUploadedFileInformation($file);
    }

    /**
     * @param FormObject ...$formObjects
     * @return $this
     */
    public function addFormObject(Internal\Form\FormObject ...$formObjects): self
    {
        foreach ($formObjects as $formObject) {
            //TODO: check if addCell, setSelectedId and setNestedSelectedIds has to be called here or can be moved to a later stage
            $formObject->addCell($this->cell);
            if ($formObject instanceof Control\Combo) {
                $formObject->setSelectedID($this->cell->getContentSelectedID($formObject->getName()));
            }
            $traits = class_uses($formObject);
            if (array_key_exists(Nested::class, $traits)) {
                /** @phpstan-ignore-next-line */
                $formObject->setNestedSelectedIds();
            }
            $this->formObjects[] = $formObject;
        }
        return $this;
    }

    public function addFormSettings(FormSettingsInterface $formSettings): self
    {
        $this->formSettings = $formSettings;
        return $this;
    }

    /**
     * @session write (Cell::setRequestTimestamp)
     * store client request time
     * only needed for cells with write access
     */
    private function setRequestTimestamp(): void
    {
        $this->cell->setRequestTimestamp();
    }

    /**
     * @session read (Cell::getName, Cell::getID)
     * @session write (Form::evaluateFormObject)
     * @throws Exception
     */
    private function evaluate(string $nonce): void
    {
        if (!empty($this->formObjects)) {
            $randomIdArray     = [];
            $localeToken       = $this->cell->createLocaleBaseToken('Cell').'.Form.';
            $defaultInputWidth = $this->formSettings?->getInputWidth();
            foreach ($this->formObjects as $formObject) {
                $formObjectId = $formObject->getFormObjectId();
                if (isset($this->data_binding) && is_object($this->data_binding) && property_exists($this->data_binding, $formObjectId)) {
                    if ($formObject instanceof ValueInterface) {
                        if ($formObject->getValue() === null) {
                            $formObject->setValue($this->data_binding->{$formObjectId});
                        }
                    } elseif ($formObject instanceof DateValueInterface) {
                        if ($formObject->getValue() === '' && is_string($this->data_binding->{$formObjectId}) || $this->data_binding->{$formObjectId} instanceof DateTime) {
                            $formObject->setValue($this->data_binding->{$formObjectId});
                        }
                    }
                }
                if ($formObject->getName() === '') {
                    do {
                        $randomName = substr(md5(random_bytes(64)), 0, 6);
                        if (in_array($randomName, $randomIdArray)) {
                            $randomName = '';
                        }
                    } while ($randomName === '');
                    $randomIdArray[] = $randomName;
                    $formObject->setRandomNameForObjectsWithoutId($randomName);
                }
                if ($formObject instanceof CollectionInterface) {
                    $elements = $formObject->getElements();
                    foreach ($elements as $element) {
                        $element->setLocaleBaseToken($localeToken);
                        $this->internalFormObjects[] = new Proxy($element, $this->cell, $this->getAccessType(), $defaultInputWidth, $nonce);
                    }
                } else {
                    $formObject->setLocaleBaseToken($localeToken);
                    $this->internalFormObjects[] = new Proxy($formObject, $this->cell, $this->getAccessType(), $defaultInputWidth, $nonce);
                }
            }
            foreach ($this->internalFormObjects as $formObject) {
                if ($formObject->getAccessType() > 0) {
                    $this->evaluateFormObject($formObject);
                }
            }
        }
    }

    /**
     * this will call defineCellContent without data bindings
     * @return FormObject[]
     * @internal
     */
    public function getFormControls(): array
    {
        $this->defineCellContent();
        return $this->formObjects;
    }

    /**
     * set the value or options to a form control
     * each form object has a binding property which defaults to 'this'
     * data binding can be omitted by setting the binding property to null
     * alternatively a closure can be set on each form object
     * @param Proxy $formObject
     */
    private function bindData(Proxy $formObject): void
    {
        if ($formObject->binding === null) {
            return;
        }
        if (is_string($formObject->binding)) {
            if ($formObject->binding === 'this' || trim($formObject->binding, '\\') === trim(get_class($this), '\\')) {
                if ($this->data_binding !== null && isset($this->data_binding->{$formObject->internalName})) {
                    $formObject->setValueFromData($this->data_binding->{$formObject->internalName});
                }
            }
            return;
        }
        if ($formObject->binding instanceof Closure) {
            $closure = $formObject->binding;
            $formObject->setValueFromData($closure());
        }
    }

    /**
     * returns an array with all defined form controls
     * @return FormObject[]
     * @API
     */
    public function getFormObjects(): array
    {
        return $this->formObjects;
        //this method was not used anywhere. it used to have the following implementation:
        //return $this->cell->getContentControlType();
    }

    /**
     * @param FormObject ...$formControls
     * @return string
     * @throws Exception
     */
    public function getControlsForAction(FormObject ...$formControls): string
    {
        $this->addFormObject(...$formControls);
        $this->evaluate($this->getNonce());
        $this->evaluateContentEvents();
        return $this->getJSON();
    }


    /**
     * setFormObjectType: store relevant form objects in the session
     * Not only RW Objects have to be stored, e.g. hidden objects probably are relevant as well
     * TODO: method to complex, refactor
     *
     * @session write
     * @param Proxy $formObject
     * @throws Exception
     */
    private function evaluateFormObject(Internal\Form\FormObject\Proxy $formObject): void
    {
        //events and validation only for objects with write access
        //change name for all objects with write access
        //TODO: change name to md5


        $alterations = $formObject->register($this->cell);
        if ($alterations->isAsynchronous() === true) {
            $this->asynchronousControls[] = $alterations->getName();
        }
        foreach ($alterations->getProperties() as $objectProperties) {
            $this->objectProperties[] = $objectProperties;
        }
        foreach ($alterations->getEvents() as $eventName) {
            if ($eventName === 'event_on_input_change') {
                $this->inputChangeObjects[] = $alterations->getName();
            }
            $this->{$eventName} = true;
        }
        if ($formObject->getComboAllowsNewEntries() === true) {
            $this->combosWithNewEntries[] = $alterations->getName();
        }
        if ($alterations->getHelpObject() !== '') {
            $this->inputHelpObjects[] = $alterations->getHelpObject();
        }
        if (!empty($alterations->getArrayOfMethodsWhichWillBeExecutedOnTheClient())) {
            $this->clientEvents = array_merge_recursive($alterations->getArrayOfMethodsWhichWillBeExecutedOnTheClient());
        }
        if (!empty($alterations->getParameters())) {
            $this->formObjectParameters[$alterations->getName()] = $alterations->getParameters();
        }
        if ($alterations->isSetOptions() === true) {
            $data = property_exists($formObject, 'data') ? $formObject->data : null;
            if (method_exists($this, 'defineComboOptions')) {
                $formObject->setOptions($this->defineComboOptions($formObject->internalName, $data));
            } elseif (method_exists($this, 'defineComboContent')) {
                trigger_error('Method defineComboContent is deprecated. Use defineComboOptions instead', E_USER_DEPRECATED);
                $formObject->setOptions($this->defineComboContent($formObject->internalName, $data));
            }
        }
        if ($alterations->getSelectedClientOption() !== null) {
            $this->selectedComboOptions[$alterations->getName()] = $alterations->getSelectedClientOption();
        }

        $this->bindData($formObject);

        // register nested items in combo
        $nestedProxies = $formObject->processFormControlsInComboOptions($this->cell, $this->getAccessType());
        foreach ($nestedProxies as $nestedProxy) {
            $this->evaluateFormObject($nestedProxy);
        }

        // register nested items
        foreach ($formObject->getNestedItems() as $nestedObject) {
            $this->evaluateFormObject($nestedObject);
        }
    }

    public function getProcessedClientData(string $control, string $value): ProcessedClientDataInterface
    {
        // deprecated
        $result       = new ProcessedClientData(
            encryptedImplementation: false,
            clientId               : $control
        );
        $formControls = $this->cell->getContentControlType();
        if (array_key_exists($control, $formControls)) {
            $result->id         = $formControls[$control]['name'];
            $result->objectType = $formControls[$control]['objectType'];
            $result->accessType = $formControls[$control]['accessType'];
            $result->label      = $formControls[$control]['label'];
            if ($formControls[$control]['objectType'] === Control\Radio::class && array_key_exists($value, $formControls[$control]['radio_value'])) {
                $result->value = $formControls[$control]['radio_value'][$value];
            } else {
                $result->value = $value;
            }
        } else {
            $result->failedValidationMessages[] = 'Control not found in session';
        }
        return $result;
    }

    /**
     * @session none
     * @return array
     */
    private function getCellEvents(): array
    {
        $cellEvents = $this->getParentEventsForClient();
        $result     = [];
        foreach ($cellEvents as $eventName => $events) {
            foreach ($events as $handler) {
                $result[] = new ClientCellEvent($eventName, $handler);
            }
        }
        if ($this->getAccessType() === Enum\AccessType::RW) {
            if ($this->event_on_button_click === true) {
                $result[] = new ClientCellEvent('onButtonClick', 'doOnButtonClick');
            }
            if ($this->event_on_change === true) {
                $result[] = new ClientCellEvent('onChange', 'doOnChange');
            }
            if ($this->event_on_input_change === true) {
                $result[] = new ClientCellEvent('onInputChange', 'doOnInputChange');
            }
            if ($this->event_on_upload_file === true) {
                $result[] = new ClientCellEvent('onUploadFile', 'doOnUploadFile');
                $result[] = new ClientCellEvent('onUploadFail', 'doOnUploadFail');
                if ($this->use_single_file_mode === true) {
                    $result[] = new ClientCellEvent('onUploadFile', 'hideModalSpinner');
                    $result[] = new ClientCellEvent('onUploadFail', 'hideModalSpinner');
                    $result[] = new ClientCellEvent('onBeforeFileAdd', 'limitUploadToSingleFileOnly');
                    $result[] = new ClientCellEvent('onBeforeFileUpload', 'showModalSpinner');
                }
            }
            if ($this->event_on_blur === true) {
                $result[] = new ClientCellEvent('onBlur', 'doOnBlur');
            }
        }
        if ($this->eventOnUnrestrictedButtonClick === true) {
            $result[] = new ClientCellEvent('onButtonClick', 'doOnButtonClick');
        }
        if ($this->event_on_info === true) {
            $result[] = new ClientCellEvent('onInfo', 'doOnInfo');
        }
        if ($this->event_on_close_button_click === true) {
            $result[] = new ClientCellEvent('onButtonClick', 'doOnCloseButtonClick');
        }
        if ($this->event_on_show_help === true) {
            $result[] = new ClientCellEvent('onFocus', 'doShowHelp');
            $result[] = new ClientCellEvent('onBlur', 'doHideHelp');
        }
        return $result;
    }

    /**
     * @session write (Cell::registerContentEvent)
     * @throws Exception
     */
    private function evaluateContentEvents(): void
    {
        foreach ($this->getEvents() as $event) {
            // only one poll action per cell is currently allowed
            if ($this->event_on_poll === false && $event instanceof Form\Event\OnPoll) {
                $actions = $event->getActionArray();
                foreach ($actions as $action) {
                    if ($action instanceof Action\PollMethod) {
                        $this->event_on_poll = true;
                        $this->pollId        = $action->getId();
                        break;
                    }
                }
            }
            $this->cell->registerContentEvent($event);
        }
    }

    private function getPreParameters(string $nonce): array
    {
        $parameters = [];
        if (!empty($this->clientEvents)) {
            $parameters['client'] = $this->clientEvents;
        }
        if ($this->lock === true) {
            $parameters['beforeDataLoading']['self']['lock'] = true;
        }
        if ($this->liveValidation === true && $this->getAccessType() === Enum\AccessType::RW) {
            $parameters['settings']['self']['enableLiveValidation'] = true;
        }
        foreach ($this->formObjectParameters as $encryptedControlName => $parameterArray) {
            foreach ($parameterArray as $location => $controlParameters) {
                if ($location === 'beforeDataLoading') {
                    $parameters['beforeDataLoading']['nested'][$encryptedControlName] = $controlParameters;
                }
            }
        }
        if ($this->alignContent !== '') {
            $parameters['align'] = $this->alignContent;
        }
        if ($this->className !== '') {
            $parameters['className'] = $this->className;
        }
        $parameters['rt']  = date('YmdHis', time()); // request time
        $parameters['cn']  = base64_encode($nonce); // cell nonce
        $parameters['nce'] = $this->combosWithNewEntries; // ids of combos which allow new entries
        $parameters['sco'] = $this->getSelectedComboOptions(); // array of combo objects and their selected option
        $parameters['op']  = self::getObjectProperties($this->objectProperties);
        if (!empty($this->asynchronousControls)) {
            $parameters['ae'] = $this->asynchronousControls;
        }
        if (!empty($this->inputChangeObjects)) {
            $parameters['ev']['onInputChange'] = $this->inputChangeObjects;
        }
        return $parameters;
    }

    private function getPostParameters(): array
    {
        $parameters = [];
        if (!empty($this->inputHelpObjects)) {
            $parameters['help'] = $this->inputHelpObjects;
        }
        if ($this->has_dependency_validation === true) {
            $parameters['validations'] = true;
        }
        if ($this->has_placeholders === true) {
            $parameters['placeholders'] = true;
        }
        foreach ($this->formObjectParameters as $encryptedControlName => $parameterArray) {
            foreach ($parameterArray as $location => $controlParameters) {
                if ($location === 'afterDataLoading') {
                    if (is_array($controlParameters)) {
                        foreach ($controlParameters as $element => $controlParameter) {
                            switch ($element) {
                                case 'autoCompletion':
                                case 'tagify':
                                case 'editor':
                                    $parameters[$element][$encryptedControlName] = $controlParameter;
                                    break;
                                case 'base64':
                                    $parameters['base64'][] = $encryptedControlName;
                                    break;
                                default:
                                    $parameters['nested'][$encryptedControlName] = $controlParameters;
                                    break;
                            }
                        }
                    } else {
                        $parameters['nested'][$encryptedControlName] = $controlParameters;
                    }
                }
            }
        }
        return $parameters;
    }

    private function getSelectedComboOptions(): array
    {
        return $this->selectedComboOptions;
    }

    public static function getObjectProperties(array $properties): string
    {
        $objectProperties = [];
        foreach ($properties as $objectProperty) {
            $objectProperties[$objectProperty->i] = clone $objectProperty;
            unset($objectProperties[$objectProperty->i]->i);
        }
        if (extension_loaded('zlib') === true) {
            return Session::encrypt(gzcompress(json_encode($objectProperties), 9), Session::getTopLevelNonce());
        }
        return Session::encrypt(json_encode($objectProperties), Session::getTopLevelNonce());
    }

    /**
     * @return string
     */
    private function getJSON(): string
    {
        $json = [];
        foreach ($this->internalFormObjects as $formObject) {
            if ($formObject->getAccessType() > 0) {
                $json[] = $formObject->getJsonArray();
            }
        }
        return json_encode($json);
    }

    /**
     * @session none
     * @return string
     * @throws \Exception
     */
    private function getXML(): string
    {
        SimpleXML::initializeDecode();
        $xmlElement = new SimpleXMLElement('<?xml version="1.0" encoding="'.$this->getOutputCharset().'" ?><items/>');
        if ($this->formSettings !== null) {
            $this->formSettings->getXMLElement($xmlElement, false);
        } else {
            global $env;
            $settings = $env->getFormSettings();
            if ($settings instanceof Form\Settings) {
                $settings->getXMLElement($xmlElement);
            }
        }
        foreach ($this->internalFormObjects as $formObject) {
            if ($formObject->getAccessType() > 0) {
                $formObject->getXMLElement($xmlElement);
            }
        }
        return SimpleXML::asString($xmlElement);
    }

    /**
     * This method is called by Action\ReloadFormObject
     *
     * @session unspecified depends on defineComboOptions
     * @throws Exception|\Exception
     * @internal
     */
    public function getComboOptions($id, $data = null): string|array
    {
        if (method_exists($this, 'defineComboOptions')) {
            trigger_error('Method defineComboContent is deprecated. Use setDataBinding instead', E_USER_DEPRECATED);
            $options = $this->defineComboOptions($id, $data);
            if ($options instanceof Combo) {
                return $options->getXML();
            }
            // TODO: else check return type...
            // actually, I think this should throw an exception
        } elseif (method_exists($this, 'defineComboContent')) {
            trigger_error('Method defineComboContent is deprecated. Use setDataBinding instead', E_USER_DEPRECATED);
            $options = $this->defineComboContent($id, ID::explode($data));
            if ($options instanceof Combo) {
                return $options->getXML();
            }
            // TODO: else check return type...
            // actually, I think this should throw an exception
        }
        return ['state' => 1];
    }

    /**
     * defineComboOptions and defineComboContent are deprecated.
     * we check if these are still in use or not.
     * remove method and call once getComboOptions is removed
     * @return bool
     */
    public function hasComboContent(): bool
    {
        if (method_exists($this, 'defineComboOptions') || method_exists($this, 'defineComboContent')) {
            return true;
        }
        return false;
    }
}
