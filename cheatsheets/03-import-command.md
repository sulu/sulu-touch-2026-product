# Step 3: Import with a console command

**Branch** `step-03-import-command`

**Goal** Load the data with the same messages the admin uses.

## Do

`src/Command/ImportCatalogueCommand.php`
```php
$created = $this->dispatch(new CreateProductMessage([
    'locale'        => 'en',
    'template'      => 'product',
    'type'          => ProductInterface::TYPE_PRODUCT,
    'productFamily' => $familyIds[$product['family']],
    'code'          => $product['code'],
    'title'         => $product['title'],
    'attributes'    => $this->values($product['attributes'], $attributeIds),  // attribute uuid => value
    'claim'         => $product['claim'],
    'url'           => $this->url($product['title']),
    'details'       => ['image' => ['id' => $this->image($product['image'], $product['title'])]],
]));

$this->dispatch(new ApplyWorkflowTransitionProductMessage(['uuid' => $uuid], 'en', 'publish'));
```

```bash
bin/adminconsole app:import-catalogue
```

About a minute.

## Live

The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:

```bash
git checkout -q -f step-02-catalogue-data && git checkout step-03-import-command -- .
bin/adminconsole app:import-catalogue
bin/data 3
```

Run the import while the database is still empty, talk for a few seconds, then Ctrl+C: `bin/data 3` loads the complete data. Run again on full data, the import only says "The catalogue is already imported."

## Show

- No preview and no product page yet: the Twig template comes in step 4 ("Page does not exist in html format"). Use it as the bridge to step 4.
- Admin: Products list, open one: Details (attributes), Content, SEO.
- Start the import first and talk while it runs.

## If it goes wrong

Typos: `git checkout -f step-03-import-command` (instant, keeps the data). Stuck: `bin/stand 3 force` (the finished step with its data, about 30 seconds).
