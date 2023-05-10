<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

/**
 * Class SetRequiredFormObject
 * @package byteShard\Action
 */
class SetRequiredFormObject extends ModifyFormObject
{
    protected string $modification = 'required';
}
