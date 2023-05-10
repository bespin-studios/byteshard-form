<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Form;

use byteShard\Action\ModifyFormObject;
use byteShard\Session;

/**
 * Class SetRequiredFormObject
 * @package byteShard\Action
 */
class SelectComboOption extends ModifyFormObject
{
    protected string   $modification = 'selectOption';
    private string|int $optionToSelect;

    /** @API */
    public function selectOption(int|string $index): self
    {
        $this->optionToSelect = $index;
        return $this;
    }

    public function getComboOptionToSelect(string $cellNonce): ?string
    {
        if (isset($this->optionToSelect)) {
            return Session::encrypt($this->optionToSelect, $cellNonce);
        }
        return null;
    }
}
