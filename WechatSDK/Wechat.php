<?php
/**
 * 微信sdk基类
 * @todo 需要服务号权限的接口
 */

namespace WechatSDK;
use WechatSDK\WXBizMsgCrypt;
use WechatSDK\ErrorCode;
use LSS\Array2XML;
use LSS\XML2Array;

class Wechat
{
	/**
	 * 接收消息的类型
	 */
	const MSG_TYPE_TEXT     = 'text';	//文本消息
	const MSG_TYPE_IMAGE    = 'image';	//图片消息
	const MSG_TYPE_VOICE    = 'voice';	//语音消息
	const MSG_TYPE_VIDEO    = 'video';	//视频消息
	const MSG_TYPE_LOCATION = 'location';	//地理位置消息
	const MSG_TYPE_LINK     = 'link';	//链接消息
	const MSG_TYPE_EVENT    = 'event';	//事件
	
	/**
	 * 接收事件的类型
	 */
	const EVENT_SUBSCRIBE          = 'subscribe';	//关注
	const EVENT_UNSUBSCRIBE        = 'unsubscribe';	//取消关注
	const EVENT_SCAN_SUBSCRIBE     = 'scan_subscribe';	//扫描带参数二维码事件(用户未关注时，进行关注后的事件)
	const EVENT_SCAN               = 'SCAN';	//扫描带参数二维码事件(用户已关注时的事件)
	const EVENT_LOCATION           = 'LOCATION';	//上报地理位置事件
	const EVENT_CLICK              = 'CLICK';	//自定义菜单事件(点击菜单拉取消息时的事件)
	const EVENT_VIEW               = 'VIEW';	//自定义菜单事件(点击菜单跳转链接时的事件)
	const EVENT_MASSSENDJOBFINISH  = 'MASSSENDJOBFINISH';	//群发任务提交成功并结束时，会向开发者推送的事件。
	const EVENT_SCANCODE_PUSH      = 'scancode_push';	//扫码推事件的事件推送
	const EVENT_SCANCODE_WAITMSG   = 'scancode_waitmsg';	//扫码推事件且弹出“消息接收中”提示框的事件推送
	const EVENT_PIC_SYSPHOTO       = 'pic_sysphoto';	//弹出系统拍照发图的事件推送
	const EVENT_PIC_PHOTO_OR_ALBUM = 'pic_photo_or_album';	//弹出拍照或者相册发图的事件推送
	const EVENT_PIC_WEIXIN         = 'pic_weixin';	//弹出微信相册发图器的事件推送
	const EVENT_LOCATION_SELECT    = 'location_select';	//弹出地理位置选择器的事件推送

	public $err;
	protected $_token;
	protected $_appid;
	protected $_appsecret;
	protected $_encodingaeskey;
	protected $_debug;
	protected $_data = array();	//由请求的xml解析而成的数据
	protected $_isEncrypted = false;	//微信消息是否加密传输
	protected $_cryptObj = null;	//第三方回复加密消息给公众平台对象
	
	/**
	 * 相关URL
	 * @var string
	 */
	protected $_url     = 'https://api.weixin.qq.com/cgi-bin';		//请求微信服务器的URL
	protected $_fileUrl = 'http://file.api.weixin.qq.com/cgi-bin';	//微信文件相关URL
	protected $_kfUrl   = 'https://api.weixin.qq.com/customservice/kfaccount';	//客服接口相关URL

	/**
	 * @param array $options 相关配置项
	 */
	public function __construct($options = array()){
		$this->_token = isset($options['token']) ? $options['token'] : '';
		if (empty($this->_token)) {
			throw new \Exception(ErrorCode::info('20001'));
		}

		$this->_encodingaeskey = isset($options['encodingaeskey']) ? $options['encodingaeskey'] : '';
		if (empty($this->_encodingaeskey)) {
			throw new \Exception(ErrorCode::info('20002'));
		}

		$this->_appid = isset($options['appid']) ? $options['appid'] : '';
		if (empty($this->_encodingaeskey)) {
			throw new \Exception(ErrorCode::info('20003'));
		}

		$this->_appsecret = isset($options['appsecret']) ? $options['appsecret'] : '';
		if (empty($this->_encodingaeskey)) {
			throw new \Exception(ErrorCode::info('20004'));
		}

		$this->_debug = isset($options['debug']) ? $options['debug'] : false;

		$this->_init();
	}

	/**
	 * 验证合法性并初始化数据
	 */
	protected function _init()
	{
		//判断是否为第一次接入
		if(isset($_GET["echostr"]) && $this->checkSignature()){
			echo $_GET['echostr'];exit;
		}

		if ($_SERVER['REQUEST_METHOD'] == "POST") {
			$postStr = file_get_contents("php://input");
			
			//判断是否加密
			if(isset($_GET['encrypt_type']) && $_GET['encrypt_type'] == 'aes'){
				$this->_cryptObj = new WXBizMsgCrypt($this->_token, $this->_encodingaeskey, $this->_appid);
				$postData = '';
				$errCode = $this->_cryptObj->decryptMsg($_GET['msg_signature'], $_GET["timestamp"], $_GET["nonce"], $postStr, $postData);
				if ($errCode != 0) {
					throw new \Exception(ErrorCode::info($errCode));
				}
				
				$this->_isEncrypted = true;
				$postStr = $postData;

				if(empty($postStr)){
					return ;
				}
			}elseif(!$this->checkSignature()){
				throw new \Exception(ErrorCode::info('40002'));
			}

			$this->_data = XML2Array::createArray($postStr);
			$this->_data = $this->_data['xml'];
		}
	}

	/**
	 * 检测签名合法性
	 * @param  string $msgEncrypt
	 * @return boolean
	 */
	protected function checkSignature($msgEncrypt = '')
	{
		$signature = isset($_GET["signature"]) ? $_GET["signature"] : '';
		$signature = isset($_GET["msg_signature"]) ? $_GET["msg_signature"] : $signature; //如果存在加密验证则用加密验证段
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];

		$tmpArr = array($this->_token, $timestamp, $nonce, $msgEncrypt);
		// use SORT_STRING rule
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}

	/**
	 * 开发者微信号
	 */
	public function getToUserName(){
		if(!isset($this->_data['ToUserName'])){
			return '';
		}
		return $this->_data['ToUserName']['@cdata'];
	}

	/**
	 * 获取发送方帐号（一个OpenID）
	 */
	public function getFromUserName(){
		if(!isset($this->_data['FromUserName'])){
			return '';
		}
		return $this->_data['FromUserName']['@cdata'];
	}

	/**
	 * 获取消息创建时间 （整型）
	 */
	public function getCreateTime(){
		if(!isset($this->_data['CreateTime'])){
			return 0;
		}
		return $this->_data['CreateTime'];
	}

	/**
	 * 获取接收消息的类型
	 */
	public function getMsgType(){
		if(!isset($this->_data['MsgType'])){
			return '';
		}
		return $this->_data['MsgType']['@cdata'];
	}

	/**
	 * 获取事件类型
	 */
	public function getEventType(){
		$msgType = $this->getMsgType();
		if($msgType != self::MSG_TYPE_EVENT){
			throw new \Exception(ErrorCode::info('40008'));
		}

		$eventType = $this->getEvent();
		if($eventType == self::EVENT_SUBSCRIBE && isset($this->_data['EventKey'])){
			$eventType = self::EVENT_SCAN_SUBSCRIBE;
		}

		return $eventType;
	}

	/**
	 * 获取文本消息内容
	 */
	public function getContent(){
		if(!isset($this->_data['Content'])){
			return '';
		}
		return $this->_data['Content']['@cdata'];
	}

	/**
	 * 获取消息id，64位整型
	 */
	public function getMsgId(){
		if(!isset($this->_data['MsgId'])){
			return 0;
		}
		return $this->_data['MsgId'];
	}

	/**
	 * 获取图片链接
	 */
	public function getPicUrl(){
		if(!isset($this->_data['PicUrl'])){
			return '';
		}
		return $this->_data['PicUrl']['@cdata'];
	}

	/**
	 * 获取消息媒体id，可以调用多媒体文件下载接口拉取数据
	 */
	public function getMediaId(){
		if(!isset($this->_data['MediaId'])){
			return '';
		}
		return $this->_data['MediaId']['@cdata'];
	}

	/**
	 * 获取语音格式，如amr，speex等
	 */
	public function getFormat(){
		if(!isset($this->_data['Format'])){
			return '';
		}
		return $this->_data['Format']['@cdata'];
	}

	/**
	 * 获取视频消息缩略图的媒体id，可以调用多媒体文件下载接口拉取数据。
	 */
	public function getThumbMediaId(){
		if(!isset($this->_data['ThumbMediaId'])){
			return '';
		}
		return $this->_data['ThumbMediaId']['@cdata'];
	}

	/**
	 * 获取地理位置维度
	 */
	public function getLocationX(){
		if(!isset($this->_data['Location_X'])){
			return '';
		}
		return $this->_data['Location_X'];
	}

	/**
	 * 获取地理位置经度
	 */
	public function getLocationY(){
		if(!isset($this->_data['Location_Y'])){
			return '';
		}
		return $this->_data['Location_Y'];
	}

	/**
	 * 获取地图缩放大小
	 */
	public function getScale(){
		if(!isset($this->_data['Scale'])){
			return 0;
		}
		return $this->_data['Scale'];
	}

	/**
	 * 获取地理位置信息
	 */
	public function getLabel(){
		if(!isset($this->_data['Label'])){
			return '';
		}
		return $this->_data['Label']['@cdata'];
	}

	/**
	 * 获取消息标题
	 */
	public function getTitle(){
		if(!isset($this->_data['Title'])){
			return '';
		}
		return $this->_data['Title']['@cdata'];
	}

	/**
	 * 获取消息描述
	 */
	public function getDescription(){
		if(!isset($this->_data['Description'])){
			return '';
		}
		return $this->_data['Description']['@cdata'];
	}

	/**
	 * 获取消息链接
	 */
	public function getUrl(){
		if(!isset($this->_data['Url'])){
			return '';
		}
		return $this->_data['Url']['@cdata'];
	}

	/**
	 * 获取事件类型
	 */
	public function getEvent(){
		if(!isset($this->_data['Event'])){
			return '';
		}
		return $this->_data['Event']['@cdata'];
	}

	/**
	 * 获取二维码的ticket，可用来换取二维码图片
	 */
	public function getTicket(){
		if(!isset($this->_data['Ticket'])){
			return '';
		}
		return $this->_data['Ticket']['@cdata'];
	}

	/**
	 * 获取事件KEY值，qrscene_为前缀，后面为二维码的参数值
	 */
	public function getEventKey(){
		if(!isset($this->_data['EventKey'])){
			return '';
		}
		return $this->_data['EventKey']['@cdata'];
	}

	/**
	 * 获取地理位置纬度
	 */
	public function getLatitude(){
		if(!isset($this->_data['Latitude'])){
			return '';
		}
		return $this->_data['Latitude'];
	}

	/**
	 * 获取地理位置经度
	 */
	public function getLongitude(){
		if(!isset($this->_data['Longitude'])){
			return '';
		}
		return $this->_data['Longitude'];
	}

	/**
	 * 获取地理位置精度
	 */
	public function getPrecision(){
		if(!isset($this->_data['Precision'])){
			return '';
		}
		return $this->_data['Precision'];
	}

	/**
	 * 获取语音识别结果，UTF8编码
	 */
	public function getRecognition(){
		if(!isset($this->_data['Recognition'])){
			return '';
		}
		return $this->_data['Recognition']['@cdata'];
	}

	/**
	 * 获取access token
	 * @return string
	 */
	public function getAccessToken(){
		$url = $this->_url . "/token?grant_type=client_credential&appid={$this->_appid}&secret={$this->_appsecret}";
		return $this->httpRequest($url);
	}

	/**
	 * 获取微信服务器IP地址
	 * @param string $accessToken
	 * @return mixed
	 */
	public function getIpList($accessToken){
		if(empty($accessToken)){
			throw new \Exception(ErrorCode::info('40035'));
		}
		$url = $this->_url . "/getcallbackip?access_token={$accessToken}";
		return $this->httpRequest($url);
	}

	/**
	 * 上传多媒体文件
	 * @param string $accessToken
	 * @param string $type 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
	 * @param string $filepath 文件的绝对路径
	 * @return mixed
	 */
	public function upload($accessToken, $type, $filepath){
		if(empty($accessToken) || empty($type) || empty($filepath)){
			throw new \Exception(ErrorCode::info('40035'));
		}
		$url = $this->_fileUrl . "/media/upload?access_token={$accessToken}&type={$type}";
		$fileData = array('media' => '@'.$filepath);
		return $this->httpRequest($url, false, $fileData);
	}

	/**
	 * 下载多媒体
	 * @param  string $accessToken
	 * @param  string $mediaId 媒体文件上传后，获取时的唯一标识
	 * @param  string $destination 下载文件放置的地址（绝对路径）
	 */
	public function download($accessToken, $mediaId, $destination){
		if(empty($accessToken) || empty($mediaId) || empty($destination)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_fileUrl . "/media/get?access_token={$accessToken}&media_id={$mediaId}";
		$handle = fopen($destination, 'w');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FILE, $handle);
		$output = curl_exec($ch);
		if(!$output || !curl_errno($ch) == 0){
			$this->err = curl_error($ch);
		}

		curl_close($ch);
		return $output;
	}

	/**
	 * 回复文本消息
	 * @param string $content 回复的消息内容（换行：在content中能够换行，微信客户端就支持换行显示）
	 * @return mixed
	 */
	public function responseText($content){
		if(!is_string($content)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$data = array(
			'ToUserName' => array('@cdata' => $this->getFromUserName()),
			'FromUserName' => array('@cdata' => $this->getToUserName()),
			'CreateTime' => time(),
			'MsgType' => array('@cdata' => 'text'),
			'Content' => array('@cdata' => $content),
		);
		
		return $this->_createXML($data);
	}

	/**
	 * 回复图片消息
	 * @param string $mediaId 通过上传多媒体文件，得到的id
	 * @return mixed
	 */
	public function responsePic($mediaId){
		if(empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$data = array(
			'ToUserName' => array('@cdata' => $this->getFromUserName()),
			'FromUserName' => array('@cdata' => $this->getToUserName()),
			'CreateTime' => time(),
			'MsgType' => array('@cdata' => 'image'),
			'Image' => array('MediaId' => array('@cdata' => $mediaId)),
		);
		
		return $this->_createXML($data);
	}

	/**
	 * 回复语音消息
	 * @param string $mediaId 通过上传多媒体文件，得到的id
	 * @return mixed
	 */
	public function responseVoice($mediaId){
		if(empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}
		$data = array(
			'ToUserName' => array('@cdata' => $this->getFromUserName()),
			'FromUserName' => array('@cdata' => $this->getToUserName()),
			'CreateTime' => time(),
			'MsgType' => array('@cdata' => 'voice'),
			'Voice' => array('MediaId' => array('@cdata' => $mediaId)),
		);
		
		return $this->_createXML($data);
	}

	/**
	 * 回复视频消息
	 * @param string $mediaId 通过上传多媒体文件，得到的id
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'title' => 'xxx',	//视频消息的标题
	 *  	'description' => 'xxx',	//视频消息的描述
	 *  );
	 * @return mixed
	 */
	public function responseVideo($mediaId, $options = array()){
		if(empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$data = array(
			'ToUserName' => array('@cdata' => $this->getFromUserName()),
			'FromUserName' => array('@cdata' => $this->getToUserName()),
			'CreateTime' => time(),
			'MsgType' => array('@cdata' => 'video'),
			'Video' => array(
				'MediaId' => array('@cdata' => $mediaId),
			),
		);
		if(!empty($options)){
			if(isset($options['title'])){
				$data['Video']['Title'] = $options['title'];
			}
			if(isset($options['description'])){
				$data['Video']['Description'] = $options['description'];
			}
		}
		
		return $this->_createXML($data);
	}

	/**
	 * 回复音乐消息
	 * @param string $thumbMediaId 缩略图的媒体id，通过上传多媒体文件，得到的id
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'title' => 'xxx',	//音乐标题
	 *  	'description' => 'xxx',	//音乐描述
	 *  	'musicUrl' => 'xxx',	//音乐链接
	 *  	'HQMusicUrl' => 'xxx',	//高质量音乐链接，WIFI环境优先使用该链接播放音乐
	 *  );
	 * @return mixed
	 */
	public function responseMusic($thumbMediaId, $options = array()){
		if(empty($thumbMediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$data = array(
			'ToUserName' => array('@cdata' => $this->getFromUserName()),
			'FromUserName' => array('@cdata' => $this->getToUserName()),
			'CreateTime' => time(),
			'MsgType' => array('@cdata' => 'music'),
			'Music' => array(
				'ThumbMediaId' => array('@cdata' => $thumbMediaId)
			),
		);
		if(!empty($options)){
			if(isset($options['title'])){
				$data['Music']['Title'] = $options['title'];
			}
			if(isset($options['description'])){
				$data['Music']['Description'] = $options['description'];
			}
			if(isset($options['musicUrl'])){
				$data['Music']['MusicUrl'] = $options['musicUrl'];
			}
			if(isset($options['HQMusicUrl'])){
				$data['Music']['HQMusicUrl'] = $options['HQMusicUrl'];
			}
		}

		return $this->_createXML($data);
	}

	/**
	 * 回复图文消息
	 * @param string $articleCount 图文消息个数，限制为10条以内
	 * @param string $articleArr 图文消息内容
	 *   eg: $articleArr = array(
	 *   	0 => array(
	 *			'Title' => array('@cdata' => 'xxxx'),	//图文消息标题（非必须）
	 *			'Description' => array('@cdata' => 'xxxxx'),	//图文消息描述（非必须）
	 *			'PicUrl' => array('@cdata' => 'http://www.xxxx.com/1.jpg'),	//图片链接支持JPG、PNG格式，较好的效果为大图360*200，小图200*200（非必须）
	 *			'Url' => array('@cdata' => 'http://www.xxxx.com')	//点击图文消息跳转链接（非必须）
	 *		),
	 *	);
	 * @return mixed
	 */
	public function responseNews($articleCount, $articleArr){
		if(empty($articleCount) || empty($articleArr) || !is_array($articleArr)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		if($articleCount > 10){
			throw new \Exception(ErrorCode::info('45008'));
		}

		$xmlArr = array(
			'ToUserName' => array('@cdata' => $this->getFromUserName()),
			'FromUserName' => array('@cdata' => $this->getToUserName()),
			'CreateTime' => time(),
			'MsgType' => array('@cdata' => 'news'),
			'ArticleCount' => $articleCount,
			'Articles' => array(
				'item' => $articleArr
			),
		);
		return $this->_createXML($xmlArr);
	}

	/**
	 * 客服接口（添加客服账号）
	 * @param string $accessToken
	 * @param string $kfAccount 完整客服账号，格式为：账号前缀@公众号微信号
	 * @param string $nickname  客服昵称
	 * @param string $passwd    客服账号登录密码，格式为密码明文的32位加密MD5值。（非必须）
	 *                          该密码仅用于在公众平台官网的多客服功能中使用，若不使用多客服功能，则不必设置密码
	 * ps:必须先在公众平台官网为公众号设置微信号后才能使用该能力。
	 *                          
	 */
	public function addKf($accessToken, $kfAccount, $nickname, $passwd = ''){
		if(empty($accessToken) || empty($kfAccount) || empty($nickname)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_kfUrl . "/add?access_token={$accessToken}";
		$data = array(
			"kf_account" => $kfAccount,
			"nickname" => $nickname,
		);
		if(!empty($passwd)){
			$data['password'] = $passwd;
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 客服接口（修改客服账号）
	 * @param string $accessToken
	 * @param string $kfAccount 完整客服账号，格式为：账号前缀@公众号微信号
	 * @param string $nickname  客服昵称
	 * @param string $passwd    客服账号登录密码，格式为密码明文的32位加密MD5值。（非必须）
	 *                          该密码仅用于在公众平台官网的多客服功能中使用，若不使用多客服功能，则不必设置密码
	 *                          
	 */
	public function updateKf($accessToken, $kfAccount, $nickname, $passwd = ''){
		if(empty($accessToken) || empty($kfAccount) || empty($nickname)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_kfUrl . "/update?access_token={$accessToken}";
		$data = array(
			"kf_account" => $kfAccount,
			"nickname" => $nickname,
		);
		if(!empty($passwd)){
			$data['password'] = $passwd;
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 客服接口（删除客服账号）
	 * @param string $accessToken
	 * @param string $kfAccount 完整客服账号，格式为：账号前缀@公众号微信号
	 * @param string $nickname  客服昵称
	 * @param string $passwd    客服账号登录密码，格式为密码明文的32位加密MD5值。（非必须）
	 *                          该密码仅用于在公众平台官网的多客服功能中使用，若不使用多客服功能，则不必设置密码
	 *                          
	 */
	public function delKf($accessToken, $kfAccount, $nickname, $passwd = ''){
		if(empty($accessToken) || empty($kfAccount) || empty($nickname)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_kfUrl . "/del?access_token={$accessToken}";
		$data = array(
			"kf_account" => $kfAccount,
			"nickname" => $nickname,
		);
		if(!empty($passwd)){
			$data['password'] = $passwd;
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 设置客服账号头像
	 * @param string $accessToken
	 * @param string $kfAccount 完整客服账号，格式为：账号前缀@公众号微信号
	 * @param string $filepath  头像绝对路径
	 * @return mixed
	 */
	public function setKfHead($accessToken, $kfAccount, $filepath){
		if(empty($accessToken) || empty($kfAccount) || empty($filepath)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "http://api.weixin.qq.com/customservice/kfaccount/uploadheadimg?access_token={$accessToken}&kf_account={$kfAccount}";
		$fileData = array('media' => '@'.$filepath);
		return $this->httpRequest($url, false, $fileData);
	}

	/**
	 * 获取所有客服账号
	 * @param string $accessToken
	 * @return json
	 */
	public function getKfList($accessToken){
		if(empty($accessToken)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/customservice/getkflist?access_token={$accessToken}";
		return $this->httpRequest($url);
	}

	/**
	 * 发送客服消息(文本消息)
	 * @param  string $accessToken
	 * @param  string $openId
	 * @param  string $content 文本内容
	 * @param  string $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'kfAccount' => 'xxx',	//客服账号
	 *  );
	 * @return string
	 */
	public function sendText($accessToken, $openId, $content, $options = array()){
		if(empty($accessToken) || empty($openId) || empty($content)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_url . "/message/custom/send?access_token={$accessToken}";
		$data = array(
			"touser" => $openId,
			"msgtype" => "text",
			"text" => array('content' => $content),
		);
		if(!empty($options) && isset($options['kfAccount'])){
			$data['customservice'] = array("kf_account" => $options['kfAccount']);
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 发送客服消息(图片消息)
	 * @param  string $accessToken
	 * @param  string $openId
	 * @param  string $mediaId
	 * @param  string $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'kfAccount' => 'xxx',	//客服账号
	 *  );
	 * @return string
	 */
	public function sendImg($accessToken, $openId, $mediaId, $options = array()){
		if(empty($accessToken) || empty($openId) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_url . "/message/custom/send?access_token={$accessToken}";
		$data = array(
			"touser" => $openId,
			"msgtype" => "image",
			"image" => array('media_id' => $mediaId),
		);
		if(!empty($options) && isset($options['kfAccount'])){
			$data['customservice'] = array("kf_account" => $options['kfAccount']);
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 发送客服消息(语音消息)
	 * @param  string $accessToken
	 * @param  string $openId
	 * @param  string $mediaId
	 * @param  string $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'kfAccount' => 'xxx',	//客服账号
	 *  );
	 * @return array
	 */
	public function sendVoice($accessToken, $openId, $mediaId, $kfAccount = ''){
		if(empty($accessToken) || empty($openId) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_url . "/message/custom/send?access_token={$accessToken}";
		$data = array(
			"touser" => $openId,
			"msgtype" => "voice",
			"voice" => array('media_id' => $mediaId),
		);
		if(!empty($options) && isset($options['kfAccount'])){
			$data['customservice'] = array("kf_account" => $options['kfAccount']);
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 发送客服消息(视频消息)
	 * @param string $accessToken
	 * @param string $openId
	 * @param string $mediaId 发送的视频的媒体ID
	 * @param string $thumbMediaId 缩略图的媒体ID
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'title' => 'xxx',	//视频消息的标题（非必须）
	 *  	'description' => 'xxx',	//视频消息的描述（非必须）
	 *  	'kfAccount' => 'xxx',	//客服账号（非必须）
	 *  );
	 * @return array
	 */
	public function sendVideo($accessToken, $openId, $mediaId, $thumbMediaId, $options = array()){
		if(empty($accessToken) || empty($openId) || empty($mediaId) || empty($thumbMediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_url . "/message/custom/send?access_token={$accessToken}";
		$data = array(
			"touser" => $openId,
			"msgtype" => "video",
			"video" => array(
				'media_id' => $mediaId,
				'thumb_media_id' => $thumbMediaId,
			),
		);
		if(!empty($options)){
			if(isset($options['title'])){
				$data['video']['title'] = $options['title'];
			}
			if(isset($options['description'])){
				$data['video']['description'] = $options['description'];
			}
			if(isset($options['kfAccount'])){
				$data['customservice'] = array("kf_account" => $options['kfAccount']);
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 发送客服消息(音乐消息)
	 * @param  string $accessToken
	 * @param  string $openId
	 * @param  string $musicUrl 音乐链接
	 * @param  string $hqMusicUrl 高品质音乐链接，wifi环境优先使用该链接播放音乐
	 * @param  string $thumbMediaId 缩略图的媒体ID
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'title' => 'xxx',	//音乐消息的标题
	 *  	'description' => 'xxx',	//音乐消息的描述
	 *  	'kfAccount' => 'xxx',	//客服账号
	 *  );
	 * @return array
	 */
	public function sendMusic($accessToken, $openId, $musicUrl, $hqMusicUrl, $thumbMediaId, $options = array()){
		if(empty($accessToken) || empty($openId) || empty($musicUrl) || empty($hqMusicUrl) || empty($thumbMediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = $this->_url . "/message/custom/send?access_token={$accessToken}";
		$data = array(
			"touser" => $openId,
			"msgtype" => "music",
			"music" => array(
				'musicurl' => $musicUrl,
				'hqmusicurl' => $hqMusicUrl,
				'thumb_media_id' => $thumbMediaId,
			),
		);
		if(!empty($options)){
			if(isset($options['title'])){
				$data['music']['title'] = $options['title'];
			}
			if(isset($options['description'])){
				$data['music']['description'] = $options['description'];
			}
			if(isset($options['kfAccount'])){
				$data['customservice'] = array("kf_account" => $options['kfAccount']);
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 发送客服消息(图文消息)
	 * @param string $accessToken
	 * @param string $openId
	 * @param string $articleArr 图文消息内容
	 *  eg: $articleArr = array(
	 *   	0 => array(
	 *			'title' => array('@cdata' => 'xxxx'),	//图文消息的标题（非必须）
	 *			'description' => array('@cdata' => 'xxxxx'),	//图文消息的描述（非必须）
	 *			'picurl' => array('@cdata' => 'xxxx'),	//图文消息的图片链接，支持JPG、PNG格式，较好的效果为大图640*320，小图80*80(非必须)
	 *			'url' => array('@cdata' => 'http://www.xxxx.com')	//图文消息被点击后跳转的链接(非必须)
	 *		),
	 *	);
	 * @param  string $kfAccount 客服账号
	 * @return mixed
	 */
	public function sendNews($accessToken, $openId, $articleArr, $kfAccount = ''){
		if(empty($accessToken) || empty($openId) || empty($articleArr)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		if(count($articleArr) > 10){
			throw new \Exception(ErrorCode::info('45008'));
		}

		$url = $this->_url . "/message/custom/send?access_token={$accessToken}";
		$data = array (
			'touser' => $openId,
			'msgtype' => 'news',
			'news' => array (
				'articles' => $articleArr
			),
		);
		if(!empty($kfAccount)){
			$data['customservice'] = array("kf_account" => $kfAccount);
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 上传图文消息素材
	 * @param  string $accessToken
	 * @param array $articleArr
	 *  eg: $articleArr =array (
	 *	    0 => array (
	 *	    	'thumb_media_id' => 'xxxx',	//图文消息缩略图的media_id，可以在基础支持-上传多媒体文件接口中获得
	 *	     	'title' => 'xxxx',  //图文消息的标题
	 *	     	'content' => 'xxxx', //图文消息页面的内容，支持HTML标签
	 *	     	'author' => 'xxxx', //图文消息的作者（非必须）
	 *	    	'content_source_url' => 'xxxx',	//在图文消息页面点击“阅读原文”后的页面（非必须）
	 *	      	'digest' => 'xxxx',  //图文消息的描述（非必须）
	 *	       	'show_cover_pic' => '1', //是否显示封面，1为显示，0为不显示（非必须）
	 *	    ),
	 *	);
	 *	@return mixed
	 */
	public function uploadNews($accessToken, $articleArr){
		if(empty($accessToken) || empty($articleArr)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/media/uploadnews?access_token={$accessToken}";
		$data = array('articles' => $articleArr);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 根据分组进行群发(图文消息)
	 * @param string $accessToken
	 * @param string 用于群发的消息的media_id,通过上传图文消息素材接口（uploadNews）获得
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'isToAll' => 'xxx',	//用于设定是否向全部用户发送，值为true或false，选择true该消息群发给所有用户，选择false可根据group_id发送给指定群组的用户
	 *  	'groupId' => 'xxx',	//群发到的分组的group_id，参加用户管理中用户分组接口，若is_to_all值为true，可不填写group_id
	 *  );
	 * @return mixed
	 */
	public function sendAllNews($accessToken, $mediaId, $options = array()){
		if(empty($accessToken) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token={$accessToken}";
		$data = array(
			'filter' => array(),
			'mpnews' => array(
				'media_id' => $mediaId,
			),
			'msgtype' => 'mpnews',
		);
		if(!empty($options)){
			if(isset($options['isToAll'])){
				$data['filter']['is_to_all'] = $options['isToAll'];
			}
			if(isset($options['groupId'])){
				$data['filter']['group_id'] = $options['groupId'];
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 根据分组进行群发(文本消息)
	 * @param string $accessToken
	 * @param string $content
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'isToAll' => 'xxx',	//用于设定是否向全部用户发送，值为true或false，选择true该消息群发给所有用户，选择false可根据group_id发送给指定群组的用户
	 *  	'groupId' => 'xxx',	//群发到的分组的group_id，参加用户管理中用户分组接口，若is_to_all值为true，可不填写group_id
	 *  );
	 * @return mixed
	 */
	public function sendAllText($accessToken, $content, $options = array()){
		if(empty($accessToken)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token={$accessToken}";
		$data = array(
			'filter' => array(),
			'text' => array(
				'content' => $content,
			),
			'msgtype' => 'text',
		);
		if(!empty($options)){
			if(isset($options['isToAll'])){
				$data['filter']['is_to_all'] = $options['isToAll'];
			}
			if(isset($options['groupId'])){
				$data['filter']['group_id'] = $options['groupId'];
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 根据分组进行群发(语音消息)
	 * @param string $accessToken
	 * @param string 用于群发的消息的media_id,通过基础支持中的上传下载多媒体文件来得到
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'isToAll' => 'xxx',	//用于设定是否向全部用户发送，值为true或false，选择true该消息群发给所有用户，选择false可根据group_id发送给指定群组的用户
	 *  	'groupId' => 'xxx',	//群发到的分组的group_id，参加用户管理中用户分组接口，若is_to_all值为true，可不填写group_id
	 *  );
	 * @return mixed
	 */
	public function sendAllVoice($accessToken, $mediaId, $options = array()){
		if(empty($accessToken) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token={$accessToken}";
		$data = array(
			'filter' => array(),
			'voice' => array(
				'media_id' => $mediaId,
			),
			'msgtype' => 'voice',
		);
		if(!empty($options)){
			if(isset($options['isToAll'])){
				$data['filter']['is_to_all'] = $options['isToAll'];
			}
			if(isset($options['groupId'])){
				$data['filter']['group_id'] = $options['groupId'];
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 根据分组进行群发(图片消息)
	 * @param string $accessToken
	 * @param string 用于群发的消息的media_id,通过基础支持中的上传下载多媒体文件来得到
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'isToAll' => 'xxx',	//用于设定是否向全部用户发送，值为true或false，选择true该消息群发给所有用户，选择false可根据group_id发送给指定群组的用户
	 *  	'groupId' => 'xxx',	//群发到的分组的group_id，参加用户管理中用户分组接口，若is_to_all值为true，可不填写group_id
	 *  );
	 * @return mixed
	 */
	public function sendAllImg($accessToken, $mediaId, $options = array()){
		if(empty($accessToken) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token={$accessToken}";
		$data = array(
			'filter' => array(),
			'image' => array(
				'media_id' => $mediaId,
			),
			'msgtype' => 'image',
		);
		if(!empty($options)){
			if(isset($options['isToAll'])){
				$data['filter']['is_to_all'] = $options['isToAll'];
			}
			if(isset($options['groupId'])){
				$data['filter']['group_id'] = $options['groupId'];
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 上传视频素材
	 * @param  string $accessToken [description]
	 * @param  string $mediaId 需通过基础支持中的上传下载多媒体文件来得到
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'title' => 'xxx',	//消息的标题
	 *  	'description' => 'xxx',	//消息的描述
	 *  );
	 * @return mixed
	 */
	public function uploadMpVideo($accessToken, $mediaId, $options = array()){
		if(empty($accessToken) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://file.api.weixin.qq.com/cgi-bin/media/uploadvideo?access_token={$accessToken}";
		$data = array(
			'media_id' => $mediaId,
		);
		if(!empty($options)){
			if(isset($options['title'])){
				$data['title'] = $options['title'];
			}
			if(isset($options['description'])){
				$data['description'] = $options['description'];
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 根据分组进行群发(视频消息)
	 * @param string $accessToken
	 * @param string $mediaId 用于群发的消息的media_id,通过本类 uploadMpVideo 方法获得
	 * @param array $options 额外项（非必须）
	 *  eg:
	 *  $options = array(
	 *  	'isToAll' => 'xxx',	//用于设定是否向全部用户发送，值为true或false，选择true该消息群发给所有用户，选择false可根据group_id发送给指定群组的用户
	 *  	'groupId' => 'xxx',	//群发到的分组的group_id，参加用户管理中用户分组接口，若is_to_all值为true，可不填写group_id
	 *  );
	 * @return mixed
	 */
	public function sendAllVideo($accessToken, $mediaId, $options = array()){
		if(empty($accessToken) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token={$accessToken}";
		$data = array(
			'filter' => array(),
			'mpvideo' => array(
				'media_id' => $mediaId,
			),
			'msgtype' => 'mpvideo',
		);
		if(!empty($options)){
			if(isset($options['isToAll'])){
				$data['filter']['is_to_all'] = $options['isToAll'];
			}
			if(isset($options['groupId'])){
				$data['filter']['group_id'] = $options['groupId'];
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 删除群发
	 * @param string $accessToken
	 * @param string $msgId
	 * @return mixed
	 */
	public function delMass($accessToken, $msgId){
		if(empty($accessToken) || empty($msgId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/delete?access_token={$accessToken}";
		$data = array('msg_id' => $msgId);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 预览接口,可通过该接口发送消息给指定用户，在手机端查看消息的样式和排版(图文消息)
	 * @param string $accessToken
	 * @param string $openId
	 * @param string 用于群发的消息的media_id,通过上传图文消息素材接口（uploadNews）获得
	 * @return mixed
	 */
	public function previewNews($accessToken, $openId, $mediaId){
		if(empty($accessToken) || empty($openId) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token={$accessToken}";
		$data = array(
			'touser' => $openId,
			'mpnews' => array(
				'media_id' => $mediaId,
			),
			'msgtype' => 'mpnews',
		);
		
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 预览接口,可通过该接口发送消息给指定用户，在手机端查看消息的样式和排版(文本消息)
	 * @param string $accessToken
	 * @param string $openId
	 * @param string $content
	 * @return mixed
	 */
	public function previewText($accessToken, $content){
		if(empty($accessToken) || empty($content)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token={$accessToken}";
		$data = array(
			'touser' => $openId,
			'text' => array(
				'content' => $content,
			),
			'msgtype' => 'text',
		);

		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 预览接口,可通过该接口发送消息给指定用户，在手机端查看消息的样式和排版(语音消息)
	 * @param string $accessToken
	 * @param string $openId
	 * @param string 用于群发的消息的media_id,通过基础支持中的上传下载多媒体文件来得到
	 * @return mixed
	 */
	public function previewVoice($accessToken, $openId, $mediaId){
		if(empty($accessToken) || empty($openId) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token={$accessToken}";
		$data = array(
			'touser' => $openId,
			'voice' => array(
				'media_id' => $mediaId,
			),
			'msgtype' => 'voice',
		);
		if(!empty($options)){
			if(isset($options['isToAll'])){
				$data['filter']['is_to_all'] = $options['isToAll'];
			}
			if(isset($options['groupId'])){
				$data['filter']['group_id'] = $options['groupId'];
			}
		}
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 预览接口,可通过该接口发送消息给指定用户，在手机端查看消息的样式和排版(图片消息)
	 * @param string $accessToken
	 * @param string 用于群发的消息的media_id,通过基础支持中的上传下载多媒体文件来得到
	 * @return mixed
	 */
	public function previewImg($accessToken, $mediaId){
		if(empty($accessToken) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token={$accessToken}";
		$data = array(
			'touser' => $openId,
			'image' => array(
				'media_id' => $mediaId,
			),
			'msgtype' => 'image',
		);

		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 预览接口,可通过该接口发送消息给指定用户，在手机端查看消息的样式和排版(视频消息)
	 * @param string $accessToken
	 * @param string $mediaId 用于群发的消息的media_id,通过本类 uploadMpVideo 方法获得
	 * @return mixed
	 */
	public function previewVideo($accessToken, $mediaId){
		if(empty($accessToken) || empty($mediaId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token={$accessToken}";
		$data = array(
			'touser' => $openId,
			'mpvideo' => array(
				'media_id' => $mediaId,
			),
			'msgtype' => 'mpvideo',
		);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 查询群发消息发送状态
	 * @param  string $accessToken
	 * @param  string $msgId 群发消息后返回的消息id
	 * @return mixed
	 */
	public function getMassStatus($accessToken, $msgId){
		if(empty($accessToken) || empty($msgId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/message/mass/get?access_token={$accessToken}";
		$data = array('msg_id' => $msgId);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 获取群发状态（群发任务结束时，向CallBack Url推送的事件中获得）
	 */
	public function getStatus(){
		if(!isset($this->_data['Status'])){
			return '';
		}
		return $this->_data['Status']['@cdata'];
	}

	/**
	 * 获取group_id下粉丝数；或者openid_list中的粉丝数（群发任务结束时，向CallBack Url推送的事件中获得）
	 */
	public function getTotalCount(){
		if(!isset($this->_data['Status'])){
			return '';
		}
		return $this->_data['Status']['@cdata'];
	}

	/**
	 * 获取过滤（过滤是指特定地区、性别的过滤、用户设置拒收的过滤，用户接收已超4条的过滤）后，准备发送的粉丝数，原则上，FilterCount = SentCount + ErrorCount（群发任务结束时，向CallBack Url推送的事件中获得）
	 */
	public function getFilterCount(){
		if(!isset($this->_data['Status'])){
			return '';
		}
		return $this->_data['Status']['@cdata'];
	}

	/**
	 * 获取发送成功的粉丝数（群发任务结束时，向CallBack Url推送的事件中获得）
	 */
	public function getSentCount(){
		if(!isset($this->_data['Status'])){
			return '';
		}
		return $this->_data['Status']['@cdata'];
	}

	/**
	 * 获取发送失败的粉丝数（群发任务结束时，向CallBack Url推送的事件中获得）
	 */
	public function getErrorCount(){
		if(!isset($this->_data['Status'])){
			return '';
		}
		return $this->_data['Status']['@cdata'];
	}

	/**
	 * 创建自定义菜单
	 * @param  string $accessToken
	 * @param  array $menuArr
	 * @return mixed
	 */
	public function createMenu($accessToken, $menuArr){
		
		if(empty($accessToken) || is_array($menuArr)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$accessToken}";
		$data = array(
			'button' => $menuArr
		);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 自定义菜单查询
	 * @param  string $accessToken
	 * @param  array $menuArr
	 * @return mixed
	 */
	public function getMenu($accessToken){
		
		if(empty($accessToken)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/menu/get?access_token={$accessToken}";
		return $this->httpRequest($url);
	}

	/**
	 * 自定义菜单删除
	 * @param  string $accessToken
	 * @param  array $menuArr
	 * @return mixed
	 */
	public function deleteMenu($accessToken){
		
		if(empty($accessToken)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/menu/delete?access_token={$accessToken}";
		return $this->httpRequest($url);
	}

	/**
	 * 获取扫描信息
	 */
	public function getScanCodeInfo(){
		if(!isset($this->_data['ScanCodeInfo'])){
			return '';
		}
		return $this->_data['ScanCodeInfo'];
	}

	/**
	 * 获取发送的图片信息
	 */
	public function getSendPicsInfo(){
		if(!isset($this->_data['SendPicsInfo'])){
			return '';
		}
		return $this->_data['SendPicsInfo'];
	}

	/**
	 * 获取发送的位置信息
	 */
	public function getSendLocationInfo(){
		if(!isset($this->_data['SendLocationInfo'])){
			return '';
		}
		return $this->_data['SendLocationInfo'];
	}

	/**
	 * 创建用户分组
	 * @param  string $accessToken
	 * @param  string $name 分组名字（30个字符以内）
	 * @return mixed
	 */
	public function creatGroup($accessToken, $name){
		if(empty($accessToken) || empty($name)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/groups/create?access_token={$accessToken}";
		$data = array(
			'group' => array('name' => $name)
		);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 查询所有分组
	 * @param  string $accessToken
	 * @return mixed
	 */
	public function getGroups($accessToken){
		if(empty($accessToken)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/groups/get?access_token={$accessToken}";
		return $this->httpRequest($url, true);
	}

	/**
	 * 查询用户所在分组
	 * @param  string $accessToken
	 * @param  string $openId
	 * @return mixed
	 */
	public function getGroupByOpenId($accessToken, $openId){
		if(empty($accessToken) || empty($openId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/groups/getid?access_token={$accessToken}";
		$data = array(
			'openid' => $openId
		);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 修改分组名
	 * @param  string $accessToken
	 * @param  string $groupId
	 * @param  string $name
	 * @return mixed
	 */
	public function updateGroup($accessToken, $groupId, $name){
		if(empty($accessToken) || empty($groupId) || empty($name)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/groups/update?access_token={$accessToken}";
		$data = array(
			'group' => array(
				'id' => $groupId, 
				'name' => $name,
			),
		);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 移动用户分组
	 * @param  string $accessToken
	 * @param  string $openId
	 * @param  string $toGroupId
	 * @return mixed
	 */
	public function updateMember($accessToken, $openId, $toGroupId){
		if(empty($accessToken) || empty($openId) || empty($toGroupId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/groups/members/update?access_token={$accessToken}";
		$data = array(
			'openid' => $openId,
			'to_groupid' => $toGroupId,
		);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 批量移动用户分组
	 * @param  string $accessToken
	 * @param  array $openIdArr 用户唯一标识符openid的数组（size不能超过50）
	 *  eg: 
	 *  $openIdArr = array (
	 *		0 => 'xxx',
	 *  	1 => 'xxx',
	 *  )
	 * @param  string $toGroupId
	 * @return mixed
	 */
	public function batchUpateMembers($accessToken, $openIdArr, $toGroupId){
		if(empty($accessToken) || empty($openIdArr) || empty($toGroupId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/groups/members/batchupdate?access_token={$accessToken}";
		$data = array(
			'openid_list' => $openIdArr,
			'to_groupid' => $toGroupId,
		);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 设置备注名
	 * @param  string $accessToken
	 * @param  string $openId
	 * @param  string $remark
	 * @return mixed
	 */
	public function updateRemark($accessToken, $openId, $remark){
		if(empty($accessToken) || empty($openId) || empty($remark)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/user/info/updateremark?access_token={$accessToken}";
		$data = array(
			'openid' => $openId,
			'remark' => $remark,
		);
		$post = json_encode($data, JSON_UNESCAPED_UNICODE);
		return $this->httpRequest($url, true, $post);
	}

	/**
	 * 获取用户基本信息（包括UnionID机制）
	 * @param  string $accessToken
	 * @param  string $openId
	 * @return mixed
	 */
	public function getUserInfo($accessToken, $openId, $lang = 'zh_CN'){
		if(empty($accessToken) || empty($openId)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$accessToken}&openid={$openId}&lang={$lang}";
		return $this->httpRequest($url, true);
	}

	/**
	 * 获取用户列表
	 * @param  string $accessToken
	 * @param  string $nextOpenId 第一个拉取的OPENID，不填默认从头开始拉取
	 * @return mixed
	 */
	public function getUserList($accessToken, $nextOpenId = ''){
		if(empty($accessToken)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token={$accessToken}&next_openid={$nextOpenId}";
		return $this->httpRequest($url, true);
	}

	/**
	 * 生成消息返回的xml
	 * @param  array $xmlArr
	 * @return string
	 */
	protected function _createXML($xmlArr){
		$xmlObj = Array2XML::createXML('xml', $xmlArr);
		$xml = $xmlObj->saveXML();
		
		if($this->_isEncrypted){
			$encryptMsg = '';
			$errCode = $this->_cryptObj->encryptMsg($xml, time(), $_GET['nonce'], $encryptMsg);
			if ($errCode != 0) {
				$this->err = ErrorCode::info($errCode);
			}
			$xml = $encryptMsg;
		}
		return $xml;
	}

	/**
	 * 发送http请求
	 * @param  string  $url
	 * @param  boolean $isHttps
	 * @param  string  $post	POST请求的参数
	 */
	public function httpRequest($url, $isHttps = true, $post = ''){
		if(empty($url)){
			throw new \Exception(ErrorCode::info('40035'));
		}

		$ch = curl_init();
		if($isHttps){
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSLVERSION, 1);
		}

		if(!empty($post)){
			curl_setopt($ch, CURLOPT_POST,true);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$post);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		
		$output = curl_exec($ch);
		if(!$output || !curl_errno($ch) == 0){
			$this->err = curl_error($ch);
		}

		curl_close($ch);
		return $output;
	}
	
}