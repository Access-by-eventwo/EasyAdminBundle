<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\MetadataConfigPass;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Guesser\DoctrineOrmFilterTypeGuesser;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Guesser\MissingDoctrineOrmTypeGuesser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class DoctrineOrmCompatibilityTest extends TestCase
{
    private ClassMetadata $metadata;

    private ManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->metadata = new ClassMetadata('CompatibilityArticle');
        $this->metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);
        $this->metadata->mapField(['fieldName' => 'payload', 'type' => 'json', 'nullable' => true]);
        $this->metadata->mapManyToOne([
            'fieldName' => 'author', 'targetEntity' => 'CompatibilityAuthor',
            'joinColumns' => [['name' => 'author_id', 'referencedColumnName' => 'id', 'nullable' => false]],
        ]);
        $this->metadata->mapManyToOne([
            'fieldName' => 'editor', 'targetEntity' => 'CompatibilityAuthor',
            'joinColumns' => [['name' => 'editor_id', 'referencedColumnName' => 'id', 'nullable' => true]],
        ]);
        $this->metadata->mapOneToMany([
            'fieldName' => 'comments', 'targetEntity' => 'CompatibilityComment', 'mappedBy' => 'article',
        ]);
        $this->metadata->mapManyToMany([
            'fieldName' => 'tags', 'targetEntity' => 'CompatibilityTag',
            'joinTable' => [
                'name' => 'article_tag',
                'joinColumns' => [['name' => 'article_id', 'referencedColumnName' => 'id']],
                'inverseJoinColumns' => [['name' => 'tag_id', 'referencedColumnName' => 'id']],
            ],
        ]);

        $factory = $this->createStub(ClassMetadataFactory::class);
        $factory->method('getMetadataFor')->willReturn($this->metadata);
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getMetadataFactory')->willReturn($factory);
        $manager->method('getClassMetadata')->willReturn($this->metadata);
        $this->registry = $this->createStub(ManagerRegistry::class);
        $this->registry->method('getManagerForClass')->willReturn($manager);
        $this->registry->method('getManagers')->willReturn(['default' => $manager]);
    }

    public function testMetadataRemainsArrayBased(): void
    {
        $pass = new MetadataConfigPass($this->registry);
        $config = $pass->process(['entities' => ['Article' => ['class' => 'CompatibilityArticle']]]);
        $entity = $config['entities']['Article'];
        $properties = $entity['properties'];

        self::assertSame('id', $entity['primary_key_field_name']);
        self::assertIsArray($properties['payload']);
        self::assertSame('json', $properties['payload']['type']);
        self::assertTrue($properties['payload']['nullable']);
        self::assertSame('association', $properties['author']['type']);
        self::assertSame(2, $properties['author']['associationType']);
        self::assertTrue($properties['author']['isOwningSide']);
        self::assertArrayNotHasKey('sortable', $properties['author']);
        self::assertIsArray($properties['author']['joinColumns'][0]);
        self::assertFalse($properties['author']['joinColumns'][0]['nullable']);
        self::assertSame(4, $properties['comments']['associationType']);
        self::assertFalse($properties['comments']['isOwningSide']);
        self::assertFalse($properties['comments']['sortable']);
        self::assertSame(8, $properties['tags']['associationType']);
        self::assertFalse($properties['tags']['sortable']);
        self::assertIsArray($properties['tags']['joinTable']);
        self::assertIsArray($properties['tags']['joinTable']['joinColumns'][0]);
        self::assertSame('tag_id', $properties['tags']['joinTable']['inverseJoinColumns'][0]['name']);
    }

    public function testAssociationFiltersPreserveNullabilityAndMultiplicity(): void
    {
        $guesser = new DoctrineOrmFilterTypeGuesser($this->registry);
        $required = $guesser->guessType('CompatibilityArticle', 'author');
        $optional = $guesser->guessType('CompatibilityArticle', 'editor');
        $comments = $guesser->guessType('CompatibilityArticle', 'comments');
        $tags = $guesser->guessType('CompatibilityArticle', 'tags');
        $requiredOptions = $required->getOptions();
        $optionalOptions = $optional->getOptions();
        $commentOptions = $comments->getOptions();
        $tagOptions = $tags->getOptions();
        $type = $required->getType();

        self::assertSame(EntityFilterType::class, $type);
        self::assertSame('CompatibilityAuthor', $requiredOptions['value_type_options']['class']);
        self::assertFalse($requiredOptions['value_type_options']['multiple']);
        self::assertArrayNotHasKey('placeholder', $requiredOptions['value_type_options']);
        self::assertSame('label.form.empty_value', $optionalOptions['value_type_options']['placeholder']);
        self::assertTrue($commentOptions['value_type_options']['multiple']);
        self::assertTrue($tagOptions['value_type_options']['multiple']);
    }

    public function testJsonFormTypeIsPreserved(): void
    {
        $guesser = new MissingDoctrineOrmTypeGuesser($this->registry);
        $guess = $guesser->guessType('CompatibilityArticle', 'payload');
        $type = $guess->getType();

        self::assertSame(TextareaType::class, $type);
    }
}
