<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

/**
 * Class UnsetRequiredFormObject
 * @package byteShard\Action
 */
class UnsetRequiredFormObject extends ModifyFormObject
{
    protected string $modification      = 'required';
    protected bool|int|string   $modificationValue = false;
}
