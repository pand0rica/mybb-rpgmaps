<?php
/**
 * RPG Maps - Admin Module Loader
 * This file is required by myBB's admin system
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Load the actual admin module
require_once MYBB_ROOT . 'inc/plugins/rpgmaps/modules/admin/rpgmaps_admin.php';
