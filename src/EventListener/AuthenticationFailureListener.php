<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\EventListener;

use HeimrichHannot\LdapBundle\HeimrichHannotLdapBundle;
use HeimrichHannot\LdapBundle\Util\LdapUtil;
use HeimrichHannot\RequestBundle\Component\HttpFoundation\Request;
use HeimrichHannot\UtilsBundle\Util\Utils;
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
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var Utils
     */
    protected $utils;

    public function __construct(Utils $utils, LdapUtil $ldapUtil, Request $request)
    {
        $this->utils = $utils;
        $this->ldapUtil = $ldapUtil;
        $this->request = $request;
    }

    public function __invoke(AuthenticationFailureEvent $event)
    {
        $mode = $this->utils->container()->isFrontend() ? HeimrichHannotLdapBundle::MODE_MEMBER : HeimrichHannotLdapBundle::MODE_USER;

        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        $result = $this->ldapUtil->authenticateLdapPerson(
            $mode,
            $username,
            $password
        );

        if (true === $result) {
            $this->ldapUtil->syncPerson($mode, $username);

            $event->stopPropagation();
        }
    }
}
