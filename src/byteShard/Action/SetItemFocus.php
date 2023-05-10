<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

class SetItemFocus extends ModifyFormObject
{
    protected string $modification = 'setItemFocus';

    public function __construct(string $cell, string $formItem)
    {
        parent::__construct($cell, $formItem);
    }
}