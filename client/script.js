var token,
	username,
	episodes = {},
	casts = {},
	root,
	apiRoot,
	loggedIn = false,
	contextCastId,
	currentEpisodeId = null,
	videoLoading = false;

var Event = {
	Start: 10,
	Pause: 20,
	Play: 30,
	SleepStart: 40,
	SleepEnd: 50,
	EndOfTrack: 60,
	Delete: 70
}

$(document).ready(function() {
	var Router = Backbone.Router.extend({
		routes: {
			"": "podcasts",
			"podcasts": "podcasts",
			"settings": "settings",
			"*any": "podcasts"
		},

		podcasts: function() {
			$(".tab").hide();
			if (!loggedIn) {
				$("#tab-login").show();
			}
			else {
				$("#tab-podcasts").show();
			}
		},

		settings: function() {
			$(".tab").hide();
			if (!loggedIn) {
				$("#tab-login").show();
			}
			else {
				$("#tab-settings").show();
			}
		}
	});

	var path = window.location.pathname;
	root = path.substr(0, path.indexOf("client/") + 7);
	apiRoot = root.replace("client", "api");

	var router = new Router();

	$("a").click(function(e) {
		e.preventDefault();
		var url = e.target.pathname.substr(root.length, e.target.pathname.length - root.length + 1);
		router.navigate(url, { trigger: true });
	});

	$("#vid").on("timeupdate", function() {
		var video = el("vid");
		var date = new Date(video.currentTime * 1000);
		var dateTotal = new Date(video.duration * 1000);
		var progress = 1 / video.duration * video.currentTime;

		date.setHours(date.getHours() - 1);
		dateTotal.setHours(dateTotal.getHours() - 1);
		
		var time = "";
		if (date.getHours() > 0) {
			time += date.getHours().pad() + ":";
		}
		time += date.getMinutes().pad() + ":" + date.getSeconds().pad() + "/";
		if (dateTotal.getHours() > 0) {
			time += dateTotal.getHours().pad() + ":";
		}
		time += dateTotal.getMinutes().pad() + ":" + dateTotal.getSeconds().pad();

		$("#time").html(time);
		$("#seekbar div").css("width", window.innerWidth * progress + "px")
	});

	$("#vid").click(function() {
		playPauseToggle();
	});

	$("#vid").dblclick(function() {
		var video = el("vid-container");
		if ($("#vid-container").hasClass("fs")) {
			document.webkitExitFullscreen();
		}
		else {
			if (video.requestFullscreen) {
				video.requestFullscreen();
			} else if (video.msRequestFullscreen){
				video.msRequestFullscreen();
			} else if (video.mozRequestFullScreen){
				video.mozRequestFullScreen();
			} else if (video.webkitRequestFullscreen){
				video.webkitRequestFullscreen();
			}			
		}
	});

	$(document).on('webkitfullscreenchange mozfullscreenchange fullscreenchange', function() {
		$("#vid-container").toggleClass("fs");
		if ($("#vid-container").hasClass("fs")) {
			$("#playbar").show();
		}
		else {
			if (timer != null) {
				clearTimeout(timer);
			}
			$("#overlay-info").stop().hide();			
		}
	});

	var timer = null;
	$("#vid").mousemove(function() {
		if ($(this).parent().hasClass("fs")) {
			$("#playbar").show();
			$("#overlay-info").fadeIn("fast");
			if (timer != null) {
				clearTimeout(timer);
			}
			timer = setTimeout(function() { 
				$("#playbar").hide();
				$("#overlay-info").stop().fadeOut("fast");
			}, 1000);
		}
	});

	$("#playbar").mouseover(function() {
		if ($(this).parent().hasClass("fs")) {
			if (timer != null) {
				clearTimeout(timer);
			}
		}
	});

	$("#playbar").mouseout(function() {
		if ($(this).parent().hasClass("fs")) {
			timer = setTimeout(function() {
				$("#playbar").hide();
				$("#overlay-info").fadeOut("fast");
			}, 1000);
		}
	});

	$(window).on("beforeunload", function() {
		if (currentEpisodeId != null) {
			if (!el("vid").paused) {
				pushEvent(Event.Play, currentEpisodeId, null);
			}
		}
	});

	$(window).on("unload", function() {
		if (currentEpisodeId != null) {
			if (!el("vid").paused) {
				pushEvent(Event.Play, currentEpisodeId, null);
			}
		}
	});

	$(".button-skipback").click(function() {
		var video = el("vid");
		pushEvent(Event.Pause, currentEpisodeId, 0);
		video.currentTime = video.currentTime - 15;
		pushEvent(Event.Play, currentEpisodeId, 1);
		if (video.paused) {
			pushEvent(Event.Pause, currentEpisodeId, 2);
		}
	});

	$(".button-play").click(function() {
		playPauseToggle();
	});

	$(".button-skipforward").click(function() {
		var video = el("vid");
		pushEvent(Event.Pause, currentEpisodeId, 0);
		video.currentTime = video.currentTime + 15;
		pushEvent(Event.Play, currentEpisodeId, 1);
		if (video.paused) {
			pushEvent(Event.Pause, currentEpisodeId, 2);
		}
	});

	$("#vid").on("canplay", function() {
		var lastevent = episodes[currentEpisodeId].lastevent;
		if (lastevent != null && videoLoading) {
			videoLoading = false;
			el("vid").currentTime = lastevent.positionts;

			if (lastevent.type == Event.Pause) {
				el("vid").pause();
			}
		}
	});

	$("#vid").on("play", function() {
		pushEvent(Event.Play, currentEpisodeId, null);
		$(".button-play").html("||");
	});

	$("#vid").on("pause", function() {
		pushEvent(Event.Pause, currentEpisodeId, null);
		$(".button-play").html(">");
	});

	var seeking = false;
	$("#seekbar").mousedown(function(e) {
		pushEvent(Event.Pause, currentEpisodeId, 0);
		el("vid").currentTime = 1 / window.innerWidth * e.pageX * el("vid").duration;
		seeking = true;
	});

	$(document).mouseup(function() {
		if (seeking) {
			seeking = false;
			pushEvent(Event.Play, currentEpisodeId, 1);
			if (el("vid").paused) {
				pushEvent(Event.Pause, currentEpisodeId, 2);
			}
		}
	});

	$(document).mousemove(function(e) {
		if (seeking) {
			var video = el("vid");
			video.currentTime = 1 / window.innerWidth * e.pageX * video.duration;
			var progress = 1 / video.duration * video.currentTime;
			$("#seekbar div").css("width", window.innerWidth * progress + "px")
		}
	});

	$("#button-login").click(login);

	$("#input-password").keydown(function(e) {
		if (e.which == 13) {
			login();
		}
	});

	$("#button-logout").click(function() {
		$.removeCookie("token");
		$("#playbar").hide();
		$("#topbar nav").hide();
		$("#userinfo").hide();
		$(".tab").hide();
		$("#tab-login").show();
	});

	$("#vmenu-add").click(function() {
		$("#input-vmenu-add").toggle();
		$("#button-vmenu-add").toggle();
		$("#input-vmenu-add").focus();
	});

	$("#button-vmenu-add").click(function() {
		addFeed($("#input-vmenu-add").val());
		$("#input-vmenu-add").val("");
		$("#input-vmenu-add").toggle();
		$("#button-vmenu-add").toggle();
	});

	$("#input-vmenu-add").keydown(function(e) {
		if (e.which == 13) {
			addFeed($("#input-vmenu-add").val());
			$("#input-vmenu-add").val("");
			$("#input-vmenu-add").toggle();
			$("#button-vmenu-add").toggle();
		}
	});

	$("#login-container").css("padding-top", window.innerHeight / 2 - 150 + "px");
	$(window).resize(function() {
		$("#login-container").css("padding-top", window.innerHeight / 2 - 150 + "px");
	});

	$(document).click(function() {
		$("#cast-context-menu").hide();
	});

	$("#volume").mousemove(function(e) {
		var width = window.innerWidth - e.pageX - 200;
		var height = 40 / 150 * width;
		el("vid").volume = width / 150;
		$("#volume-top").css("border-left-width", width+"px");
		$("#volume-top").css("border-top-width", height+"px");
	});

	$("#cast-context-unsub").click(function() {
		$.ajax(apiRoot + "library/casts/" + contextCastId, { 
			type: "DELETE",
			headers: { 
				Authorization: token 
			}, 
			success: function(res) {
				loadCasts();
			}
		});
	});
	
	if ($.cookie("token") != null) {
		token = $.cookie("token");
		finishLogin();

		$("#userinfo span").html($.cookie("username"));

		$("#playbar").show();
		$("#topbar nav").show();
		$("#userinfo").show();
	}

	Backbone.history.start({ pushState: true, root: root });
});

function addFeed(feedurl) {
	$.ajax(apiRoot + "library/casts", { 
		type: "POST",
		data: { 
			feedurl: feedurl 
		}, 
		headers: { 
			Authorization: token 
		}, 
		success: function(res) {
			console.log(res.status);

			loadCasts();
		}
	});
}

function playEpisode(id) {
	if (currentEpisodeId != null) {
		if (!el("vid").paused) {
			pushEvent(Event.Play, currentEpisodeId, null);
		}
	}

	currentEpisodeId = id;

	var video = el("vid");
	video.setAttribute("src", episodes[id].feed.enclosure.url);
	video.load();
	videoLoading = true;

	$("#vid").show();
	$("#episode-title, #overlay-info h2").html(episodes[id].feed.title);
	$("#episode-date").html(new Date(episodes[id].feed.pubDate).toLocaleString());
	$("#episode-desc").html(episodes[id].feed.description);
	$("#overlay-info h5").html(casts[episodes[id].castid].title);
}

function playPauseToggle() {
	var video = el("vid");
	if (video.paused) {
		video.play();
	}
	else {
		video.pause();
	}
}

function login() {
	var username = $("#input-username").val();
	$.post(apiRoot + "account/login", {
		username: username,
		password: $("#input-password").val(),
		clientname: "Castcloud",
		clientdescription: "Best",
		clientversion: "1.0",
		uuid: "1881"
	}, function(res) {
		token = res.token;
		console.log(token);
		if (token != null) {
			$.cookie("token", token);
			$.cookie("username", username);
			
			finishLogin();

			$("#tab-podcasts").fadeIn("fast");
			$("#playbar").slideDown("fast");
			$("#topbar nav").fadeIn("fast");
			$("#userinfo").fadeIn("fast");
		}
	});

	$("#input-username").val("");
	$("#input-password").val("");
}

function finishLogin() {
	loggedIn = true;

	$(".tab").hide();

	loadCasts();
	loadSettings();
}

function pushEvent(type, episodeid, concurrentorder) {
	episodes[episodeid].lastevent = {
		type: type,
		positionts: el("vid").currentTime | 0,
		clientts: unix(),
		clientname: null,
		clientdescription: null
	}

	$.ajax(apiRoot + "library/events", { 
		type: "POST",
		data: { 
			json: [{
				type: type,
				itemid: episodeid,
				positionts: el("vid").currentTime | 0,
				concurrentorder: concurrentorder,
				clientts: unix()				
			}]
		}, 
		headers: { 
			Authorization: token 
		}
	});

	$.cookie("episode-" + episodeid, el("vid").duration, { expires: 1});
}

function loadCasts() {
	$("#podcasts").empty();
	get("library/casts", function(res) {
		var template = _.template($("script.podcasts").html());
		$("#podcasts").append(template({ casts: res }));

		res.forEach(function(cast) {
			var cc = cast.castcloud;
			casts[cc.id] = cast;

			$("#cast-" + cc.id).click(function() {
				get("library/episodes/" + cc.id, function(res) {
					var template = _.template($("script.episodes").html());
					$("#episodes").empty().append(template({ episodes: res }));

					res.forEach(function(episode) {
						$("#ep-" + episode.id).click(function() {
							playEpisode(episode.id);
						});
						episodes[episode.id] = episode;
						if (episode.lastevent != null && $.cookie("episode-" + episode.id) != null) {
							$("#ep-" + episode.id + " .bar").css("width", (episode.lastevent.positionts / $.cookie("episode-" + episode.id) * 100)+"%");
						}
					});

					$(".episode").mouseover(function() {
						$(this).children(".bar").css("background", "#0099cc");
					});

					$(".episode").mouseout(function() {
						$(this).children(".bar").css("background", "#333");
					});
				});
			});
		});

		$(".cast").on("contextmenu", function(e) {
			contextCastId = $(this).prop("id").split("-")[1];

			$("#cast-context-menu").css("left", e.pageX + "px");
			$("#cast-context-menu").css("top", e.pageY + "px");
			$("#cast-context-menu").show();
			e.preventDefault();
		});
	});
}

function loadSettings() {
	get("account/settings", function(res) {
		var settings = {};
		for (var key in res) {
			var category = key.split("/")[0];
			var setting = key.split("/")[1];
			if (setting == null) {
				setting = category;
				category = "General";
			}
			if (settings[category] == null) {
				settings[category] = {};
			}
			settings[category][setting] = res[key];
			
		}
		for (var c in settings) {
			$("#tab-settings").append($("<h2>").text(c));
			for (var s in settings[c]) {
				$("#tab-settings").append("<p><label>" + s + "</label><input type=\"text\"></p>");
			}
		}
	});
}

function get(url, cb) {
	$.ajax(apiRoot + url, { headers: { Authorization: token }, success: cb});
}

function post(url, cb) {
	$.ajax(apiRoot + url, { type: "POST", headers: { Authorization: token }, success: cb});
}

function el(id) {
	return $("#" + id).get(0);
}

function unix() {
	return $.now() / 1000 | 0;
}

Number.prototype.pad = function() {
	var str = this.toString();
	while (str.length < 2) {
		str = "0" + str;
	}
	return str;
}