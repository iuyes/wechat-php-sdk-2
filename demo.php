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
$wechatObj = new WechatSubscribe($options);

//获取access_token
$accessToken = array (
	'access_token' => 'adhohuQIbN3d5CNx6wKU6mhgPL2SBraLFYgP5um2pbYMvDI93b7VgL2pcHWPDHB2s_8PdMMZg-37s9hHiKIKdXZFadCRMGVZG9yinEMp6Dc',
	'expires_in' => 7200
);
// $accessToken = $wechatObj->getAccessToken();
// $accessToken = json_decode($accessToken, true);
// var_export($accessToken);
$accessToken = $accessToken['access_token'];

//获取微信服务器IP地址
/*
$access = array (
	'access_token' => 'pDMjaLlVwrWWGYU3fb-_IoFuNAlxfWELd57OkXpv3M-HR8GZbkR1XjTnSnJ8sPtKsv1AvLqDUs9dal02jC4pMLNXuCW236JzB1Chh_op6Yk',
	'expires_in' => 7200,
);
$ipList = $wechatObj->getIpList($access['access_token']);
*/

// 上传多媒体
/*
$filePath = '/tmp/1.jpg';
$result = $wechatObj->upload($accessToken, 'image', $filePath);
*/
$mediaId = 'xx-xx';

//下载多媒体
/*
$result = $wechatObj->download($accessToken, $mediaId, '/tmp/11.jpg');
var_export($result);
*/

// 发送文本客服消息

/*
$content = "测试发送文本";
$result = $wechatObj->sendText($accessToken, $openId, $content);
var_export($result);
*/

// 发送客服消息(图片)

$result = $wechatObj->sendImg($accessToken, $openId, $mediaId);
var_export($result);


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
var_dump($result);
*/

//添加客服账号
// $result = $wechatObj->addKf($accessToken, 'mer@gh_089fa58ad5d9', 'merssss', 'sfdsfsdf');
// var_export($result);

