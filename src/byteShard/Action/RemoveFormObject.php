<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

class RemoveFormObject extends ModifyFormObject
{
    protected string $modification       = 'removeItem';
    protected string $clientModification = 'removeItem';
}
