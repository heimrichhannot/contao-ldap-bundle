<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

$dca = &$GLOBALS['TL_DCA']['tl_user_group'];

/*
 * Fields
 */
$dca['fields']['ldapGid'] = [
    'sql' => "int(10) unsigned NOT NULL default '0'",
];
