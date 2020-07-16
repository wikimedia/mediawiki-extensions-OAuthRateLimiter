<?php

namespace MediaWiki\Extension\OAuthRateLimiter;

use DatabaseUpdater;
use MediaWiki\Extensions\OAuth\Backend\Utils;
use MediaWiki\Extensions\OAuth\Entity\ClaimEntity;
use MediaWiki\Extensions\OAuth\Entity\MWClientEntityInterface;
use MediaWiki\Extensions\OAuth\Repository\Hook\OAuthClaimStoreGetClaimsHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class Hooks implements LoadExtensionSchemaUpdatesHook, OAuthClaimStoreGetClaimsHook {

	/**
	 * @var TierManager
	 */
	private $tierManager;

	/**
	 * @param TierManager $tierManager
	 */
	public function __construct( TierManager $tierManager ) {
		$this->tierManager = $tierManager;
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool|void
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		if ( !Utils::isCentralWiki() ) {
			// no tables to add
			return true;
		}

		$dbType = $updater->getDB()->getType();

		if ( $dbType === 'mysql' ) {
			$updater->addExtensionTable(
				'oauth_ratelimit_client_tier',
				dirname( __DIR__ ) . '/schema/tables-generated.sql'
			);
		} elseif ( $dbType === 'sqlite' ) {
			$updater->addExtensionTable(
				'oauth_ratelimit_client_tier',
				dirname( __DIR__ ) . '/schema/sqlite/tables-generated.sql'
			);
		}
	}

	/**
	 * @param string $grantType
	 * @param MWClientEntityInterface $clientEntity
	 * @param array &$privateClaims
	 * @param string|null $userIdentifier
	 */
	public function onOAuthClaimStoreGetClaims(
		string $grantType, MWClientEntityInterface $clientEntity, array &$privateClaims, $userIdentifier = null
	) {
		$clientID = $clientEntity->getIdentifier();
		$res = $this->tierManager->getClientTierConfig( $clientID );

		foreach ( $res as $name => $value ) {
			$privateClaims[] = new ClaimEntity( $name, $value );
		}
	}
}
