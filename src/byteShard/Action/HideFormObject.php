<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

/**
 * Class HideFormObject
 * @package byteShard\Action
 * @API
 */
class HideFormObject extends ModifyFormObject
{
    protected string $modification       = 'hideItem';
    protected string $clientModification = 'hideItem';
}
