<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\EventListener;

use HeimrichHannot\LdapBundle\HeimrichHannotLdapBundle;
use HeimrichHannot\LdapBundle\Util\LdapUtil;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Terminal42\ServiceAnnotationBundle\Annotation\ServiceTag;

/**
 * @ServiceTag("kernel.event_listener", event="kernel.response")
 *
 * Used to update existing users where LDAP and local password are the same and hence checkCredentials hook isn't called.
 */
class KernelResponseListener
{
    /**
     * @var LdapUtil
     */
    protected $ldapUtil;
    /**
     * @var Utils
     */
    protected $utils;
    /**
     * @var SessionInterface
     */
    protected $session;

    public function __construct(Utils $utils, LdapUtil $ldapUtil, SessionInterface $session)
    {
        $this->utils = $utils;
        $this->ldapUtil = $ldapUtil;
        $this->session = $session;
    }

    public function __invoke(ResponseEvent $event)
    {
        if (!$this->session->get('HUH_LDAP_BUNDLE_SYNC_PERSON')) {
            return;
        }

        // local authentication successful (local password used) -> no authentication against LDAP necessary (password mismatch between ldap and local for same username)
        $username = $this->session->get('HUH_LDAP_BUNDLE_SYNC_PERSON');

        $this->session->remove('HUH_LDAP_BUNDLE_SYNC_PERSON');

        $mode = $this->utils->container()->isFrontend() ? HeimrichHannotLdapBundle::MODE_MEMBER : HeimrichHannotLdapBundle::MODE_USER;

        $this->ldapUtil->syncPerson($mode, $username, [
            'setLoginTimestamps' => true,
        ]);
    }
}
