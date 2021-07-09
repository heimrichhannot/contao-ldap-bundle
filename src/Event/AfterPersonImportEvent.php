<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class AfterPersonImportEvent extends Event
{
    const NAME = 'huh.ldap.after_person_import';

    /**
     * @var array
     */
    protected $ldapPerson;
    /**
     * @var array
     */
    protected $person;

    public function __construct(array $ldapPerson, array $person)
    {
        $this->ldapPerson = $ldapPerson;
        $this->person = $person;
    }

    public function getLdapPerson(): array
    {
        return $this->ldapPerson;
    }

    public function getPerson(): array
    {
        return $this->person;
    }
}
