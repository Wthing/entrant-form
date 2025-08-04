<?php

use diecoding\aws\s3\Service;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
//    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@mdm'   => '@vendor/mdmsoft',
        '@mdm/admin' => '@mdm/yii2-admin',
//        '@ip2L' => '@vendor/ip2location/ip2location-yii',
    ],

    'bootstrap' => [
        'admin', // required
    ],
    'modules' => [
        'admin' => [
            'class' => 'mdm\admin\Module',
            'layout' => 'left-menu',
            'menus' => [
                'assignment' => [
                    'label' => 'Grant Access' // change label
                ],
            ],
            'controllerMap' => [
                'assignment' => [
                    'class' => 'mdm\admin\controllers\AssignmentController',
                    /* 'userClassName' => 'app\models\User', */
                    'idField' => 'id',
                    'usernameField' => 'username',
                    'searchClass' => 'app\models\UserSearch'
                ],
            ],
            'as access' => [
                'class' => 'mdm\admin\components\AccessControl',
                'allowActions' => [
                    'site/*',
                    'admin/*',
                ]
            ],
        ],
    ],

    'components' => [

        's3' => [
            'class' =>  Service::class,
            'endpoint' => $_ENV['S3_ENDPOINT'],
            'usePathStyleEndpoint' => true,
            'credentials' => [
                'key' => $_ENV['S3_AUTH_KEY'],
                'secret' => $_ENV['S3_SECRET_KEY'],
            ],
            'region' => 'eu-north-1',
            'defaultBucket' => $_ENV['S3_BUCKET'],
            'defaultAcl' => 'public-read',
            'httpOptions' => [
                'verify' => false,
            ]
        ],

        'as access' => [
            'class' => 'mdm\admin\components\AccessControl',
            'allowActions' => [
                'site/*',
            ]
        ],

        'authManager' => [
            'class' => 'yii\rbac\DbManager',
        ],

        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'zK9eynzrLXVoyTFh6ppaQPX-ibKVAqd7',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,

        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['*'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['*'],
    ];
}

//define('IP2LOCATION_DATABASE', Yii::getAlias('@ip2l/IP2LOCATION-LITE-DB3.BIN'));
//Yii::info(Yii::getAlias('@ip2l/IP2LOCATION.BIN'));
//use IP2LocationYii\IP2Location_Yii;
//
//Yii::$container->setSingleton(IP2Location_Yii::class, function () {
//    return new IP2Location_Yii();
//});


return $config;
