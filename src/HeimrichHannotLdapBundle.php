<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle;

use HeimrichHannot\LdapBundle\DependencyInjection\HeimrichHannotLdapExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotLdapBundle extends Bundle
{
    const MODE_USER = 'user';
    const MODE_MEMBER = 'member';

    public function getContainerExtension()
    {
        return new HeimrichHannotLdapExtension();
    }
}
