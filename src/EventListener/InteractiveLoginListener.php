<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\EventListener;

use HeimrichHannot\LdapBundle\Util\LdapUtil;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Terminal42\ServiceAnnotationBundle\Annotation\ServiceTag;

/**
 * @ServiceTag("kernel.event_listener", event="security.interactive_login")
 *
 * Used to update existing users where LDAP and local password are the same and hence checkCredentials hook isn't called.
 */
class InteractiveLoginListener
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

    public function __invoke(InteractiveLoginEvent $event)
    {
        if (!($user = $event->getAuthenticationToken()->getUser())) {
            return;
        }

        // local authentication successful (local password used) -> no authentication against LDAP necessary (password mismatch between ldap and local for same username)
        $this->session->set('HUH_LDAP_BUNDLE_SYNC_PERSON', $user->getUsername());
    }
}
