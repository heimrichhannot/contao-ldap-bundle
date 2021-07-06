<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\EventListener;

use HeimrichHannot\LdapBundle\Util\LdapUtil;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Terminal42\ServiceAnnotationBundle\Annotation\ServiceTag;

/**
 * @ServiceTag("kernel.event_listener", event="security.authentication.failure")
 */
class AuthenticationFailureListener
{
    /**
     * @var LdapUtil
     */
    protected $ldapUtil;

    public function __construct(LdapUtil $ldapUtil)
    {
        $this->ldapUtil = $ldapUtil;
    }

    public function __invoke(AuthenticationFailureEvent $event)
    {
    }
}
