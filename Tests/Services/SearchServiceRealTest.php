<?php
declare(strict_types=1);

namespace Services;

require_once(__DIR__.'/../TestLogger.php');

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Paginator;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use StingerSoft\EntitySearchBundle\Model\Document;
use StingerSoft\EntitySearchBundle\Model\Query;
use StingerSoft\EntitySearchBundle\Model\Result\FacetSetAdapter;
use StingerSoft\EntitySearchBundle\Tests\AbstractORMTestCase;
use StingerSoft\EntitySearchBundle\Tests\Fixtures\ORM\Beer;
use StingerSoft\EntitySearchBundle\Tests\Fixtures\ORM\Car;
use StingerSoft\ElasticEntitySearchBundle\Services\SearchService;
use TestLogger;

class SearchServiceRealTest extends AbstractORMTestCase {

	protected $indexCount = 0;

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see PHPUnit_Framework_TestCase::setUp()
	 */
	public function setUp(): void {
		parent::setUp();
		$this->getMockSqliteEntityManager();
		$this->indexCount = 0;
		try {
			$this->getSearchService($this->em)->clearIndex();
		}catch (\Elasticsearch\Common\Exceptions\NoNodesAvailableException $exception) {
			$this->markTestSkipped('No elastic node available');
			return;
		}
		$this->assertEquals(0, $this->getSearchService($this->em)->getIndexSize());
	}

	public function testSaveDocument(): void {
		$service = $this->getSearchService();
		$this->indexBeer($service);
//		$service->clearIndex();
//		$this->assertEquals(0, $service->getIndexSize());
	}

	public function testSaveDocumentComposite(): void {
		$car = new Car('S500', 2016);
		$this->em->persist($car);
		$this->em->flush();

		$service = $this->getSearchService();
		$document = $service->createEmptyDocumentFromEntity($car);
//		$this->assertEquals(0, $service->getIndexSize());
		$service->saveDocument($document);
		$this->em->flush();

//		$this->assertEquals(1, $service->getIndexSize());

//		$service->clearIndex();
//		$this->assertEquals(0, $service->getIndexSize());
	}

	public function testRemoveDocument(): void {
		$service = $this->getSearchService();
		$result = $this->indexBeer($service);

		$service->removeDocument($result[1]);
		$this->em->flush();
//		$this->assertEquals(0, $service->getIndexSize());
	}

	public function testAutocompletion(): void {
		$service = $this->getSearchService();
		$result = $this->indexBeer($service);

		$suggests = $service->autocomplete('He');
		$this->assertCount(1, $suggests);
		$this->assertContains($result[0]->getTitle(), $suggests);
	}

	public function testSearch(): void {
		$service = $this->getSearchService();
		$this->indexBeer($service);
		$this->indexBeer($service, 'Haake Beck');
		$this->indexBeer($service, 'Haake Beck');
		$this->indexBeer($service, 'Haake Beck Kräusen');

		$query = new Query('Beck', array(), array(
			Document::FIELD_TITLE,
			Document::FIELD_TYPE
		));

		$result = $service->search($query);
		$this->assertCount(3, $result->getResults());

		/**
		 *
		 * @var FacetSetAdapter $facets
		 */
		$facets = $result->getFacets();
		$titleFacets = $facets->getFacet(Document::FIELD_TITLE);
		$this->assertNotNull($titleFacets);
		$this->assertCount(3, $titleFacets);
		$this->assertArrayHasKey('Haake Beck', $titleFacets);
		$this->assertArrayHasKey('Haake Beck Kräusen', $titleFacets);
		$this->assertEquals($titleFacets['Haake Beck']['count'], 2);
		$this->assertEquals($titleFacets['Haake Beck Kräusen']['count'], 1);
		$typeFacets = $facets->getFacet(Document::FIELD_TYPE);
		$this->assertCount(1, $typeFacets);

	}

	public function testSearchCorrection(): void {
		$service = $this->getSearchService();
		$this->indexBeer($service);
		$this->indexBeer($service, 'Haake Beck');
		$this->indexBeer($service, 'Haake Beck Beer');
		$this->indexBeer($service, 'Haake Beck Kräusen');

		$query = new Query('Hake Bcek', array(), array(
			Document::FIELD_TITLE,
			Document::FIELD_TYPE
		));

		$result = $service->search($query);
		$this->assertCount(0, $result->getResults());

		$this->assertGreaterThan(0, count($result->getCorrections()));
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \StingerSoft\EntitySearchBundle\Tests\AbstractTestCase::getUsedEntityFixtures()
	 */
	protected function getUsedEntityFixtures(): array {
		return array(
			Beer::class,
			Car::class
		);
	}

	/**
	 *
	 * @param EntityManagerInterface|null $em
	 * @return SearchService
	 */
	protected function getSearchService(EntityManagerInterface $em = null): SearchService {
		$dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();

		$service = new SearchService(new Paginator($dispatcher), new TestLogger(), array(
			'ipaddress' => '127.0.0.1',
			'indexname' => 'gettingstarted'
		));
		$service->setObjectManager($em ?? $this->em);
		$service->deleteIndex();
		$service->createIndex();
		return $service;
	}

	protected function indexBeer(SearchService $service, string $title = 'Hemelinger'): array {
		$beer = new Beer();
		$beer->setTitle($title);
		$this->em->persist($beer);
		$this->em->flush();

		$document = $service->createEmptyDocumentFromEntity($beer);
//		$this->assertEquals($this->indexCount, $service->getIndexSize());
		$beer->indexEntity($document);
		$service->saveDocument($document);
		$this->em->flush();
//		$this->assertEquals(++$this->indexCount, $service->getIndexSize());
		return array(
			$beer,
			$document
		);
	}
}

