<?php

declare(strict_types = 1);

namespace Lingoda\SentryBundle\Tests\Sentry\Serializer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lingoda\SentryBundle\Sentry\Serializer\ObjectJsonSerializer;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\AuthorMoved;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\AuthorRenamed;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity\Address;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\Entity\Author;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\Review;
use Lingoda\SentryBundle\Tests\Sentry\Fixture\ReviewPosted;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ObjectJsonSerializerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ObjectJsonSerializer $serializer;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $serializer = self::getContainer()->get('test.object_json_serializer');
        self::assertInstanceOf(ObjectJsonSerializer::class, $serializer);
        $this->serializer = $serializer;
    }

    public function testADetachedEntityIsSerializedAsItsClassAndIdWithoutLoadingItsBooks(): void
    {
        $authorId = $this->storeAuthorWithTwoBooks();
        $author = $this->entityManager->find(Author::class, $authorId);
        self::assertNotNull($author);
        $this->entityManager->clear();

        $serialized = ($this->serializer)(new AuthorRenamed($author, 'Jane'));

        self::assertSame(
            ['author' => ['class' => Author::class, 'id' => ['id' => $authorId]], 'newName' => 'Jane'],
            json_decode($serialized['json'], true, flags: \JSON_THROW_ON_ERROR),
        );
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
    }

    public function testAReferenceIsSerializedWithoutLoadingIt(): void
    {
        $authorId = $this->storeAuthorWithTwoBooks();
        $reference = $this->entityManager->getReference(Author::class, $authorId);
        self::assertNotNull($reference);

        $serialized = ($this->serializer)(new AuthorRenamed($reference, 'Jane'));

        self::assertSame(
            ['author' => ['class' => Author::class, 'id' => ['id' => $authorId]], 'newName' => 'Jane'],
            json_decode($serialized['json'], true, flags: \JSON_THROW_ON_ERROR),
        );
        self::assertTrue($this->entityManager->getUnitOfWork()->isUninitializedObject($reference));
    }

    public function testAnEmbeddableIsSerializedWithItsFields(): void
    {
        $authorId = $this->storeAuthorWithTwoBooks();
        $reference = $this->entityManager->getReference(Author::class, $authorId);
        self::assertNotNull($reference);

        $serialized = ($this->serializer)(new AuthorMoved($reference, new Address('Main Street 1', 'Berlin')));

        self::assertSame(
            [
                'author' => ['class' => Author::class, 'id' => ['id' => $authorId]],
                'address' => ['street' => 'Main Street 1', 'city' => 'Berlin'],
            ],
            json_decode($serialized['json'], true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testAPlainObjectIsSerializedWithItsFieldsAndItsEntityAsAReference(): void
    {
        $authorId = $this->storeAuthorWithTwoBooks();
        $reference = $this->entityManager->getReference(Author::class, $authorId);
        self::assertNotNull($reference);

        $serialized = ($this->serializer)(new ReviewPosted(new Review($reference, 'Great read')));

        self::assertSame(
            [
                'review' => [
                    'reviewer' => ['class' => Author::class, 'id' => ['id' => $authorId]],
                    'text' => 'Great read',
                ],
            ],
            json_decode($serialized['json'], true, flags: \JSON_THROW_ON_ERROR),
        );
        self::assertTrue($this->entityManager->getUnitOfWork()->isUninitializedObject($reference));
    }

    private function storeAuthorWithTwoBooks(): int
    {
        $author = new Author(7, 'John');
        $author->writeBook(1, 'First');
        $author->writeBook(2, 'Second');
        $this->entityManager->persist($author);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $author->getId();
    }
}
