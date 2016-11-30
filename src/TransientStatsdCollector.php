<?php

namespace SMW;

use Onoi\BlobStore\BlobStore;
use RuntimeException;

/**
 * Collect statistics in a provisional schema-free storage that depends on the
 * availability of the cache back-end.
 *
 * @license GNU GPL v2+
 * @since 2.5
 *
 * @author mwjames
 */
class TransientStatsdCollector {

	/**
	 * Update this version number when the serialization format
	 * changes.
	 */
	const VERSION = '0.2';

	/**
	 * Available operations
	 */
	const STATS_INIT = 'init';
	const STATS_INCR = 'incr';
	const STATS_SET = 'set';
	const STATS_MEDIAN = 'median';

	/**
	 * Namespace occupied by the BlobStore
	 */
	const CACHE_NAMESPACE = 'smw:stats:store';

	/**
	 * @var BlobStore
	 */
	private $blobStore;

	/**
	 * @var string|integer
	 */
	private $statsdId;

	/**
	 * @var boolean
	 */
	private $shouldRecord = true;

	/**
	 * @var array
	 */
	private $stats = array();

	/**
	 * Identifies an update fingerprint to compare invoked deferred updates
	 * against each other and filter those with the same print to avoid recording
	 * duplicate stats.
	 *
	 * @var string
	 */
	private $fingerprint = null;

	/**
	 * @var array
	 */
	private $operations = array();

	/**
	 * @since 2.5
	 *
	 * @param BlobStore $blobStore
	 * @param string $statsdId
	 */
	public function __construct( BlobStore $blobStore, $statsdId ) {
		$this->blobStore = $blobStore;
		$this->statsdId = $statsdId;
		$this->fingerprint = $statsdId . uniqid();
	}

	/**
	 * @since 2.5
	 *
	 * @param boolean $shouldRecord
	 */
	public function shouldRecord( $shouldRecord ) {
		$this->shouldRecord = (bool)$shouldRecord;
	}

	/**
	 * @since 2.5
	 *
	 * @return array
	 */
	public function getStats() {

		$container = $this->blobStore->read(
			md5( $this->statsdId . self::VERSION )
		);

		$data = $container->getData();
		$stats = array();

		foreach ( $data as $key => $value ) {
			if ( strpos( $key, '.' ) !== false ) {
				$stats = array_merge_recursive( $stats, $this->stringToArray( $key, $value ) );
			} else {
				$stats[$key] = $value;
			}
		}

		return $stats;
	}

	/**
	 * @since 2.5
	 *
	 * @param string|array $key
	 */
	public function incr( $key ) {

		if ( !isset( $this->stats[$key] ) ) {
			$this->stats[$key] = 0;
		}

		$this->stats[$key]++;
		$this->operations[$key] = self::STATS_INCR;
	}

	/**
	 * @since 2.5
	 *
	 * @param string|array $key
	 * @param string|integer $default
	 */
	public function init( $key, $default ) {
		$this->stats[$key] = $default;
		$this->operations[$key] = self::STATS_INIT;
	}

	/**
	 * @since 2.5
	 *
	 * @param string|array $key
	 * @param string|integer $value
	 */
	public function set( $key, $value ) {
		$this->stats[$key] = $value;
		$this->operations[$key] = self::STATS_SET;
	}

	/**
	 * @since 2.5
	 *
	 * @param string|array $key
	 * @param integer $value
	 */
	public function calcMedian( $key, $value ) {

		if ( !isset( $this->stats[$key] ) ) {
			$this->stats[$key] = $value;
		} else {
			$this->stats[$key] = ( $this->stats[$key] + $value ) / 2;
		}

		$this->operations[$key] = self::STATS_MEDIAN;
	}

	/**
	 * @since 2.5
	 */
	public function saveStats() {

		$container = $this->blobStore->read(
			md5( $this->statsdId . self::VERSION )
		);

		foreach ( $this->stats as $key => $value ) {

			$old = $container->has( $key ) ? $container->get( $key ) : 0;

			if ( $this->operations[$key] === self::STATS_INIT && $old != 0 ) {
				$value = $old;
			}

			if ( $this->operations[$key] === self::STATS_INCR ) {
				$value = $old + $value;
			}

			// Use as-is
			// $this->operations[$key] === self::STATS_SET

			if ( $this->operations[$key] === self::STATS_MEDIAN ) {
				$value = $old > 0 ? ( $old + $value ) / 2 : $value;
			}

			$container->set( $key, $value );
		}

		$this->blobStore->save(
			$container
		);

		$this->stats = array();
	}

	/**
	 * @since 2.5
	 */
	public function recordStats() {

		if ( $this->shouldRecord === false ) {
			return $this->stats = array();
		}

		// #2046
		// __destruct as event trigger has shown to be unreliable in a MediaWiki
		// environment therefore rely on the deferred update and any caller
		// that invokes the recordStats method

		$deferredCallableUpdate = ApplicationFactory::getInstance()->newDeferredCallableUpdate(
			function() { $this->saveStats(); }
		);

		$deferredCallableUpdate->setOrigin( __METHOD__ );

		$deferredCallableUpdate->setFingerprint(
			__METHOD__ . $this->fingerprint
		);

		$deferredCallableUpdate->pushToUpdateQueue();
	}

	// http://stackoverflow.com/questions/10123604/multstatsdIdimensional-array-from-string
	private function stringToArray( $path, $value ) {

		$separator = '.';
		$pos = strpos( $path, $separator );

		if ( $pos === false ) {
			return array( $path => $value );
		}

		$key = substr( $path, 0, $pos );
		$path = substr( $path, $pos + 1 );

		$result = array(
			$key => $this->stringToArray( $path, $value )
		);

		return $result;
	}

}