@foreach ($products as $product)
    <li><x-storefront.product-card :product="$product" /></li>
@endforeach
