<?php
use WechatSDK\WechatSubscribe;
require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

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

//获取access_token
// $accessTokenJson = $wechatObj->getAccessToken();
// $accessTokenArr = json_decode($accessTokenJson, true);
// $accessToken = $accessTokenArr['access_token'];

//获取微信服务器IP地址
// $ipList = $wechatObj->getIpList($accessToken);

// 上传多媒体

// $filePath = '/tmp/1.jpg';
// $result = $wechatObj->upload($accessToken, 'image', $filePath);

//新增永久素材（非图文）
//$filePath = '/tmp/1.jpg';
// $result = $wechatObj->addMaterial($accessToken, 'image', $filePath);

//下载多媒体
// $filePath = '/tmp/1.jpg';
// $mediaId = xxx;
// $result = $wechatObj->download($accessToken, $mediaId, $filePath);

//获取永久素材
//$mediaId = xxx;
//$destination = 'xxx';
//$wechatObj->getMaterial($accessToken, $mediaId, $destination);

//删除永久素材
//$mediaId = xxx;
//$wechatObj->delMaterial($accessToken, $mediaId);

//获取永久素材总数
//$mediaId = xxx;
//$wechatObj->getMaterialCount($accessToken, $mediaId);

//获取永久素材总数
//$result = $wechatObj->getMaterialList($accessToken, 'image');


// 发送文本客服消息
// $content = "测试发送文本";
// $result = $wechatObj->sendText($accessToken, $openId, $content);

// 发送客服消息(图片)
// $result = $wechatObj->sendImg($accessToken, $openId, $mediaId);

//发送客服消息(图文)
/*
$articleArr = array(
	0 => array(
		'title' => 'xxx', 
		'description' => 'dddd',
		'picurl' => 'http://www.workec.com/newtpl/images/new/hou_02.jpg',
		'url' => 'http://www.baidu.com'
	),
	1 => array(
		'title' => 'test2', 
		'description' => 'ddd222',
		'picurl' => 'http://www.workec.com/newtpl/images/new/hou_03.jpg',
		'url' => 'http://www.douban.com'
	),
);
$result = $wechatObj->sendNews($accessToken, $openId, $articleArr);
*/

//添加客服账号
// $result = $wechatObj->addKf($accessToken, 'mer@gh_089fa58ad5d9', 'merssss', 'sfdsfsdf');

