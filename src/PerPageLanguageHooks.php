<?php

namespace PerPageLanguage;

use IContextSource;
use Language;
use MediaWiki\MediaWikiServices;
use ReflectionException;
use ReflectionMethod;
use SkinTemplate;
use SpecialPage;
use User;

class PerPageLanguageHooks {

	/**
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public static function onSkinTemplateNavigationUniversal( SkinTemplate $sktemplate, array &$links ) {
		global $wgPageLanguageUseDB;

		if ( !$wgPageLanguageUseDB ) {
			// Nothing to do if the setting is disabled
			return;
		}

		$context = $sktemplate->getContext();
		$title = $context->getTitle();
		$user = $context->getUser();

		if ( !$title ) {
			return;
		}

		if ( !$title->exists() ) {
			return;
		}

		if ( $title->getNamespace() === NS_SPECIAL ) {
			return;
		}

		if ( !MediaWikiServices::getInstance()->getPermissionManager()->userHasRight( $user, 'pagelang' ) ) {
			return;
		}

		$links['actions']['pagelang'] = [
			'class' => false,
			'text' => $sktemplate->msg( 'perpagelanguage-label' )->text(),
			'href' => SpecialPage::getTitleFor( 'PageLanguage' )->getLocalURL()
					  . '/' . $context->getTitle()->getPrefixedText()
		];
	}

	/**
	 * @param User $user
	 * @param string &$code
	 * @param IContextSource $context
	 *
	 * @throws ReflectionException
	 */
	public static function onUserGetLanguageObject( User $user, string &$code, IContextSource $context ) {
		global $wgPageLanguageUseDB, $wgPerPageLanguageIgnoreUserSetting, $wgLanguageCode;

		if ( !$wgPageLanguageUseDB ) {
			// Nothing to do if the setting is disabled
			return;
		}

		$title = $context->getTitle();
		if ( !$title || !$title->exists() ) {
			// Do nothing for non existing titles
			return;
		}

		// To avoid language recursion in RequestContext::getLanguage
		// we always need to check if the page has language set in the database
		// and otherwise just bow out quickly
		// We need some magic here! *~~o-_*
		try {
			$method = new ReflectionMethod( $title, 'getDbPageLanguageCode' );
		} catch ( ReflectionException $e ) {
			// Do nothing on fail
			return;
		}
		$method->setAccessible( true );
		/** @var Language $pageLanguageDB */
		$pageLanguageDB = $method->invoke( $title );
		if ( !$pageLanguageDB ) {
			// Do nothing if the page has no language set in the database
			return;
		}

		if ( !$wgPerPageLanguageIgnoreUserSetting ) {
			// If we want to respect the user preference on language
			$userLanguage = MediaWikiServices::getInstance()
				->getUserOptionsLookup()
				->getOption( $user, 'language' );
			if ( $userLanguage != $wgLanguageCode ) {
				// If user did set language option to value different from
				// the default one - do nothing
				return;
			}
		}
		// Forcefully set the interface language code to the page view language
		// Handling PageViewLanguage for language variants is needed here
		// Directly using $pageLanguageDB will ignore language variant selection
		$code = Language::factory( $pageLanguageDB )->getPreferredVariant();
	}

}
