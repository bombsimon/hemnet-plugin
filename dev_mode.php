<?php

/*
 * Use this file to run PHP from the CLI to do plugin development without the
 * need to fire up a Wordpress instance.
 */

include_once 'hemnet.php';

$hemnet = new Hemnet(TRUE);
$sold = $hemnet->scrape_hemnet([476608], 'sold', [8, 10]);
$for_sale = $hemnet->scrape_hemnet([476608], 'for-sale', [8, 10]);

printf("SOLD\n");
var_dump($sold);

printf("FOR SALE\n");
var_dump($for_sale);
