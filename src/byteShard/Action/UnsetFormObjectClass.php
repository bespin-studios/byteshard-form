<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

/**
 * Class UnsetFormObjectClass
 * @package byteShard\Action
 */
class UnsetFormObjectClass extends ModifyFormObject
{
    protected string $modification       = 'removeClass';
    protected string $clientModification = 'removeClass';

    public function __construct(string $cell, string $className, string ...$formItems)
    {
        $this->modificationValue = $className;
        parent::__construct($cell, ...$formItems);
    }
}
