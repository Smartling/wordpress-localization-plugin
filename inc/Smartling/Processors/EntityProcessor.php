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
	 * @return PostEntity
	 */
	private function getEntity($type) {
		$entity = null;
		switch($type) {
			case 'post':
				$entity = new PostEntity($this->getLogger());
		}

		return $entity;
	}

	public function toXml(SubmissionEntity $entity) {
		$fields = $this->getMapper($entity->getContentType())->getFields();

		$entityArray = $this->getEntity($entity->getContentType())->get($entity->getSourceGUID());
		$object = array_intersect_key($entityArray, array_flip($fields));

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
		$xml->load($entity->getTargetFileUri());
		$xpath = new \DOMXPath($xml);

		$fields = $this->getMapper($entity->getContentType())->getFields();

		$wpEntity = $this->getEntity($entity->getContentType());
		if($entity->getTargetGUID() == null) {
			$helper = $this->getEntityHelper();
			$targetId = $helper->createTarget($entity->getSourceGUID(), $entity->getTargetBlog(), $entity->getContentType());
			$entity->setTargetGUID($targetId);
			$this->getEntityHelper()->getConnector()->linkObjects($entity);
		}

		$this->getEntityHelper()->getSiteHelper()->switchBlogId($entity->getTargetBlog());
		$data = $wpEntity->get($entity->getTargetGUID());
		foreach($fields as $field) {
			$item = $xpath->query('//string[@name="' . $field .'"]')->item(0);
			if($item) {
				$data[$field] = (string)$item->nodeValue;
			}
		}
		$wpEntity->update($data);
		$this->getEntityHelper()->getSiteHelper()->restoreBlogId();
		return true;
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