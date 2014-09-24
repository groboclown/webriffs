// REFERENCE:
// https://developers.google.com/youtube/js_api_reference

// TODO eventually, replace this with a Dart library.

var youtube = {
    // The YouTube player object.
    player: null,

    // List of functions that will be called when the video player is ready.
    onPlayerReady: [],

    // List of functions that will be called when the player's state changes.
    onPlayerStateChange: [],
    
    // List of functions that will be called when the player encounters an error
    // Argument:
    // 2 - The request contains an invalid parameter value. For example, this error occurs if you specify a video ID that does not have 11 characters, or if the video ID contains invalid characters, such as exclamation points or asterisks.
    // 5 - The requested content cannot be played in an HTML5 player or another error related to the HTML5 player has occurred.
    // 100 - The video requested was not found. This error occurs when a video has been removed (for any reason) or has been marked as private.
    // 101 - The owner of the requested video does not allow it to be played in embedded players.
    onError: [],

    lastPlayerState: null,
    
    // -2: not loaded
    // -1: unstarted
    // 0: ended
    // 1: playing
    // 2: paused
    // 3: buffering
    // 4: video cued
    getPlayerState: function() {
        if (youtube.player == null) {
            return -2;
        }
        return youtube.player.getPlayerState();
    },
    
    /**
     * Current time in seconds since the video started playing (float)
     */
    getPlaybackSeconds: function() {
        if (youtube.player == null) {
            return 0.0;
        }
        return youtube.player.getCurrentTime();
    },
    
    getDuration: function() {
        if (youtube.player == null) {
            return -1.0;
        }
        return youtube.player.getDuration();
    },
    
    stop: function() {
        youtube.player.stopVideo();
    },
    
    pause: function() {
        youtube.player.pauseVideo();
    },
    
    play: function() {
        youtube.player.playVideo();
    },
    
    seekTo: function(seconds) {
        youtube.player.seekTo(seconds, true);
    },
    
    getBufferedFraction: function() {
        return youtube.player.getVideoLoadedFraction();
    },
    
    videoId: null,
    
    setVideoId: function(id) {
        console.log("set the video id: " + id);
        youtube._validateState();
        
        //if (youtube.videoId !== null) {
        //    console.log(" - the old video id was " + youtube.videoId);
        //}
        youtube.videoId = id;
        if (youtube.isLoaded) {
            if (youtube.isReady) {
                console.log("Set the video id in the loaded player");
                youtube.player.loadVideoById(id);
            } else {
                throw new Exception("video id = " + id +
                        "; the player is loaded, but not ready.");
            }
        } else if (! youtube._initialized) {
            console.log("loading the player with video id " + id);
            
            youtube._initialized = true;
            
            // TODO allow the element to be set by the caller.
            var obj = document.getElementById("media_container");
            if (obj === null) {
                throw new Exception("No such element media_container");
            }
            
            var params = { allowScriptAccess: "always" };
            var atts = { id: "my_youtube_player" };
            var url = "http://www.youtube.com/v/" +
                id  + "?enablejsapi=1&playerapiid=ytplayer&version=3";
            console.log("Youtube player url: " + url);
            // TODO use customized version of swfobject that allows us to
            // directly embed the media container div.
            swfobject.embedSWF(url, "media_container",
                // width, height
                "425", "356",
                "8", null, null, params, atts);
        } else {
            console.log("video id = " + id +
                "; the player is not loaded but is initialized; the video id was probably set too quickly.  Ignoring.");
        }
    },
    
    name: "YouTube Media Player",
    
    isReady: false,
    isLoaded: false,
    
    // private initialization stuff.
    _initialized: false,
    _onReady: function(playerId) {
        youtube.isReady = true;
        for (var i = 0; i < youtube.onPlayerReady.length; ++i) {
            youtube.onPlayerReady[i](playerId);
        }
    },
    _onStateChange: function(newState) {
        youtube.lastPlayerState = newState;
        for (var i = 0; i < youtube.onPlayerStateChange.length; ++i) {
            youtube.onPlayerStateChange[i](newState);
        }
    },
    _onError: function(errcode) {
        if (errcode == 150) {
            errcode = 101;
        }
        for (var i = 0; i < youtube.onError.length; ++i) {
            youtube.onError[i](errcode);
        }
    },
    _validateState: function() {
        if (youtube.player === null) {
            youtube.isReady = false;
            youtube.isLoaded = false;
            youtube._initialized = false;
        } else if (! youtube.player['loadVideoById']) {
            console.log("Out-of-date version of the player");
            youtube.isReady = false;
            youtube.isLoaded = false;
            youtube.player = null;
            youtube._initialized = false;
        }
    }
};
media_config = youtube;


function onYouTubePlayerReady(playerId) {
    console.log("onYouTubePlayerReady");
    youtube.isLoaded = true;
    youtube.player = document.getElementById("my_youtube_player");
    youtube.player.addEventListener("onStateChange", "onYouTubeStateChange");
    // onPlaybackQualityChange
    // onPlaybackRateChange
    youtube.player.addEventListener("onError", "onYouTubeError");
    // onApiChange
    youtube._onReady(playerId);
}


function onYouTubeStateChange(newState) {
    youtube._onStateChange(newState);
}

function onYouTubeError(errcode) {
    youtube._onError(errcode);
}
