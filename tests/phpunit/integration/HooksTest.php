<?php

namespace MediaWiki\Extension\OAuthRateLimiter\Tests\Integration;

use MediaWiki\Extension\OAuth\Entity\ClaimEntity;
use MediaWiki\Extension\OAuth\Repository\ClaimStore;
use MediaWiki\Extension\OAuth\Tests\Entity\MockClientEntity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OAuthRateLimiter\Hooks
 * @group Database
 */
class HooksTest extends MediaWikiIntegrationTestCase {
	/**
	 * @var ClaimStore
	 */
	private $claimStore;

	protected function setUp(): void {
		$this->claimStore = new ClaimStore();
	}

	private function getClientEntity() {
		$clientEntity = MockClientEntity::newMock( $this->getTestUser()->getUser() );
		$db = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$this->assertTrue( $clientEntity->save( $db ), 'Sanity: must create a client' );

		return $clientEntity;
	}

	public static function provideDefaultClientTier() {
		yield 'empty default name' => [
			'',
			[],
			[]
		];

		yield 'default name set without match in tier config' => [
			'default',
			[
				'tier 1' => [
					'ratelimit' => [
						'requests_per_unit' => 10,
						'unit'  => 'minute'
					]
				]
			],
			[]
		];

		yield 'default name set with match in tier config' => [
			'default',
			[
				'default' => [
					'ratelimit' => [
						'requests_per_unit' => 10,
						'unit'  => 'minute'
					]
				]
			],
			[
				new ClaimEntity( 'ratelimit', [
					'requests_per_unit' => 10,
					'unit'  => 'minute'
				] )
			]
		];
	}

	/**
	 * @dataProvider provideDefaultClientTier
	 */
	public function testOnOAuthClaimStoreGetClaimsWithDefaultTier( $defaultName, $tierConfig, $expectedClaimEntities ) {
		$this->overrideConfigValues( [
			'OAuthRateLimiterDefaultClientTier' => $defaultName,
			'OAuthRateLimiterTierConfig' => $tierConfig,
		] );

		$claims = $this->claimStore->getClaims( 'dummyGrant', $this->getClientEntity() );

		$this->assertSameSize( $expectedClaimEntities, $claims );
		foreach ( $expectedClaimEntities as $index => $claimEntity ) {
			$this->assertEquals( $claimEntity->getName(), $claims[$index]->getName() );
			$this->assertEquals( $claimEntity->getValue(), $claims[$index]->getValue() );
		}
	}

	public static function provideDatabaseTiers() {
		yield 'tier name in db and matches tier config' => [
			'tier IV',
			'tier I',
			[
				'tier I' => [
					'ratelimit' => [
						'requests_per_unit' => 10,
						'unit'  => 'minute'
					]
				],
				'tier IV' => [
					'ratelimit' => [
						'requests_per_unit' => 999,
						'unit'  => 'minute'
					]
				]
			],
			[
				new ClaimEntity( 'ratelimit',
					[
						'requests_per_unit' => 999,
						'unit'  => 'minute'
					]
				)
			]
		];

		yield 'tier name in db does not match tier config' => [
			'tier II',
			'default',
			[
				'tier III' => [
					'ratelimit' => [
						'requests_per_unit' => 999,
						'unit'  => 'minute'
					]
				],
				'default' => [
					'ratelimit' => [
						'requests_per_unit' => 10,
						'unit'  => 'minute'
					]
				]
			],
			[
				new ClaimEntity( 'ratelimit',
					[
						'requests_per_unit' => 10,
						'unit'  => 'minute'
					]
				)
			]
		];

		yield 'tier name in db with empty default and tier config' => [
			'tier II',
			'',
			[],
			[]
		];
	}

	/**
	 * @dataProvider provideDatabaseTiers
	 */
	public function testOnOAuthClaimStoreGetClaimsWithTiersInDB(
		$tierName, $defaultTierName, $tierConfig, $expectedClaimEntities
	) {
		$clientEntity = $this->getClientEntity();
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'oauth_ratelimit_client_tier' )
			->row( [
				'oarct_client_id' => $clientEntity->getConsumerKey(),
				'oarct_tier_name' => $tierName,
			] )
			->caller( __METHOD__ )
			->execute();

		$this->overrideConfigValues( [
			'OAuthRateLimiterDefaultClientTier' => $defaultTierName,
			'OAuthRateLimiterTierConfig' => $tierConfig,
		] );

		$claims = $this->claimStore->getClaims( 'dummyType', $clientEntity );
		$this->assertSameSize( $expectedClaimEntities, $claims );
		foreach ( $expectedClaimEntities as $index => $claimEntity ) {
			$this->assertEquals( $claimEntity->getName(), $claims[$index]->getName() );
			$this->assertEquals( $claimEntity->getValue(), $claims[$index]->getValue() );
		}
	}
}
