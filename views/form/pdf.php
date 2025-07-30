<?php
/** @var app\models\Form $model */
/** @var $pdfData */
/** @var yii\web\View $this */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$types = [
    'обладателя государственного образовательного гранта;',
    'обладателя государственного образовательного гранта по сокращённым образовательным программам;',
    'обладателя гранта местного исполнительного органа;',
    'на платной основе по конкурсу сертификатов, выданных по результатам ЕНТ;',
    'на платной основе по сокращённым образовательным программам;',
    'грант «Казахстан Халкына»;',
    'образовательный грант для молодёжи из густонаселённых и западных регионов;',
];
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


                var responseStatus = response['status'];
                var responseBody = response['body'];
                console.log(response.body.result);


                if (responseStatus === true && responseBody.hasOwnProperty('result')) {
                    const signed = response.body.result;

                    const formData = new FormData();
                    formData.append("signData", signed);
                    formData.append("formId", document.getElementById("formId").value);
                    formData.append("_csrf", yii.getCsrfToken());

                    const request = new XMLHttpRequest();
                    request.open("POST", "/form/add");

                    request.onload = () => {
                        if (request.status === 200) {
                            document.open();
                            document.write(request.responseText);
                            document.close();
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

<style>
    @media print {
        .no-print {
            display: none !important;
        }
    }

    body {
        font-family: "Times New Roman", serif;
        font-size: 10pt;
        line-height: 1.5;
        margin: 0;
        padding: 20mm 20mm 20mm 20mm;
    }
    .header-right {
        text-align: right;
        font-weight: bold;
        margin-bottom: 10mm;
    }
    .header-right-cap {
        text-align: right;
        font-weight: normal;
        font-style: italic;
        margin-bottom: 10mm;
    }
    .center-title {
        text-align: center;
        font-weight: bold;
        font-size: 10pt;
        margin-top: 15mm;
        margin-bottom: 5mm;
    }
    .label-small {
        font-size: 10pt;
        text-align: center;
    }
    ul {
        margin-top: 5mm;
        margin-left: 20mm;
        padding-left: 0;
    }
    li {
        margin-bottom: 2mm;
    }
    .footer-space {
        margin-top: 25mm;
    }
</style>

<!--<div class="no-print" style="margin-top: 20px;">-->
<!--    --><?php //= Html::a('Подписать', ['form/view', 'formId' => $model->id], ['class' => 'btn btn-primary']) ?>
<!--</div>-->

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



<div class="header-right-cap">Приложение 2</div>

<table style="width: 100%; border-spacing: 0; margin-bottom: 10mm;">
    <tr>
        <td style="width: 65%;"></td>
        <td class="header-right" style="width: 35%; text-align: left;">
            Председателю Правления – Ректору<br>
            НАО «Карагандинский технический университет имени Абылкаса Сагинова»<br>
            д.э.н., профессору<br>
            Сагинтаевой С.С.
        </td>
    </tr>
</table>

<table style="width: 100%; border-spacing: 0; margin-bottom: 2mm;">
    <tr>
        <td style="border-bottom: 1px solid black; text-align: center;">
            <?= Html::encode("{$model->surname} {$model->first_name} {$model->patronymic}") ?>
        </td>
    </tr>
    <tr>
        <td class="label-small">(фамилия имя отчество полностью)</td>
    </tr>
</table>

<table style="width: 100%; border-spacing: 0; margin-bottom: 3mm;">
    <tr>
        <td style="width: 55mm;">проживающего(ей) по адресу:</td>
        <td style="border-bottom: 1px solid black;">
            <?= Html::encode($model->address) ?>
        </td>
    </tr>
</table>

<div class="center-title">
    ЗАЯВЛЕНИЕ <span style="font-weight: normal;">(личное поступающего)</span>
</div>

<p>
    Прошу зачислить меня в число студентов 1 курса <i>(нужное подчеркнуть)</i>:
</p>

<ul>
    <?php foreach ($types as $type): ?>
        <li>
            <?= rtrim($type, ';') === $model->education_type
                ? '<span style="text-decoration: underline;">' . Html::encode($type) . '</span>'
                : Html::encode($type) ?>
        </li>
    <?php endforeach; ?>
</ul>

<table style="width: 100%; border-spacing: 0; margin-top: 5mm; margin-bottom: 2mm;">
    <tr>
        <td style="width: 55mm;">по образовательной программе:</td>
        <td style="border-bottom: 1px solid black;">
            <?= Html::encode($model->edu_program) ?>
        </td>
    </tr>
</table>

<table style="width: 100%; border-spacing: 0; margin-bottom: 5mm;">
    <tr>
        <td style="width: 35mm;">язык обучения:</td>
        <td style="border-bottom: 1px solid black;">
            <?= Html::encode($model->edu_language) ?>
        </td>
    </tr>
</table>

<p style="margin-top: 15mm">
    С правилами приёма в высшее учебное заведение на 2025 г., ознакомлен(а)
</p>

<table style="width: 100%; border-spacing: 0; margin-bottom: 3mm; margin-top: 10mm">
    <tr>
        <td style="width: 45mm;">
            Дата:
            <span style="border-bottom: 1px solid black; display: inline-block; min-width: 25mm; text-align: center;">
                <?= date('d.m.Y', $model->date_filled) ?>
            </span>
        </td>
        <td></td>
        <td style="text-align: right; white-space: nowrap;">
            Подпись:
        </td>
        <td style="border-bottom: 1px solid black; width: 40mm;"></td>
    </tr>
</table>

<div class="footer-space">
    Ответственный секретарь приёмной комиссии ___________________________
</div>
