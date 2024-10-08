<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\OAuth\Backend\Utils;
use MediaWiki\Extension\OAuthRateLimiter\ClientTierStore;
use MediaWiki\Extension\OAuthRateLimiter\TierManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'OAuthRateLimiterClientTierStore' => static function ( MediaWikiServices $services ): ClientTierStore {
		return new ClientTierStore(
			$services->getDBLoadBalancerFactory(),
			Utils::getCentralWiki()
		);
	},

	'OAuthRateLimiterTierManager' => static function ( MediaWikiServices $services ): TierManager {
		return new TierManager(
			new ServiceOptions( TierManager::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			LoggerFactory::getInstance( 'OAuthRateLimiterTierManager' ),
			$services->getService( 'OAuthRateLimiterClientTierStore' )
		);
	},
];
