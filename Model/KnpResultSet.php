<?php
declare(strict_types=1);

/*
 * This file is part of the Stinger Entity Search package.
 *
 * (c) Oliver Kotte <oliver.kotte@stinger-soft.net>
 * (c) Florian Meyer <florian.meyer@stinger-soft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace StingerSoft\ElasticEntitySearchBundle\Model;

use Elastica\ResultSet;
use Elastica\SearchableInterface;
use Elasticsearch\Client;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Knp\Component\Pager\PaginatorInterface;
use StingerSoft\EntitySearchBundle\Model\Document;
use StingerSoft\EntitySearchBundle\Model\PaginatableResultSet;
use StingerSoft\EntitySearchBundle\Model\Result\Correction;
use StingerSoft\EntitySearchBundle\Model\ResultSetAdapter;

class KnpResultSet extends ResultSetAdapter implements PaginatableResultSet {

	/**
	 *
	 * @var \Elastica\Query
	 */
	protected $query = null;

	/**
	 *
	 * @var array
	 */
	protected $queryParams = null;

	/**
	 *
	 * @var SearchableInterface
	 */
	protected $client = null;

	/**
	 *
	 * @var SlidingPagination|Document[]
	 */
	protected $lastResult = null;

	/**
	 *
	 * @var ResultSet
	 */
	protected $lastSearchResult = null;

	/**
	 * @var PaginatorInterface
	 */
	protected $paginator;

	/**
	 * KnpResultSet constructor.
	 * @param PaginatorInterface $paginator
	 * @param Client $client
	 * @param \Elastica\Query $query
	 * @param string $term
	 */
	public function __construct(PaginatorInterface $paginator, SearchableInterface $client, \Elastica\Query $query, array $queryParams) {
		$this->query = $query;
		$this->queryParams = $queryParams;
		$this->client = $client;
		$this->paginator = $paginator;
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Model\PaginatableResultSet::paginate()
	 */
	public function paginate(int $page = 1, int $limit = 10, array $options = array()): \Knp\Component\Pager\Pagination\PaginationInterface {
		$this->lastResult = $this->paginator->paginate(array(
			$this->client,
			$this->query
		), $page, $limit, $options);
		$this->lastSearchResult = $this->lastResult->getCustomParameter('resultSet');

		return $this->lastResult;
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Model\ResultSetAdapter::getResults()
	 */
	public function getResults(int $offset = 0, ?int $limit = null): array {
		$oldStart = $this->query->getParam('from');
		$oldOffset = $this->query->getParam('size');

		$this->query->setFrom($offset);
		if($limit) {
			$this->query->setSize($limit);
		}
		$this->lastSearchResult = $elasticResults = $this->client->search($this->query);

		$this->query->setFrom($oldStart);
		$this->query->setSize($oldOffset);

		$documents = array();
		foreach($elasticResults->getResults() as $elasticResult) {
			$documents[] = \StingerSoft\ElasticEntitySearchBundle\Model\Document::createFromElasticResult($elasticResult);
		}

		return $documents;
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Model\ResultSet::getExcerpt()
	 */
	public function getExcerpt(Document $document): ?string {
		return null;
	}

	public function getCorrections(): array {
		$result = array();

		return $result;
	}
}