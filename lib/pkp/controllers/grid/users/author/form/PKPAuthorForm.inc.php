<?php

/**
 * @file controllers/grid/users/author/form/PKPAuthorForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorForm
 * @ingroup controllers_grid_users_author_form
 *
 * @brief Form for adding/editing a author
 */

import('lib.pkp.classes.form.Form');

class PKPAuthorForm extends Form {
	/** The context associated with the contributor being edited **/
	var $_context;

	/** The publication associated with the contributor being edited **/
	var $_publication;

	/** Author the author being edited **/
	var $_author;

	/**
	 * Constructor.
	 */
	function __construct($publication, $author, $context) {
		parent::__construct(
			'controllers/grid/users/author/form/authorForm.tpl',
			true,
			$publication->getData('locale'),
			$context->getSupportedSubmissionLocaleNames()
		);

		$this->setContext($context);
		$this->setPublication($publication);
		$this->setAuthor($author);

		// the publication locale should be the default/required locale
		$this->setDefaultFormLocale($publication->getData('locale'));

		// Validation checks for this form
		$form = $this;
		$this->addCheck(new FormValidatorLocale($this, 'givenName', 'required', 'user.profile.form.givenNameRequired', $this->defaultLocale));
		$this->addCheck(new FormValidatorCustom($this, 'familyName', 'optional', 'user.profile.form.givenNameRequired.locale', function($familyName) use ($form) {
			$givenNames = $form->getData('givenName');
			foreach ($familyName as $locale => $value) {
				if (!empty($value) && empty($givenNames[$locale])) {
					return false;
				}
			}
			return true;
		}));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'form.emailRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
		$this->addCheck(new FormValidator($this, 'userGroupId', 'required', 'submission.submit.form.contributorRoleRequired'));
		$this->addCheck(new FormValidatorORCID($this, 'orcid', 'optional', 'user.orcid.orcidInvalid'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the author
	 * @return Author
	 */
	function getAuthor() {
		return $this->_author;
	}

	/**
	 * Set the author
	 * @param @author Author
	 */
	function setAuthor($author) {
		$this->_author = $author;
	}

	/**
	 * Get the Publication
	 * @return Publication
	 */
	function getPublication() {
		return $this->_publication;
	}

	/**
	 * Set the Publication
	 * @param Publication
	 */
	function setPublication($publication) {
		$this->_publication = $publication;
	}

	/**
	 * Get the Context
	 * @return Context
	 */
	function getContext() {
		return $this->_context;
	}

	/**
	 * Set the Context
	 * @param Context
	 */
	function setContext($context) {
		$this->_context = $context;
	}

	//
	// Overridden template methods
	//
	/**
	 * Initialize form data from the associated author.
	 */
	function initData() {
		$author = $this->getAuthor();

		if ($author) {
			$this->_data = array(
				'authorId' => $author->getId(),
				'givenName' => $author->getGivenName(null),
				'familyName' => $author->getFamilyName(null),
				'preferredPublicName' => $author->getPreferredPublicName(null),
				'affiliation' => $author->getAffiliation(null),
				'country' => $author->getCountry(),
				'email' => $author->getEmail(),
				'userUrl' => $author->getUrl(),
				'orcid' => $author->getOrcid(),
				'competingInterests' => $author->getCompetingInterests(null),
				'userGroupId' => $author->getUserGroupId(),
				'biography' => $author->getBiography(null),
				'primaryContact' => $this->getPublication()->getData('primaryContactId') === $author->getId(),
				'includeInBrowse' => $author->getIncludeInBrowse(),
			);
		} else {
			// assume authors should be listed unless otherwise specified.
			$this->_data = array('includeInBrowse' => true);
		}
		// in order to be able to use the hook
		return parent::initData();
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		AppLocale::requireComponents([LOCALE_COMPONENT_PKP_REVIEWER]);
		$context = $request->getContext();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$authorUserGroups = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR);
		$publication = $this->getPublication();
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		$countries = array();
		foreach ($isoCodes->getCountries() as $country) {
			$countries[$country->getAlpha2()] = $country->getLocalName();
		}
		asort($countries);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'submissionId' => $publication->getData('submissionId'),
			'publicationId' => $publication->getId(),
			'countries' => $countries,
			'authorUserGroups' => $authorUserGroups,
			'requireAuthorCompetingInterests' => $context->getData('requireAuthorCompetingInterests'),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'authorId',
			'givenName',
			'familyName',
			'preferredPublicName',
			'affiliation',
			'country',
			'email',
			'userUrl',
			'orcid',
			'competingInterests',
			'userGroupId',
			'biography',
			'primaryContact',
			'includeInBrowse',
		));
	}

	/**
	 * Save author
	 * @see Form::execute()
	 */
	function execute(...$functionParams) {
		$context = $this->getContext();

		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
		$publication = $this->getPublication();

		$author = $this->getAuthor();
		if (!$author) {
			// this is a new submission contributor
			$this->_author = $authorDao->newDataObject();
			$author = $this->getAuthor();
			$author->setData('publicationId', $publication->getId());
			$author->setData('seq', count($publication->getData('authors')));
			$existingAuthor = false;
		} else {
			$existingAuthor = true;
			if ($publication->getId() !== $author->getData('publicationId')) fatalError('Invalid author!');
		}

		$author->setGivenName(array_map('trim',$this->getData('givenName')), null);
		$author->setFamilyName($this->getData('familyName'), null);
		$author->setPreferredPublicName($this->getData('preferredPublicName'), null);
		$author->setAffiliation($this->getData('affiliation'), null); // localized
		$author->setCountry($this->getData('country'));
		$author->setEmail($this->getData('email'));
		$author->setUrl($this->getData('userUrl'));
		$author->setOrcid($this->getData('orcid'));
		if ($context->getData('requireAuthorCompetingInterests')) $author->setCompetingInterests($this->getData('competingInterests'), null);
		$author->setUserGroupId($this->getData('userGroupId'));
		$author->setBiography($this->getData('biography'), null); // localized
		$author->setIncludeInBrowse(($this->getData('includeInBrowse') ? true : false));

		// in order to be able to use the hook
		parent::execute(...$functionParams);

		if ($existingAuthor) {
			$authorDao->updateObject($author);
			$authorId = $author->getId();
		} else {
			$authorId = $authorDao->insertObject($author);
		}

		if ($this->getData('primaryContact')) {
			$params = ['primaryContactId' => $authorId];
			$errors = Services::get('publication')->validate(
				VALIDATE_ACTION_EDIT,
				$params,
				$context->getData('supportedLocales'),
				$publication->getData('locale')
			);
			if (!empty($errors)) {
				throw new Exception('Invalid primary contact ID. This author can not be a primary contact.');
			}
			$publication = Services::get('publication')->edit($publication, $params, Application::get()->getRequest());
		}

		return $authorId;
	}
}


