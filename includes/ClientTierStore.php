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
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA, [], $this->centralWiki );

		$res = $dbr->newSelectQueryBuilder()
			->select( 'oarct_tier_name' )
			->from( 'oauth_ratelimit_client_tier' )
			->where( [ 'oarct_client_id' => $clientID ] )
			->caller( __METHOD__ )
			->fetchField();

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
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY, [], $this->centralWiki );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'oauth_ratelimit_client_tier' )
			->row( [
				'oarct_client_id' => $clientID,
				'oarct_tier_name' => $tierName,
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'oarct_client_id' ] )
			->set( [ 'oarct_tier_name' => $tierName ] )
			->caller( __METHOD__ )
			->execute();

		return (bool)$dbw->affectedRows();
	}
}
