<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 06.02.2015
 * Time: 9:31
 */

namespace Smartling\Processors;


use Psr\Log\LoggerInterface;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Submissions\SubmissionEntity;

class EntityProcessor {

	function __construct ( EntityHelper $entityHelper, LoggerInterface $logger ) {
		$this->entityHelper = $entityHelper;
		$this->logger = $logger;
	}

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var EntityHelper
	 */
	private $entityHelper;

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}

	/**
	 * @return EntityHelper
	 */
	public function getEntityHelper () {
		return $this->entityHelper;
	}

	/**
	 * @return MapperAbstract
	 */
	private function getMapper($type) {
		switch($type) {
			case 'post':
				return new PostMapper();
		}
		return null;
	}

	/**
	 * @return array
	 */
	private function getEntityInArray($id, $type) {
		$entity = null;
		switch($type) {
			case 'post':
				$entity = new PostEntity($this->getLogger());
		}

		return $entity ? $entity->get($id)->toArray() : array();
	}

	public function toXml(SubmissionEntity $entity) {
		$fields = $this->getMapper($entity->getContentType())->getFields();

		$object = array_intersect_key($this->getEntityInArray($entity->getSourceGUID(), $entity->getContentType()), array_flip($fields));

		$xml = $this->initXml();
	    $data = $this->arrayToXml($object, $xml);
		$xml->appendChild($data);

		$folder = $this->getEntityHelper()->getPluginInfo()->getUpload();
		$name = $this->buildXmlFileName($entity);
		$path = $folder . DIRECTORY_SEPARATOR . $name;

		$xml->save($path);
		return $path;
	}

	public function fromXml(SubmissionEntity $entity) {
		$xml = new \DOMDocument();
		$xml->load($entity->getTargetLocale());
		
		$fields = $this->getMapper($entity->getContentType())->getFields();


		$xml = $this->initXml();
		$data = $this->arrayToXml($object, $xml);
		$xml->appendChild($data);

		$folder = $this->getEntityHelper()->getPluginInfo()->getUpload();
		$name = $this->buildXmlFileName($entity);
		$path = $folder . DIRECTORY_SEPARATOR . $name;

		$xml->save($path);
		return $path;
	}

	private function initXml() {
		$xml = new \DOMDocument('1.0', 'UTF-8');

		$xml->appendChild($xml->createComment(' smartling.translate_paths = data/string '));
		$xml->appendChild($xml->createComment(' smartling.string_format_paths = html : data/string '));

		return $xml;
	}

	private function arrayToXml(array $array, \DOMDocument $xml)
	{
		$data = $xml->createElement('data');
		foreach ($array as $key => $value) {
			$string = $xml->createElement('string');

			$attr = $xml->createAttribute('name');
			$attr->value = $key;
			$string->appendChild($attr);

			$text = $xml->createTextNode($value);
			$string->appendChild($text);

			$data->appendChild($string);
		}
		return $data;
	}

	private function buildXmlFileName(SubmissionEntity $entity) {
		return strtolower(trim(preg_replace('#\W+#', '_', $entity->getSourceTitle()), '_')) . '_' . $entity->getContentType() . '_' . $entity->getSourceGUID() . '.xml';
	}
}