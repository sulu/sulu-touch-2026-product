<?php

declare(strict_types=1);

namespace App\Tests;

use App\Catalogue\CatalogueData;
use PHPUnit\Framework\TestCase;

final class CatalogueDataTest extends TestCase
{
    public function testTheShopIsComplete(): void
    {
        $data = new CatalogueData();

        self::assertCount(13, $data->attributes());
        self::assertCount(2, $data->families());
        self::assertCount(27, $data->products());
    }

    public function testEveryReferenceIsDefined(): void
    {
        $data = new CatalogueData();
        $attributes = $data->attributes();
        $families = $data->families();
        $codes = \array_column($data->products(), 'code');

        foreach ($families as $family) {
            foreach (\array_keys($family['attributes']) as $key) {
                self::assertArrayHasKey($key, $attributes);
            }
        }

        foreach ($data->products() as $product) {
            self::assertArrayHasKey($product['family'], $families, $product['code']);
            self::assertFileExists(__DIR__ . '/../data/images/' . $product['image']);
            foreach (\array_keys($product['attributes']) as $key) {
                self::assertArrayHasKey($key, $families[$product['family']]['attributes'], $product['code'] . ' ' . $key);
            }
            foreach ($product['associations'] ?? [] as $associated) {
                foreach ($associated as $code) {
                    self::assertContains($code, $codes, $product['code']);
                }
            }
            foreach ($product['variants'] ?? [] as $variant) {
                self::assertFileExists(__DIR__ . '/../data/images/' . $variant['image']);
                foreach (\array_keys($variant['attributes']) as $key) {
                    self::assertArrayHasKey($key, $families[$product['family']]['attributes'], $variant['code'] . ' ' . $key);
                }
            }
        }
    }
}
