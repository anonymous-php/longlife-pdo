# Longlife Pdo [![Build Status](https://travis-ci.org/anonymous-php/longlife-pdo.svg?branch=master)](https://travis-ci.org/anonymous-php/longlife-pdo)

This library will be helpful in case you are working with connections in workers or under process managers like PHP-PM. 
It provides the easiest way to stop to think about connection life cycle in looped applications.

Longlife Pdo provides an extension to the _Aura.Sql_ library along with a reconnection and prepared statements cache.

### Installation

```
composer require anonymous-php/longlife-pdo
```

### Usage

```php
<?php

use \Anonymous\Longlife\LonglifePdo;

$pdo = new LonglifePdo('mysql:host=127.0.0.1;dbname=test', 'test', 'test');

// To check connection in 60 seconds after the last connection usage
$pdo->setCheckConnectionTimeout(60);

// To use prepared statements cache and limit it with 100 items
$pdo->setStatementsCacheLimit(100);
```

### Dependencies

This packages is just an extension for the _Aura.Sql_ so check it's requirements:

> This package requires PHP 5.6 or later; it has also been tested on PHP 7 and HHVM. We recommend using the latest 
available version of PHP as a matter of principle.