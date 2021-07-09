<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use HeimrichHannot\LdapBundle\HeimrichHannotLdapBundle;
use HeimrichHannot\LdapBundle\Util\LdapUtil;
use HeimrichHannot\UtilsBundle\Util\Utils;

/**
 * @Hook("importUser")
 *
 * Used to create non-existing or update existing users and groups on login.
 */
class ImportUserListener
{
    /**
     * @var LdapUtil
     */
    protected $ldapUtil;
    /**
     * @var Utils
     */
    protected $utils;

    public function __construct(Utils $utils, LdapUtil $ldapUtil)
    {
        $this->utils = $utils;
        $this->ldapUtil = $ldapUtil;
    }

    public function __invoke(string $username, string $password, string $table)
    {
        $mode = $this->utils->container()->isFrontend() ? HeimrichHannotLdapBundle::MODE_MEMBER : HeimrichHannotLdapBundle::MODE_USER;

        $result = $this->ldapUtil->authenticateLdapPerson(
            $mode,
            $username,
            $password
        );

        if (true === $result) {
            $this->ldapUtil->syncPerson($mode, $username, [
                'setLoginTimestamps' => true,
            ]);

            return true;
        }

        return false;
    }
}
