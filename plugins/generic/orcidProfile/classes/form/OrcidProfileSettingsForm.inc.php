<?php

/**
 * @file OrcidProfileSettingsForm.inc.php
 *
 * Copyright (c) 2015-2019 University of Pittsburgh
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidProfileSettingsForm
 * @ingroup plugins_generic_orcidProfile
 *
 * @brief Form for site admins to modify ORCID Profile plugin settings
 */


import('lib.pkp.classes.form.Form');
import('plugins.generic.orcidProfile.classes.OrcidValidator');

class OrcidProfileSettingsForm extends Form {

	const CONFIG_VARS = array(
		'orcidProfileAPIPath' => 'string',
		'orcidClientId' => 'string',
		'orcidClientSecret' => 'string',
		'sendMailToAuthorsOnPublication' => 'bool',
		'logLevel' => 'string',
		'isSandBox' => 'bool',
		'country' => 'string',
		'city' => 'string'

	);
	/** @var $contextId int */
	var $contextId;

	/** @var $plugin object */
	var $plugin;

	var $validator;

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $contextId int
	 */
	function __construct($plugin, $contextId) {
		$this->contextId = $contextId;
		$this->plugin = $plugin;
		$orcidValidator = new OrcidValidator($plugin);
		$this->validator = $orcidValidator;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));

		if (!$this->plugin->isGloballyConfigured()) {
			$this->addCheck(new FormValidator($this, 'orcidProfileAPIPath', 'required', 'plugins.generic.orcidProfile.manager.settings.orcidAPIPathRequired'));
			$this->addCheck(new FormValidatorCustom($this, 'orcidClientId', 'required', 'plugins.generic.orcidProfile.manager.settings.orcidClientId.error', function ($clientId) {
				return $this->validator->validateClientId($clientId);
			}));
			$this->addCheck(new FormValidatorCustom($this, 'orcidClientSecret', 'required', 'plugins.generic.orcidProfile.manager.settings.orcidClientSecret.error', function ($clientSecret) {
				return $this->validator->validateClientSecret($clientSecret);
			}));
		}

	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$contextId = $this->contextId;
		$plugin =& $this->plugin;
		$this->_data = array();
		foreach (self::CONFIG_VARS as $configVar => $type) {
			$this->_data[$configVar] = $plugin->getSetting($contextId, $configVar);
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array_keys(self::CONFIG_VARS));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('globallyConfigured', $this->plugin->isGloballyConfigured());
		$templateMgr->assign('pluginName', $this->plugin->getName());
		$templateMgr->assign('applicationName', Application::get()->getName());
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$plugin =& $this->plugin;
		$contextId = $this->contextId;
		foreach (self::CONFIG_VARS as $configVar => $type) {
			if ($configVar === 'orcidProfileAPIPath') {
				$plugin->updateSetting($contextId, $configVar, trim($this->getData($configVar), "\"\';"), $type);
			} else {
				$plugin->updateSetting($contextId, $configVar, $this->getData($configVar), $type);
			}
		}
		if (strpos($this->getData("orcidProfileAPIPath"), "sandbox.orcid.org") == true) {
			$plugin->updateSetting($contextId, "isSandBox", true, "bool");
		}

		parent::execute(...$functionArgs);
	}

	public function _checkPrerequisites() {
		$messages = array();

		$clientId = $this->getData('orcidClientId');
		if (!$this->validator->validateClientId($clientId)) {
			$messages[] = __('plugins.generic.orcidProfile.manager.settings.orcidClientId.error');
		}
		$clientSecret = $this->getData('orcidClientSecret');
		if (!$this->validator->validateClientSecret($clientSecret)) {
			$messages[] = __('plugins.generic.orcidProfile.manager.settings.orcidClientSecret.error');
		}
		if (strlen($clientId) == 0 or strlen($clientSecret) == 0) {
			$this->plugin->setEnabled(false);
		}
		return $messages;
	}


}

