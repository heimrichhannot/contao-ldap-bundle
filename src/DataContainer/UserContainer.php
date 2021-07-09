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
use HeimrichHannot\UtilsBundle\Model\ModelUtil;

class UserContainer
{
    /**
     * @var ModelUtil
     */
    protected $modelUtil;

    public function __construct(ModelUtil $modelUtil)
    {
        $this->modelUtil = $modelUtil;
    }

    /**
     * @Callback(table="tl_user", target="list.label.label")
     */
    public function addUidNumber($row, $label, DataContainer $dc, $args)
    {
        $callback = System::importStatic('tl_user');
        $args = $callback->addIcon($row, $label, $dc, $args);

        if ($row['ldapUidNumber']) {
            $args[2] .= ' <span style="color:#999;padding-left:3px">[LDAP-Uid: '.$row['ldapUidNumber'].']</span>';
        }

        return $args;
    }

    /**
     * @Callback(table="tl_user", target="config.onload")
     */
    public function disableFields(DataContainer $dc = null)
    {
        if (null === ($user = $this->modelUtil->findModelInstanceByPk('tl_user', $dc->id)) || !$user->ldapUidNumber) {
            return;
        }

        $dca = &$GLOBALS['TL_DCA']['tl_user'];

        $dca['fields']['username']['eval']['disabled'] = true;
        $dca['fields']['password']['eval']['disabled'] = true;
        $dca['fields']['pwChange']['eval']['disabled'] = true;
    }
}
