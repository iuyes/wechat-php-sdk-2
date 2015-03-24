# wechatsdk
微信sdk（php版）

usage:
$options = array(<br/>
\t'token' => 'xxx',<br/>
\t'encodingaeskey' => 'xxxx',<br/>
\t'appid' => 'xx',<br/>
\t'appsecret' => 'xxx'<br/>
);<br/>
$openId = isset($_GET['openid']) ? $_GET['openid'] : "oxt_8jg-cIh-Tv0fvv7yOep_GHEg";<br/>
$wechatObj = new Wechat($options);<br/>
//判断是否为第一次接入<br/>
if(isset($_GET["echostr"]) && $wechatObj->checkSignature()){<br/>
\techo $_GET['echostr'];exit;<br/>
}<br/>
$wechatObj->checkAndInit();<br/>

//发送客服消息(文本)<br/>
$result = $wechatObj->sendText($accessToken, $openId, $content);<br/>
