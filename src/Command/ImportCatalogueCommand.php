<?php

declare(strict_types=1);

namespace App\Command;

use App\Catalogue\CatalogueData;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Collection\Manager\CollectionManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Message\CreateAttributeGroupMessage;
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Writes the catalogue through the message bus the administration uses too, so every product passes the
 * same mappers, route generator and workflow as one saved in the admin.
 */
#[AsCommand(name: 'app:import-catalogue', description: 'Imports the merch shop: attributes, families, products and pictures')]
final class ImportCatalogueCommand extends Command
{
    use HandleTrait;

    private const LOCALE = 'en';

    /** @var array<string, int> file => media id, so a picture that variants share is uploaded once */
    private array $media = [];

    private ?int $collectionId = null;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly CatalogueData $data,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'sulu_media.collection_manager')]
        private readonly CollectionManagerInterface $collectionManager,
        #[Autowire(service: 'sulu_media.media_manager')]
        private readonly MediaManagerInterface $mediaManager,
        #[Autowire('%kernel.project_dir%/data/images')]
        private readonly string $imageDirectory,
        private readonly PageRepositoryInterface $pageRepository,
    ) {
        parent::__construct();
        $this->messageBus = $messageBus;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null !== $this->entityManager->getRepository(Attribute::class)->findOneBy([])) {
            $io->warning('The catalogue is already imported.');

            return Command::SUCCESS;
        }

        $attributeIds = $this->createAttributes();
        $familyIds = $this->createFamilies($attributeIds);
        $productIds = $this->createProducts($familyIds, $attributeIds);
        $this->associateAndPublish($productIds);
        $this->updateHomepage($productIds);

        $io->success(\sprintf('Imported %d attributes, %d families and %d products.', \count($attributeIds), \count($familyIds), \count($this->data->products())));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> attribute key => uuid
     */
    private function createAttributes(): array
    {
        $groupIds = [];
        $attributeIds = [];
        foreach ($this->data->attributes() as $key => $attribute) {
            $options = [];
            foreach ($attribute['options'] ?? [] as $optionKey => $name) {
                $options[] = ['key' => $optionKey, 'name' => $name];
            }

            $groupIds[$attribute['group']] ??= $this->createGroup($attribute['group']);
            $created = $this->dispatch(new CreateAttributeMessage([
                'locale' => self::LOCALE,
                'key' => $key,
                'type' => $attribute['type'],
                'name' => $attribute['name'],
                'group' => $groupIds[$attribute['group']],
                'options' => $options ?: null,
                'config' => $attribute['config'] ?? [],
                'localized' => $attribute['localized'] ?? false,
                'filterable' => $attribute['filterable'] ?? false,
            ]));
            \assert($created instanceof Attribute);
            $attributeIds[$key] = (string) $created->getUuid();
        }

        return $attributeIds;
    }

    private function createGroup(string $name): string
    {
        $created = $this->dispatch(new CreateAttributeGroupMessage(['locale' => self::LOCALE, 'name' => $name]));
        \assert($created instanceof AttributeGroupInterface);

        return (string) $created->getUuid();
    }

    /**
     * @param array<string, string> $attributeIds
     *
     * @return array<string, string> family key => uuid
     */
    private function createFamilies(array $attributeIds): array
    {
        $familyIds = [];
        foreach ($this->data->families() as $key => $family) {
            $attributes = [];
            foreach ($family['attributes'] as $attributeKey => $flags) {
                $attributes[] = [
                    'id' => $attributeIds[$attributeKey],
                    'required' => $flags['required'] ?? false,
                    'variantSpecific' => $flags['variant'] ?? false,
                ];
            }

            $created = $this->dispatch(new CreateProductFamilyMessage([
                'locale' => self::LOCALE,
                'name' => $family['name'],
                'key' => $key,
                'attributes' => $attributes,
            ]));
            \assert($created instanceof ProductFamilyInterface);
            $familyIds[$key] = (string) $created->getUuid();
        }

        return $familyIds;
    }

    /**
     * @param array<string, string> $familyIds
     * @param array<string, string> $attributeIds
     *
     * @return array<string, string> product or variant code => uuid
     */
    private function createProducts(array $familyIds, array $attributeIds): array
    {
        $productIds = [];
        foreach ($this->data->products() as $product) {
            $created = $this->dispatch(new CreateProductMessage([
                'locale' => self::LOCALE,
                'template' => 'product',
                'type' => isset($product['variants']) ? ProductInterface::TYPE_PRODUCT_WITH_VARIANTS : ProductInterface::TYPE_PRODUCT,
                'productFamily' => $familyIds[$product['family']],
                'code' => $product['code'],
                'title' => $product['title'],
                'status' => 'available',
                'attributes' => $this->values($product['attributes'], $attributeIds),
                'headline' => $product['title'],
                'claim' => $product['claim'],
                'description' => $product['description'],
                'url' => $this->url($product['title']),
                'details' => ['image' => ['id' => $this->image($product['image'], $product['title'])]],
            ]));
            \assert($created instanceof ProductInterface);
            $productIds[$product['code']] = (string) $created->getUuid();

            // a variant belongs to its product and carries only the attributes its family marks as variant
            foreach ($product['variants'] ?? [] as $variant) {
                $created = $this->dispatch(new CreateProductMessage([
                    'locale' => self::LOCALE,
                    'type' => ProductInterface::TYPE_VARIANT,
                    'parent' => $productIds[$product['code']],
                    'productFamily' => $familyIds[$product['family']],
                    'code' => $variant['code'],
                    'title' => $variant['title'],
                    'attributes' => $this->values($variant['attributes'], $attributeIds),
                    'url' => $this->url($variant['title']),
                    'details' => ['image' => ['id' => $this->image($variant['image'], $variant['title'])]],
                ]));
                \assert($created instanceof ProductInterface);
                $productIds[$variant['code']] = (string) $created->getUuid();
            }
        }

        return $productIds;
    }

    /**
     * Associations point at other products, so they come after all products exist.
     *
     * @param array<string, string> $productIds
     */
    private function associateAndPublish(array $productIds): void
    {
        foreach ($this->data->products() as $product) {
            $associations = [];
            foreach ($product['associations'] ?? [] as $type => $codes) {
                $associations[$type] = \array_map(static fn (string $code): string => $productIds[$code], $codes);
            }

            if ([] !== $associations) {
                $this->dispatch(new ModifyProductMessage(['uuid' => $productIds[$product['code']]], ['locale' => self::LOCALE, 'associations' => $associations]));
            }

            $this->publish($productIds[$product['code']]);
            foreach ($product['variants'] ?? [] as $variant) {
                $this->publish($productIds[$variant['code']]);
            }
        }
    }

    private function publish(string $uuid): void
    {
        $this->dispatch(new ApplyWorkflowTransitionProductMessage(['uuid' => $uuid], self::LOCALE, 'publish'));
    }

    /**
     * The start page gets its headline and its lead text.
     *
     * @param array<string, string> $productIds
     */
    private function updateHomepage(array $productIds): void
    {
        $homepage = $this->homepage();
        $this->dispatch(new ModifyPageMessage(['uuid' => $homepage->getUuid()], [
            'locale' => self::LOCALE,
            'template' => 'homepage',
            'title' => 'Merch for developers',
            'url' => '/',
            'article' => '<p>Shirts, hoodies and mugs that compile on the first try. Made for the people who build the web.</p>',
        ]));
        $this->dispatch(new ApplyWorkflowTransitionPageMessage(['uuid' => $homepage->getUuid()], self::LOCALE, 'publish'));
    }

    private function homepage(): PageInterface
    {
        $homepage = $this->pageRepository->findOneBy(['parentId' => null, 'locale' => self::LOCALE, 'stage' => 'draft']);
        \assert($homepage instanceof PageInterface);

        return $homepage;
    }

    /**
     * Uploads a picture once. Every picture of the shop was made with an image model, which Sulu stores
     * as the origin of the media and the website shows as a badge.
     */
    private function image(string $file, string $title): int
    {
        $path = $this->imageDirectory . '/' . $file;

        return $this->media[$file] ??= (int) $this->mediaManager->save(
            new UploadedFile($path, $file, null, null, true),
            ['title' => $title, 'collection' => $this->collectionId(), 'locale' => self::LOCALE, 'origin' => 'ai_generated'],
            $this->userId(),
        )->getId();
    }

    private function collectionId(): int
    {
        return $this->collectionId ??= (int) $this->collectionManager->save(
            ['title' => 'Sulu Touch Shop', 'locale' => self::LOCALE, 'type' => ['id' => 1]],
            $this->userId(),
        )->getId();
    }

    private function userId(): ?int
    {
        return $this->entityManager->getRepository(User::class)->findOneBy([], ['id' => 'ASC'])?->getId();
    }

    /**
     * @param array<string, bool|float|int|string> $values attribute key => value
     * @param array<string, string> $attributeIds attribute key => uuid
     *
     * @return array<string, bool|float|int|string> attribute uuid => value
     */
    private function values(array $values, array $attributeIds): array
    {
        $byUuid = [];
        foreach ($values as $key => $value) {
            $byUuid[$attributeIds[$key]] = $value;
        }

        return $byUuid;
    }

    private function url(string $title): string
    {
        return '/products/' . (new AsciiSlugger())->slug($title)->lower();
    }

    private function dispatch(object $message): mixed
    {
        return $this->handle(new Envelope($message, [new EnableFlushStamp()]));
    }
}
