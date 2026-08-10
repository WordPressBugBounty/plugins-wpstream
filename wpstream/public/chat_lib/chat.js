/*
 * WPStream live-chat client.
 *
 * First-party front-end for the WPStream live-chat widget. It opens a
 * WebSocket to the chat server, keeps the connection alive with pings,
 * renders incoming messages/roster/system events into the DOM, handles the
 * local user's outgoing messages plus a set of slash commands (/pm, /ban,
 * /role, /kick, /me, /name, /clear, /help, ...), and transparently
 * reconnects when the socket drops. Optional extras: emoji shortcodes,
 * greentext, inline image/link expansion, desktop notifications, sound,
 * and speech synthesis/recognition.
 *
 * Depends on jQuery (+ jQuery UI autocomplete), emojione, linkify, and a
 * localized `wpstream_chat_vars` object supplied by the PHP that enqueues this
 * file. State is kept in module-level globals declared just below.
 */

/* Variables */
var user;                    // Our own client id, set from the 'user' server event.
var timer;                   // setTimeout handle for the reconnect attempt.
var ping;                    // setInterval handle for the keep-alive ping; cleared on close.
var lastChatUri;             // Last chat URI passed to connect(), reused on auto-reconnect.
var socket;                  // The active WebSocket instance.
var socketGeneration = 0;    // Monotonic owner id; stale socket callbacks must not mutate active state.
var oldname;                 // Previous username, remembered across a /name change.
var username;                // The local user's current display name.
var typeTimer;               // setTimeout handle that clears the "typing" flag.
var clients = [];            // Roster of connected clients, keyed by client id.
var usersTyping = [];        // Names of remote users currently typing.
var nmr = 0;                 // Running count of rendered messages (index into .msg nodes).
var dev = true;              // Dev flag (currently unused by the runtime logic).
var key=null;               // Auth/session key sent with the update handshake.
var unread = 0;              // Unread message counter shown while the window is blurred.
var focus = true;            // Whether the browser window currently has focus.
var typing = false;          // Whether we have announced ourselves as typing.
var connected = false;       // Whether the chat handshake has succeeded.
var version = '1.0.6';       // Client version string.

var blop = '';               // Audio element for the notification sound (assigned elsewhere).
var regex = /(&zwj;|&nbsp;)/g; // Matches zero-width-joiner / non-breaking-space entities to strip from input.

// User-facing preferences, persisted to localStorage under `settings`.
var settings = {
    'name': null,            // Remembered display name.
    'emoji': true,           // Convert :shortcodes: to emoji images.
    'greentext': true,       // Colour messages that start with '>' green.
    'inline': true,          // Expand image/gif links inline.
    'sound': true,           // Play a sound on new global/pm/mention messages.
    'desktop': false,        // Show desktop notifications.
    'synthesis': false,      // Read incoming messages aloud via speech synthesis.
    'recognition': false     // Enable speech-to-text input.
};


/* Config */
emojione.ascii = true;       // Also convert ascii smileys like :) to emoji.
emojione.imageType = 'png';  // Render emoji as PNG images.
emojione.unicodeAlt = false; // Do not put the raw unicode char in the img alt attribute.

/**
 * Close the chat WebSocket from outside this module.
 *
 * Exposed as a global so the surrounding page (e.g. when navigating away or
 * when the stream ends) can tear down the connection.
 */
var wpstream_close_socket=function(){
     socket.close();          // Trigger socket.onclose (which may schedule a reconnect if `connected`).
};
/* Connection */
/**
 * Open (or re-open) the WebSocket connection to the chat server.
 *
 * Validates the chat URI and login state, constructs the socket, and wires
 * up the open/close/message lifecycle handlers. On reconnects this is called
 * with no argument (see socket.onclose), which is why the guards below treat
 * an empty/undefined URI as a rejection.
 *
 * @param {string} chat_uri WebSocket URL of the chat server for this stream.
 */
var connect = function(chat_uri) {
    var reject=false;        // Set true if any precondition fails; short-circuits the connect.
    var message;             // Local notice text (declared so it never leaks a global).

    // Normalize the chat URI. A no-argument call is the auto-reconnect path
    // (socket.onclose) and reuses the last good URI; an explicit blank URI is the
    // "not live" signal and must NOT overwrite the remembered one — otherwise a
    // single not-live status poll would poison lastChatUri and kill reconnect.
    if(chat_uri===undefined){
        chat_uri=lastChatUri;
    } else if(chat_uri && chat_uri!=='undefined'){
        lastChatUri=chat_uri;
    }

    // No live channel to connect to (empty, null, or the string 'undefined'):
    // show the "not live" notice and bail.
    if(!chat_uri || chat_uri==='undefined'){
        message=wpstream_chat_vars.we_are_not_live;
        //showChat('light', null, message);
        reject=true;
    }

    // Anonymous visitor: chat requires a logged-in username.
    if(username===''){
        message="Please login if you want to use the chat.";
        showChat('light', null, message);
        reject=true;
    }

    // Only a valid connection attempt supersedes the active socket. Cancel any
    // scheduled reconnect now so an explicit replacement cannot create a second
    // socket when the old timer fires.
    if(reject===true){
        return;
    }
    clearTimeout(timer);
    timer = undefined;
    var previousSocket = socket;
    var connectionGeneration = ++socketGeneration;
    clearInterval(ping);
    ping = undefined;

    var protocol;
    protocol = 'wss://';     // Chat always uses secure WebSockets (value not otherwise consumed).
    // Build the socket and surface connection errors as a system notice. The
    // guards above already rejected every empty/blank URI, so reaching here with
    // reject===false means chat_uri is a usable string.
    var activeSocket = new WebSocket(chat_uri);
    socket = activeSocket;

    // Retire the previous transport after advancing the generation. Its
    // eventual close callback will see that it is stale and do cleanup only.
    if(previousSocket && previousSocket !== activeSocket && previousSocket.readyState < 2) {
        previousSocket.close();
    }

    activeSocket.addEventListener('error', function (event) {
        if(connectionGeneration !== socketGeneration) {
            return;
        }
        //message="We are not live at this moment.Please check back later.";
        message=wpstream_chat_vars.we_are_not_live;
        showChat('light', null, message);
        reject=true;
    });
    
    

    // Fallback error logger (note: the real error UI is wired via addEventListener above).
    activeSocket.error =function(){
      console.log('socket error');
    };

    // Socket opened: start the keep-alive ping loop and send the update handshake.
    var socketPing;
    activeSocket.onopen = function() {
        if(connectionGeneration !== socketGeneration) {
            activeSocket.close();
            return;
        }
        // Ping every 50s so the server does not consider us idle and drop the socket.
        // Stored on the module-level `ping` so onclose can clear it — otherwise a
        // new interval would accumulate on every reconnect.
        clearInterval(socketPing);
        socketPing = setInterval(function(){
            if(activeSocket.readyState === 1) {
                activeSocket.send(JSON.stringify({type: 'ping'}));
            }
        }, 50 * 1000);
        ping = socketPing;

        updateInfo(activeSocket); // Send our username/key to (re)join the chat.
    };

    // Socket closed: reset local state and, if we were connected, reconnect.
    activeSocket.onclose = function() {
        clearInterval(socketPing); // Stop only this socket's keep-alive ping.
        if(ping === socketPing) {
            ping = undefined;
        }

        // A replaced socket may close after its successor is already active. It
        // must not reset the roster/UI or schedule another reconnect.
        if(connectionGeneration !== socketGeneration || socket !== activeSocket) {
            return;
        }
        clearTimeout(typeTimer);  // Stop any pending "stopped typing" notification.
        jQuery('#admin').hide();  // Hide moderator controls until we re-authenticate.
        typing = false;           // We are no longer typing.
        clients = [];             // Clear the roster; it will be resent on reconnect.

        // Only auto-reconnect if we had a working session (avoids looping on hard rejections).
        if(connected) {
            updateBar('mdi-action-autorenew spin', 'Connection lost, reconnecting...', true);

            // Wait 1.5s, then reconnect. connect() with no argument reuses the
            // last-known chat URI (see the URI normalization at the top).
            timer = setTimeout(function() {
                if(connectionGeneration !== socketGeneration) {
                    return;
                }
                timer = undefined;
                console.warn('Connection lost, reconnecting...');
                connect();
            }, 1500);
        }
    };

    /**
     * Central handler for every frame the server pushes down the socket.
     *
     * Parses the JSON payload and dispatches on `data.type`: message deletion,
     * typing indicators, `server` control events (join/leave/roster/errors),
     * kick/ban, role changes, and ordinary chat messages.
     *
     * @param {MessageEvent} e Raw WebSocket message; e.data is a JSON string.
     */
    activeSocket.onmessage = function(e) {
        if(connectionGeneration !== socketGeneration || socket !== activeSocket) {
            return;
        }
        var data = JSON.parse(e.data);  // Decode the frame into a plain object.


        // Deletion event: remove the message DOM node matching the given mid.
        if(data.type == 'delete') {
            return getChatRowsByMid(data.message).remove();
        }

        // Typing event: maintain the list of remote users typing and render the label.
        if(data.type == 'typing') {
            var string;
            // Track other users' typing state (ignore echoes of our own name).
            if(data.user != username) {
                if(data.typing) {
                    usersTyping.push(data.user);          // Add user who started typing.
                } else {
                    usersTyping.splice(usersTyping.indexOf(data.user), 1); // Remove who stopped.
                }
            }


            // Compose the "X is writing..." label depending on how many are typing.
            if(usersTyping.length == 1) {
                string = usersTyping + ' is writing...';           // Single user.
            } else if(usersTyping.length > 4) {
                string = 'Several people are writing...';           // Too many to list.
            } else if(usersTyping.length > 1) {
                // 2-4 users: join as "a, b and c are writing..." without mutating the array.
                var lastUser = usersTyping.pop();
                string = usersTyping.join(', ') + ' and ' + lastUser + ' are writing...';
                usersTyping.push(lastUser);
            } else {
                string = '<br>';                                    // Nobody typing: keep line height.
            }

            // Paint the label into the typing indicator element and stop. The
            // '<br>' spacer is a hardcoded constant so it may go through innerHTML;
            // every other value contains remote usernames and is set as text.
            var typingEl = document.getElementById('typing');
            if(string === '<br>') {
                typingEl.innerHTML = '<br>';
            } else {
                typingEl.textContent = string;
            }
            return;
        }

        // Server control events: connection handshake results and roster changes.
        if(data.type == 'server') {
            switch(data.info) {
                case 'rejected':
                    // The server refused our username/join; explain why.
                    var message;

                    // Username too short/long.
                    if(data.reason == 'length') {
                        message = 'Your username must have at least 3 characters and no more than 16 characters';
                    }

                    // Username contains disallowed characters.
                    if(data.reason == 'format') {
                        message = 'Your username must only contain alphanumeric characters (numbers, letters and underscores)';
                    }

                    // Username already in use by another client.
                    if(data.reason == 'taken') {
                        message = 'This username is already taken';
                    }

                    // We are banned; convert the ban duration from ms to minutes for the notice.
                    if(data.reason == 'banned') {
                        message = 'You have been banned from the server for ' + data.time / 60 / 1000 + ' minutes. You have to wait until you get unbanned to be able to connect again';
                    }

                    showChat('light', null, message);  // Show the rejection reason.

                    // If the server says not to keep the session, forget the username; otherwise
                    // restore the previous name (e.g. a rejected /name change reverts to oldname).
                    if(!data.keep) {
                        username = undefined;
                        connected = false;
                    } else {
                        username = oldname;
                    }
                    break;

                case 'success':
                    // Join accepted: switch the UI into the connected/ready state and persist settings.
                    document.getElementById('send').childNodes[0].nodeValue = 'Send';
                    updateBar('mdi-content-send', 'Enter your message here', false);
                    connected = true;
                    settings.name = username;
                    localStorage.settings = JSON.stringify(settings);
                    break;

                case 'update':
                    // A user renamed themselves: announce it and refresh their roster entry.
                    showChat('info', null, data.user.oldun + ' changed its name to ' + data.user.un);
                    clients[data.user.id] = data.user;
                    break;

                case 'connection':
                    // Another user connected: optionally show their IP, announce, and update the count.
                    var userip = data.user.ip ? ' [' + data.user.ip + ']' : '';
                    showChat('info', null, data.user.un + userip + ' connected to the server');

                    clients[data.user.id] = data.user;
                    document.getElementById('users').innerHTML = Object.keys(clients).length + ' USERS';
                    break;

                case 'disconnection':
                    // A user disconnected: announce (if named), drop from roster, update the count.
                    var userip = data.user.ip ? ' [' + data.user.ip + ']' : '';

                    if(data.user.un != null) {
                        showChat('info', null, data.user.un + userip + ' disconnected from the server');
                    }

                    delete clients[data.user.id];
                    document.getElementById('users').innerHTML = Object.keys(clients).length + ' USERS';
                    break;

                case 'spam':
                    // Rate-limit warning from the server.
                    showChat('global', null, 'You have to wait 1 second between messages. Continuing on spamming the servers may get you banned. Warning ' + data.warn + ' of 5');
                    break;

                case 'clients':
                    // Full roster snapshot: replace local list and refresh the user count.
                    clients = data.clients;
                    document.getElementById('users').innerHTML = Object.keys(clients).length + ' USERS';
                    break;

                case 'user':
                    // The server tells us our own client id.
                    user = data.client.id;
                    break;
            }
        } else if((data.type == 'kick' || data.type == 'ban') && data.extra == username) {
            // We were kicked/banned: reload the page to fully reset the client.
            location.reload();
        } else {
            // Any other type is treated as a chat message to render.
            // If our name is @-mentioned in the message, upgrade the type to 'mention'.
            if(data.message.indexOf('@' + username) > -1) {
                data.type = 'mention';
            }

            // Optionally read the incoming message aloud.
            if(settings.synthesis) {
                textToSpeech.text = data.message;
                speechSynthesis.speak(textToSpeech);
            }

            // Render the message into the chat panel.
            showChat(data.type, data.user, data.message, data.subtxt, data.mid);
        }

        // Role change event: toggle moderator UI for ourselves and update the roster role.
        if(data.type == 'role') {
            if(getUserByName(data.extra) != undefined) {
                // We were granted a staff role: reveal the admin controls.
                if(data.extra == username && data.role > 0) {
                    jQuery('#admin').show();
                    jQuery('#menu-admin').show();
                }

                // We were demoted to plain user: hide the admin controls.
                if(data.extra == username && data.role == 0) {
                    jQuery('#admin').hide();
                    jQuery('#menu-admin').hide();
                }

                // Store the new role on the affected client's roster entry.
                clients[getUserByName(data.extra).id].role = data.role;
            }
        }

        // Attention-worthy messages: bump unread badge and fire sound/desktop alerts when blurred.
        if(data.type == 'global' || data.type == 'pm' || data.type == 'mention') {
            if(!focus) {
                unread++;                                   // Count it as unread.
                if(settings.sound) {
                    blop.play();                            // Audible ping.
                }

                if(settings.desktop) {
                    desktopNotif(data.user + ': ' + data.message); // OS notification.
                }
            }
        }
    }
};


/* Functions */
/**
 * Return chat rows whose opaque data-mid exactly matches the supplied value.
 *
 * The value is compared as data, never interpolated into a selector. Message
 * ids originate in server frames and may contain arbitrary CSS syntax.
 *
 * @param {*} mid Message id received from the chat server.
 * @return {jQuery} Matching rows in the chat panel.
 */
function getChatRowsByMid(mid) {
    var expected = String(mid);
    return jQuery('#panel > div[data-mid]').filter(function() {
        return this.getAttribute('data-mid') === expected;
    });
}

/**
 * Serialize and send a frame to the chat server.
 *
 * @param {string} value  Primary payload (message text, target user, etc.).
 * @param {string} method Frame type the server dispatches on (e.g. 'message', 'pm', 'ban').
 * @param {string} [other] Extra argument (e.g. target user or role value).
 * @param {string} [txt]  Optional sub-text label (e.g. 'PM').
 */
function sendSocket(value, method, other, txt) {
    // Build the frame from the four positional args and push it down the socket.
    socket.send(JSON.stringify({
        type: method,
        message: value,
        subtxt: txt,
        extra: other
    }));

    jQuery('#message').focus();  // Return focus to the input for the next message.
}

/**
 * Send the join/update handshake announcing our username and key.
 *
 * Called on socket open and after a /name change to (re)register with the server.
 */
function updateInfo(targetSocket) {
    var destination = targetSocket || socket;
    destination.send(JSON.stringify({
        user: username,
        type: 'update',
        key:key
    }));
}

/**
 * Look up a roster entry by display name.
 *
 * @param {string} name Display name to search for.
 * @return {Object|undefined} The matching client object, or undefined if not found.
 */
function getUserByName(name) {
    // Linear scan over the roster comparing display names.
    for(client in clients) {
        if(clients[client].un == name) {
            return clients[client];
        }
    }
}






/**
 * Update the message input bar's placeholder and enabled/disabled state.
 *
 * @param {string} icon        Icon class (currently unused; the line is commented out).
 * @param {string} placeholder Placeholder text for the input.
 * @param {boolean} disable    Whether to disable the input and send button.
 */
function updateBar(icon, placeholder, disable) {
//    document.getElementById('icon').className = 'mdi ' + icon;
    jQuery('#message').attr('placeholder', placeholder);  // Set the hint text.
    jQuery('#message').prop('disabled', disable);         // Enable/disable the text input.
    jQuery('#send').prop('disabled', disable);            // Enable/disable the send button.
}

/**
 * Render a single chat entry into the #panel message list.
 *
 * Resolves the author's role-based CSS class, appends the message markup,
 * scrolls to the bottom, and (when inline expansion is on) tries to embed any
 * image/gif links found in the message.
 *
 * @param {string} type    Message class/type (message, emote, pm, info, light, global, ...).
 * @param {string} user    Author display name (overridden to 'System' for system types).
 * @param {string} message Message body / HTML.
 * @param {string} [subtxt] Optional sub-label (e.g. 'PM') shown before the timestamp.
 * @param {string} [mid]   Message id used as the data-mid for later deletion/edits.
 */
function showChat(type, user, message, subtxt, mid) {
    var nameclass = '';      // CSS class applied to the author name based on their role.

    // Normalize the /me and /em action types to the internal 'emote' type.
    if(type == 'me' || type == 'em') {
        type = 'emote';
    }

    // Confine the server-supplied `type` to a known set before it is used as the
    // row's CSS class. `className` is a DOM property (not an HTML sink), so this
    // protects class/behaviour integrity rather than escaping; an unknown or
    // hostile value collapses to the neutral 'light' type and is then treated as
    // a System notice by the block below.
    var allowedTypes = ['message', 'emote', 'pm', 'mention', 'global', 'kick', 'ban', 'info', 'light', 'help', 'role'];
    if(allowedTypes.indexOf(type) === -1) {
        type = 'light';
    }

    // System-generated notices are attributed to a "System" author.
    if(type == 'global' || type == 'kick' || type == 'ban' || type == 'info' || type == 'light' || type == 'help' || type == 'role') {
        user = 'System';
    }

    // Fallback message id for system lines.
    if(!mid) {
        mid = 'system';
    }

    // For real user messages/emotes, colour the name by the author's role.
    if(type == 'emote' || type == 'message') {
        if(user == username && getUserByName(user).role == 0) {
            nameclass = 'self';                                   // Our own regular message.
        } else {
            if(getUserByName(user).role == 1) nameclass = 'helper';        // Helper.
            if(getUserByName(user).role == 2) nameclass = 'moderator';     // Moderator.
            if(getUserByName(user).role == 3) nameclass = 'administrator'; // Administrator.
        }
    }

    // Build and append the message row with DOM APIs so every remote-controlled
    // field (user, message, subtxt, mid, type) is inserted as text or an opaque
    // attribute — never parsed as HTML (SEC-06 stored-XSS fix).
    var row = document.createElement('div');
    row.setAttribute('data-mid', mid);   // Opaque id; consumers compare it as data, never selector text.
    row.className = type;                 // Already validated against the allowlist above.

    // Author name: <span class="name <role>"><b><a class="namelink">user:</a></b></span>
    var nameSpan = document.createElement('span');
    nameSpan.className = 'name ' + nameclass;
    var nameBold = document.createElement('b');
    var nameLink = document.createElement('a');
    nameLink.className = 'namelink';
    nameLink.setAttribute('href', 'javascript:void(0)');
    nameLink.textContent = user + ':';
    nameBold.appendChild(nameLink);
    nameSpan.appendChild(nameBold);
    row.appendChild(nameSpan);

    if(!subtxt) {
        // No sub-label: add the DELETE control and a plain timestamp.
        var deleteSpan = document.createElement('span');
        deleteSpan.className = 'delete';
        var deleteLink = document.createElement('a');
        deleteLink.setAttribute('href', 'javascript:void(0)');
        deleteLink.textContent = 'DELETE';
        deleteSpan.appendChild(deleteLink);
        row.appendChild(deleteSpan);

        var timeSpan = document.createElement('span');
        timeSpan.className = 'timestamp';
        timeSpan.textContent = getTime();
        row.appendChild(timeSpan);
    } else {
        // With a sub-label (e.g. PM): "(subtxt) time".
        var timeSpanSub = document.createElement('span');
        timeSpanSub.className = 'timestamp';
        timeSpanSub.textContent = '(' + subtxt + ') ' + getTime();
        row.appendChild(timeSpanSub);
    }

    // Message body: set as text so markup in `message` is shown literally. The
    // emoji/linkify/greentext post-processing in updateStyle() then runs on it.
    var msgSpan = document.createElement('span');
    msgSpan.className = 'msg';
    msgSpan.textContent = message;
    row.appendChild(msgSpan);

    jQuery('#panel').append(row);

    // Scroll the panel to the newest message.
    jQuery('#panel').animate({scrollTop: jQuery('#panel').prop('scrollHeight')}, 500);
    updateStyle();           // Apply linkify / greentext / emoji post-processing to the new row.
    jQuery('#message').focus();

    nmr++;                   // Advance the rendered-message index used by updateStyle().

    // Inline link/image expansion (when enabled in settings).
    if(settings.inline) {
        // Find http(s)/ftp URLs in the message body.
        var m = message.match(/(https?|ftp):\/\/[^\s/jQuery.?#].[^\s]*/gmi);

        if(m) {
            // For each URL, try to embed it as an image.
            m.forEach(function(e, i, a) {
                // Gfycat Support: hit the cajax endpoint to resolve the real gif URL.
                if(e.indexOf('//gfycat') !== -1) {
                    var oldUrl = e;
                    e = e.replace('//gfycat.com', '//gfycat.com/cajax/get').replace('http://', 'https://');

                    // Fetch metadata, then swap in the resolved gif for the original link.
                    jQuery.getJSON(e, function(data) {
                        testImage(data.gfyItem.gifUrl.replace('http://', 'https://'), mid, oldUrl);
                    });
                } else {
                    // Plain URL: attempt to load it directly as an image.
                    testImage(e, mid, e);
                }
            });
        }
    }
}

/**
 * Probe a URL as an image and, if it loads, replace its link with the image inline.
 *
 * @param {string} url    URL to load as an image.
 * @param {string} mid    Message id whose row contains the link.
 * @param {string} oldUrl Original link URL used to locate the anchor to replace.
 */
function testImage(url, mid, oldUrl) {
    var img = new Image();   // Off-DOM image used purely to test loadability.

    // On successful load, swap the matching anchor's contents for the image and re-scroll.
    img.onload = function() {
        var normalizedOldUrl = oldUrl.replace('https://', 'http://');
        getChatRowsByMid(mid).find('.msg a').filter(function() {
            return this.getAttribute('href') === normalizedOldUrl;
        }).empty().append(img);
        jQuery('#panel').animate({scrollTop: jQuery('#panel').prop('scrollHeight')}, 500);
    };

    img.src = url;           // Kick off the load; onload fires only for valid images.
}


/**
 * Read the input box, then either run a slash command or send a chat message.
 *
 * Handles the full slash-command grammar (/pm, /msg, /ban, /role, /kick,
 * /me, /em, /name, /alert, /clear, /shrug, /help, /users, /reconnect) and
 * falls back to sending the raw text as a normal message.
 */
function handleInput() {
    // Grab the input, strip zwj/nbsp entities, and trim surrounding whitespace.
    var value = jQuery('#message').val().replace(regex, ' ').trim();
    jQuery('#message').focus();
    if(value.length > 0) {

        // No username yet: the auto-connect-on-first-message path is disabled (commented out).
        if(username === undefined) {
//            username = value;
//            connect();
        } else if(value.charAt(0) == '/') {
            // Leading slash => command. Split off the command word and its arguments.
            var command = value.substring(1).split(' ');

            // Dispatch on the (lower-cased) command keyword.
            switch(command[0].toLowerCase()) {
                // Commands that take one or more arguments after the keyword.
                case 'pm': case 'msg': case 'role': case 'kick': case 'ban': case 'name': case 'alert': case 'me': case 'em':
                    // Only proceed if there is something after the command word.
                    if(value.substring(command[0].length).length > 1) {
                        // /pm | /msg [user] [message]: send a private message when a body is present.
                        if((command[0] == 'pm' || command[0] == 'msg') && value.substring(command[0].concat(command[1]).length).length > 2) {
                            sendSocket(value.substring(command[0].concat(command[1]).length + 2), 'pm', command[1], 'PM');
                        } else if(command[0] == 'pm' || command[0] == 'msg') {
                            showChat('light', 'Error', 'Use /' + command[0] + ' [user] [message]');  // Usage hint.
                        }

                        // /ban [user] [minutes]: ban a user when a duration is supplied.
                        if(command[0] == 'ban' && value.substring(command[0].concat(command[1]).length).length > 2) {
                            sendSocket(command[1], 'ban', command[2]);
                        } else if(command[0] == 'ban') {
                            showChat('light', 'Error', 'Use /ban [user] [minutes]');  // Usage hint.
                        }

                        // /alert [message]: broadcast a global system alert stamped with our name.
                        if(command[0] == 'alert') {
                            sendSocket(value.substring(command[0].length + 2), 'global', null, username);
                        }

                        // /role [user] [0-3]: set a user's role when a level is supplied.
                        if((command[0] == 'role') && value.substring(command[0].concat(command[1]).length).length > 3) {
                            sendSocket(command[1], 'role', value.substring(command[0].concat(command[1]).length + 3));
                        } else if(command[0] == 'role') {
                            showChat('light', 'Error', 'Use /' + command[0] + ' [user] [0-3]');  // Usage hint.
                        }

                        // /kick | /me | /em: forward the trailing text under the command's own type.
                        if(command[0] == 'kick' || command[0] == 'me' || command[0] == 'em') {
                            sendSocket(value.substring(command[0].length + 2), command[0]);
                        }

                        // /name [name]: change our display name and re-announce via updateInfo.
                        if(command[0] == 'name') {
                            oldname = username;                              // Remember for possible rollback.
                            username = value.substring(command[0].length + 2);
                            updateInfo();
                        }
                    } else {
                        // No argument given: build and show the correct usage string per command.
                        var variables;
                        // Expected argument suffix per command, used in the usage message.
                        if(command[0] == 'alert' || command[0] == 'me' || command[0] == 'em') {
                            variables = ' [message]';
                        }

                        if(command[0] == 'role') {
                            variables = ' [user] [0-3]';
                        }

                        if(command[0] == 'ban') {
                            variables = ' [user] [minutes]';
                        }

                        if(command[0] == 'pm') {
                            variables = ' [user] [message]';
                        }

                        if(command[0] == 'kick') {
                            variables = ' [user]';
                        }

                        if(command[0] == 'name') {
                            variables = ' [name]';
                        }

                        showChat('light', 'Error', 'Use /' + command[0] + variables);  // Show usage.
                    }
                    break;

                // /clear: reset the rendered-message counter and wipe the panel locally.
                case 'clear':
                    nmr = 0;
                    document.getElementById('panel').innerHTML = '';
                    showChat('light', 'System', 'Messages cleared');
                    break;

                // /shrug: append the shrug kaomoji to the trailing text and send as a message.
                case 'shrug':
                    sendSocket(value.substring(6) + ' ¯\\_(ツ)_/¯', 'message');
                    break;

                // /help: open the help modal.
                case 'help':
                    jQuery('#help-dialog').modal('show');
                    break;

                // /users: trigger the user-list button to show the roster dialog.
                case 'users':
                    jQuery('#user').click();
                    break;

                // /reconnect: close the socket (onclose will reconnect if we were connected).
                case 'reconnect':
                    socket.close();
                    break;

                // Anything else: unknown command.
                default:
                    showChat('light', 'Error', 'Unknown command, use /help to get a list of the available commands');
                    break;
            }
        } else {
            // Not a command: send the plain text as a normal chat message.
            sendSocket(value, 'message');
        }

        // Clear the input and refocus, ready for the next entry.
        jQuery('#message').val('');
        jQuery('#message').focus();

    }
}

/**
 * Build a zero-padded HH:MM:SS timestamp for the current local time.
 *
 * @return {string} Time formatted as 'HH:MM:SS'.
 */
function getTime() {
    var now = new Date();
    var time = [now.getHours(), now.getMinutes(), now.getSeconds()];  // [h, m, s].

    // Zero-pad any single-digit component.
    for(var i = 0; i < 3; i++) {
        if(time[i] < 10) {
            time[i] = '0' + time[i];
        }
    }

    return time.join(':');   // Join into HH:MM:SS.
}

/**
 * Post-process the most recently rendered message row.
 *
 * Runs linkify on the panel, then applies greentext colouring and emoji
 * shortcode-to-image conversion to the newest .msg element (indexed by nmr).
 */
function updateStyle() {
    // The .msg body was written with textContent in showChat(), so element.innerHTML
    // here is already HTML-escaped. This function reads that escaped string back and
    // re-assigns innerHTML, so the SEC-06 escaping depends on linkify() and
    // emojione.shortnameToImage() staying escape-safe: linkify only rewrites text
    // nodes and re-escapes '<', and shortnameToImage only substitutes fixed emoji
    // shortcodes. Keep those bundled libraries pinned (see TASK-12) — a version bump
    // that changed either behaviour could reopen the sink.
    jQuery('#panel').linkify();  // Turn bare URLs in the panel into clickable links.
    var element = document.getElementsByClassName('msg')[nmr];  // The just-added message body.

    if(element.innerHTML != undefined) {
        // Greentext: colour the line green when it begins with an escaped '>'.
        if(settings.greentext && element.innerHTML.indexOf('&gt;') == 0) {
            element.style.color = '#689f38';
        }

        // Emoji: replace :shortcodes: with emoji <img> tags.
        if(settings.emoji) {
            var input = element.innerHTML;
            var output = emojione.shortnameToImage(element.innerHTML);
            element.innerHTML = output;
        }
    }
}


/* Binds */
// DOM-ready: wire up all the chat UI event handlers once the page is available.
jQuery(document).ready(function() {
    // Close button on the user-list dialog: hide it.
    jQuery('#close-users-dialog').on('click',function(){
        jQuery('#users-dialog').hide();
    });

    // User-list button: build the roster list and show the dialog.
    jQuery('#user').bind('click', function() {
        // Rebuild the list from scratch each open, using DOM APIs so the
        // server-supplied id/ip/name fields render as text, never as HTML
        // (SEC-06 stored-XSS fix).
        var usersContent = document.getElementById('users-content');
        usersContent.innerHTML = '';   // Clear the previous roster (safe: constant).

        // Role number -> label; 0 (plain user) has no label.
        var roleLabels = { 1: 'Helper', 2: 'Moderator', 3: 'Administrator' };

        // Walk the roster, composing one list item per connected client.
        for(var i in clients) {
            if(clients[i] != undefined) {
                var li = document.createElement('li');

                // "#id" in bold.
                var idBold = document.createElement('b');
                idBold.textContent = '#' + clients[i].id;
                li.appendChild(idBold);

                // " (ip) - name" as a single text node (ip only when provided).
                var prefix = ' ';
                if(clients[i].ip) {
                    prefix += '(' + clients[i].ip + ')';
                }
                prefix += ' - ' + clients[i].un;
                li.appendChild(document.createTextNode(prefix));

                // Role label (staff only) in bold, preceded by " - ". Look up by
                // numeric role and via hasOwnProperty so a server-sent role like
                // "toString"/"constructor" can't resolve to a Object.prototype member.
                var role = Number(clients[i].role);
                if(Object.prototype.hasOwnProperty.call(roleLabels, role)) {
                    li.appendChild(document.createTextNode(' - '));
                    var roleBold = document.createElement('b');
                    roleBold.textContent = roleLabels[role];
                    li.appendChild(roleBold);
                }

                usersContent.appendChild(li);
            }
        }

        // Reveal the dialog.
        jQuery('#users-dialog').show();
    });

    // Hovering a message row (staff only): reveal the DELETE control, hide the timestamp.
    jQuery('#panel').on('mouseenter', '.message', function() {
        if(clients[user].role > 0) {
            jQuery(this).find('span:eq(1)').show();
            jQuery(this).find('span:eq(2)').hide();
        }
    });

    // Leaving a message row (staff only): hide the DELETE control, restore the timestamp.
    jQuery('#panel').on('mouseleave', '.message',function() {
        if(clients[user].role > 0) {
            jQuery(this).find('span:eq(1)').hide();
            jQuery(this).find('span:eq(2)').show();
        }
    });

    // Same delete-on-hover behaviour for emote rows.
    jQuery('#panel').on('mouseenter', '.emote', function() {
        if(clients[user].role > 0) {
            jQuery(this).find('span:eq(1)').show();
            jQuery(this).find('span:eq(2)').hide();
        }
    });

    jQuery('#panel').on('mouseleave', '.emote', function() {
        if(clients[user].role > 0) {
            jQuery(this).find('span:eq(1)').hide();
            jQuery(this).find('span:eq(2)').show();
        }
    });

    // Click DELETE: read the row's data-mid and ask the server to delete that message.
    jQuery('#panel').on('click', '.delete', function(e) {
        var value = jQuery(this)[0].parentElement.getAttribute('data-mid');
        sendSocket(value, 'delete');
    });

    // Click an author name: prefill the input with an @mention of that user.
    jQuery('#panel').on('click', '.name', function(e) {
        jQuery('#message').val('@' + jQuery(this)[0].children[0].children[0].innerHTML + ' ');
        jQuery('#message').focus();
    });

    // Send button: submit whatever is in the input.
    jQuery('#send').bind('click', function() {
        handleInput();
    });

    // Admin menu button: open the moderator help dialog.
    jQuery('#menu-admin').bind('click', function() {
        jQuery('#admin-help-dialog').modal('show');
    });

    // Help button: open the help dialog.
    jQuery('#help').bind('click', function() {
        jQuery('#help-dialog').modal('show');
    });

    // Options button: open the options dialog.
    jQuery('#options').bind('click', function() {
        jQuery('#options-dialog').modal('show');
    });

    // Options menu item: open the options dialog.
    jQuery('#menu-options').bind('click', function() {
        jQuery('#options-dialog').modal('show');
    });

    // Audio button: start speech recognition and switch the bar into listening mode.
    jQuery('#audio').bind('click', function() {
        speechToText.start();
        updateBar('mdi-av-mic', 'Start speaking', true);
    });

    // Each settings checkbox writes its state back into `settings` and persists to localStorage.

    // Emoji toggle.
    jQuery('#emoji').bind('change', function() {
        settings.emoji = document.getElementById('emoji').checked;
        localStorage.settings = JSON.stringify(settings);
    });

    // Greentext toggle.
    jQuery('#greentext').bind('change', function() {
        settings.greentext = document.getElementById('greentext').checked;
        localStorage.settings = JSON.stringify(settings);
    });

    // Notification sound toggle.
    jQuery('#sound').bind('change', function() {
        settings.sound = document.getElementById('sound').checked;
        localStorage.settings = JSON.stringify(settings);
    });

    // Speech synthesis (read-aloud) toggle.
    jQuery('#synthesis').bind('change', function() {
        settings.synthesis = document.getElementById('synthesis').checked;
        localStorage.settings = JSON.stringify(settings);
    });

    // Inline image/link expansion toggle.
    jQuery('#inline').bind('change', function() {
        settings.inline = document.getElementById('inline').checked;
        localStorage.settings = JSON.stringify(settings);
    });

    // Desktop notifications toggle: also request OS permission when enabling.
    jQuery('#desktop').bind('change', function() {
        settings.desktop = document.getElementById('desktop').checked;
        localStorage.settings = JSON.stringify(settings);

        if(Notification.permission !== 'granted') {
            Notification.requestPermission();
        }
    });

    // Speech recognition toggle: show/hide the mic button to match the setting.
    jQuery('#recognition').bind('change', function() {
        settings.recognition = document.getElementById('recognition').checked;
        localStorage.settings = JSON.stringify(settings);

        if(settings.recognition)
            jQuery('#audio').show();
        else {
            jQuery('#audio').hide();
        }
    });

    // Message input keypress: Enter submits; any other key drives the typing indicator.
    jQuery('#message').keypress(function(e) {
        if(e.which == 13) {
            // Enter pressed: if we had announced typing, clear it before sending.
            if(connected && typing) {
                typing = false;
                clearTimeout(typeTimer);
                socket.send(JSON.stringify({type:'typing', typing:false}));
            }

            handleInput();   // Submit the message/command.
        } else if(connected) {
            // A character was typed: announce "typing" once, then debounce the "stopped" signal.
            if(!typing) {
                typing = true;
                socket.send(JSON.stringify({type:'typing', typing:true}));
            }

            // Reset the idle timer; after 2s of no keypresses we announce we stopped typing.
            clearTimeout(typeTimer);
            typeTimer = setTimeout(function() {
                typing = false;
                socket.send(JSON.stringify({type:'typing', typing:false}));
            }, 2000);
        }
    });

    //addition keypress binding for handling autocompletion
    // Second keypress binding: @mention username autocomplete via jQuery UI.
    jQuery("#message").keypress( function(event) {
        // don't navigate away from the field on tab when selecting an item
        if (event.keyCode === jQuery.ui.keyCode.TAB )
            event.preventDefault();
    })
    .autocomplete({
        minLength: 0,
        // Provide candidate usernames only for the word currently being typed after '@'.
        source: function(request, response) {
            var term = request.term;
            var results = [];
            term = term.split(/ \s*/).pop();  // Consider only the last whitespace-separated token.

            // Only suggest when the token starts with '@'; match roster names after the '@'.
            if (term.length > 0 && term[0] === '@') {
                var names = jQuery.map( clients, function( val ) { return val.un; });
                results = jQuery.ui.autocomplete.filter(names, term.substr(1));
            }
            response(results);
        },
        focus: function() {
            return false; // prevent value inserted on focus
        },
        // On select: replace the partial @token with the chosen "@name " completion.
        select: function(event, ui) {
            var terms = this.value.split(/ \s*/);
            var old = terms.pop();  //get old word
            var ins = "@" + ui.item.value + " "; //new word to insert
            var ind = this.value.lastIndexOf(old); //location to insert at
            this.value = this.value.slice(0,ind) + ins;
            return false;
        }
    });
});

/* Internal */
// Feature detection and one-time initialization run at load time.

// Reveal the desktop-notification toggle only if the Notification API exists.
if(Notification) {
    jQuery('#toggle-desktop').show();
}

// Speech synthesis available: reveal its toggle and prepare an English utterance object.
if('speechSynthesis' in window) {
    jQuery('#toggle-synthesis').show();
    textToSpeech = new SpeechSynthesisUtterance();
    textToSpeech.lang = 'en';
}

// Speech recognition available: reveal its toggle and configure the recognizer.
if('SpeechRecognition' in window || 'webkitSpeechRecognition' in window) {
    jQuery('#toggle-recognition').show();
    var speechToText = new webkitSpeechRecognition();
    speechToText.interimResults = true;  // Emit partial results as the user speaks.
    speechToText.continuous = true;      // Keep listening across pauses.
    speechToText.lang = 'en-US';
}

// If a recognizer was created, wire its result/error callbacks.
if(speechToText) {
    // Recognition results: fill the input, submitting on each finalized phrase.
    speechToText.onresult = function(event) {
        jQuery('#message').val('');

        // Walk the new results from resultIndex onward.
        for (var i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                // Final phrase: set it as the input, restore the bar, stop, and send.
                jQuery('#message').val(event.results[i][0].transcript);
                updateBar('mdi-content-send', 'Enter your message here', false);
                speechToText.stop();
                handleInput();
            } else {
                // Interim phrase: append the partial transcript to the current input.
                var oldval = jQuery('#message').val();
                jQuery('#message').val(oldval + event.results[i][0].transcript);
            }
        }
    }

    // Recognition error: just restore the input bar to its normal state.
    speechToText.onerror = function(event) {
        updateBar('mdi-content-send', 'Enter your message here', false);
    }

}

/**
 * Show an OS-level desktop notification for a new message.
 *
 * @param {string} message Notification body text.
 */
function desktopNotif(message) {
    // Bail if the Notification API is unavailable.
    if(!Notification) {
        return;
    }

    // Create the notification (permission is requested elsewhere via the settings toggle).
    var notification = new Notification('You\'ve got a new message', {
        icon: 'http://i.imgur.com/ehB0QcM.png',
        body: message
    });
}

// Load persisted settings from localStorage, seeding it on first run.
if(typeof(Storage) !== 'undefined') {
    if(!localStorage.settings) {
        localStorage.settings = JSON.stringify(settings);  // First visit: store defaults.
    } else {
        settings = JSON.parse(localStorage.settings);       // Returning visit: restore prefs.
         if(settings.recognition) {
            jQuery('#audio').show();                         // Show mic if recognition was on.
        }
    }
}

// Window regains focus: mark focused and clear the unread counter.
window.onfocus = function() {
   // document.title = 'Node.JS Chat';
    focus = true;
    unread = 0;
};


// Window loses focus: mark blurred so new messages count as unread and alert.
window.onblur = function() {
    focus = false;
};
