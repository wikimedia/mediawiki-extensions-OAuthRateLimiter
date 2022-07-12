<?php

namespace MediaWiki\Extension\OAuthRateLimiter;

use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\ILoadBalancer;

class ClientTierStore {

	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var string|false
	 */
	private $centralWiki;

	/**
	 * @param ILBFactory $lbFactory
	 * @param string|false $centralWiki
	 */
	public function __construct(
		ILBFactory $lbFactory,
		$centralWiki
	) {
		$this->centralWiki = $centralWiki;
		$this->loadBalancer = $lbFactory->getMainLB( $centralWiki );
	}

	/**
	 * @param string $clientID
	 * @return int|string|null
	 */
	public function getClientTierName( string $clientID ) {
		$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA, [], $this->centralWiki );

		$res = $dbr->selectField(
			'oauth_ratelimit_client_tier',
			'oarct_tier_name',
			[ 'oarct_client_id' => $clientID ],
			__METHOD__
		);

		if ( $res ) {
			return $res;
		}

		return null;
	}

	/**
	 * @param string $clientID
	 * @param string $tierName
	 * @return bool True if successful, false otherwise
	 */
	public function setClientTierName( string $clientID, string $tierName ): bool {
		$dbw = $this->loadBalancer->getConnectionRef( DB_PRIMARY, [], $this->centralWiki );

		$dbw->upsert(
			'oauth_ratelimit_client_tier',
			[
				'oarct_client_id' => $clientID,
				'oarct_tier_name' => $tierName,
			],
			[ [ 'oarct_client_id' ] ],
			[ 'oarct_tier_name' => $tierName ],
			__METHOD__
		);

		return (bool)$dbw->affectedRows();
	}
}
