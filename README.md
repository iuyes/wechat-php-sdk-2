# wechatsdk
微信sdk（php版）

usage:
----
```php
$options = array(
    'token' => 'xxx',
    'encodingaeskey' => 'xxxx',
    'appid' => 'xx',
    'appsecret' => 'xxx'
);
$openId = isset($_GET['openid']) ? $_GET['openid'] : "oxt_8jg-cIh-Tv0fvv7yOep_GHEg";
$wechatObj = new Wechat($options);

//判断是否为第一次接入
if(isset($_GET["echostr"]) && $wechatObj->checkSignature()){
    echo $_GET['echostr'];exit;
}
$wechatObj->checkAndInit();

//发送客服消息(文本)
$result = $wechatObj->sendText($accessToken, $openId, $content);
```