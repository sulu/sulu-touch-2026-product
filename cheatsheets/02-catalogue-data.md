# Step 2: The catalogue as data

**Branch** `step-02-catalogue-data`

**Goal** Explain the entities with real data: group, attributes, families, products.

## Do

`src/Catalogue/CatalogueData.php` (prepared, not typed)
```php
'colour' => [
    'type' => 'options', 'name' => 'Colour', 'group' => 'Look', 'filterable' => true,
    'options' => ['teal' => 'Teal', 'mustard' => 'Mustard', 'black' => 'Black'],
],
'weight' => ['type' => 'number', 'name' => 'Weight', 'group' => 'Specs', 'config' => ['unit' => 'GRAM']],
// families
'clothing' => ['name' => 'Clothing', 'attributes' => [
    'material' => ['required' => true],
    'colour' => ['variant' => true],
    'size' => ['variant' => true],
]],
// products
['code' => 'TS-1001', 'family' => 'clothing', 'title' => 'Hello World T-Shirt',
 'image' => 'hello-world-t-shirt-teal.jpg', 'attributes' => ['material' => 'organic-cotton', 'weight' => 180], ...],
```

```bash
vendor/bin/phpunit
```

The test checks 27 products: every family, attribute, association and picture exists.

## Live

The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:

```bash
git checkout -q -f step-01-install && git checkout step-02-catalogue-data -- .
bin/data 2
```

## Show

- Five attribute types: text, number with a unit, date, options, boolean (yes or no).
- Booleans here: `limited_edition` (filterable) and `dishwasher_safe`.
- 27 products, 41 photos in `data/images/`. Nothing is in the database yet.

## If it goes wrong

Typos: `git checkout -f step-02-catalogue-data` (instant, keeps the data). Stuck: `bin/stand 2 force` (the finished step with its data, about 30 seconds).
