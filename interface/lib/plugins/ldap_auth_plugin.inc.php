<?php

/*
Copyright (c) 2025, ISPConfig LDAP Auth Contributors
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

	* Redistributions of source code must retain the above copyright notice,
	  this list of conditions and the following disclaimer.
	* Redistributions in binary form must reproduce the above copyright notice,
	  this list of conditions and the following disclaimer in the documentation
	  and/or other materials provided with the distribution.
	* Neither the name of ISPConfig nor the names of its contributors
	  may be used to endorse or promote products derived from this software without
	  specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/**
 * ISPConfig LDAP Authentication Plugin
 *
 * Adds LDAP authentication capability fields to mail domains and mailboxes.
 * Integrates with ispconfig_ldap_auth_server for external LDAP authentication.
 *
 * @package ISPConfig
 * @subpackage LDAPAuth
 * @version 1.0.0
 */
class ldap_auth_plugin {

	var $plugin_name = 'ldap_auth_plugin';
	var $class_name = 'ldap_auth_plugin';
	var $plugin_dir;

	private $ldap_tables = array(
		'mail_domain' => array('ldap_enabled'),
		'mail_user' => array('ldap_enabled')
	);

	/**
	 * Constructor
	 */
	function __construct() {
		$this->plugin_dir = ISPC_ROOT_PATH . '/lib/plugins/' . $this->plugin_name;
	}

	/**
	 * This function is called when the plugin is loaded
	 *
	 * @return void
	 */
	function onLoad() {
		global $app;

		// Check and create database columns if needed
		$this->checkTables();

		// Register events to inject fields into forms
		$app->plugin->registerEvent('mail:mail_domain:on_after_formdef', $this->plugin_name, 'mail_domain_form');
		$app->plugin->registerEvent('mail:mail_domain:on_remote_after_formdef', $this->plugin_name, 'mail_domain_form');

		$app->plugin->registerEvent('mail:mail_user:on_after_formdef', $this->plugin_name, 'mail_user_form');
		$app->plugin->registerEvent('mail:mail_user:on_remote_after_formdef', $this->plugin_name, 'mail_user_form');
	}

	/**
	 * Check if required database columns exist and create them if needed
	 *
	 * @return void
	 */
	private function checkTables() {
		global $app;

		foreach($this->ldap_tables as $table => $columns) {
			$list = "'" . implode("','", $columns) . "'";
			$sql = "SHOW COLUMNS FROM $table WHERE Field IN($list)";
			$result = $app->db->queryAllRecords($sql);

			// If columns don't exist, create them
			if(empty($result)) {
				$this->createColumns($table);
			}
		}
	}

	/**
	 * Create database columns from SQL file
	 *
	 * @param string $table Table name
	 * @return void
	 */
	private function createColumns($table) {
		global $app;

		$file = $this->plugin_dir . "/sql/$table.sql";

		if(is_file($file)) {
			$sql = preg_replace('/\s+/', ' ', file_get_contents($file));
			if($sql) {
				$app->db->query($sql);
			}
		}
	}

	/**
	 * Add LDAP Auth tab and fields to mail domain form
	 *
	 * @param string $event_name Event name
	 * @param object $page_form Form object
	 * @return void
	 */
	function mail_domain_form($event_name, $page_form) {
		$this->loadLang($page_form);

		$tabs = array(
			'ldap_auth' => array(
				'title' => 'LDAP Auth',
				'width' => 100,
				'template' => $this->plugin_dir . '/templates/mail_domain_edit.htm',
				'fields' => array(
					'ldap_enabled' => array(
						'datatype' => 'VARCHAR',
						'formtype' => 'CHECKBOX',
						'default' => 'n',
						'value' => array(1 => 'y', 0 => 'n')
					)
				)
			)
		);

		$this->insert($tabs, $page_form);
	}

	/**
	 * Add LDAP Auth tab and fields to mail user form
	 *
	 * @param string $event_name Event name
	 * @param object $page_form Form object
	 * @return void
	 */
	function mail_user_form($event_name, $page_form) {
		$this->loadLang($page_form);

		$tabs = array(
			'ldap_auth' => array(
				'title' => 'LDAP Auth',
				'width' => 100,
				'template' => $this->plugin_dir . '/templates/mail_user_edit.htm',
				'fields' => array(
					'ldap_enabled' => array(
						'datatype' => 'VARCHAR',
						'formtype' => 'CHECKBOX',
						'default' => 'n',
						'value' => array(1 => 'y', 0 => 'n')
					)
				)
			)
		);

		$this->insert($tabs, $page_form);
	}

	/**
	 * Load language file for the plugin
	 *
	 * @param object $page_form Form object
	 * @return void
	 */
	private function loadLang($page_form) {
		global $app, $conf;

		$language = $app->functions->check_language(
			$_SESSION['s']['user']['language'] ?? $conf['language']
		);

		$file = $this->plugin_dir . "/lib/lang/$language.lng";

		if(!is_file($file)) {
			$file = $this->plugin_dir . "/lib/lang/en.lng";
		}

		@include $file;

		if(isset($page_form->wordbook) && isset($wb) && is_array($wb)) {
			if(is_array($page_form->wordbook)) {
				$page_form->wordbook = array_merge($page_form->wordbook, $wb);
			} else {
				$page_form->wordbook = $wb;
			}
		}
	}

	/**
	 * Insert tabs into form definition
	 *
	 * @param array $tabs Tabs to insert
	 * @param object $page_form Form object
	 * @return void
	 */
	private function insert($tabs, $page_form) {
		if(isset($page_form->formDef['tabs'])) {
			$page_form->formDef['tabs'] += $tabs;
		} elseif(isset($page_form->formDef['fields'])) {
			// Fallback: if no tabs, add fields directly
			foreach($tabs as $tab) {
				foreach($tab['fields'] as $key => $value) {
					$page_form->formDef['fields'][$key] = $value;
				}
			}
		}
	}
}

?>
