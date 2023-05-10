<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Permission\Cell\NoPermission;

use byteShard\Environment;
use byteShard\Form;

/**
 * This cell will be displayed if the user does not have the needed permissions
 * Class a
 * @package byteShard\Internal\Permission\Cell\NoPermission
 */
class NoPermissionCell extends Form
{
    protected function defineCellContent()
    {
        global $env;
        /* @var $env Environment */
        $content = $env->getNoApplicationPermissionContent();
        if (isset($content->label_width)) {
            $this->addFormSettings($env->getFormSettings($content->label_width));
        }
        if (isset($content->labels)) {
            if (!is_array($content->labels)) {
                $content->labels = array($content->labels);
            }
            $count = 0;
            foreach ($content->labels as $label) {
                $this->addFormObject(new Form\Control\Label('noPermission'.$count, $label));
                $count++;
            }
        }
    }
}
