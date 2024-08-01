<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Action\Form;

use byteShard\Internal\Action;
use byteShard\Internal\Action\ActionResultInterface;

class ClearUpload extends Action
{
    protected function runAction(): ActionResultInterface
    {
        return new Action\ActionResult();
    }
}
