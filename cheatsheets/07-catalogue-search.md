# Step 7: Overview page with filters and search

**Branch** `step-07-catalogue-search`

**Goal** A catalogue page with filters and a search box. The URL is the whole state.

## Do

`config/packages/sulu_product.yaml`
```yaml
sulu_product:
    search:
        website:
            additional_product_filters: true
```

`src/Controller/Website/CatalogueController.php`
```php
$search = $this->engine->createSearchBuilder('website')
    ->addFilter(Condition::equal('resourceKey', 'product'))
    ->addFilter(Condition::equal('locale', $locale));

$search->addFilter(Condition::search($term));
$search->addFilter(Condition::in('product.attributes_text_values', ['colour:teal']));
```

`assets/website/controllers/catalogue_filter_controller.js`
```js
export default class extends Controller {
    static targets = ['form'];
    submit() { this.formTarget.requestSubmit(); }
}
```

## Live

The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:

```bash
git checkout -q -f step-06-variants && git checkout step-07-catalogue-search -- .
bin/data 7
```

## Show

- `/products`: tick Teal, then Hoodie sizes M. The list changes.
- Tick "Limited edition: Yes": 6 products. A filterable boolean is one "Yes" checkbox, stored as `limited_edition:true` in the index.
- Type a typo, "hodie": Loupe still finds the hoodies. Elasticsearch does not out of the box.
- After a NEW filterable attribute: `cache:clear` and `cmsig:seal:reindex --index website --drop`.

## If it goes wrong

Typos: `git checkout -f step-07-catalogue-search` (instant, keeps the data). Stuck: `bin/stand 7 force` (the finished step with its data, about 30 seconds). Never `bin/reset-demo` on stage: about 2 minutes.
