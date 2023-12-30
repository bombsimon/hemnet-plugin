<?php

// phpcs:ignoreFile

require_once "hemnet.php";

$hemnet = new Hemnet();

$for_sale = filterExactNumbers(
    $hemnet->getListingsForSale([898472]),
    [153, 49, 59, 11],
);

$sold = filterExactNumbers(
    $hemnet->getListingsSold([898472]),
    [153, 49, 59, 11],
);

echo "--------\n";
echo "FOR SALE\n";
echo "--------\n";
foreach ($for_sale as $x) {
    _printListing($x);
}

echo "--------\n";
echo "SOLD\n";
echo "--------\n";

foreach ($sold as $x) {
    _printListing($x);
}

function _printListing($listing)
{
    $floor = "";
    if (isset($listing->floor) && $listing->floor) {
        $floor = sprintf(" (floor %.0f)", $listing->floor);
    }

    echo $listing->url . "\n";
    echo sprintf("%-20s | %s%s\n", "Address", $listing->address(), $floor);
    echo sprintf("%-20s | %s\n", "Price", $listing->formattedPrice());
    echo sprintf("%-21s | %s\n", "Price per m²", $listing->formattedPricePersQuareMeter());
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
