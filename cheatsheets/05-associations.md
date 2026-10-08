# Step 5: Associations

**Branch** `step-05-associations`

**Goal** Show accessories and alternatives on the product page, and show how little it takes to change what is resolved.

## Do

`config/forms/product_associations.xml`
```xml
<property name="associations/accessory" type="product_selection">
    <meta><title lang="en">Goes well with</title></meta>
    <params>
        <param name="properties" type="collection">
            <param name="headline" value="headline"/>
            <param name="claim" value="claim"/>
            <param name="image" value="image"/>
        </param>
    </params>
</property>
```

`templates/products/product.html.twig`
```twig
{% for item in product.associations.accessory %}
    {% include 'products/_card.html.twig' %}
{% endfor %}
```

`templates/products/_card.html.twig`
```twig
<h3>{{ item.headline }}</h3>
<p>{{ item.claim }}</p>
<img src="{{ item.image.thumbnails['product-card'] }}">
```

**Live change:** delete the `claim` line, reload: the claim is gone from the cards. Add it again.
Now add `<param name="code" value="code"/>` and print `{{ item.code }}` in the card.

## Live

The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:

```bash
git checkout -q -f step-04-product-page && git checkout step-05-associations -- .
bin/data 5
```

## Show

- `/products/hello-world-t-shirt`: "Goes well with" and "Alternatives" under the product.
- Admin: the Associations tab of the same product.

## If it goes wrong

Typos: `git checkout -f step-05-associations` (instant, keeps the data). Stuck: `bin/stand 5 force` (the finished step with its data, about 30 seconds).
