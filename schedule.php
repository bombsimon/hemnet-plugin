<?php

// phpcs:ignoreFile

require_once "hemnet.php";

$hemnet = new Hemnet();

echo "Checking objects for sale\n";
$for_sale = $hemnet->getListingsForSale([898472]);
assert(count($for_sale) > 0, "Expected to find at least one item for sale");
foreach ($for_sale as $item) {
    _assertListing($item);
}

echo "Checking sold objects\n";
$sold = $hemnet->getListingsSold([898472]);
assert(count($sold) > 0, "Expected to find at least one sold item");
foreach ($sold as $item) {
    _assertListing($item);
}

echo "Parsing OK!\n";

function _assertListing($item, $is_sold = false)
{
    assert($item->url != "", "Expected URL to be non empty");
    assert($item->address() != "", "Expected address to be non empty");

    assert(preg_match("/^\d+$/", $item->price), "Expected price to be numeric");
    assert(preg_match("/^\d+$/", $item->price_per_square_meter), "Expected price per square meter to be numeric");
    assert(preg_match("/^\d+$/", $item->fee), "Expected fee to be numeric");
    assert($item->rooms >= 1.0, "Expected rooms to be greater than or equal to 1.0");
    assert(isset($item->living_area), "Expected living area to be set");

    if (isset($item->floor)) {
        assert(preg_match("/^\d+$/", $item->floor), "Expected floor to be numeric");
    }

    $total_area = $item->living_area;
    if (isset($item->living_bi_area)) {
        assert(preg_match("/^\d+(\.\d+)?$/", $item->living_bi_area), "Expected living bi area to be numeric");
        $total_area += $item->living_bi_area;
    }

    assert($total_area >= 1.0, "Expected total area to be greater than or equal to 1.0");

    if ($is_sold) {
        assert(is_a($item->sold_at, "DateTime"), "Expeted sold at to be a DateTime object");
        assert(preg_match("/^-?\d+$/", $item->price_change), "Expected price change to be numeric");
    }
}
