# Contao LDAP Bundle

This bundle offers functionality concerning LDAP servers for the Contao CMS.

## Features

- synchronization for users and their groups from an ldap server (works both for frontend members and backend users)
- synchronize by command and/or on demand (login in frontend and backend -> no custom login module required)

## Installation & configuration

1. Run `composer require heimrichhannot/contao-ldap-bundle`.
1. Update the database.
1. **IMPORTANT: Create a backup of your tables `tl_user`, `tl_user_group`, `tl_member` and `tl_member_group` just in
   case something goes wrong.**
1. Create your configuration as described in the section "Configuration".
1. Clear the cache if your system is not in dev environment.

## Configuration

Run `vendor/bin/contao-console config:dump-reference huh_ldap` to see the complete config reference.

A sample configuration for syncing backend users could be as follows (member configuration is nearly identical):

```yaml
huh_ldap:
  user:
    connection: # here you can pass in all options allowed in symfony/ldap connections
      host: localhost
      encryption: ssl
    bind_dn: cn=admin,dc=example,dc=com
    bind_password: some_password
    person_username_ldap_field: uid # this field is used to match the username in contao login forms with the ldap representation
    person: # config for persons
      admin_gid_number: 5002
      base_dn: ou=People,dc=example,dc=com
    group: # config for groups
      base_dn: ou=Groups,dc=example,dc=com
```

## Technical details

### How does the synchronization work?

Basically, by running the command `vendor/bin/contao-console huh_ldap:sync_persons` the users and groups are imported as
specified in your `config.yml` configuration. You can do that initially to retrieve all users/members.

In addition, on login (backend and frontend), the data for the given username is retrieved from ldap and synced to the
local entity (you can specify the field being used in your `person_username_ldap_field` config).

In most cases, you won't necessarily need to call the command as a cronjob every night, because the data is retrieved on
demand on login. Nevertheless, if you need to have up-to-date data, you can call the command as often as you like ;-)

### What if the users/members already exist locally *and* in the ldap directory?

If – by means of username – a user or member already exists in the local system and in the ldap directory but has not
been "migrated", i.e. has a `ldapUidNumber` set in the database, yet?

In this case the match is done by the value of username in contao and in ldap (field is specified
by `person_username_ldap_field` in your config). Then the corresponding `ldapUidNumber` is set and the data from the
ldap directory is stored to the local user object so that everything is in sync.

### Commands

Name | Description | Options
-----|-------------|--------
`huh_ldap:sync_persons` | Synchronize the members/users as specified in your `config.yml` | `dry-run`: See what the command would do without changing any data.<br>`mode`: Limit the command to users or members ("user" or "member"). Dismiss the parameter to do both.<br>`uids`: Limit the command to specific uids by providing a comma-separated list.

### Events

Name | Description
-----|------------
AfterPersonImport | Run after a person is initially imported.
AfterPersonUpdate | Run after a person is updated.
