# Step 6: Colour and size variants

**Branch** `step-06-variants`

**Goal** One product, many variants: each with its own URL, colour, size and photo.

## Do

`src/Catalogue/CatalogueData.php` (prepared)
```php
'variants' => [
    ['code' => 'TS-1001-TEA-M', 'title' => 'Hello World T-Shirt, Teal, M',
     'image' => 'hello-world-t-shirt-teal.jpg', 'attributes' => ['colour' => 'teal', 'size' => 'm']],
],
```

`config/packages/sulu_product.yaml`
```yaml
sulu_product:
    variants:
        properties:
            attributes: product.attributes
```

`templates/products/product.html.twig`
```twig
{% set variant = product.currentVariant|default(null) %}
{% set image = variant.image|default(product.image) %}

{% for option in product.variants %}
    <a href="{{ sulu_content_path(option.url) }}">
        {{ option.attributes.colour|sulu_product_format_attribute_value }}
    </a>
{% endfor %}
```

`config/forms/product_associations.xml` (a product with variants has no URL, so cards link to its first variant)
```xml
<param name="variants" value="product.variants"/>
```

`templates/products/_card.html.twig`
```twig
{% set url = item.url|default((item.variants|default([])|first).url|default(null)) %}
```

```bash
```
The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:
```bash
git checkout -q -f step-05-associations && git checkout step-06-variants -- .
bin/data 6
```

## Show

- `/products/hello-world-t-shirt-teal-m` works, `/products/hello-world-t-shirt` does not.
- Click a colour: the photo changes. Click a size: the URL changes.

## If it goes wrong

Typos: `git checkout -f step-06-variants` (instant, keeps the data). Stuck: `bin/stand 6 force` (the finished step with its data, about 30 seconds).
