# Step 1: Install and configure the bundle

**Branch** `step-01-install`

**Goal** Install the product bundle with three small changes and one config file.

## Do

```bash
composer require sulu/product-bundle:3.0.x-dev
```

What it takes. On stage the Live block, no download.

`config/bundles.php`
```php
Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle::class => ['all' => true],
```

`config/routes/sulu_admin.yaml`
```yaml
sulu_product_api:
    resource: "@SuluProductBundle/config/routing_admin_api.yaml"
    prefix: /admin/api
```

`config/packages/sulu_product.yaml`
```yaml
sulu_product:
    default_main_webspace: website
    association_types:
        accessory: { label: 'Goes well with' }
        alternative: { label: 'Alternatives' }
    measurements:
        weight: { units: [GRAM, KILOGRAM] }
        temperature: { units: [CELSIUS] }
        volume: ~   # every volume unit
```

The admin build is done before the talk and committed in `public/build/admin` (`npm install && npm run build` in `assets/admin`). Never on stage: a local build does not match the installed Sulu.

```bash
bin/adminconsole sulu:build dev
```

New `pr_*` tables and product rights for the admin role. On stage `bin/data 1` does it.

## Live

The code of this step on top of the previous step, so the IDE shows exactly its changes; then its packages, assets and data:

```bash
git checkout -q -f step-00-skeleton && git checkout step-01-install -- .
bin/data 1
```

## Show

- Admin menu: Products, Attributes, Attribute groups, Product families.
- `sulu:build dev` AFTER the install: the admin role only gets product rights when it is created.

## If it goes wrong

Typos: `git checkout -f step-01-install` (instant, keeps the data). Stuck: `bin/stand 1 force` (the finished step with its data, about 30 seconds).
