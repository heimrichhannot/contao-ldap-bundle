<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\System;

class MemberGroupContainer
{
    /**
     * @Callback(table="tl_member_group", target="list.label.label")
     */
    public function addGidNumber($row, $label, DataContainer $dc, $args)
    {
        $callback = System::importStatic('tl_member_group');
        $label = $callback->addIcon($row, $label, $dc, $args);

        if ($row['ldapGidNumber']) {
            $label = str_replace('</div>', ' <span style="color:#999;padding-left:3px">[LDAP-Gid: '.$row['ldapGidNumber'].']</span></div>', $label);
        }

        return $label;
    }
}
