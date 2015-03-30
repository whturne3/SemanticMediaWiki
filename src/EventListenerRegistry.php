<?php

namespace SMW;

use Onoi\EventDispatcher\EventListenerCollection;
use Onoi\EventDispatcher\EventDispatcherFactory;
use SMWExporter as Exporter;

/**
 * @license GNU GPL v2+
 * @since 2.2
 *
 * @author mwjames
 */
class EventListenerRegistry implements EventListenerCollection {

	/**
	 * @var EventListenerCollection
	 */
	private $eventListenerCollection = null;

	/**
	 * @since 2.2
	 *
	 * @param EventListenerCollection $eventListenerCollection
	 */
	public function __construct( EventListenerCollection $eventListenerCollection ) {
		$this->eventListenerCollection = $eventListenerCollection;
	}

	/**
	 * @see  EventListenerCollection::getCollection
	 *
	 * @since 2.2
	 */
	public function getCollection() {
		return $this->addListenersToCollection()->getCollection();
	}

	private function addListenersToCollection() {

		$this->eventListenerCollection->registerCallback(
			'property.spec.change', function( $dispatchContext ) {

				$title = $dispatchContext->get( 'subject' )->getTitle();

				$updateDispatcherJob = ApplicationFactory::getInstance()->newJobFactory()->newUpdateDispatcherJob( $title );
				$updateDispatcherJob->run();

				Exporter::getInstance()->resetCache();

				$dispatchContext->set( 'propagationstop', true );
		} );

		return $this->eventListenerCollection;
	}

}
