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
	 * Wordpress date-time format
	 */
	const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * @var \DateTimeZone
	 */
	private $timezone;
	/**
	 * The guid field name for the entity. Most times it should be 'id'
	 *
	 * @var string
	 */
	private $guidField = '';

	/**
	 * @param \DateTime $dateTime
	 *
	 * @return string
	 */
	protected function dateToString(\DateTime $dateTime)
	{
		return $dateTime->format(self::DATE_TIME_FORMAT);
	}

	/**
	 * @param $dateTime
	 *
	 * @return \DateTime
	 */
	protected function stringToDate($dateTime)
	{
		return \DateTime::createFromFormat(self::DATE_TIME_FORMAT, $dateTime, $this->timezone);
	}

	/**
	 * @var array
	 */
	private $entityFields = array ('hash');

	/**
	 * @param array $entityFields
	 */
	public function setEntityFields ( array $entityFields ) {
		$this->entityFields = array_merge(array('hash'), $entityFields);
	}

	/**
	 * property-like magic getter for entity fields
	 *
	 * @param $fieldName
	 *
	 * @return
	 */
	public function __get ( $fieldName ) {
		if ( array_key_exists( $fieldName, $this->entityFields ) ) {
			return $this->entityFields[ $fieldName ];
		}
	}

	/**
	 * property-like magic setter for entity fields
	 *
	 * @param $fieldName
	 * @param $fieldValue
	 */
	public function __set ( $fieldName, $fieldValue ) {
		if ( array_key_exists( $fieldName, $this->entityFields ) ) {
			$this->entityFields[ $fieldName ] = $fieldValue;
		}
	}

	/**
	 * @param $method
	 *
	 * @return string
	 */
	protected function getFieldNameByMethodName ( $method ) {
		return lcfirst( substr( $method, 2 ) );
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
	 * @var LoggerInterface
	 */
	private $logger;

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

		$this->timezone = new \DateTimeZone('UTC');
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
	 * Stores entity to database
	 *
	 * @param EntityAbstract $entity
	 *
	 * @return mixed
	 */
	abstract public function set ( EntityAbstract $entity );

	/**
	 * Calculates the hash of entity
	 *
	 * @return string
	 */
	abstract public function calculateHash();

	protected function entityNotFound ( $type, $guid ) {
		$template = 'The \'%s\' entity with guid \'%s\' not found';

		$message = vsprintf( $template, array ( $type, $guid ) );
		$this->getLogger()->info( $message );

		throw new EntityNotFoundException( $message );
	}
}