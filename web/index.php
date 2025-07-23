<?php

// comment out the following two lines when deployed to production
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));   //  /var/www/html
$dotenv->safeLoad();


require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

Yii::setAlias('@ip2l', dirname(__DIR__) . '/runtime/ip2location/');

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
