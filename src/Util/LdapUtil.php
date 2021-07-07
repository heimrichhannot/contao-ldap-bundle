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
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Ldap\Ldap;
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
     * @return Ldap|false The connection for the given mode or false in case of error
     */
    public function getConnection(string $mode, array $options = [])
    {
        $throwExceptions = $options['throwExceptions'] ?? false;

        if (isset(static::$connections[$mode])) {
            return static::$connections[$mode];
        }

        if (!isset($this->bundleConfig[$mode]['connection'])) {
            return false;
        }

        $connection = Ldap::create('ext_ldap', $this->bundleConfig[$mode]['connection']);

        try {
            $connection->bind($this->bundleConfig[$mode]['bind_dn'], $this->bundleConfig[$mode]['bind_password']);
        } catch (\Exception $e) {
            if ($throwExceptions) {
                throw $e;
            }

            return false;
        }

        static::$connections[$mode] = $connection;

        return static::$connections[$mode];
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
            'uidNumber',
            'givenName',
            'displayName',
            'sn',
            'mail',
        ];

        foreach ($fieldMapping as $mapping) {
            $attributes[] = $mapping['ldap_field'];
        }

        $attributes = array_unique($attributes);

        $ldapPersons = $connection->query($dn, $config['filter'] ?? '(cn=*)', [
            'filter' => $attributes,
        ])->execute();

        if ($ldapPersons->count() < 1) {
            return [];
        }

        // prepare for simpler usage
        $result = [];

        foreach ($ldapPersons as $ldapPerson) {
            $person = [
                'ldapUidNumber' => $ldapPerson->getAttribute('uidNumber')[0],
                'ldapUid' => $ldapPerson->getAttribute('uid')[0],
                'email' => $ldapPerson->getAttribute('mail')[0] ?? '',
                'dn' => $ldapPerson->getDn(),
            ];

            if (\in_array($person['ldapUidNumber'], $skipUidNumbers) || \in_array($person['ldapUid'], $skipUids)) {
                continue;
            }

            if ($ldapPerson->getAttribute($this->bundleConfig[$mode]['person_username_ldap_field'])) {
                $person['username'] = $ldapPerson->getAttribute($this->bundleConfig[$mode]['person_username_ldap_field'])[0];
            }

            if (HeimrichHannotLdapBundle::MODE_MEMBER === $mode) {
                $person['firstname'] = $ldapPerson->getAttribute('givenName') ? $ldapPerson->getAttribute('givenName')[0] : '';
                $person['lastname'] = $ldapPerson->getAttribute('sn') ? $ldapPerson->getAttribute('sn')[0] : '';
            } else {
                $person['name'] = $ldapPerson->getAttribute('displayName') ? $ldapPerson->getAttribute('displayName')[0] : '';
            }

            foreach ($fieldMapping as $mapping) {
                if ($ldapPerson->getAttribute($mapping['ldap_field'])) {
                    $person[$mapping['contao_field']] = $ldapPerson->getAttribute($mapping['ldap_field'])[0];
                }
            }

            foreach ($defaultValues as $mapping) {
                $person[$mapping['field']] = $mapping['value'];
            }

            $result[$person['username']] = $person;
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

        $adminGidNumber = $this->bundleConfig[$mode]['person']['admin_gid_number'] ?? 0;

        if (!($dn = $config['base_dn'])) {
            return [];
        }

        // create the attributes array for performance reasons (getting all attributes is more expensive)
        $attributes = [
            'gidNumber',
            'memberUid',
            'cn',
        ];

        foreach ($fieldMapping as $mapping) {
            $attributes[] = $mapping['ldap_field'];
        }

        $attributes = array_unique($attributes);

        $ldapGroups = $connection->query($dn, $config['filter'] ?? '(cn=*)', [
            'filter' => $attributes,
        ])->execute();

        if ($ldapGroups->count() < 1) {
            return [];
        }

        // prepare for simpler usage
        $result = [];

        foreach ($ldapGroups as $ldapGroup) {
            $group = [
                'ldapGidNumber' => $ldapGroup->getAttribute('gidNumber')[0],
                'name' => $ldapGroup->getAttribute('cn') ? $ldapGroup->getAttribute('cn')[0] : '',
                'memberUids' => [],
            ];

            if (\in_array($group['ldapGidNumber'], $skipGidNumbers)) {
                continue;
            }

            $group['isAdmin'] = ($adminGidNumber && $adminGidNumber === (int) $group['ldapGidNumber']);

            if ($ldapGroup->getAttribute('memberUid')) {
                foreach ($ldapGroup->getAttribute('memberUid') as $uid) {
                    $group['memberUids'][] = $uid;
                }

                $group['memberUids'] = array_unique($group['memberUids']);
            }

            foreach ($fieldMapping as $mapping) {
                if ($ldapGroup->getAttribute($mapping['ldap_field'])) {
                    $group[$mapping['contao_field']] = $ldapGroup->getAttribute($mapping['ldap_field'])[0];
                }
            }

            foreach ($defaultValues as $mapping) {
                $group[$mapping['field']] = $mapping['value'];
            }

            $result[] = $group;
        }

        return $result;
    }

    /**
     * @param string $mode HeimrichHannotLdapBundle::MODE_USER or HeimrichHannotLdapBundle::MODE_MEMBER
     */
    public function syncPerson(string $mode, string $uid): void
    {
        $this->syncPersons($mode, [
            'limitUids' => [$uid],
        ]);
    }

    /**
     * @param string $mode HeimrichHannotLdapBundle::MODE_USER or HeimrichHannotLdapBundle::MODE_MEMBER
     */
    public function syncPersons(string $mode, array $options = []): void
    {
        $limitUids = $options['limitUids'] ?? [];
        $dryRun = $options['dryRun'] ?? [];

        /** @var SymfonyStyle $io */
        $io = $options['io'] ?? [];

        $ldapPersons = $this->retrievePersonsFromLdap($mode);

        if ($io) {
            if (\count($ldapPersons) > 0) {
                $io->success('Retrieved '.\count($ldapPersons).' person(s) from LDAP:');
                $io->listing(array_map(function ($person) use ($mode) {
                    if (HeimrichHannotLdapBundle::MODE_MEMBER === $mode) {
                        return $person['firstname'].' '.$person['lastname'].' (uid: '.$person['ldapUid'].')';
                    }

                    return $person['name'].' (uid: '.$person['ldapUid'].')';
                }, $ldapPersons));
            } else {
                $io->warning('Retrieved 0 persons from LDAP.');
            }
        }

        // create groups
        $groupIdMapping = [];
        $groupAssociations = [];
        $adminPersonUids = [];

        if (isset($this->bundleConfig[$mode]['group'])) {
            $ldapGroups = $this->retrieveGroupsFromLdap($mode);

            if ($io) {
                if (\count($ldapGroups) > 0) {
                    $io->success('Retrieved '.\count($ldapGroups).' group(s) from LDAP:');

                    $io->listing(array_map(function ($group) use ($mode) {
                        return $group['name'].' (gid number: '.$group['ldapGidNumber'].($group['isAdmin'] ? ', admin group -> won\'t be imported' : '').')';
                    }, $ldapGroups));
                } else {
                    $io->warning('Retrieved 0 group from LDAP.');
                }
            }

            foreach ($ldapGroups as $ldapGroup) {
                $groupData = $ldapGroup;

                $table = 'tl_'.$mode.'_group';

                $memberUids = $groupData['memberUids'] ?? [];

                unset($groupData['memberUids']);

                // don't import admin group
                if ($ldapGroup['isAdmin']) {
                    $adminPersonUids = array_merge($adminPersonUids, $memberUids);

                    continue;
                }

                unset($groupData['isAdmin']);

                $group = $this->databaseUtil->findOneResultBy($table, ["$table.ldapGidNumber=?"], [$ldapGroup['ldapGidNumber']]);

                if ($group->numRows < 1) {
                    $groupData['tstamp'] = time();

                    if ($io) {
                        $io->success('Inserted new '.$table.' instance for LDAP group "'.$groupData['name'].' (gid number: '.$ldapGroup['ldapGidNumber'].')".');
                    }

                    if (!$dryRun) {
                        $statement = $this->databaseUtil->insert($table, $groupData);

                        $groupIdMapping[$ldapGroup['ldapGidNumber']] = $statement->insertId;
                    }
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

                        if ($io) {
                            $io->success('Updated '.$table.' instance ID '.$group->row()['id'].' for LDAP group "'.$groupData['name'].' (gid number: '.$ldapGroup['ldapGidNumber'].')".');
                        }

                        if (!$dryRun) {
                            $this->databaseUtil->update($table, $groupData, "$table.id=?", [$group->row()['id']]);
                        }
                    }
                }

                // group associations
                $groupId = $groupIdMapping[$ldapGroup['ldapGidNumber']];

                foreach ($memberUids as $memberUid) {
                    if (!isset($groupAssociations[$memberUid])) {
                        $groupAssociations[$memberUid] = [];
                    }

                    $data = [
                        'id' => $groupId,
                        'ldapGidNumber' => $ldapGroup['ldapGidNumber'],
                    ];

                    if (!\in_array($data, $groupAssociations[$memberUid])) {
                        $groupAssociations[$memberUid][] = $data;
                    }
                }
            }
        }

        // create persons
        foreach ($ldapPersons as $ldapPerson) {
            $personData = $ldapPerson;

            if (!empty($limitUids) && !\in_array($ldapPerson['ldapUid'], $limitUids)) {
                continue;
            }

            $table = 'tl_'.$mode;

            // associate groups
            $personData['groups'] = serialize(array_map(function ($data) {
                return $data['id'];
            }, $groupAssociations[$ldapPerson['ldapUid']] ?? []));

            unset($personData['ldapUid'], $personData['dn']);

            switch ($mode) {
                case HeimrichHannotLdapBundle::MODE_USER:
                    // admin?
                    $personData['admin'] = \in_array($ldapPerson['ldapUid'], $adminPersonUids) ? true : '';

                    $ioLogName = $personData['name'];

                    break;

                case HeimrichHannotLdapBundle::MODE_MEMBER:
                    $ioLogName = $personData['firstname'].' '.$personData['lastname'];

                    break;
            }

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

                // set password to random to ensure checkCredentials hook is always called
                $password = uniqid('', true);

                $personData['password'] = $encoder->encodePassword($password, null);

                switch ($mode) {
                    case HeimrichHannotLdapBundle::MODE_USER:
                        $personData['language'] = $ldapPerson['language'] ?? 'de';
                        $personData['backendTheme'] = $ldapPerson['backendTheme'] ?? 'flexible';
                        $personData['uploader'] = $ldapPerson['uploader'] ?? 'DropZone';

                        break;

                    case HeimrichHannotLdapBundle::MODE_MEMBER:
                        $personData['login'] = true;

                        break;
                }

                if ($io) {
                    $io->success('Inserted new '.$table.' instance for LDAP person "'.$ioLogName.' (uid: '.$ldapPerson['ldapUid'].')".');
                }

                if (!$dryRun) {
                    $statement = $this->databaseUtil->insert($table, $personData);

                    // create passwort history to avoid pwChange=1
                    if (class_exists('Terminal42\PasswordValidationBundle\Terminal42PasswordValidationBundle')) {
                        $this->databaseUtil->insert('tl_password_history', [
                            'tstamp' => time(),
                            'user_id' => $statement->insertId,
                            'user_entity' => HeimrichHannotLdapBundle::MODE_USER === $mode ? 'Contao\BackendUser' : 'Contao\FrontendUser',
                            'password' => $personData['password'],
                        ]);
                    }
                }
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

                    if ($io) {
                        $io->success('Updated '.$table.' instance ID '.$person->row()['id'].' for LDAP person "'.$ioLogName.' (uid: '.$ldapPerson['ldapUid'].')".');
                    }

                    if (!$dryRun) {
                        $this->databaseUtil->update($table, $personData, "$table.id=?", [$person->row()['id']]);
                    }
                }
            }
        }
    }

    public function authenticateLdapPerson(string $mode, string $username, string $password): bool
    {
        if (false === ($person = $this->retrievePersonFromLdap($mode, $username))) {
            return false;
        }

        try {
            $this->getConnection($mode)->bind($person['dn'], $password);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
