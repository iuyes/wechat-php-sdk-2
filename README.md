# wechatsdk
微信sdk（php版）

usage:
$options = array(<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'token' => 'xxx',<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'encodingaeskey' => 'xxxx',<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'appid' => 'xx',<br/>
&nbsp;&nbsp;&nbsp;&nbsp;'appsecret' => 'xxx'<br/>
);<br/>
$openId = isset($_GET['openid']) ? $_GET['openid'] : "oxt_8jg-cIh-Tv0fvv7yOep_GHEg";<br/>
$wechatObj = new Wechat($options);<br/>
//判断是否为第一次接入<br/>
if(isset($_GET["echostr"]) && $wechatObj->checkSignature()){<br/>
&nbsp;&nbsp;&nbsp;&nbsp;echo $_GET['echostr'];exit;<br/>
}<br/>
$wechatObj->checkAndInit();<br/>

//发送客服消息(文本)<br/>
$result = $wechatObj->sendText($accessToken, $openId, $content);<br/>
