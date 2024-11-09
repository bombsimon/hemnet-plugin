<?php

// phpcs:ignoreFile

require_once "hemnet.php";

define("LOCATION_IDS", [898472]);

$hemnet = new Hemnet();

echo "Checking objects for sale\n";

$for_sale = $hemnet->getListingsForSale(LOCATION_IDS);
assert(count($for_sale) > 0, "Expected to find at least one item for sale");

foreach ($for_sale as $item) {
    _assertListing($item);
}

echo "\nChecking sold objects\n";
$sold = $hemnet->getListingsSold(LOCATION_IDS);
assert(count($sold) > 0, "Expected to find at least one sold item");

foreach ($sold as $item) {
    _assertListing($item);
}

echo "\nParsing OK!\n";

function _assertListing(Listing $item, bool $is_sold = false): void
{
    _assertWithError($item, $item->url != "", "Expected URL to be non empty");
    _assertWithError($item, $item->address() != "", "Expected address to be non empty");

    _assertWithError($item, preg_match("/^\d+$/", $item->price), "Expected price to be numeric");
    _assertWithError($item, preg_match("/^\d+$/", $item->price_per_square_meter), "Expected price per square meter to be numeric");
    _assertWithError($item, preg_match("/^\d+$/", $item->fee), "Expected fee to be numeric");
    _assertWithError($item, $item->rooms >= 1.0, "Expected rooms to be greater than or equal to 1.0");
    _assertWithError($item, isset($item->living_area), "Expected living area to be set");

    if (isset($item->floor)) {
        _assertWithError($item, preg_match("/^\d+$/", $item->floor), "Expected floor to be numeric");
    }

    $total_area = $item->living_area;
    if (isset($item->living_bi_area)) {
        _assertWithError($item, preg_match("/^\d+(\.\d+)?$/", $item->living_bi_area), "Expected living bi area to be numeric");
        $total_area += $item->living_bi_area;
    }

    _assertWithError($item, $total_area >= 1.0, "Expected total area to be greater than or equal to 1.0");

    if ($is_sold) {
        _assertWithError($item, is_a($item->sold_at, "DateTime"), "Expeted sold at to be a DateTime object");
        _assertWithError($item, preg_match("/^-?\d+$/", $item->price_change), "Expected price change to be numeric");
    }
}

function _assertWithError(Listing $item, bool $is_ok, string $message): void
{
    if ($is_ok) {
        return;
    }

    _printListingWithMessage($item, "⚠️ ASSERTION FAIELD: $message");
    exit(1);
}


function _printListingWithMessage(Listing $listing, string $message): void
{
    echo "$message\n\n";

    $floor = "";
    if (isset($listing->floor) && $listing->floor) {
        $floor = sprintf(" (floor %.0f)", $listing->floor);
    }

    echo $listing->url . "\n";
    echo sprintf("%-20s | %s%s\n", "Address", $listing->address(), $floor);
    echo sprintf("%-20s | %s\n", "Price", $listing->formattedPrice());
    echo sprintf("%-21s | %s\n", "Price per m²", $listing->formattedPricePerSquareMeter());
    echo sprintf("%-20s | %s\n", "Fee", $listing->formattedFee());
    echo sprintf("%-20s | %s\n", "Living area", $listing->formattedLivingArea());
    echo sprintf("%-20s | %s\n", "Rooms", $listing->rooms);

    if (isset($listing->sold_at) && $listing->sold_at) {
        echo sprintf("%-20s | %s\n", "Sold at", $listing->sold_at->format("Y-m-d"));
    }

    if (isset($listing->price_change)) {
        echo sprintf("%-20s | %s%%\n", "Price change", $listing->price_change);
    }

    echo "\n";
}
