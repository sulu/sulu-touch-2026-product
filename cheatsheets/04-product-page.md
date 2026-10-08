# Step 4: The product page

**Branch** `step-04-product-page`

**Goal** Render a product on the website with Tailwind and Stimulus.

## Do

```bash
composer require symfony/asset-mapper symfony/stimulus-bundle symfony/ux-turbo symfonycasts/tailwind-bundle
bin/websiteconsole importmap:install
bin/websiteconsole tailwind:build
```

`templates/products/product.html.twig`
```twig
{% set groups = product.attributes|sulu_product_attribute_groups %}

<h1>{{ content.headline }}</h1>
<p>{{ content.claim }}</p>

{% for group in groups %}
    <h2>{{ group.label }}</h2>
    {% for attribute in group.attributes %}
        {{ attribute.label }}: {{ attribute.formattedValue }}
    {% endfor %}
{% endfor %}
```

## Live

The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:

```bash
git checkout -q -f step-03-import-command && git checkout step-04-product-page -- .
bin/data 4
```

## Show

- Every picture has a small "AI" badge in the top right corner (`templates/products/_image.html.twig`): it reads the media's `origin`, the disclosure text is the tooltip.
- `/products/hello-world-t-shirt`: unit ("180 g"), date and option labels come formatted.
- A boolean comes as a real `true` or `false` in `formattedValue`: the template prints Yes or No.  Example: a mug says "Dishwasher safe: Yes".

## If it goes wrong

Typos: `git checkout -f step-04-product-page` (instant, keeps the data). Stuck: `bin/stand 4 force` (the finished step with its data, about 30 seconds).
