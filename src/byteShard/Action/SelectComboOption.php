<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action;

/**
 * Class SelectComboOption
 * @package byteShard\Action
 */
class SelectComboOption extends ModifyFormObject
{
    protected string $modification       = 'selectOption';
    protected string $clientModification = 'selectOption';

    /**
     * @param int $id
     * @return $this
     */
    public function select(int $id): self
    {
        $this->modificationValue = $id;
        return $this;
    }
}
