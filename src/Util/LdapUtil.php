<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\Util;

use Contao\BackendUser;
use Contao\FrontendUser;
use HeimrichHannot\LdapBundle\HeimrichHannotLdapBundle;
use HeimrichHannot\UtilsBundle\Database\DatabaseUtil;
use HeimrichHannot\UtilsBundle\Model\ModelUtil;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class LdapUtil
{
    /**
     * @var array
     */
    protected $bundleConfig;

    protected static $connections = [];
    /**
     * @var DatabaseUtil
     */
    protected $databaseUtil;

    /**
     * @var ModelUtil
     */
    protected $modelUtil;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    public function __construct(array $bundleConfig, EncoderFactoryInterface $encoderFactory, DatabaseUtil $databaseUtil, ModelUtil $modelUtil)
    {
        $this->bundleConfig = $bundleConfig;
        $this->encoderFactory = $encoderFactory;
        $this->databaseUtil = $databaseUtil;
        $this->modelUtil = $modelUtil;
    }

    /**
     * @param string $mode HeimrichHannotLdapBundle::MODE_USER or HeimrichHannotLdapBundle::MODE_MEMBER
     *
     * @return resource|false The connection for the given mode or false in case of error
     */
    public function getConnection(string $mode)
    {
        if (isset(static::$connections[$mode])) {
            return static::$connections[$mode];
        }

        $config = $this->bundleConfig[$mode];

        if (!($host = $config['host'])
            || !($port = $config['port'])
            || !($bindDn = $config['bind_dn'])
            || !($bindPassword = $config['bind_password'])
        ) {
            return false;
        }

        $connection = ldap_connect(('ssl' == $config['encryption'] ? 'ldaps://' : 'ldap://').$host, (int) $port);

        if (!$connection || !\is_resource($connection)) {
            return false;
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        // check if bind dn can connect to ldap server
        if (!@ldap_bind($connection, $bindDn, $bindPassword)) {
            return false;
        }

        static::$connections[$mode] = $connection;

        return $connection;
    }

    /**
     * @param string $mode HeimrichHannotLdapBundle::MODE_USER or HeimrichHannotLdapBundle::MODE_MEMBER
     */
    public function retrievePersonsFromLdap(string $mode): array
    {
        if (false === ($connection = $this->getConnection($mode))) {
            return [];
        }

        $config = $this->bundleConfig[$mode]['person'];

        $fieldMapping = $config['field_mapping'] ?? [];
        $defaultValues = $config['default_values'] ?? [];
        $skipUids = $config['skip_uids'] ?? [];
        $skipUidNumbers = $config['skip_uid_numbers'] ?? [];

        if (!($dn = $config['base_dn'])) {
            return [];
        }

        // create the attributes array for performance reasons (getting all attributes is more expensive)
        $attributes = [
            'uid',
            'uidnumber',
            'givenname',
            'displayname',
            'sn',
            'mail',
        ];

        foreach ($fieldMapping as $mapping) {
            $attributes[] = $mapping['ldap_field'];
        }

        $attributes = array_unique($attributes);

        $query = ldap_search(
            $connection,
            $dn,
            $config['filter'] ?? '(cn=*)',
            $attributes
        );

        if (!$query) {
            return [];
        }

        $ldapPersons = ldap_get_entries($connection, $query);

        if (!\is_array($ldapPersons)) {
            return [];
        }

        // prepare for simpler usage
        $result = [];

        if ($ldapPersons['count'] > 0) {
            unset($ldapPersons['count']);

            foreach ($ldapPersons as $ldapPerson) {
                $person = [
                    'ldapUidNumber' => $ldapPerson['uidnumber'][0],
                    'ldapUid' => $ldapPerson['uid'][0],
                    'email' => $ldapPerson['mail'][0] ?? '',
                    'dn' => $ldapPerson['dn'],
                ];

                if (\in_array($person['ldapUidNumber'], $skipUidNumbers) || \in_array($person['ldapUid'], $skipUids)) {
                    continue;
                }

                if (isset($ldapPerson[$this->bundleConfig[$mode]['person_username_ldap_field']][0])) {
                    $person['username'] = $ldapPerson[$this->bundleConfig[$mode]['person_username_ldap_field']][0];
                }

                if (HeimrichHannotLdapBundle::MODE_MEMBER === $mode) {
                    $person['firstname'] = $ldapPerson['givenname'][0] ?? '';
                    $person['lastname'] = $ldapPerson['sn'][0] ?? '';
                } else {
                    $person['name'] = $ldapPerson['displayname'][0] ?? '';
                }

                foreach ($fieldMapping as $mapping) {
                    if (isset($ldapPerson[$mapping['ldap_field']][0])) {
                        $person[$mapping['contao_field']] = $ldapPerson[$mapping['ldap_field']][0];
                    }
                }

                foreach ($defaultValues as $mapping) {
                    $person[$mapping['field']] = $mapping['value'];
                }

                $result[$person['username']] = $person;
            }
        }

        return $result;
    }

    /**
     * @param string $mode     HeimrichHannotLdapBundle::MODE_USER or HeimrichHannotLdapBundle::MODE_MEMBER
     * @param string $username The username according to the config "person_username_ldap_field"
     *
     * @return array|false
     */
    public function retrievePersonFromLdap(string $mode, string $username)
    {
        $persons = $this->retrievePersonsFromLdap($mode);

        return $persons[$username] ?? false;
    }

    /**
     * @param string $mode HeimrichHannotLdapBundle::MODE_USER or HeimrichHannotLdapBundle::MODE_MEMBER
     */
    public function retrieveGroupsFromLdap(string $mode): array
    {
        if (false === ($connection = $this->getConnection($mode))) {
            return [];
        }

        $config = $this->bundleConfig[$mode]['group'];

        $fieldMapping = $config['field_mapping'] ?? [];
        $defaultValues = $config['default_values'] ?? [];
        $skipGidNumbers = $config['skip_gid_numbers'] ?? [];

        if (!($dn = $config['base_dn'])) {
            return [];
        }

        // create the attributes array for performance reasons (getting all attributes is more expensive)
        $attributes = [
            'gidnumber',
            'memberuid',
            'cn',
        ];

        foreach ($fieldMapping as $mapping) {
            $attributes[] = $mapping['ldap_field'];
        }

        $attributes = array_unique($attributes);

        $query = ldap_search(
            $connection,
            $dn,
            $config['filter'] ?? '(cn=*)',
            $attributes
        );

        if (!$query) {
            return [];
        }

        $ldapGroups = ldap_get_entries($connection, $query);

        if (!\is_array($ldapGroups)) {
            return [];
        }

        // prepare for simpler usage
        $result = [];

        if ($ldapGroups['count'] > 0) {
            unset($ldapGroups['count']);

            foreach ($ldapGroups as $ldapGroup) {
                $person = [
                    'ldapGidNumber' => $ldapGroup['gidnumber'][0],
                    'name' => $ldapGroup['cn'][0] ?? '',
                    'memberUids' => [],
                ];

                if (\in_array($person['ldapGidNumber'], $skipGidNumbers)) {
                    continue;
                }

                if (isset($ldapGroup['memberuid']['count']) && $ldapGroup['memberuid']['count'] > 0) {
                    unset($ldapGroup['memberuid']['count']);

                    foreach ($ldapGroup['memberuid'] as $uid) {
                        $person['memberUids'][] = $uid;
                    }

                    $person['memberUids'] = array_unique($person['memberUids']);
                }

                foreach ($fieldMapping as $mapping) {
                    if (isset($ldapGroup[$mapping['ldap_field']][0])) {
                        $person[$mapping['contao_field']] = $ldapGroup[$mapping['ldap_field']][0];
                    }
                }

                foreach ($defaultValues as $mapping) {
                    $person[$mapping['field']] = $mapping['value'];
                }

                $result[] = $person;
            }
        }

        return $result;
    }

    /**
     * @param string $mode HeimrichHannotLdapBundle::MODE_USER or HeimrichHannotLdapBundle::MODE_MEMBER
     */
    public function syncPersons(string $mode): void
    {
        $ldapPersons = $this->retrievePersonsFromLdap($mode);
        $ldapGroups = $this->retrieveGroupsFromLdap($mode);

        // create groups
        $groupIdMapping = [];
        $groupAssociations = [];

        foreach ($ldapGroups as $ldapGroup) {
            $groupData = $ldapGroup;

            $table = 'tl_'.$mode.'_group';

            $memberUids = $groupData['memberUids'];

            unset($groupData['memberUids']);

            $group = $this->databaseUtil->findOneResultBy($table, ["$table.ldapGidNumber=?"], [$ldapGroup['ldapGidNumber']]);

            if ($group->numRows < 1) {
                $groupData['tstamp'] = time();

                $statement = $this->databaseUtil->insert($table, $groupData);

                $groupIdMapping[$ldapGroup['ldapGidNumber']] = $statement->insertId;
            } else {
                $existingGroupData = $group->row();

                $groupIdMapping[$ldapGroup['ldapGidNumber']] = $existingGroupData['id'];

                unset($existingGroupData['id']);

                // check if something changed
                $update = false;

                foreach ($groupData as $field => $value) {
                    if ($existingGroupData[$field] !== $value) {
                        $update = true;

                        break;
                    }
                }

                if ($update) {
                    $groupData['tstamp'] = time();

                    $this->databaseUtil->update($table, $groupData, "$table.id=?", [$group->row()['id']]);
                }
            }

            // group associations
            $groupId = $groupIdMapping[$ldapGroup['ldapGidNumber']];

            foreach ($memberUids as $memberUid) {
                if (!isset($groupAssociations[$memberUid])) {
                    $groupAssociations[$memberUid] = [];
                }

                if (!\in_array($groupId, $groupAssociations[$memberUid])) {
                    $groupAssociations[$memberUid][] = $groupId;
                }
            }
        }

        // create persons
        foreach ($ldapPersons as $ldapPerson) {
            $personData = $ldapPerson;

            $table = 'tl_'.$mode;

            // associate groups
            if (isset($groupAssociations[$ldapPerson['ldapUid']])) {
                $personData['groups'] = serialize($groupAssociations[$ldapPerson['ldapUid']]);
            }

            unset($personData['ldapUid'], $personData['dn']);

            $person = $this->databaseUtil->findOneResultBy($table, ["$table.ldapUidNumber=?"], [$ldapPerson['ldapUidNumber']]);

            if ($person->numRows < 1) {
                $personData['tstamp'] = $personData['dateAdded'] = time();

                // set in order to avoid that the user needs to set a new password
                $personData['lastLogin'] = $personData['dateAdded'] + 1;
                $personData['currentLogin'] = $personData['dateAdded'] + 2;

                // set random password
                $encoder = $this->encoderFactory->getEncoder(
                    HeimrichHannotLdapBundle::MODE_MEMBER === $mode ? FrontendUser::class : BackendUser::class
                );

                $password = uniqid('', true);

                $personData['password'] = $encoder->encodePassword($password, null);

                switch ($mode) {
                    case HeimrichHannotLdapBundle::MODE_USER:
                        $personData['language'] = $ldapPerson['language'] ?? 'de';
                        $personData['backendTheme'] = $ldapPerson['backendTheme'] ?? 'flexible';
                        $personData['uploader'] = $ldapPerson['uploader'] ?? 'DropZone';

                        break;

                    case HeimrichHannotLdapBundle::MODE_MEMBER:
                        break;
                }

                $this->databaseUtil->insert($table, $personData);
            } else {
                $existingPersonData = $person->row();

                unset($existingPersonData['id']);

                // check if something changed
                $update = false;

                foreach ($personData as $field => $value) {
                    if ($existingPersonData[$field] !== $value) {
                        $update = true;

                        break;
                    }
                }

                if ($update) {
                    $personData['tstamp'] = time();

                    $this->databaseUtil->update($table, $personData, "$table.id=?", [$person->row()['id']]);
                }
            }
        }
    }

    /**
     * importUser hook.
     */
    public function importPersonFromLdap($strUsername, $strPassword, $strTable)
    {
        if (static::authenticateLdapPerson($strUsername, $strPassword)) {
            $strLdapModelClass = static::$strLdapModel;
            static::createOrUpdatePerson(null, $strLdapModelClass::findByUsername($strUsername), $strUsername);

            return true;
        }

        return false;
    }

    /**
     * check credentials hook -> ldap password != contao password.
     */
    public function authenticateAgainstLdap($strUsername, $strPassword, $objPerson)
    {
        if (static::authenticateLdapPerson($strUsername, $strPassword)) {
            // update since groups and/or mapped fields could have changed remotely
            $strLdapModelClass = static::$strLdapModel;
            static::createOrUpdatePerson($objPerson, $strLdapModelClass::findByUsername($strUsername), $strUsername);

            return true;
        }

        return false;
    }

    public function authenticateLdapPerson(string $mode, string $username, string $password): bool
    {
        if (false === ($person = $this->retrievePersonFromLdap($mode, $username))) {
            return false;
        }

        if (!@ldap_bind($this->getConnection($mode), $person['dn'], $password)) {
            return false;
        }

        return true;
    }
}
