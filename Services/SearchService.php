<?php
declare(strict_types=1);

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

use Elastica\Aggregation\Terms;
use Elastica\Query\Term;
use Elastica\Suggest;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use StingerSoft\ElasticEntitySearchBundle\Model\KnpResultSet;
use StingerSoft\EntitySearchBundle\Model\Document;
use StingerSoft\EntitySearchBundle\Model\Query;
use StingerSoft\EntitySearchBundle\Model\Result\FacetSetAdapter;
use StingerSoft\EntitySearchBundle\Model\ResultSet;
use StingerSoft\EntitySearchBundle\Services\AbstractSearchService;

class SearchService extends AbstractSearchService {

	/**
	 *
	 * @var Client
	 */
	protected $client;

	/**
	 * @var \Elastica\Client
	 */
	protected $elasticaClient;

	/**
	 * @var ClientConfiguration
	 */
	protected $configuration;

	/**
	 * @var PaginatorInterface
	 */
	protected $paginator;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(PaginatorInterface $paginator, LoggerInterface $logger, array $configuration = array()) {
		$this->configuration = new ClientConfiguration($configuration);
		$this->paginator = $paginator;
		$this->logger = $logger;
	}

	/**
	 *
	 */
	public function createIndex(): void {
		$params = [
			'index' => $this->configuration->indexName,
			'body'  => [
				'settings' => [
					'number_of_shards'   => 5,
					'number_of_replicas' => 0,
					'analysis'           => [
						'analyzer' => [
							'trigram' => [
								'type'      => 'custom',
								'tokenizer' => 'standard',
								'filter'    => ['standard', 'shingle']
							],
							'reverse' => [
								'type'      => 'custom',
								'tokenizer' => 'standard',
								'filter'    => ['standard', 'reverse']
							]
						],
						'filter'   => [
							'shingle' => [
								'type'             => 'shingle',
								'min_shingle_size' => 2,
								'max_shingle_size' => 3
							]
						]
					]
				],
				'mappings' => [
					'doc' => [
						'_source'           => [
							'enabled' => true
						],
						'dynamic_templates' => [
							[
								'newtextfields' => [
									'match_mapping_type' => 'string',
									'mapping'            => [
										'type' => 'keyword'
									]
								]
							]
						],
						'properties'        => [
							'type'                             => ['type' => 'keyword'],
							Document::FIELD_CONTENT            => [
								'type'     => 'text',
								'analyzer' => 'standard'
							],
							Document::FIELD_TITLE              => [
								'type'   => 'text',
								'fields' => [
									'trigram' => [
										'type'     => 'text',
										'analyzer' => 'trigram'
									],
									'reverse' => [
										'type'     => 'text',
										'analyzer' => 'reverse'
									],
								]
								//								'copy_to'  => 'suggest',
							],
							Document::FIELD_TITLE . '_suggest' => [
								'type' => 'completion'
							],
							'clazz'                            => ['type' => 'keyword'],
							'entityType'                       => ['type' => 'keyword'],
							'internalId'                       => ['type' => 'keyword'],
							Document::FIELD_AUTHOR             => ['type' => 'keyword'],
							Document::FIELD_EDITORS            => ['type' => 'keyword'],
							Document::FIELD_LAST_MODIFIED      => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
							Document::FIELD_DELETED_AT         => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
							'file_content'                     => ['type' => 'text', 'index' => false],
							'attachment.content'               => [
								'type'     => 'text',
								'analyzer' => 'standard'
							],
							'attachment.title'                 => [
								'type'     => 'text',
								'copy_to'  => 'title',
								'analyzer' => 'standard'
							],
							'attachment.content_type'          => [
								'type' => 'keyword'
							],
							//							"suggest"                     => [
							//								"type" => "completion"
							//							],
						]
					]
				]
			]
		];
		$this->logger->debug('Creating elastic index', ['params' => $params]);
		$this->getClient()->indices()->create($params);

		$params = [
			'id'   => 'attachment',
			'body' => [
				'description' => 'Extract attachment information',
				'processors'  => [
					[
						'attachment' => [
							'field'         => 'file_content',
							'indexed_chars' => -1
						]
					]
				]
			]
		];
		$this->logger->debug('Activating Inges Extract Pipeline', ['params' => $params]);
		$this->getClient()->ingest()->putPipeline($params);
	}

	/**
	 *
	 */
	public function deleteIndex(): void {
		$deleteParams = [
			'index' => $this->configuration->indexName,
		];
		$this->logger->debug('Deleting index ' . $this->configuration->indexName);
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
	public function clearIndex(): void {
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
		if($document->getFile() !== null) {
			$params['pipeline'] = 'attachment';
			$params['body']['file_content'] = base64_encode(file_get_contents($document->getFile()));
		}

		foreach($document->getFields() as $key => $value) {
			if(\is_array($value)) {
				$params['body'][$key] = [];
				foreach($value as $item) {
					$stringValue = $this->stringifyValue($item);
					if(!empty($stringValue)) {
						$params['body'][$key][] = $this->stringifyValue($stringValue);
					}
				}
			} else {
				$stringValue = $this->stringifyValue($value);
				if(!empty($stringValue)) {
					$params['body'][$key] = $this->stringifyValue($stringValue);
				}
			}
		}

		$titles = $document->getFieldValue(Document::FIELD_TITLE);
		if(!empty($titles)) {
			$params['body'][Document::FIELD_TITLE . '_suggest'] = $titles;
		}

		try {
			$this->getClient()->index($params);
		} catch(\Exception $e) {
			$this->logger->warning('Cannot index entity', ['exception' => $e, 'params' => $params]);
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

		$this->getClient()->delete($params);
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::autocomplete()
	 */
	public function autocomplete(string $search, int $maxResults = 10): array {

		$suggestParams = [
			'suggest' => [
				'suggest' => [
					'my_suggest' => [
						'text'       => $search,
						'completion' => [
							'field' => Document::FIELD_TITLE . '_suggest'
						]
					]
				]
			]
		];

		$elClient = $this->getElasticaClient();
		$index = $elClient->getIndex($this->configuration->indexName);

		$elQuery = new \Elastica\Query($suggestParams);
		$result = $index->search($elQuery);

		$suggestions = [];
		if($result->countSuggests()) {
			$suggests = $result->getSuggests()['my_suggest'];
			foreach($suggests as $suggestion) {
				$options = $suggestion['options'];
				if(\count($options) > 0) {
					foreach($options as $option) {
						$suggestions[] = $option['text'];
					}
				}
			}
		}
		return $suggestions;
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::search()
	 */
	public function search(Query $query): ?ResultSet {

		$queryParams = [
			'query'     => [
				'query_string' => [
					'query'     => $query->getSearchTerm(),
					'fields'    => [
						Document::FIELD_TITLE,
						Document::FIELD_CONTENT,
						'attachment.' . Document::FIELD_CONTENT
					],
					'fuzziness' => 'AUTO'
				]
			],
			'highlight' => [
				'fields' => [
					Document::FIELD_CONTENT                 => [
						'type'         => 'plain',
						'phrase_limit' => 3,
					],
					'attachment.' . Document::FIELD_CONTENT => [
						'type'         => 'plain',
						'phrase_limit' => 3,
					]
				]
			]
		];

		$elClient = $this->getElasticaClient();

		$elQuery = new \Elastica\Query($queryParams);

		if($query->getUsedFacets() !== null) {
			foreach($query->getUsedFacets() as $facetKey) {
				$facetKey = $this->escapeFacetKey($facetKey);
				$termAgg = new Terms($facetKey);
				$termAgg->setField($facetKey);
				$elQuery->addAggregation($termAgg);
			}
		}

		$querySuggest = new Suggest();
		$suggest = new Suggest\Phrase(Document::FIELD_TITLE, Document::FIELD_TITLE);
		$suggest->setText($query->getSearchTerm());
		$querySuggest->addSuggestion($suggest);

		$suggest = new Suggest\Phrase(Document::FIELD_CONTENT, Document::FIELD_CONTENT);
		$suggest->setText($query->getSearchTerm());
		$querySuggest->addSuggestion($suggest);

		$elQuery->setSuggest($querySuggest);

		$facetsQuery = new \Elastica\Query\BoolQuery();

		foreach($query->getFacets() as $facetKey => $values) {
			if(count($values) <= 0) {
				continue;
			}
			$facetKey = $this->escapeFacetKey($facetKey);

			foreach($values as $value) {
				$singleFacetQuery = new Term();
				$singleFacetQuery->setTerm($facetKey, $value);
				$facetsQuery->addShould($singleFacetQuery);
			}
		}

		$elQuery->setPostFilter($facetsQuery);

		$index = $elClient->getIndex($this->configuration->indexName);
		$result = new KnpResultSet($this->paginator, $index, $elQuery, $queryParams);

		$facetSet = new FacetSetAdapter();
		$facetResult = $index->search($elQuery);

		$responseData = $facetResult->getResponse()->getData();
		$aggregations = $responseData['aggregations'] ?? [];

		foreach($aggregations as $name => $data) {
			$facetKey = $this->unescapeFacetKey($name);
			foreach($data['buckets'] as $bucket) {
				$facetSet->addFacetValue($facetKey, (string)$bucket['key'], $bucket['key'], (int)$bucket['doc_count']);
			}
		}

		$result->setFacets($facetSet);

		return $result;
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Services\SearchService::getIndexSize()
	 */
	public function getIndexSize(): int {
		$response = (int)$this->getClient()->count([
			'index' => $this->configuration->indexName
		]);
		return $response;
	}

	/**
	 * @param $value
	 * @return string
	 */
	protected function stringifyValue($value): string {
		if(\is_scalar($value)) {
			return (string)$value;
		}
		if($value instanceof \DateTime) {
			return $value->format('Y-m-d H:i:s');
		}
		if(\is_object($value)) {
			if(\method_exists($value, '__toString')) {
				return $value->__toString();
			}

			try {
				$refl = new \ReflectionClass($value);
				if($refl->implementsInterface('\Symfony\Component\Security\Core\User\UserInterface')) {
					return $value->getUsername();
				}
			} catch(\ReflectionException $e) {
				$this->logger->warning('Cannot create ReflectionClass for object of type ' . \get_class($value));
			}

			return \get_class($value);
		}
		return '';
	}

	/**
	 * @return Client
	 */
	protected function getClient(): Client {
		if($this->client === null) {
			$this->client = ClientBuilder::create()->setHosts(array(
				$this->configuration->ipAddress
			))->setLogger($this->logger)->build();
		}
		return $this->client;
	}

	/**
	 * @return \Elastica\Client
	 */
	protected function getElasticaClient(): \Elastica\Client {
		if($this->elasticaClient === null) {
			$this->elasticaClient = new \Elastica\Client([], null, $this->logger);
			$this->elasticaClient->setConfigValue('host', $this->configuration->ipAddress);
		}
		return $this->elasticaClient;
	}

	/**
	 * @param string $facetKey
	 * @return string
	 */
	protected function escapeFacetKey(string $facetKey): string {
		$facetKey = $facetKey === Document::FIELD_TYPE ? 'entityType' : $facetKey;
//		$facetKey = $facetKey === Document::FIELD_CONTENT_TYPE ? 'attr_Content-Type' : $facetKey;
		return $facetKey;
	}

	/**
	 * @param string $facetKey
	 * @return string
	 */
	protected function unescapeFacetKey(string $facetKey): string {
		$facetKey = $facetKey === 'entityType' ? Document::FIELD_TYPE : $facetKey;
//		$facetKey = $facetKey == 'attr_Content-Type' ? Document::FIELD_CONTENT_TYPE : $facetKey;
		return $facetKey;
	}
}
