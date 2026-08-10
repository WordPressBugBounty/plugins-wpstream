
<!--
	Live chat widget markup (WpStream plugin).

	Static HTML shell for the SockJS-based chat client (public/chat_lib/chat.js).
	Rendered by widgets/wpstream_chat.php; the JS hydrates these hooks at runtime.
	Contains: the chat container + message log area, the "current users" overlay,
	the typing indicator, the message composer (textarea + Connect/send + users
	toggle), the badge row, and three Bootstrap modals (user command help, admin
	command help, and chat options). Element IDs/classes here are the contract the
	chat JS binds to, so they must stay stable.

	@package Wpstream
-->
<!-- Outer chat wrapper -->
<div class="chat_wrapper main">
      
    <!-- Chat container: message stream plus the composer controls -->
    <div class=" wpstream_chat_container">
         
                    <!-- Chat "meat": where the rendered message log and input UI are injected -->
                    <div class=" wpestream_chat_meat">
                     
                                <!-- Main chat panel: message output target plus overlays and composer -->
                                <div id="panel">
                                    <!-- Users overlay: pop-up list of currently connected users (toggled by the user button); close "x", title, and JS-filled list -->
                                    <div id="users-dialog"  >
                                        <div id="close-users-dialog">x</div>
                                        <h4>Current users</h4>
                                        <div id="users-content"></div>
                                    </div>
                                    
                                    <!-- Typing indicator: chat JS writes "X is typing..." text here -->
                                    <p id="typing"><br></p>
                                    <hr>

                                
                                    <!-- Composer: message textarea, Connect/send button, and the users-overlay toggle -->
                                    <div class="wpstream_chat_input">
                                        <div class="col-lg-12">
                                            <div class="input-group">

                                                <textarea id="message" type="text"></textarea>
                                                <span class="input-group-btn wpstream_chat_actions">
                                                    <button id="send" class="btn btn-primary btn-flat">Connect</button>
                                                    <div id ="user" class="wpstream_chat_users"><i class="fas fa-user"></i></div>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        
                                
                      
                   
                    </div>
                 
                    <!-- Status badges: live user count (hidden until populated) and an ADMIN badge for moderators -->
                    <div id="badges" class="text-right pull-right">
                        <span style="display:none;"><label id="users" class="label">0 USERS</label></span>
                        <span><label id="admin" class="label label-warning" style="display:none">ADMIN</label></span>                              
                    </div>
      
    </div>
</div>




    <!-- Command-reference modal: three columns (command / variables / description) for the /pm, /me, /shrug, etc. user commands -->
    <div id="help-dialog" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row">
                        <h4><b>Current available commands:</b></h4>
                        <div class="col-xs-3"><b>
                            <b>COMMAND</b>
                            <br>/pm
                            <br>/me or /em
                            <br>/shrug
                            <br>/name
                            <br>/users
                            <br>/help
                            <br>/clear
                            <br>/reconnect
                        </b></div>
                        <div class="col-xs-2">
                            <b>VARIABLES</b>
                            <br>[user] [message]
                            <br>[message]
                            <br>[message]
                            <br>[name]
                            <br>
                            <br>
                            <br>
                        </div>
                        <div class="col-xs-7">
                            <b>DESCRIPTION</b>
                            <br>Sends a private <i>[message]</i> for <i>[user]</i>
                            <br>Sends <i>[message]</i> in italics
                            <br>Sends <i>[message]</i> followed by 'Â¯\_(ãƒ„)_/Â¯'
                            <br>Changes your name to <i>[name]</i>
                            <br>Shows users on the server
                            <br>Shows this help dialog
                            <br>Clears your chat history
                            <br>Reconnects to the server
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin command-reference modal: moderator-only commands (/alert, /kick, /ban, /role) in the same three-column layout -->
    <div id="admin-help-dialog" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row">
                        <h4><b>Current available administrator commands:</b></h4>
                        <div class="col-xs-3"><b>
                            <b>COMMAND</b>
                            <br>/alert
                            <br>/kick
                            <br>/ban
                            <br>/role
                        </b></div>
                        <div class="col-xs-2">
                            <b>VARIABLES</b>
                            <br>[message]
                            <br>[user]
                            <br>[user] [minutes]
                            <br>[user] [1-3]
                        </div>
                        <div class="col-xs-7">
                            <b>DESCRIPTION</b>
                            <br>Sends global <i>[message]</i>
                            <br>Kicks <i>[user]</i> from the server
                            <br>Bans <i>[user]</i> from the server for <i>[minutes]</i>
                            <br>Changes <i>[user]</i> administrator permissions
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat options modal: per-user toggles (emojis, greentext, inline images, mention sound) plus feature-gated desktop/speech options hidden until supported -->
    <div id="options-dialog" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body">
                    <h4><b>Chat options:</b></h4>
                    <div class="togglebutton">
                        <label>Emojis<input id="emoji" type="checkbox"></label>
                    </div>
                    <div class="togglebutton">
                        <label>Greentext<input id="greentext" type="checkbox"></label>
                    </div>
                    <div class="togglebutton" id="toggle-inline">
                        <label>Inline Images<input id="inline" type="checkbox"></label>
                    </div>
                    <div class="togglebutton">
                        <label>Mention Sound<input id="sound" type="checkbox"></label>
                    </div>
                    <div class="togglebutton" id="toggle-desktop" style="display:none">
                        <label>Desktop Notifications<input id="desktop" type="checkbox"></label>
                    </div>
                    <div class="togglebutton" id="toggle-synthesis" style="display:none">
                        <label>Speech Synthesis [Experimental]<input id="synthesis" type="checkbox"></label>
                    </div>
                    <div class="togglebutton" id="toggle-recognition" style="display:none">
                        <label>Speech Recognition [Experimental]<input id="recognition" type="checkbox"></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    



<!--
//    global $post;
//    wpstream_connect_to_chat($post->ID);-->
   
