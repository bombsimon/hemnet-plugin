<?php

/*
 * Use this file to run PHP from the CLI to do plugin development without the
 * need to fire up a Wordpress instance.
 */

include_once 'hemnet.php';

$hemnet = new Hemnet(TRUE);

printf("SOLD\n");
$sold = $hemnet->scrape_hemnet([898472], 'sold', [153, 49, 59, 11]);
var_dump($sold);

printf("FOR SALE\n");
$for_sale = $hemnet->scrape_hemnet([898472], 'for-sale', [153, 49, 59, 11]);
var_dump($for_sale);
