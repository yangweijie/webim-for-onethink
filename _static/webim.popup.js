//custom
(function(webim){
	var path = _IMC.path;
	webim.extend(webim.setting.defaults.data, _IMC.setting );

	webim.route( {
		online: path + "Api/online",
		offline: path + "Api/offline",
		deactivate: path + "Api/refresh",
		message: path + "Api/message",
		presence: path + "Api/presence",
		status: path + "Api/status",
		setting: path + "Api/setting",
		history: path + "Api/history",
		clear: path + "Api/clear_history",
		download: path + "Api/download_history",
		members: path + "Api/members",
		join: path + "Api/join",
		leave: path + "Api/leave",
		buddies: path + "Api/buddies",
		//upload: path + "static/images/upload.php",
		notifications: path + "Api/notifications"
	} );

	webim.ui.emot.init({"dir": path + "static/images/emot/default"});
	var soundUrls = {
		lib: path + "static/assets/sound.swf",
		msg: path + "static/assets/sound/msg.mp3"
	};
	var ui = new webim.ui(document.getElementById("webim_content"), {
		imOptions: {
			jsonp: _IMC.jsonp
		},
		soundUrls: soundUrls,
		layout: "layout.popup",
		layoutOptions: {
			unscalable: true
		},
		buddyChatOptions: {
			simple: true,
			upload: _IMC.upload
		},
		roomChatOptions: {
			simple: true,
			upload: _IMC.upload
		}
	}), im = ui.im;

	if( _IMC.user ) im.setUser( _IMC.user );

	ui.addApp("buddy", {
		is_login: _IMC['is_login'],
		//	loginOptions: _IMC['login_options']
		userOptions: {show: true},
		showUnavailable: _IMC.showUnavailable,
		disable_group: false
	} );

	if(_IMC.enable_room )ui.addApp("room", { discussion: true });
	ui.addApp("setting", {"data": {
		play_sound: webim.setting.defaults.data.play_sound
	}});

	ui.render();
	_IMC['is_login'] && im.autoOnline() && im.online();
})(webim);

