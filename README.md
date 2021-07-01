# Contao LDAP Bundle

This bundle offers functionality concerning LDAP servers for the Contao CMS.

## Features

- synchronization for users and their groups from an ldap server (works both for frontend members and backend users)

## Installation & configuration

1. Run `composer require heimrichhannot/contao-ldap-bundle`.
1. Update the database.

## Technical details

### How does the synchronization work?

Basically, by running the command `vendor/bin/contao-console huh_ldap:sync` the groups and users are imported as
specified in your `config.yml` configuration. In most cases it will be sufficient to run this command as a cronjob once
per day in order to have all users up-to-date.

### Commands

Name | Description
-----|------------
huh_ldap:sync | Synchronize the members/users as specified in your `config.yml`

### Events

Name | Description
-----|------------
AfterPersonImport | Run after a person is initially imported.
AfterPersonUpdate | Run after a person is updated.

TODO: implement
