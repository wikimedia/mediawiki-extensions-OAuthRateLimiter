<?php

namespace MediaWiki\Extension\OAuthRateLimiter\Tests\Integration;

use MediaWiki\Extension\OAuth\Entity\ClientEntity;
use MediaWiki\Extension\OAuth\Tests\Entity\MockClientEntity;
use MediaWiki\Extension\OAuthRateLimiter\ClientTierStore;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @covers \MediaWiki\Extension\OAuthRateLimiter\ClientTierStore
 * @group Database
 */
class ClientTierStoreTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var ClientTierStore
	 */
	private $clientTierStore;

	protected function setUp(): void {
		$lbFactory = $this->getServiceContainer()->getDBLoadBalancerFactory();
		$this->loadBalancer = $lbFactory->getMainLB();
		$this->clientTierStore = new ClientTierStore( $lbFactory, false );
	}

	private function getClientEntity(): ClientEntity {
		$clientEntity = MockClientEntity::newMock( $this->getTestUser()->getUser() );
		$db = $this->loadBalancer->getConnection( DB_PRIMARY );
		$this->assertTrue( $clientEntity->save( $db ), 'Sanity: must create a client' );

		return $clientEntity;
	}

	public static function provideTiersDB() {
		yield 'Number tier name' => [
			9,
			'9'
		];

		yield 'String tier name' => [
			'Tier 1',
			'Tier 1'
		];

		yield 'Empty tier name' => [
			'',
			null
		];
	}

	/**
	 * @dataProvider provideTiersDB
	 */
	public function testGetClientTierWithDBTiers( $tierName, $expectedTierName ) {
		$clientId = $this->getClientEntity()->getConsumerKey();

		$this->assertTrue(
			$this->clientTierStore->setClientTierName( $clientId, $tierName ),
			'Sanity: must add tier to database'
		);
		$tierNameFromStore = $this->clientTierStore->getClientTierName( $clientId );

		$this->assertSame( $expectedTierName, $tierNameFromStore );
	}

	public function testGetClientTierWithoutDBTiers() {
		$clientId = $this->getClientEntity()->getConsumerKey();
		$tierFromStore = $this->clientTierStore->getClientTierName( $clientId );

		$this->assertNull( $tierFromStore );
	}
}
