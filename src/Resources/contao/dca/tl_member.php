<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

$dca = &$GLOBALS['TL_DCA']['tl_member'];

/*
 * Fields
 */
$dca['fields']['ldapUidNumber'] = [
    'sql' => "int(10) unsigned NOT NULL default '0'",
];
