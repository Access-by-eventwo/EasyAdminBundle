<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Search;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\Driver\StaticPHPDriver;
use EasyCorp\Bundle\EasyAdminBundle\Search\Paginator;
use EasyCorp\Bundle\EasyAdminBundle\Search\QueryBuilder;
use PHPUnit\Framework\TestCase;

class DoctrineOrmCompatibilityTest extends TestCase
{
    private EntityManager $manager;

    private QueryBuilder $builder;

    private array $entityConfig;

    protected function setUp(): void
    {
        $configuration = new Configuration();
        $driver = new StaticPHPDriver([]);
        $configuration->setMetadataDriverImpl($driver);
        $configuration->setProxyDir(sys_get_temp_dir());
        $configuration->setProxyNamespace('EasyAdminCompatibilityProxies');
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->manager = new EntityManager($connection, $configuration);
        $metadata = $this->manager->getClassMetadata(CompatibilityRecord::class);
        $schema = new SchemaTool($this->manager);
        $schema->createSchema([$metadata]);

        $parent = new CompatibilityRecord(1, 'Zulu');
        $second = new CompatibilityRecord(2, 'Alpha Needle', $parent);
        $third = new CompatibilityRecord(3, 'Beta Needle', $second);
        $this->manager->persist($parent);
        $this->manager->persist($second);
        $this->manager->persist($third);
        $this->manager->flush();
        $this->manager->clear();

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->manager);
        $this->builder = new QueryBuilder($registry);
        $this->entityConfig = [
            'class' => CompatibilityRecord::class,
            'search' => ['fields' => ['name' => ['dataType' => 'string'], 'id' => ['dataType' => 'integer']]],
        ];
    }

    protected function tearDown(): void
    {
        $connection = $this->manager->getConnection();
        $this->manager->close();
        $connection->close();
    }

    public function testTextSearchAndPagination(): void
    {
        $query = $this->builder->createSearchQueryBuilder($this->entityConfig, 'NEEDLE', 'name', 'DESC');
        $paginator = new Paginator();
        $page = $paginator->createOrmPaginator($query, 2, 1);
        $count = $page->getNbResults();
        $results = $page->getCurrentPageResults();
        $records = iterator_to_array($results);

        self::assertSame(2, $count);
        self::assertCount(1, $records);
        self::assertSame(2, $records[0]->id);
    }

    public function testNumericSearch(): void
    {
        $builder = $this->builder->createSearchQueryBuilder($this->entityConfig, '3');
        $query = $builder->getQuery();
        $records = $query->getResult();

        self::assertCount(1, $records);
        self::assertSame(3, $records[0]->id);
    }

    public function testAssociationSortingAndFilteredPagination(): void
    {
        $query = $this->builder->createListQueryBuilder($this->entityConfig, 'parent.name', 'ASC', 'entity.id > 1');
        $paginator = new Paginator();
        $page = $paginator->createOrmPaginator($query, 1, 1);
        $count = $page->getNbResults();
        $results = $page->getCurrentPageResults();
        $records = iterator_to_array($results);

        self::assertSame(2, $count);
        self::assertCount(1, $records);
        self::assertSame(3, $records[0]->id);
    }
}

class CompatibilityRecord
{
    public function __construct(public int $id, public string $name, public ?self $parent = null)
    {
    }

    public static function loadMetadata(ClassMetadata $metadata): void
    {
        $metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);
        $metadata->mapField(['fieldName' => 'name', 'type' => 'string']);
        $metadata->mapManyToOne(['fieldName' => 'parent', 'targetEntity' => self::class]);
    }
}
