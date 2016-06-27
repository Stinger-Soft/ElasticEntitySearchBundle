<?php

/*
 * This file is part of the Stinger Elastic Entity Search package.
 *
 * (c) Oliver Kotte <oliver.kotte@stinger-soft.net>
 * (c) Florian Meyer <florian.meyer@stinger-soft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace StingerSoft\ElasticEntitySearchBundle\Services;

use StingerSoft\EntitySearchBundle\Services\AbstractSearchService;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use StingerSoft\EntitySearchBundle\Model\Query;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Client;

class SearchService extends AbstractSearchService {
	
	use ContainerAwareTrait;

	/**
	 *
	 * @var Client
	 */
	protected $client;

	protected $configuration;

	public function __construct(ClientConfiguration $configuration) {
		$this->configuration = $configuration;
		$this->client = ClientBuilder::create ()->setHosts(array($configuration->ipAddress))->build ();
	}

	public function createIndex() {
		$params = [ 
			'index' => $this->configuration->indexName,
			'body' => [ 
				'settings' => [ 
					'number_of_shards' => 2,
					'number_of_replicas' => 0 
				] 
			] 
		];
		
		$response = $this->client->indices ()->create ( $params );
		print_r ( $response );
	}

	public function deleteIndex() {
		$deleteParams = [ 
			'index' => $this->configuration->indexName 
		];
		$response = $this->client->indices ()->delete ( $deleteParams );
		print_r ( $response );
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::clearIndex()
	 */
	public function clearIndex() {
		$this->deleteIndex ();
		$this->createIndex ();
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::saveDocument()
	 */
	public function saveDocument(\StingerSoft\EntitySearchBundle\Model\Document $document) {
		$params = [ 
			'index' => $this->configuration->indexName,
			'type' => $document->getEntityClass (),
			'id' => $document->getEntityId (),
			'body' => [ ] 
		];
		foreach ( $document->getFields () as $key => $value ) {
			$params['body'][$key] = $value;
		}
		$response = $this->client->index ( $params );
		print_r ( $response );
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::removeDocument()
	 */
	public function removeDocument(\StingerSoft\EntitySearchBundle\Model\Document $document) {
		$params = [ 
			'index' => $this->configuration->indexName,
			'type' => $document->getEntityClass (),
			'id' => $document->getEntityId () 
		];
		
		$response = $this->client->delete ( $params );
		print_r ( $response );
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::autocomplete()
	 */
	public function autocomplete($search, $maxResults = 10) {
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::search()
	 */
	public function search(Query $query) {
		
	}

	public function getIndexSize() {
		$response = $this->client->count([
				'index' => $this->configuration->indexName,
		]);
		return $response;
	}

}