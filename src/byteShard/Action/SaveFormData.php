<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Cell;
use byteShard\Enum\AccessType;
use byteShard\Form;
use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;
use byteShard\Internal\ActionCollector;
use byteShard\Internal\CellContent;
use byteShard\Internal\Struct\GetData;
use byteShard\Locale;
use byteShard\Popup\Message;

/**
 * Class SaveFormData
 * @package byteShard\Action
 */
class SaveFormData extends Action
{
    protected function runAction(): ActionResultInterface
    {
        $container = $this->getLegacyContainer();
        $id        = $this->getLegacyId();
        $result    = ['state' => 2];
        if ($container instanceof Cell) {
            $className = $container->getContentClass();
            if (class_exists($className)) {
                $cellContent = new $className($container);
                if ($cellContent instanceof CellContent) {
                    if ($cellContent->getAccessType() !== AccessType::RW) {
                        return $this->getErrorPopup($cellContent);
                    }

                    $clientData       = $this->getClientData();
                    $getData          = $this->getGetData();
                    $clientTimeZone   = $this->getClientTimeZone();
                    $objectProperties = $this->getObjectProperties();

                    $cellContent->setClientTimeZone($clientTimeZone);
                    if ($id instanceof GetData) {
                        $container->setGetDataActionClientData($id);
                    }

                    $result = $cellContent->runClientUpdate($clientData);
                    if ($result === null) {
                        return $this->getErrorPopup($cellContent, 'byteShard.cellContent.unexpected_return_value');
                    }
                    $actions = [];
                    foreach ($result as $key => $item) {
                        if ($item instanceof Action) {
                            $actions[] = ActionCollector::initializeAction($item, null, $container, null, '', '', $clientData, $getData, $clientTimeZone, $objectProperties);
                            unset($result[$key]);
                        }
                    }
                    if (!empty($actions)) {
                        foreach ($actions as $action) {
                            $result = array_merge_recursive($result, $action->getResult($container, $id));
                        }
                    }
                    if (array_key_exists('success', $result)) {
                        unset($result['success']);
                    }
                    if (array_key_exists('changes', $result)) {
                        unset($result['changes']);
                    }
                    if (array_key_exists('state', $result) && is_array($result['state'])) {
                        $result['state'] = min(...$result['state']);
                    }
                    if (!isset($result['state']) || $result['state'] !== 2) {
                        return $this->getErrorPopup($cellContent, 'byteShard.cellContent.generic');
                    }
                }
            }
        }
        return new Action\ActionResultMigrationHelper($result);
    }

    private function getErrorPopup(CellContent $cellContent, string $locale = ''): ActionResultInterface
    {
        if ($locale === '') {
            $locale = 'byteShard.cell.update.no_permission';
            if ($cellContent instanceof Form) {
                $locale = 'byteShard.form.update.no_permission';
            }
        }
        $message = new Message(Locale::get($locale));
        return new Action\ActionResultMigrationHelper($message->getNavigationArray());
    }
}
