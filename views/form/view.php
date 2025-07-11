<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Document $model */
/** @var $pdfData */

$this->title = '1212';
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Documents'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
//$this->registerJsFile('@web/js/sign.js');
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
        const iin = '871203350924';
        const bin='';
        const serialNumber='';
        const tsaProfile = null;
        let selectedStorages = ['PKCS12'];


        let signInfo = {
            "module": "kz.gov.pki.knca.basics",
            "method": "sign",
            "args": {
                "allowedStorages": selectedStorages,
                "format": signatureType,
                "data": dataToSign,
                "signingParams": { decode, encapsulate, digested, tsaProfile },
                "signerParams": {
                    "extKeyUsageOids": extKeyUsageOids,
                    "iin": iin,
                    "bin": bin,
                    "serialNumber": serialNumber,
                    "chain": caCerts
                },
                "locale": localeRadio
            }
        }

        return connect().then((webSocket) => {

            webSocket.send(JSON.stringify(signInfo))

            return new Promise((resolve, reject) => {
                webSocket.onmessage = ({ data }) => {
                    response = JSON.parse(data);
                    if (response != null) {
                        var responseStatus = response['status'];
                        if (responseStatus === true) {
                            var responseBody = response['body'];
                            if (responseBody != null) {
                                //unblockScreen();
                                if (responseBody.hasOwnProperty('result')) {
                                    let  result = responseBody.result;
                                    //  getDataComplete().value = result;
                                    var formData = new FormData();
                                    formData.append("signData",result);
                                    formData.append("_csrf",yii.getCsrfToken());
                                    var request = new XMLHttpRequest();
                                    request.open("POST", "/sign/add");

                                    try {
                                        request.send(formData);
                                        location.replace(document.URL);
                                    }catch (e) {
                                        console.log("error "+e)
                                    }

                                    // debugger
                                    //$("#signature").val(result);
                                }
                            }
                        } else if (responseStatus === false) {
                            //unblockScreen();
                            var responseCode = response['code'];
                            alert(responseCode);
                        }
                    }
                    resolve(response);
                }
            })
        })
            .catch(function (err) {
                // debugger
                //unblockScreen();
                //unblockScreen();
                console.log(err)
            });

    }

    async function clickSign() {
        await  processSign()
    }

</script>
<div class="document-view">

    <?php $form = ActiveForm::begin(['id'=>'signForm']); ?>
    <textarea type="text" hidden id="dataToSign" rows="3"><?=$pdfData ?></textarea>
    <?php ActiveForm::end(); ?>

    <button type="button" onclick="clickSign()">Sign</button>

</div>
