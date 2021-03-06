<?php
/*
 -------------------------------------------------------------------------
 Tag plugin for GLPI
 Copyright (C) 2003-2017 by the Tag Development Team.

 https://github.com/pluginsGLPI/tag
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Tag.

 Tag is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Tag is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Tag. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

define ('PLUGIN_TAG_VERSION', '2.4.0');

// Minimal GLPI version, inclusive
define("PLUGIN_TAG_MIN_GLPI", "9.4");
// Maximum GLPI version, exclusive
define("PLUGIN_TAG_MAX_GLPI", "9.5");

/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_tag_check_config($verbose = false) {
   return true;
}

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_tag() {
   global $PLUGIN_HOOKS, $UNINSTALL_TYPES, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['tag'] = true;

   $plugin = new Plugin();
   if ($plugin->isInstalled("tag") && $plugin->isActivated("tag")) {

      // define list of itemtype which can be associated with tags
      $CFG_GLPI['plugin_tag_itemtypes'] = [
         __('Assets')         => ['Computer', 'Monitor', 'Software', 'NetworkEquipment',
                                  'Peripheral', 'Printer', 'CartridgeItem', 'ConsumableItem',
                                  'Phone'],
         __('Assistance')     => ['Ticket', 'Problem', 'Change', 'TicketRecurrent',
                                  'TicketTemplate'],
         __('Management')     => ['Budget', 'Supplier', 'Contact', 'Contract', 'Document'],
         __('Tools')          => ['Project', 'Reminder', 'RSSFeed', 'KnowbaseItem'],
         __('Administration') => ['User', 'Group', 'Entity', 'Profile'],
         __('Setup')          => ['SLA', 'SlaLevel', 'Link'],
      ];

      if ($plugin->isInstalled('appliances') && $plugin->isActivated('appliances')) {
         $CFG_GLPI['plugin_tag_itemtypes'][__('Assets')][] = 'PluginAppliancesAppliance';
      }

      // add link on plugin name in Configuration > Plugin
      $PLUGIN_HOOKS['config_page']['tag'] = "front/tag.php";

      // require spectrum (for glpi >= 9.2)
      $CFG_GLPI['javascript']['config']['commondropdown']['PluginTagTag'] = ['colorpicker'];

      // Plugin use specific massive actions
      $PLUGIN_HOOKS['use_massive_action']['tag'] = true;

      // Plugin uninstall : after uninstall action
      if ($plugin->isInstalled("uninstall") && $plugin->isActivated("uninstall")) {
         foreach ($UNINSTALL_TYPES as $u_itemtype) {
            $PLUGIN_HOOKS['plugin_uninstall_after']['tag'][$u_itemtype] = 'plugin_uninstall_after_tag';
         }
      }

      // insert tag dropdown into all possible itemtypes
      $PLUGIN_HOOKS['pre_item_form']['tag'] = ['PluginTagTag', 'preItemForm'];

      // plugin datainjection
      $PLUGIN_HOOKS['plugin_datainjection_populate']['tag'] = "plugin_datainjection_populate_tag";

      // add needed javascript & css files
      $PLUGIN_HOOKS['add_javascript']['tag'][] = 'js/common.js';
      $PLUGIN_HOOKS['add_css']['tag'][]        = 'css/tag.css';
      if (Session::isMultiEntitiesMode()) {
         $PLUGIN_HOOKS['add_javascript']['tag'][] = 'js/entity.js';
      }

      // hook on object changes
      if ($itemtype = PluginTagTag::getCurrentItemtype()) {
         if (PluginTagTag::canItemtype($itemtype)) {
            $PLUGIN_HOOKS['item_add']['tag'][$itemtype]        = ['PluginTagTagItem', 'updateItem'];
            $PLUGIN_HOOKS['pre_item_update']['tag'][$itemtype] = ['PluginTagTagItem', 'updateItem'];
            $PLUGIN_HOOKS['pre_item_purge']['tag'][$itemtype]  = ['PluginTagTagItem', 'purgeItem'];
         }
      }
   }
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_tag() {
   return [
      'name'       => __('Tag Management', 'tag'),
      'version'        => PLUGIN_TAG_VERSION,
      'author'         => '<a href="http://www.teclib.com">Teclib\'</a> - Infotel conseil',
      'homepage'       => 'https://github.com/pluginsGLPI/tag',
      'license'        => '<a href="../plugins/tag/LICENSE" target="_blank">GPLv2+</a>',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_TAG_MIN_GLPI,
            'max' => PLUGIN_TAG_MAX_GLPI,
            'dev' => true, //Required to allow 9.2-dev
         ]
      ]
   ];
}

/**
 * Check pre-requisites before install
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_tag_check_prerequisites() {

   //Version check is not done by core in GLPI < 9.2 but has to be delegated to core in GLPI >= 9.2.
   if (!method_exists('Plugin', 'checkGlpiVersion')) {
      $version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);
      $matchMinGlpiReq = version_compare($version, PLUGIN_TAG_MIN_GLPI, '>=');
      $matchMaxGlpiReq = version_compare($version, PLUGIN_TAG_MAX_GLPI, '<');

      if (!$matchMinGlpiReq || !$matchMaxGlpiReq) {
         echo vsprintf(
            'This plugin requires GLPI >= %1$s and < %2$s.',
            [
               PLUGIN_TAG_MIN_GLPI,
               PLUGIN_TAG_MAX_GLPI,
            ]
         );
         return false;
      }
   }

   return true;
}

function idealTextColor($hexTripletColor) {
   $nThreshold      = 105;
   $hexTripletColor = str_replace('#', '', $hexTripletColor);
   $components      = [
      'R' => hexdec(substr($hexTripletColor, 0, 2)),
      'G' => hexdec(substr($hexTripletColor, 2, 2)),
      'B' => hexdec(substr($hexTripletColor, 4, 2)),
   ];
   $bgDelta = ($components['R'] * 0.299)
            + ($components['G'] * 0.587)
            + ($components['B'] * 0.114);
   return (((255 - $bgDelta) < $nThreshold) ? "#000000" : "#ffffff");
}
