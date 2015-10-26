<?php

namespace Smartling\Settings;

use Smartling\DbAl\EntityManagerAbstract;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;

/**
 * Class SettingsManager
 *
 * @package Smartling\Settings
 */
class SettingsManager extends EntityManagerAbstract {
	public function getEntities ( $sortOptions = [ ], $pageOptions = null, & $totalCount, $onlyActive = false ) {
		$validRequest = $this->validateRequest( $sortOptions, $pageOptions );
		$result       = [ ];
		if ( $validRequest ) {

			$cb = null;

			if ( true === $onlyActive ) {
				$cb = ConditionBlock::getConditionBlock();

				$cb->addCondition(
					Condition::getCondition(
						ConditionBuilder::CONDITION_SIGN_EQ,
						'is_active',
						[
							1,
						]
					)
				);
			}
			$dataQuery  = $this->buildQuery( $sortOptions, $pageOptions, $cb );
			$countQuery = $this->buildCountQuery();
			$tc         = $this->getDbal()->fetch( $countQuery );
			if ( 1 === count( $tc ) ) {
				// extracting from result
				$totalCount = (int) $tc[0]->cnt;
			}

			$result = $this->fetchData( $dataQuery );
		}

		return $result;
	}

	public function getEntityById ( $id ) {
		$cond = ConditionBlock::getConditionBlock();
		$cond->addCondition( Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'id', [ $id ] ) );
		$dataQuery = $this->buildQuery( [ ], null, $cond );
		$result    = $this->fetchData( $dataQuery );

		return $result;
	}

	protected function dbResultToEntity ( array $dbRow ) {
		return ConfigurationProfileEntity::fromArray( (array) $dbRow, $this->getLogger() );
	}

	private function buildQuery ( $sortOptions, $pageOptions, ConditionBlock $whereOptions = null ) {
		$query = QueryBuilder::buildSelectQuery(
			$this->getDbal()->completeTableName( ConfigurationProfileEntity::getTableName() ),
			array_keys( ConfigurationProfileEntity::getFieldDefinitions() ),
			$whereOptions,
			$sortOptions,
			$pageOptions
		);
		$this->logQuery($query);

		return $query;
	}

	public function buildCountQuery () {
		$query = QueryBuilder::buildSelectQuery(
			$this->getDbal()->completeTableName( ConfigurationProfileEntity::getTableName() ),
			[ [ 'COUNT(*)', 'cnt' ] ],
			null,
			[ ],
			null
		);
		$this->logQuery($query);

		return $query;
	}

	public function fetchData ( $query ) {
		$data = parent::fetchData( $query );
		foreach ( $data as & $result ) {
			$this->updateLabels( $result );
		}

		return $data;
	}

	public function findEntityByMainLocale ( $sourceBlogId ) {

		$conditionBlock = ConditionBlock::getConditionBlock(
			ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND
		);

		$conditionBlock->addCondition(
			Condition::getCondition(
				ConditionBuilder::CONDITION_SIGN_EQ,
				'original_blog_id',
				[ $sourceBlogId ]
			)
		);

		$conditionBlock->addCondition(
			Condition::getCondition(
				ConditionBuilder::CONDITION_SIGN_EQ,
				'is_active',
				[ 1 ]
			)
		);

		$result = $this->fetchData( $this->buildQuery( [ ], null, $conditionBlock ) );

		return $result;
	}

	private function validateRequest ( $sortOptions, $pageOptions ) {
		$fSortOptionsAreValid = QueryBuilder::validateSortOptions( array_keys( ConfigurationProfileEntity::getFieldDefinitions() ),
			$sortOptions );

		$fPageOptionsValid = QueryBuilder::validatePageOptions( $pageOptions );

		$validRequest = $fPageOptionsValid && $fSortOptionsAreValid;

		return ( $validRequest === true );
	}

	public function storeEntity ( ConfigurationProfileEntity $entity ) {
		$entityId = $entity->getId();

		$is_insert = in_array( $entityId, [ 0, null ], true );

		$fields = $entity->toArray( false );


		unset ( $fields['id'] );

		if ( $is_insert ) {
			$storeQuery = QueryBuilder::buildInsertQuery(
				$this->getDbal()->completeTableName(
					ConfigurationProfileEntity::getTableName()
				),
				$fields
			);
		} else {
			// update
			$conditionBlock = ConditionBlock::getConditionBlock();
			$conditionBlock->addCondition(
				Condition::getCondition(
					ConditionBuilder::CONDITION_SIGN_EQ,
					'id',
					[ $entityId ]
				)
			);
			$storeQuery = QueryBuilder::buildUpdateQuery(
				$this->getDbal()->completeTableName(
					ConfigurationProfileEntity::getTableName()
				),
				$fields,
				$conditionBlock,
				[ 'limit' => 1 ]
			);
		}

		// log store query before execution
		$this->logQuery($storeQuery);

		$result = $this->getDbal()->query( $storeQuery );

		if ( false === $result ) {
			$message = vsprintf( 'Failed saving submission entity to database with following error message: %s',
				[ $this->getDbal()->getLastErrorMessage() ] );

			$this->getLogger()->error( $message );
		}

		if ( true === $is_insert && false !== $result ) {
			$entityFields       = $entity->toArray( false );
			$entityFields['id'] = $this->getDbal()->getLastInsertedId();
			// update reference to entity
			$entity = ConfigurationProfileEntity::fromArray( $entityFields, $this->getLogger() );
		}

		return $entity;
	}

	public function createProfile ( array $fields ) {
		return ConfigurationProfileEntity::fromArray( $fields, $this->getLogger() );
	}

	protected function updateLabels ( ConfigurationProfileEntity $entity ) {
		$mainLocaleBlogId = $entity->getOriginalBlogId()->getBlogId();
		if ( 0 < $mainLocaleBlogId ) {
			$entity->getOriginalBlogId()->setLabel(
				$this->getSiteHelper()->getBlogLabelById(
					$this->getPluginProxy(),
					$mainLocaleBlogId
				)
			);
		}

		if ( 0 < count( $entity->getTargetLocales() ) ) {
			foreach ( $entity->getTargetLocales() as $targetLocale ) {
				$blogId = $targetLocale->getBlogId();
				if ( 0 < $blogId ) {
					$targetLocale->setLabel( $this->getSiteHelper()->getBlogLabelById( $this->getPluginProxy(),
						$blogId ) );
				}
			}
		}

		return $entity;
	}
}