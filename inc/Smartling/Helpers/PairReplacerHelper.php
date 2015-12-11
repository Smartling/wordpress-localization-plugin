<?php

namespace Smartling\Helpers;

/**
 * Class PairReplacerHelper
 *
 * @package Smartling\Helpers
 */
class PairReplacerHelper {

	private $_pairCollection = [ ];

	/**
	 * @return array
	 */
	private function getPairCollection () {
		return $this->_pairCollection;
	}

	/**
	 * @param array $pair
	 */
	private function addToCollection ( array $pair ) {
		$this->_pairCollection[] = $pair;
	}

	/**
	 * @param string $search
	 * @param string $replace
	 */
	public function addReplacementPair ( $search, $replace ) {
		$this->addToCollection( [
			'from' => $search,
			'to'   => $replace,
		] );
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	public function processString ( $string, $pairWrapper = [ '\'', '"' ] ) {
		$collection = $this->getPairCollection();
		foreach ( $collection as $pair ) {
			$pattern = '%s%s%s';
			foreach ( $pairWrapper as $wrapper ) {
				$search  = vsprintf( $pattern, [ $wrapper, $pair['from'], $wrapper ] );
				$replace = vsprintf( $pattern, [ $wrapper, $pair['to'], $wrapper ] );

				$string = str_replace( $search, $replace, $string );
			}
		}

		return $string;
	}


}