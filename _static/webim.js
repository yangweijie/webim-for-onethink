//custom
(function(webim){
	var path = _IMC.path,
		dir_path = _IMC.dir_path;

	webim.extend(webim.setting.defaults.data, _IMC.setting );

	webim.route( {
		online: path + "/_action/online",
		offline: path + "/_action/offline",
		deactivate: path + "/_action/refresh",
		message: path + "/_action/message",
		presence: path + "/_action/presence",
		status: path + "/_action/status",
		setting: path + "/_action/setting",
		history: path + "/_action/history",
		clear: path + "/_action/clear_history",
		download: path + "/_action/download_history",
		members: path + "/_action/members",
		join: path + "/_action/join",
		leave: path + "/_action/leave",
		buddies: path + "/_action/buddies",
		//upload: path + "static/images/upload.php",
		notifications: path + "/_action/notifications"
	} );

	webim.ui.emot.init({"dir": dir_path + "static/images/emot/default"});
	var soundUrls = {
		lib: dir_path + "static/assets/sound.swf",
		msg: dir_path + "static/assets/sound/msg.mp3"
	};
	var ui = new webim.ui(document.body, {
		imOptions: {
			jsonp: _IMC.jsonp
		},
		soundUrls: soundUrls,
		buddyChatOptions: {
			upload: _IMC.upload
		},
		roomChatOptions: {
			upload: _IMC.upload
		}
	}), im = ui.im;

	if( _IMC.user ) im.setUser( _IMC.user );
	if( _IMC.menu ) ui.addApp("menu", { "data": _IMC.menu } );
	if( _IMC.enable_shortcut ) ui.layout.addShortcut( _IMC.menu );

	ui.addApp("buddy", {
		showUnavailable: _IMC.showUnavailable,
		is_login: _IMC['is_login'],
		disable_login: true,
		loginOptions: _IMC['login_options']
	} );
	if(_IMC.enable_room )ui.addApp("room", { discussion: false });
	if(_IMC.enable_noti )ui.addApp("notification");
	ui.addApp("setting", {"data": webim.setting.defaults.data});
	if( _IMC.enable_chatlink )ui.addApp("chatlink", {
		space_href: [/mod=space&uid=(\d+)/i, /space\-uid\-(\d+)\.html$/i],
		space_class: /xl\sxl2\scl/,
		space_id: null,
		link_wrap: document.getElementById("ct")
	});
	ui.render();
	_IMC['is_login'] && im.autoOnline() && im.online();
})(webim);
