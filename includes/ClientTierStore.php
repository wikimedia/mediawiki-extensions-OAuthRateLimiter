<?php

namespace MediaWiki\Extension\OAuthRateLimiter;

use Wikimedia\Rdbms\IConnectionProvider;

class ClientTierStore {

	/**
	 * @var IConnectionProvider
	 */
	private $connProvider;

	/**
	 * @param IConnectionProvider $connProvider
	 */
	public function __construct( IConnectionProvider $connProvider ) {
		$this->connProvider = $connProvider;
	}

	/**
	 * @param string $clientID
	 * @return int|string|null
	 */
	public function getClientTierName( string $clientID ) {
		$dbr = $this->connProvider->getReplicaDatabase( 'virtual-oauthratelimiter' );

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
		$dbw = $this->connProvider->getPrimaryDatabase( 'virtual-oauthratelimiter' );

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
