<?php

namespace Addons\Webim\Controller;
use Home\Controller\AddonsController;
use Addons\Webim\Lib\HttpClient;
use Addons\Webim\Lib\ThinkIM;
use Addons\Webim\Lib\WebimClient;
use Addons\Webim\Lib\WebimDB;

class WebimController extends AddonsController{

	/*
	 * Webim db
	 */
	private $db;

	/*
	 * Webim Ticket
	 */
	private $ticket;

	/*
	 * Webim Client
	 */
	private $client;

	/*
	 * 与ThinkPHP接口类实例
	 */
	private $thinkim;

	private $settingModel;

	private $historyModel;

	function _initialize() {
		define('WEBIM_PATH', ONETHINK_ADDON_PATH.'Webim/_static/');
		define('WEBIMDB_CHARSET', 'utf8');
		define('WEBIM_PRODUCT_NAME', 'thinkphp');
		$webim_config = get_addon_config('Webim');
		$webim_config = array_merge(array(
			'VERSION'=>'5.0',
			'ENABLE'=>true,//开启webim
			'DOMAIN' 	=> 'localhost',	//消息服务器通信域名
			'APIKEY'	=> 'public',	//消息服务器通信APIKEY
			'HOST'		=> 'nextalk.im',//im服务器
			'PORT'		=> 8000,		//服务端接口端口
			'THEME'		=> 'base',		//界面主题，根据webim/static/themes/目录内容选择
			'LOCAL'		=> 'zh-CN',		//本地语言，扩展请修改webim/static/i18n/内容
			'EMOT'		=> 'default',	//表情主题
			'OPACITY'	=> 80,			//TOOLBAR背景透明度设置
			'VISITOR'	=> 'true', 		//支持访客聊天(默认好友为站长),开启后通过im登录无效
			'SHOW_REALNAME'		=> 'false',	//是否显示好友真实姓名
			'SHOW_UNAVAILABLE'	=> 'false', //支持显示不在线用户
			'ENABLE_UPLOAD'		=> 'false',	//是否支持文件(图片)上传
			'ENABLE_LOGIN'		=> 'false',	//允许未登录时显示IM，并可从im登录
			'ENABLE_MENU'		=> 'false',		//隐藏工具条
			'ENABLE_ROOM'		=> 'true',		//禁止群组聊天
			'ENABLE_NOTI'		=> 'true',		//禁止通知
			'ENABLE_CHATLINK'	=> 'true',	//禁止页面名字旁边的聊天链接
			'ENABLE_SHORTCUT'	=> 'false',	//支持工具栏快捷方式
		), array_change_key_case($webim_config, CASE_UPPER));
		C('IMC', $webim_config);
		$imc = C('IMC');
		//IM DB
		$imdb = new WebimDB(C('DB_USER'), C('DB_PWD'), C('DB_NAME'), C('DB_HOST'));
		$imdb->set_prefix(C('DB_PREFIX'));
		$imdb->add_tables(array('settings', 'histories'));
		$this->db = $imdb;

		//IM Ticket
		$imticket = I('ticket');
		if($imticket) $imticket = stripslashes($imticket);
		$this->ticket = $imticket;

		//Initialize ThinkIM
		$this->thinkim = new ThinkIM();

		//IM Client
		$this->client = new WebimClient($this->thinkim->user(),
			$this->ticket, $imc['DOMAIN'], $imc['APIKEY'], $imc['HOST'], $imc['PORT']);

		//IM Models
		$this->settingModel = D("Addons://Webim/WebimSetting");
		$this->historyModel = D("Addons://Webim/WebimHistory");
	}

	public function run() {
		$imc = C('IMC');
		$webim_path = WEBIM_PATH;
		C('URL_HTML_SUFFIX','');
		$url = addons_url('Webim://Webim/');
		$setting = json_encode($this->settingModel->get($this->thinkim->uid()));
		$imuser = json_encode($this->thinkim->user());
		//TODO: FIXME Later
		$script = <<<EOF
var _IMC = {
	production_name: 'thinkphp',
	version: '5.0',
	path: '$url',
	dir_path: '$webim_path',
	is_login: '1',
	login_options: {},
	user: $imuser,
	setting: $setting,
	enable_chatlink: {$imc['ENABLE_CHATLINK']},
	enable_shortcut: false,
	enable_menu: {$imc['ENABLE_MENU']},
	enable_room: {$imc['ENABLE_ROOM']},
	enable_noti: {$imc['ENABLE_NOTI']},
	theme: "{$imc['THEME']}",
	local: "{$imc['LOCAL']}",
	showUnavailable: {$imc['SHOW_UNAVAILABLE']},
	min: window.location.href.indexOf("webim_debug") != -1 ? "" : ".min"
};
_IMC.script = window.webim ? '' : ('<link href="' + _IMC.dir_path + 'static/webim.' + _IMC.production_name + _IMC.min + '.css?' + _IMC.version + '" media="all" type="text/css" rel="stylesheet"/><link href="' + _IMC.dir_path + 'static/themes/' + _IMC.theme + '/jquery.ui.theme.css?' + _IMC.version + '" media="all" type="text/css" rel="stylesheet"/><script src="' + _IMC.dir_path + 'static/webim.' + _IMC.production_name + _IMC.min + '.js?' + _IMC.version + '" type="text/javascript"></script><script src="' + _IMC.dir_path + 'static/i18n/webim-' + _IMC.local + '.js?' + _IMC.version + '" type="text/javascript"></script>');
_IMC.script += '<script src="' + _IMC.dir_path + 'webim.js?' + _IMC.version + '" type="text/javascript"></script>';
document.write( _IMC.script );

EOF;
		header("Content-type: application/javascript");
		header("Cache-Control: no-cache");
		exit($script);
	}

	public function clearHistory() {
		if($_POST) {
		    switch( $_POST['ago'] ) {
			case 'weekago':
				$ago = 7*24*60*60;break;
			case 'monthago':
				$ago = 30*24*60*60;break;
			case '3monthago':
				$ago = 3*30*24*60*60;break;
			default:
				$ago = 0;
			}
			$ago = ( time() - $ago ) * 1000;

			$db_prefix = C('DB_PREFIX');
			$sql = "DELETE FROM `{$db_prefix}webim_histories` WHERE `timestamp` < {$ago}";
		    D()->execute($sql);
		    $this->success('清除成功: ' . $sql);
	    }
	}

	public function online() {
		$IMC = C('IMC');
		$domain = I("domain");
		if ( !$this->thinkim->logined() ) {
			$this->ajaxReturn(array(
				"success" => false,
				"error_msg" => "Forbidden" ),
				'JSON');
		}
		$im_buddies = array(); //For online.
		$im_rooms = array(); //For online.
		$strangers = $this->idsArray( I('stranger_ids') );
		$cache_buddies = array();//For find.
		$cache_rooms = array();//For find.

		$active_buddies = $this->idsArray( I('buddy_ids') );
		$active_rooms = $this->idsArray( I('room_ids') );

		$new_messages = $this->historyModel->getOffline($this->thinkim->uid());
		$online_buddies = $this->thinkim->buddies();

		$buddies_with_info = array();//Buddy with info.
		//Active buddy who send a new message.
		$count = count($new_messages);
		for($i = 0; $i < $count; $i++){
			$active_buddies[] = $new_messages[$i]->from;
		}

		//Find im_buddies
		$all_buddies = array();
		foreach($online_buddies as $k => $v){
			$id = $v->id;
			$im_buddies[] = $id;
			$buddies_with_info[] = $id;
			$v->presence = "offline";
			$v->show = "unavailable";
			$cache_buddies[$id] = $v;
			$all_buddies[] = $id;
		}

		//Get active buddies info.
		$buddies_without_info = array();
		foreach($active_buddies as $k => $v){
			if(!in_array($v, $buddies_with_info)){
				$buddies_without_info[] = $v;
			}
		}
		if(!empty($buddies_without_info) || !empty($strangers)){
			//FIXME
			$bb = $this->thinkim->buddiesByIds(implode(",", $buddies_without_info), implode(",", $strangers));
			foreach( $bb as $k => $v){
				$id = $v->id;
				$im_buddies[] = $id;
				$v->presence = "offline";
				$v->show = "unavailable";
				$cache_buddies[$id] = $v;
			}
		}
		if(!$IMC['enable_room']){
			$rooms = $this->thinkim->rooms();
			$setting = $this->settingModel->get($this->thinkim->uid());
			$blocked_rooms = $setting && is_array($setting->blocked_rooms) ? $setting->blocked_rooms : array();
			//Find im_rooms
			//Except blocked.
			foreach($rooms as $k => $v){
				$id = $v->id;
				if(in_array($id, $blocked_rooms)){
					$v->blocked = true;
				}else{
					$v->blocked = false;
					$im_rooms[] = $id;
				}
				$cache_rooms[$id] = $v;
			}
			//Add temporary rooms
			$temp_rooms = $setting && is_array($setting->temporary_rooms) ? $setting->temporary_rooms : array();
			for ($i = 0; $i < count($temp_rooms); $i++) {
				$rr = $temp_rooms[$i];
				$rr->temporary = true;
				$rr->pic_url = (WEBIM_PATH . "static/images/chat.png");
				$rooms[] = $rr;
				$im_rooms[] = $rr->id;
				$cache_rooms[$rr->id] = $rr;
			}
		}else{
			$rooms = array();
		}

		//===============Online===============
		//
		trace($im_buddies);
		trace($im_rooms);
		$data = $this->client->online( implode(",", array_unique( $im_buddies ) ), implode(",", array_unique( $im_rooms ) ) );

		if( $data->success ){
			$data->new_messages = $new_messages;

			if(!$IMC['enable_room']){
				//Add room online member count.
				foreach ($data->rooms as $k => $v) {
					$id = $v->id;
					$cache_rooms[$id]->count = $v->count;
				}
				//Show all rooms.
			}
			$data->rooms = $rooms;

			$show_buddies = array();//For output.
			foreach($data->buddies as $k => $v){
				$id = $v->id;
				if(!isset($cache_buddies[$id])){
					$cache_buddies[$id] = (object)array(
						"id" => $id,
						"nick" => $id,
						"incomplete" => true,
					);
				}
				$b = $cache_buddies[$id];
				$b->presence = $v->presence;
				$b->show = $v->show;
				if( !empty($v->nick) )
					$b->nick = $v->nick;
				if( !empty($v->status) )
					$b->status = $v->status;
				#show online buddy
				$show_buddies[] = $id;
			}
			#show active buddy
			$show_buddies = array_unique(array_merge($show_buddies, $active_buddies, $all_buddies));
			$o = array();
			foreach($show_buddies as $id){
				//Some user maybe not exist.
				if(isset($cache_buddies[$id])){
					$o[] = $cache_buddies[$id];
				}
			}

			//Provide history for active buddies and rooms
			foreach($active_buddies as $id){
				if(isset($cache_buddies[$id])){
					$cache_buddies[$id]->history = $this->historyModel->get($id, "chat" );
				}
			}
			foreach($active_rooms as $id){
				if(isset($cache_rooms[$id])){
					$cache_rooms[$id]->history = $this->historyModel->get($id, "grpchat" );
				}
			}

			$show_buddies = $o;
			$data->buddies = $show_buddies;
			$this->historyModel->offlineReaded($this->thinkim->uid());
			$this->ajaxReturn($data, 'JSON');
		} else {
			$this->ajaxReturn(array(
				"success" => false,
				"error_msg" => empty( $data->error_msg ) ? "IM Server Not Found" : "IM Server Not Authorized",
				"im_error_msg" => $data->error_msg), 'JSON');
		}
	}

	public function offline() {
		$this->client->offline();
		$this->okReturn();
	}

	public function message() {
		$type = I("type");
		$offline = I("offline");
		$to = I("to");
		$body = I("body");
		$style = I("style");
		$send = $offline == "true" || $offline == "1" ? 0 : 1;
		$timestamp = $this->microtimeFloat() * 1000;
		if( strpos($body, "webim-event:") !== 0 ) {
			$this->historyModel->insert($this->thinkim->user(), array(
				"send" => $send,
				"type" => $type,
				"to" => $to,
				"body" => $body,
				"style" => $style,
				"timestamp" => $timestamp,
			));
		}
		if($send == 1){
			$this->client->message($type, $to, $body, $style, $timestamp);
		}
		$this->okReturn();
	}

	public function presence() {
		$show = I('show');
		$status = I('status');
		$this->client->presence($show, $status);
		$this->okReturn();
	}

	public function history() {
		$uid = $this->thinkim->uid();
		$with = I('id');
		$type = I('type');
		$histories = $this->historyModel->get($uid, $with, $type);
		$this->ajaxReturn($histories, "JSON");
	}

	public function status() {
		$to = I("to");
		$show = I("show");
		$this->client->status($to, $show);
		$this->okReturn();
	}

	public function members() {
		$id = I('id');
		$re = $this->client->members( $id );
		if($re) {
			$this->ajaxReturn($re, "JSON");
		} else {
			$this->ajaxReturn("Not Found", "JSON");
		}
	}

	public function join() {
		$id = I('id');
		$room = $this->thinkim->roomsByIds( $id );
		if( $room && count($room) ) {
			$room = $room[0];
		} else {
			$room = (object)array(
				"id" => $id,
				"nick" => I('nick'),
				"temporary" => true,
				"pic_url" => (WEBIM_PATH . "static/images/chat.png"),
			);
		}
		if($room){
			$re = $this->client->join($id);
			if($re){
				$room->count = $re->count;
				$this->ajaxReturn($room, "JSON");
			}else{
				header("HTTP/1.0 404 Not Found");
				exit("Can't join this room right now");
			}
		}else{
			header("HTTP/1.0 404 Not Found");
			exit("Can't found this room");
		}
	}

	public function leave() {
		$id = I('id');
		$this->client->leave( $id );
		$this->okReturn();
	}

	public function buddies() {
		$ids = I('ids');
		$this->ajaxReturn($this->thinkim->buddiesByIds($ids), 'JSON');
	}

	public function rooms() {
		$ids = I("ids");
		$this->ajaxReturn($this->thinkim->roomsByIds($ids), "JSON");
	}

	public function refresh() {
		$this->client->offline();
		$this->okReturn();
	}

	public function clear_history() {
		$id = I('id'); //$with
		$this->historyModel->clear($this->thinkim->uid(), $id);
		$this->okReturn();
	}

	public function download_history() {
		$id = trim($_GET['id']);
		$type = trim($_GET['type']);
		$with = I('with', $id);
		$histories = $this->historyModel->get($id, $with, $type, 1000 );
		$date = date( 'Y-m-d' );
		if($this->_param['date']) {
			$date = I('date');
		}
		header('Content-Disposition: attachment; filename="histories-'.$date.'.html"');
		$this->assign('date', $date);
		$this->assign('histories', $histories);
		exit($this->fetch(ONETHINK_ADDON_PATH.'Webim/View/download_history.html'));
	}

	public function setting() {
		if(isset($_GET['data'])) {
			$data = $_GET['data'];
		}
		if(isset($_POST['data'])) {
			$data = $_POST['data'];
		}
		$uid = $this->thinkim->uid();
		$this->settingModel->set($uid, $data);
		$this->okReturn();
	}

	public function notifications() {
		$notifications = $this->thinkim->notifications();
		$this->ajaxReturn($notifications, 'JSON');
	}

	public function openchat() {
		$grpid = $this->_param['group_id'];
		$nick = $this->param['nick'];
		$this->ajaxReturn($this->client->openchat($grpid, $nick), 'JSON');
	}

	public function closechat() {
		$grpid = $this->_param['group_id'];
		$buddy_id = $this->_param['buddy_id'];
		$this->ajaxReturn($this->client->closechat($grpid, $buddy_id), "JSON");
	}

	private function okReturn() {
		$this->ajaxReturn('ok', 'JSON');
	}

	private function idsArray( $ids ){
		return ($ids===NULL || $ids==="") ? array() : (is_array($ids) ? array_unique($ids) : array_unique(explode(",", $ids)));
	}

	private function microtimeFloat() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
}
