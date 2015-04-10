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

	private $mapper;

	/**
	 * @return mixed
	 */
	public function getMapper () {
		return $this->mapper;
	}

	/**
	 * @param mixed $mapper
	 */
	public function setMapper ( $mapper ) {
		$this->mapper = $mapper;
	}


	public function getEntities ( $sortOptions = array (), $pageOptions = null, & $totalCount ) {
		$validRequest = $this->validateRequest( $sortOptions, $pageOptions );
		$result       = array ();
		if ( $validRequest ) {
			$dataQuery  = $this->buildQuery( $sortOptions, $pageOptions );
			$countQuery = $this->buildCountQuery();
			$totalCount = $this->getDbal()->fetch( $countQuery );
			// extracting from result
			$totalCount = (int) $totalCount[0]->cnt;
			$result     = $this->fetchData( $dataQuery );
		}

		return $result;
	}

	public function getEntityById ( $id ) {
		$cond = ConditionBlock::getConditionBlock();
		$cond->addCondition( Condition::getCondition( ConditionBuilder::CONDITION_SIGN_EQ, 'id', array ( $id ) ) );
		$dataQuery = $this->buildQuery( array (), null, $cond );
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
		$this->getLogger()->debug( $query );

		return $query;
	}

	public function buildCountQuery () {
		$query = QueryBuilder::buildSelectQuery(
			$this->getDbal()->completeTableName( ConfigurationProfileEntity::getTableName() ),
			array ( array ( 'COUNT(*)', 'cnt' ) ),
			null,
			array (),
			null
		);
		$this->getLogger()->debug( $query );

		return $query;
	}

	public function fetchData ( $query ) {
		$data = parent::fetchData( $query );
		foreach ( $data as & $result ) {
			$this->updateLabels( $result );
		}

		return $data;
	}

	public function findEntityByMainLocale ( $mainLocale ) {

		$conditionBlock = ConditionBlock::getConditionBlock(
			ConditionBuilder::CONDITION_BLOCK_LEVEL_OPERATOR_AND
		);

		$conditionBlock->addCondition(
			Condition::getCondition(
				ConditionBuilder::CONDITION_SIGN_EQ,
				'main_locale',
				array ( $mainLocale )
			)
		);

		$conditionBlock->addCondition(
			Condition::getCondition(
				ConditionBuilder::CONDITION_SIGN_EQ,
				'is_active',
				array ( 1 )
			)
		);

		$result = $this->fetchData( $this->buildQuery( array (), null, $conditionBlock ) );

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

		$is_insert = in_array( $entityId, array ( 0, null ), true );

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
					array ( $entityId )
				)
			);
			$storeQuery = QueryBuilder::buildUpdateQuery(
				$this->getDbal()->completeTableName(
					ConfigurationProfileEntity::getTableName()
				),
				$fields,
				$conditionBlock,
				array ( 'limit' => 1 )
			);
		}

		// log store query before execution
		$this->getLogger()->debug( $storeQuery );

		$result = $this->getDbal()->query( $storeQuery );

		if ( false === $result ) {
			$message = vsprintf( 'Failed saving submission entity to database with following error message: %s',
				array ( $this->getDbal()->getLastErrorMessage() ) );

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
		$mainLocaleBlogId = $entity->getMainLocale()->getBlogId();
		if ( 0 < $mainLocaleBlogId ) {
			$entity->getMainLocale()->setLabel(
				$this->getSiteHelper()->getBlogLabelById(
					$this->getPluginProxy(),
					$mainLocaleBlogId
				)
			);
		}

		foreach ( $entity->getTargetLocales() as $targetLocale ) {
			$blogId = $targetLocale->getBlogId();
			if ( 0 < $blogId ) {
				$targetLocale->setLabel( $this->getSiteHelper()->getBlogLabelById( $this->getPluginProxy(), $blogId ) );
			}
		}

		return $entity;
	}
}