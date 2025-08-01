<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100 bg-light">
<?php $this->beginBody() ?>

<header id="header">
    <?php
    NavBar::begin([
        'brandLabel' => Html::tag('span', Html::encode(Yii::$app->name), [
            'class' => 'fw-bold text-primary fs-4'
        ]),
        'brandUrl' => Yii::$app->homeUrl,
        'options' => [
            'class' => 'navbar navbar-expand-lg navbar-light bg-white shadow-sm px-4 py-2 rounded-bottom',
            'style' => 'border-radius: 0 0 20px 20px;'
        ]
    ]);

    echo Nav::widget([
        'options' => ['class' => 'navbar-nav ms-auto align-items-center'],
        'items' => [
            ['label' => 'Главная', 'url' => ['/site/index'], 'linkOptions' => ['class' => 'nav-link px-3']],
            ['label' => 'О нас', 'url' => ['/site/about'], 'linkOptions' => ['class' => 'nav-link px-3']],
            ['label' => 'Контакты', 'url' => ['/site/contact'], 'linkOptions' => ['class' => 'nav-link px-3']],
            Yii::$app->user->isGuest
                ? ['label' => 'Вход', 'url' => ['/site/login'], 'linkOptions' => ['class' => 'btn btn-outline-primary ms-3 shadow-sm']]
                : '<li class="nav-item ms-3">'
                . Html::beginForm(['/site/logout'], 'post', ['class' => 'd-inline'])
                . Html::submitButton(
                    'Выход (' . Html::encode(Yii::$app->user->identity->username) . ')',
                    ['class' => 'btn btn-outline-secondary shadow-sm']
                )
                . Html::endForm()
                . '</li>'
        ]
    ]);

    NavBar::end();
    ?>
</header>


<main id="main" class="flex-shrink-0 py-4" role="main">
    <div class="container">
        <?php if (!empty($this->params['breadcrumbs'])): ?>
            <?= Breadcrumbs::widget([
                'links' => $this->params['breadcrumbs'],
                'options' => ['class' => 'mb-3'],
                'itemTemplate' => "<li class=\"breadcrumb-item\">{link}</li>\n",
                'activeItemTemplate' => "<li class=\"breadcrumb-item active\" aria-current=\"page\">{link}</li>\n",
            ]) ?>
        <?php endif ?>

        <?= Alert::widget() ?>

        <div class="p-4">
            <?= $content ?>
        </div>
    </div>
</main>

<footer id="footer" class="mt-auto py-3 bg-white border-top shadow-sm">
    <div class="container text-center text-muted small">
        <div class="row">
            <div class="col-md-6 mb-2 mb-md-0">
                &copy; My Company <?= date('Y') ?>
            </div>
            <div class="col-md-6">
                <?= Yii::powered() ?>
            </div>
        </div>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
