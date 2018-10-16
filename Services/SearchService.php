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

use Elastica\Index;
use Elastica\Search;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Knp\Component\Pager\PaginatorInterface;
use StingerSoft\ElasticEntitySearchBundle\Model\KnpResultSet;
use StingerSoft\EntitySearchBundle\Model\Query;
use StingerSoft\EntitySearchBundle\Model\Result\FacetSetAdapter;
use StingerSoft\EntitySearchBundle\Model\ResultSet;
use StingerSoft\EntitySearchBundle\Model\ResultSetAdapter;
use StingerSoft\EntitySearchBundle\Services\AbstractSearchService;

class SearchService extends AbstractSearchService {

	/**
	 *
	 * @var Client
	 */
	protected $client;

	/**
	 * @var ClientConfiguration
	 */
	protected $configuration;

	/**
	 * @var PaginatorInterface
	 */
	protected $paginator;


	public function __construct(PaginatorInterface $paginator, array $configuration = array()) {
		$this->configuration = new ClientConfiguration($configuration);
		$this->paginator = $paginator;

	}

	public function createIndex() {
		$params = [
			'index' => $this->configuration->indexName,
			'body'  => [
				'settings' => [
					'number_of_shards'   => 5,
					'number_of_replicas' => 0
				],
				'doc'      => [
					'properties' => [
						'type'    => ['type' => 'keyword'],
						'content' => ['type' => 'text'],
						'title'   => ['type' => 'keyword'],
					]
				]
			]
		];

		$response = $this->getClient()->indices()->create($params);
	}

	public function deleteIndex() {
		$deleteParams = [
			'index' => $this->configuration->indexName,
		];
		if($this->getClient()->indices()->exists($deleteParams)) {
			$this->getClient()->indices()->delete($deleteParams);
		}
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::clearIndex()
	 */
	public function clearIndex() {
		$this->deleteIndex();
		$this->createIndex();
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::saveDocument()
	 */
	public function saveDocument(\StingerSoft\EntitySearchBundle\Model\Document $document): void {
		$params = [
			'index' => $this->configuration->indexName,
			'id'    => $document->getEntityClass() . '#' . $document->getEntityId(),
			'type'  => 'doc',
			'body'  => [
				'internalId' => $document->getEntityId(),
				'clazz'      => $document->getEntityClass(),
				'entityType' => $document->getEntityType(),
			]
		];
		foreach($document->getFields() as $key => $value) {
			$params['body'][$key] = $value;
		}
		try {
			$this->getClient()->index($params);
		} catch(\Exception $e) {
			print_r($e->getMessage());
		}
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::removeDocument()
	 */
	public function removeDocument(\StingerSoft\EntitySearchBundle\Model\Document $document): void {
		$params = [
			'index' => $this->configuration->indexName,
			'type'  => 'doc',
			'id'    => $document->getEntityClass() . '#' . $document->getEntityId(),
		];

		$response = $this->getClient()->delete($params);
		print_r($response);
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::autocomplete()
	 */
	public function autocomplete(string $search, int $maxResults = 10): array {
		return [];
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::search()
	 */
	public function search(Query $query): ?ResultSet {

		$queryParams = [
			'query' => [
				'query_string' => [
					'query' => $query->getSearchTerm(),
				],
			],
		];

		$elClient = new \Elastica\Client();
		$elClient->setConfigValue('host', $this->configuration->ipAddress);

		$elQuery = new \Elastica\Query($queryParams);
		$index = new Index($elClient, $this->configuration->indexName);
		$result = new KnpResultSet($this->paginator, $index, $elQuery, $queryParams);
		$result->setFacets( new FacetSetAdapter());

		return $result;
	}

	public function getIndexSize(): int {
		$response = (int)$this->getClient()->count([
			'index' => $this->configuration->indexName
		]);
		return $response;
	}

	protected function getClient(): Client {
		if($this->client === null) {
			$this->client = ClientBuilder::create()->setHosts(array(
				$this->configuration->ipAddress
			))->build();
		}
		return $this->client;
	}
}