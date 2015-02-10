<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Exception\EntityNotFoundException;

/**
 * Class EntityAbstract
 *
 * @package inc\Smartling\DbAl\WordpressContentEntities
 */
abstract class EntityAbstract {

	/**
	 * The guid field name for the entity. Most times it should be 'id'
	 *
	 * @var string
	 */
	private $guidField = '';

	/**
	 * List of fields that affect the hash of the entity
	 *
	 * @var array
	 */
	protected $hashAffectingFields;

	/**
	 * @var array
	 */
	private $entityFields = array ( 'hash' );

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	private $entityArrayState = array ();

	private function initEntityArrayState () {
		if ( empty( $this->entityArrayState ) ) {
			foreach ( $this->entityFields as $field ) {
				$this->entityArrayState[ $field ] = null;
			}
		}
	}

	/**
	 * @param array $entityFields
	 */
	public function setEntityFields ( array $entityFields ) {
		$this->entityFields = array_merge( array ( 'hash' ), $entityFields );

		$this->initEntityArrayState();
	}

	/**
	 * Transforms entity instance into array
	 *
	 * @return array
	 */
	public function toArray () {
		return $this->entityArrayState;
	}

	/**
	 * property-like magic getter for entity fields
	 *
	 * @param $fieldName
	 *
	 * @return
	 */
	public function __get ( $fieldName ) {
		if ( array_key_exists( $fieldName, $this->entityArrayState ) ) {
			return $this->entityArrayState[ $fieldName ];
		}
	}

	/**
	 * property-like magic setter for entity fields
	 *
	 * @param $fieldName
	 * @param $fieldValue
	 */
	public function __set ( $fieldName, $fieldValue ) {
		if ( array_key_exists( $fieldName, $this->entityArrayState ) ) {
			$this->entityArrayState[ $fieldName ] = $fieldValue;
		}
	}

	/**
	 * @param $method
	 *
	 * @return string
	 */
	protected function getFieldNameByMethodName ( $method ) {
		return strtolower( preg_replace( '/([A-Z])/', '_$1', lcfirst( substr( $method, 3 ) ) ) );
	}

	/**
	 * @param string $method
	 * @param array  $params
	 *
	 * @return mixed
	 */
	public function __call ( $method, array $params ) {
		switch ( substr( $method, 0, 3 ) ) {
			case 'set' : {
				$field        = $this->getFieldNameByMethodName( $method );
				$this->$field = reset( $params ); // get the very first arg
				break;
			}
			case 'get' : {
				$field = $this->getFieldNameByMethodName( $method );

				return $this->$field; // get the very first arg
				break;
			}
			default : {
				$template = 'Method \'%s\' does not exists in class \'%s\'';
				$message  = vsprintf( $template, array ( $method, get_class( $this ) ) );
				throw new \BadMethodCallException( $message );
				break;
			}
		}
	}

	/**
	 * @return mixed
	 */
	abstract public function getTitle ();

	/**
	 * @return LoggerInterface
	 */
	protected function getLogger () {
		return $this->logger;
	}

	/**
	 * Constructor
	 *
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return string
	 */
	public function getGuidField () {
		return $this->guidField;
	}

	/**
	 * @param string $guidField
	 */
	public function setGuidField ( $guidField ) {
		$this->guidField = $guidField;
	}

	/**
	 * Loads the entity from database
	 *
	 * @param $guid
	 *
	 * @return mixed
	 */
	abstract public function get ( $guid );

	/**
	 * Loads ALL entities from database
	 *
	 * @return mixed
	 */
	abstract public function getAll ();

	/**
	 * Stores entity to database
	 *
	 * @param EntityAbstract $entity
	 *
	 * @return mixed
	 */
	abstract public function set ( EntityAbstract $entity = null );

	/**
	 * Calculates the hash of entity
	 *
	 * @return string
	 */
	public function calculateHash () {
		$sourceSting = '';

		foreach ( $this->hashAffectingFields as $fieldName ) {
			$sourceSting .= $this->$fieldName;
		}

		return md5( $sourceSting );
	}

	/**
	 * Converts object into EntityAbstract child
	 *
	 * @param object         $arr
	 * @param EntityAbstract $entity
	 *
	 * @return EntityAbstract
	 */
	protected function resultToEntity ( array $arr, $entity = null ) {
		if ( null === $entity ) {
			$className = get_class( $this );

			$entity = new $className( $this->getLogger() );
		}
		foreach ( $this->fields as $fieldName ) {
			if ( array_key_exists( $fieldName, $arr ) ) {
				$entity->$fieldName = $arr[$fieldName];
			}
		}
		$entity->hash = $this->calculateHash();

		return $entity;
	}

	protected function entityNotFound ( $type, $guid ) {
		$template = 'The \'%s\' entity with guid \'%s\' not found';

		$message = vsprintf( $template, array ( $type, $guid ) );
		$this->getLogger()->info( $message );

		throw new EntityNotFoundException( $message );
	}

	/**
	 * @return array
	 */
	protected abstract function getNonClonableFields ();

	/**
	 * @return EntityAbstract
	 */
	public function __clone () {
		$nonCloneFields = $this->getNonClonableFields();

		$myFields = $this->toArray();

		foreach ( $nonCloneFields as $field ) {
			unset ( $myFields[ $field ] );
		}

		$this->resultToEntity($myFields, $this);
	}
}