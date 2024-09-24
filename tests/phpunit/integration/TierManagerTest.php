<?php

namespace MediaWiki\Extension\OAuthRateLimiter\Tests\Integration;

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\OAuth\Entity\ClientEntity;
use MediaWiki\Extension\OAuth\Tests\Entity\MockClientEntity;
use MediaWiki\Extension\OAuthRateLimiter\ClientTierStore;
use MediaWiki\Extension\OAuthRateLimiter\TierManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\ILBFactory;

/**
 * @covers \MediaWiki\Extension\OAuthRateLimiter\TierManager
 * @group Database
 */
class TierManagerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var ILBFactory
	 */
	private $lbFactory;

	protected function setUp(): void {
		$this->lbFactory = $this->getServiceContainer()->getDBLoadBalancerFactory();
	}

	private function getClientEntity(): ClientEntity {
		$clientEntity = MockClientEntity::newMock( $this->getTestUser()->getUser() );
		$db = $this->lbFactory->getMainLB()->getConnection( DB_PRIMARY );
		$this->assertTrue( $clientEntity->save( $db ), 'Sanity: must create a client' );

		return $clientEntity;
	}

	private function getTierManager( $defaultClientTier, $tierConfig ) {
		return new TierManager(
			new ServiceOptions(
				TierManager::CONSTRUCTOR_OPTIONS,
				new HashConfig( [
					'OAuthRateLimiterDefaultClientTier' => $defaultClientTier,
					'OAuthRateLimiterTierConfig' => $tierConfig
				] )
			),
			LoggerFactory::getInstance( 'TierManagerTest' ),
			new ClientTierStore( $this->lbFactory, false )
		);
	}

	public static function provideTiers() {
		yield 'Tier exist in config' => [
			'tier 2',
			'default',
			[
				'tier 2' => [
					'ratelimit' => [
						'requests_per_unit' => 9,
						'unit' => 'minute'
					]
				],
				'default' => [
					'ratelimit' => [
						'requests_per_unit' => 9879,
						'unit' => 'minute'
					]
				]
			],
			[
				'ratelimit' => [
					'requests_per_unit' => 9,
					'unit' => 'minute'
				]
			]
		];

		yield 'Tier does not match config but default exist' => [
			'tier 2',
			'tier 28',
			[
				'tier 28' => [
					'ratelimit' => [
						'requests_per_unit' => 987,
						'unit' => 'minute'
					]
				],
				'tier 29' => [
					'ratelimit' => [
						'requests_per_unit' => 9,
						'unit' => 'minute'
					]
				]
			],
			[
				'ratelimit' => [
					'requests_per_unit' => 987,
					'unit' => 'minute'
				]
			]
		];

		yield 'Both default name and tier config are empty' => [
			'tier x',
			'',
			[],
			[]
		];
	}

	/**
	 * @dataProvider provideTiers
	 */
	public function testGetClientTierConfig( $tierName, $defaultTierName, $tierConfig, $expectedTierConfig ) {
		$clientID = $this->getClientEntity()->getConsumerKey();
		$tierManager = $this->getTierManager( $defaultTierName, $tierConfig );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'oauth_ratelimit_client_tier' )
			->row( [
				'oarct_client_id' => $clientID,
				'oarct_tier_name' => $tierName,
			] )
			->caller( __METHOD__ )
			->execute();

		$clientTierConfig = $tierManager->getClientTierConfig( $clientID );
		$this->assertArrayEquals( $expectedTierConfig, $clientTierConfig );
	}

	public static function provideDefaultTiers() {
		yield 'default tier name match in tier config' => [
			'tier I',
			'tier I'
		];

		yield 'Default tier empty' => [
			'',
			null
		];

		yield 'Default tier set to false' => [
			false,
			null
		];
	}

	/**
	 * @dataProvider provideDefaultTiers
	 */
	public function testGetDefaultTier( $defaultTierName, $expectedTierName ) {
		$tierManager = $this->getTierManager( $defaultTierName, [] );
		$tierName = $tierManager->getDefaultTierName();

		$this->assertSame( $expectedTierName, $tierName );
	}
}
