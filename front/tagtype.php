<?php
include ('../../../inc/includes.php');

Plugin::load('tag', true);

$plugin = new Plugin();
if ($plugin->isInstalled("tag") && $plugin->isActivated("tag")) {

   $dropdown = new PluginTagTagtype();
   
   include (GLPI_ROOT . "/front/dropdown.common.php");
}
