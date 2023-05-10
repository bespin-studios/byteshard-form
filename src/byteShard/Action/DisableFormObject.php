<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

use byteShard\Internal\Action\ClientExecutionInterface;

/**
 * Class DisableFormObject
 * @package byteShard\Action
 */
class DisableFormObject extends ModifyFormObject implements ClientExecutionInterface
{
    protected string $modification = 'disable';
    protected string $clientModification = 'disableItem';
}
