<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

$dca = &$GLOBALS['TL_DCA']['tl_user'];

/*
 * Fields
 */
$dca['fields']['ldapUid'] = [
    'sql' => "varchar(255) NOT NULL default ''",
];
