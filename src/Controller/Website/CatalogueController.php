<?php

declare(strict_types=1);

namespace App\Controller\Website;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Search\Condition\Condition;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\UserInterface\Controller\Website\ContentController;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Sulu\Product\Application\AttributeType\BooleanAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductAttributesReindexProviderEnhancer as Fields;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The page template "Product catalogue": the page content plus the products found in the website index of Sulu.
 * The query string is the whole state, so a filtered list is a plain link: `q` is the search term, `p` the page,
 * `family[]` the chosen families and `f[<attribute>][]` the chosen options.
 *
 * @extends ContentController<PageDimensionContentInterface>
 */
class CatalogueController extends ContentController
{
    private const PER_PAGE = 12;
    private const PRODUCT = 'product.';

    public function __construct(
        private readonly EngineInterface $engine,
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaManagerInterface $mediaManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    protected function resolveSuluParameters(DimensionContentInterface $object, string $webspaceKey, bool $normalize): array
    {
        $data = parent::resolveSuluParameters($object, $webspaceKey, $normalize);
        $query = $this->requestStack->getCurrentRequest()?->query;
        $locale = $object->getLocale() ?? 'en';
        $term = \trim((string) $query?->get('q', ''));
        $page = \max(1, (int) $query?->getInt('p', 1));
        $families = \array_values(\array_filter($query?->all('family') ?? [], \is_string(...)));
        $options = [];
        foreach ($query?->all('f') ?? [] as $key => $values) {
            $options[(string) $key] = \array_values(\array_filter((array) $values, \is_string(...)));
        }

        $search = $this->engine->createSearchBuilder('website')
            ->addFilter(Condition::equal('resourceKey', ProductInterface::RESOURCE_KEY))
            ->addFilter(Condition::equal('locale', $locale))
            ->addFilter(Condition::equal('webspaces', $webspaceKey))
            ->limit(self::PER_PAGE)
            ->offset(($page - 1) * self::PER_PAGE);

        if ('' !== $term) {
            $search->addFilter(Condition::search($term));
        }
        if ([] !== $families) {
            $search->addFilter(Condition::in(self::PRODUCT . Fields::PRODUCT_FAMILY_KEY_FIELD, $families));
        }
        foreach ($options as $key => $values) {
            if ([] !== $values) {
                $search->addFilter(Condition::in(self::PRODUCT . Fields::TEXT_VALUES_FIELD, \array_map(static fn (string $value): string => Fields::textValue($key, $value), $values)));
            }
        }
        // one card per colour: the other sizes show up as soon as the visitor picks a size
        if ([] === ($options['size'] ?? [])) {
            $search->addFilter(Condition::notIn(self::PRODUCT . Fields::TEXT_VALUES_FIELD, [Fields::textValue('size', 's'), Fields::textValue('size', 'l')]));
        }

        $result = $search->getResult();

        $data['catalogue'] = [
            'term' => $term,
            'total' => $result->total(),
            'page' => $page,
            'pages' => \max(1, (int) \ceil($result->total() / self::PER_PAGE)),
            'items' => $this->cards($result, $locale),
            'filters' => $this->filters($families, $options, $locale),
        ];

        return $data;
    }

    /**
     * @param iterable<array<string, mixed>> $documents
     *
     * @return list<array{headline: mixed, url: mixed, image: object|null}>
     */
    private function cards(iterable $documents, string $locale): array
    {
        $found = [];
        foreach ($documents as $document) {
            $mediaId = $document['mediaId'] ?? null;
            $found[] = ['headline' => $document['title'], 'url' => $document['url'], 'mediaId' => \is_numeric($mediaId) ? (int) $mediaId : 0];
        }

        $mediaIds = \array_values(\array_filter(\array_column($found, 'mediaId')));
        $media = [];
        foreach ([] === $mediaIds ? [] : $this->mediaManager->getByIds($mediaIds, $locale) as $item) {
            $media[$item->getId()] = $item;
        }

        return \array_map(static fn (array $card): array => ['headline' => $card['headline'], 'url' => $card['url'], 'image' => $media[$card['mediaId']] ?? null], $found);
    }

    /**
     * The families and every filterable attribute with a fixed set of values (options and yes/no).
     *
     * @param list<string> $chosenFamilies
     * @param array<string, list<string>> $chosenOptions
     *
     * @return list<array{name: string, label: string, options: list<array{value: string, label: string, checked: bool}>}>
     */
    private function filters(array $chosenFamilies, array $chosenOptions, string $locale): array
    {
        $families = [];
        foreach ($this->entityManager->getRepository(ProductFamily::class)->findBy([], ['key' => 'ASC']) as $family) {
            $families[$family->getKey()] = $family->getTranslation($locale)?->getName() ?? $family->getKey();
        }
        $filters = [$this->filter('family', 'Family', $families, $chosenFamilies)];

        foreach ($this->entityManager->getRepository(Attribute::class)->findBy(['filterable' => true], ['position' => 'ASC']) as $attribute) {
            $values = [];
            if (AttributeInterface::TYPE_BOOLEAN === $attribute->getType()) {
                $values[BooleanAttributeType::TRUE] = 'Yes';
            } elseif (AttributeInterface::TYPE_OPTIONS === $attribute->getType()) {
                foreach ($attribute->getOptions() as $option) {
                    $values[$option->getKey()] = $option->getTranslation($locale)?->getName() ?? $option->getKey();
                }
            } else {
                continue;
            }

            $filters[] = $this->filter('f[' . $attribute->getKey() . ']', $attribute->getTranslation($locale)?->getName() ?? $attribute->getKey(), $values, $chosenOptions[$attribute->getKey()] ?? []);
        }

        return $filters;
    }

    /**
     * @param array<string, string> $values value => label
     * @param list<string> $checked
     *
     * @return array{name: string, label: string, options: list<array{value: string, label: string, checked: bool}>}
     */
    private function filter(string $name, string $label, array $values, array $checked): array
    {
        $options = [];
        foreach ($values as $value => $text) {
            $options[] = ['value' => (string) $value, 'label' => $text, 'checked' => \in_array((string) $value, $checked, true)];
        }

        return ['name' => $name, 'label' => $label, 'options' => $options];
    }
}
