<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var \app\models\Form $model */
/** @var $pdfData */
/** @var yii\web\View $this */
/** @var int $userId */

$this->title = 'Информация о заявлении';
?>

<script>

    function unblockScreen() {
        console.log("unblockScreen()");
    }
    function openDialog() {
        alert("Ошибка соединения с модулем ЭЦП!");
    }


    const getDataSign = () =>{
        return document.getElementById("dataToSign").value;
    }
    const getDataComplete = () =>{
        return document.getElementById("dataCompleteSign");
    }

    const getSocketUrl = ()=>{
        return  'wss://127.0.0.1:13579/';
    }


    function connect() {
        if (connect.webSocket && connect.webSocket.readyState < 2) {
            console.log("reusing the socket connection [state = " + connect.webSocket.readyState + "]: " + connect.webSocket.url);
            return Promise.resolve(connect.webSocket);
        }

        return new Promise(function (resolve, reject) {
            connect.webSocket = new WebSocket(getSocketUrl());

            connect.webSocket.onopen = function () {
                console.log("socket connection is opened [state = " + connect.webSocket.readyState + "]: " + connect.webSocket.url);
                resolve(connect.webSocket);
            };

            connect.webSocket.onerror = function (err) {
                unblockScreen();
                console.error("socket connection error : ", err);
                reject(err);
            };

            connect.webSocket.onclose = function (event) {
                if (event.wasClean) {
                    console.error("socket connection is closed ");
                } else {
                    console.log('Connection error');
                    openDialog();
                }
                console.log('Code: ' + event.code + ' Reason: ' + event.reason);
            };
        });
    }

    async function processSign (){
        // debugger
        // const signatureType = 'cms';
        const signatureType = 'xml';
        const dataToSign = getDataSign();
        const decode = "true";
        const encapsulate = "true";
        const digested = "false";
        const extKeyUsageOidString = false;
        const extKeyUsageOids = [];
        const caCertsString = '';
        let caCerts=null;
        const localeRadio = 'ru';
        const iin = '';
        const bin='';
        const serialNumber='';
        const tsaProfile = null;
        let selectedStorages = ['PKCS12', 'AKKaztoken', 'WEB', 'CAPI', 'NCALayer'];


        let signInfo = {
            module: "kz.gov.pki.knca.basics",
            method: "sign",
            args: {
                allowedStorages: selectedStorages,
                format: "xml",
                data: getDataSign(),
                signingParams: {
                    decode: "true",
                    encapsulate: "true",
                    digested: "false",
                    tsaProfile: null
                },
                signerParams: {
                    extKeyUsageOids: extKeyUsageOids,
                    iin: iin,
                    bin: bin,
                    serialNumber: serialNumber,
                    chain: null
                },
                locale: localeRadio
            }
        }

        try {
            const webSocket = await connect();
            webSocket.send(JSON.stringify(signInfo));

            webSocket.onmessage = ({ data }) => {
                const response = JSON.parse(data);
                if (response?.status === true && response.body?.result) {
                    const signed = response.body.result;

                    const formData = new FormData();
                    formData.append("signData", signed);
                    formData.append("formId", document.getElementById("formId").value);
                    formData.append("_csrf", yii.getCsrfToken());

                    const request = new XMLHttpRequest();
                    request.open("POST", "/form/add-secretary?userId=<?= Html::encode($userId) ?>");

                    request.onload = () => {
                        if (request.status === 200) {
                            document.open();
                            document.write(request.responseText);
                            document.close(); // 👈 это безопасно рендерит success.php
                        } else {
                            alert("Ошибка при сохранении подписи: " + request.statusText);
                        }
                    };

                    request.onerror = () => {
                        alert("Ошибка сети при отправке подписи");
                    };

                    request.send(formData);
                } else {
                    alert("Ошибка подписи: " + response?.code ?? "Неизвестная ошибка");
                }
            };
        } catch (err) {
            alert("Ошибка подключения к модулю подписи: " + err);
        }
    }

    async function clickSign() {
        await  processSign()
    }

</script>

<div class="container py-5">

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h5 class="card-title">Информация о документе</h5>
            <ul class="list-group list-group-flush mt-3">
                <li class="list-group-item"><strong>ФИО:</strong> <?= Html::encode("{$model->surname} {$model->first_name} {$model->patronymic}") ?></li>
                <li class="list-group-item"><strong>Программа:</strong> <?= Html::encode($model->edu_program) ?></li>
                <li class="list-group-item"><strong>Язык обучения:</strong> <?= Html::encode($model->edu_language) ?></li>
                <li class="list-group-item"><strong>Дата подачи:</strong> <?= date('d.m.Y H:i', $model->date_filled) ?></li>
            </ul>
        </div>
    </div>

    <div class="document-view no-print mt-4">
        <?php $form = ActiveForm::begin(['id' => 'signForm']); ?>

        <!-- Скрытые данные -->
        <textarea type="text" hidden id="dataToSign" rows="3"><?= Html::encode($pdfData) ?></textarea>
        <input type="hidden" id="formId" value="<?= $model->id ?>">

        <?php ActiveForm::end(); ?>

        <!-- Кнопка подписи -->
        <button type="button"
                class="btn btn-lg btn-success shadow-sm px-4 py-2 mt-3"
                onclick="clickSign()"
        >
            <i class="bi bi-pen-fill me-2"></i> Подписать документ
        </button>
    </div>

    <div class="text-center mt-5">
        <a href="<?= Yii::$app->homeUrl ?>" class="btn btn-lg btn-secondary">
            <i class="bi bi-house-door-fill me-2"></i>Вернуться на главную
        </a>
    </div>

</div>
