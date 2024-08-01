<?php

  declare(strict_types=1);

  use Workbench\App\Models\Product;

  it('can use product model', function () {
    $product = Product::first();
    expect($product instanceof Product)->toBeTrait();
  });
