<?php

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

add_action( 'wp_ajax_wplc_user_maximize_chat', 'wplc_user_maximize_chat' );
add_action( 'wp_ajax_nopriv_wplc_user_maximize_chat', 'wplc_user_maximize_chat' );

add_action( 'wp_ajax_wplc_start_chat', 'wplc_start_chat' );
add_action( 'wp_ajax_nopriv_wplc_start_chat', 'wplc_start_chat' );

add_action( 'wp_ajax_wplc_user_close_chat', 'wplc_user_close_chat' );
add_action( 'wp_ajax_nopriv_wplc_user_close_chat', 'wplc_user_close_chat' );

add_action( 'wp_ajax_wplc_user_reset_session', 'wplc_user_reset_session' );
add_action( 'wp_ajax_nopriv_wplc_user_reset_session', 'wplc_user_reset_session' );

add_action( 'wp_ajax_wplc_user_minimize_chat', 'wplc_user_minimize_chat' );
add_action( 'wp_ajax_nopriv_wplc_user_minimize_chat', 'wplc_user_minimize_chat' );

add_action( 'wp_ajax_wplc_chat_history', 'wplc_get_chat_history' );
add_action( 'wp_ajax_nopriv_wplc_chat_history', 'wplc_get_chat_history' );

add_action( 'wp_ajax_wplc_client_polling', 'wplc_client_polling' );
add_action( 'wp_ajax_nopriv_wplc_client_polling', 'wplc_client_polling' );

add_action( 'wp_ajax_wplc_user_send_msg', 'wplc_user_send_message' );
add_action( 'wp_ajax_nopriv_wplc_user_send_msg', 'wplc_user_send_message' );

add_action( 'wp_ajax_wplc_init_session', 'wplc_init_session' );
add_action( 'wp_ajax_nopriv_wplc_init_session', 'wplc_init_session' );

add_action( 'wp_ajax_wplc_typing', 'wplc_typing' );
add_action( 'wp_ajax_nopriv_wplc_typing', 'wplc_typing' );

add_action( 'wp_ajax_wplc_send_offline_msg', 'wplc_send_offline_msg' );
add_action( 'wp_ajax_nopriv_wplc_send_offline_msg', 'wplc_send_offline_msg' );

add_action( 'wp_ajax_wplc_upload_file', 'wplc_upload_file' );
add_action( 'wp_ajax_nopriv_wplc_upload_file', 'wplc_upload_file' );

add_action( 'wp_ajax_wplc_rate_chat', 'wplc_rate_chat' );
add_action( 'wp_ajax_nopriv_wplc_rate_chat', 'wplc_rate_chat' );

add_action( 'wp_ajax_wplc_register_external_session', 'wplc_register_external_session' );
add_action( 'wp_ajax_nopriv_wplc_register_external_session', 'wplc_register_external_session' );

add_action( 'wp_ajax_wplc_wplc_get_general_info', 'wplc_get_general_info' );
add_action( 'wp_ajax_nopriv_wplc_get_general_info', 'wplc_get_general_info' );


add_action( 'wp_ajax_nopriv_wplc_test', 'wplc_test' );

function wplc_get_general_info() {
	// This function used to load general NOT protected information
	$result               = array();
	$result['dictionary'] = TCXUtilsHelper::get_client_dictionary();

	die( TCXChatAjaxResponse::success_ajax_respose( $result, ChatStatus::NOT_STARTED ) );
}

function wplc_init_session() {
	global $wpdb;
	$wplc_settings = TCXSettings::getSettings();
	$name          = TCXUtilsHelper::that_or_default_setting( '', 'wplc_user_default_visitor_name' );
	$email         = "no email set";

	$wplcsession = '';
	if ( ! empty( $_POST['wplcsession'] ) ) {
		$wplcsession = sanitize_text_field( $_POST['wplcsession'] );
	}

	if ( ! class_exists( 'Mobile_Detect' ) ) {
		require_once( WPLC_PLUGIN_DIR . '/includes/Mobile_Detect.php' );
	}
	$wplc_detect_device = new Mobile_Detect;
	$is_mobile          = $wplc_detect_device->isMobile() ? true : false;

	$add_new = false;
	TCXPhpSessionHelper::start_session();
	if ( empty( $_SESSION['wplc_session_chat_session_id'] ) ) {
		$add_new = true;
	} else {
		$chat = TCXChatData::get_chat( $wpdb, $_SESSION['wplc_session_chat_session_id'] );

		if ( $chat == null || ! in_array( $chat->status, array(
				ChatStatus::ACTIVE,
				ChatStatus::BROWSE,
				ChatStatus::PENDING_AGENT
			) ) ) {
			$add_new = true;
			TCXPhpSessionHelper::clean_session( true );
		}
	}

	if ( $add_new ) {
		$department_id = $wplc_settings->wplc_allow_department_selection ? 0 : $wplc_settings->wplc_default_department;

		$cid = TCXChatHelper::add_chat( $name, $email, $wplcsession, $is_mobile, $department_id );
		TCXPhpSessionHelper::set_session( $cid );
		TCXWebhookHelper::send_webhook( WebHookTypes::NEW_VISITOR, array( "chat_id" => $cid ) );
	}
	$chat  = TCXChatData::get_chat( $wpdb, $_SESSION['wplc_session_chat_session_id'] );
	$agent = TCXAgentsHelper::get_agent( $chat->agent_id );

	$user_data = TCXChatHelper::set_chat_user_data( $chat->id, $chat->status, $chat->session );


	$result                    = array();
	$result['cid']             = $_SESSION['wplc_session_chat_session_id'];
	$result['status']          = $chat->status;
	$result['available']       = TCXAgentsHelper::exist_available_agent();
	$result['enabled']         = $wplc_settings->wplc_settings_enabled == 1 && TCXUtilsHelper::wplc_check_chatbox_enabled_business_hours();
	$result['name']            = $chat->name;
	$result['wplc_operator']   = $agent->display_name;
	$result['nonce']           = wp_create_nonce( "wplc" );
	$result['chat_started_at'] = $chat->timestamp;
	$result['departments']     = TCXDepartmentsData::get_departments( $wpdb );
	$result['dictionary']      = TCXUtilsHelper::get_client_dictionary();
	$result['portal_id']       = get_option( 'WPLC_GUID' );
	$result['country']         = $user_data['country'];
	$result['custom_fields']   = array_map( function ( $field ) {
		$mapped         = new stdClass();
		$mapped->id     = $field->id;
		$mapped->type   = $field->field_type == "0" ? "TEXT" : "DROPDOWN";
		$mapped->name   = $field->field_name;
		$mapped->values = $field->field_type == "0" ? $field->field_content : json_decode( $field->field_content );

		return $mapped;
	}, TCXCustomFieldsData::get_active_custom_fields( $wpdb ) );

	TCXPhpSessionHelper::close_session();
	die( TCXChatAjaxResponse::success_ajax_respose( $result, $chat->status ) );
}

function wplc_start_chat() {
	global $wpdb;
	$cid = wplc_validate_user_call();
	//wp_die( $_POST['customFields']);
	if ( $cid > 0 ) {
		$wplc_settings = TCXSettings::getSettings();
		$name          = TCXUtilsHelper::that_or_default_setting( '', 'wplc_user_default_visitor_name' );
		if ( isset( $_POST['name'] ) && ! empty( $_POST['name'] ) ) {
			$name = trim( strip_tags( sanitize_text_field( $_POST['name'] ) ) ) == "" ? $name : trim( strip_tags( sanitize_text_field( $_POST['name'] ) ) );
		}

		$email = "no email set";
		if ( isset( $_POST['email'] ) && ! empty( $_POST['email'] ) ) {
			$email = strip_tags( sanitize_text_field( $_POST['email'] ) );
		}

		$department = $wplc_settings->wplc_default_department;
		if ( ! empty( $_POST['department'] ) ) {
			$post_department = intval( sanitize_text_field( $_POST['department'] ) );
			$department      = $post_department > 0 ? $post_department : $department;
		}

		$customFieldsValues = array();
		if ( ! empty( $_POST['customFields'] ) ) {
			$customFieldsValues = json_decode( stripslashes( $_POST['customFields'] ), true );
		}

		$array          = array( "check" => false );
		$array['debug'] = "";

		$chat = TCXChatData::get_chat( $wpdb, $cid );
		if ( $chat->status == ChatStatus::BROWSE || $chat->status == ChatStatus::NOT_STARTED ) {
			TCXChatHelper::user_initiate_chat( $name, $email, $cid, $department, $customFieldsValues ); // echo the chat session id
		}
		die( TCXChatAjaxResponse::success_ajax_respose( array(
			'cid' => $cid
		), $chat->status ) );
	}
}

function wplc_register_external_session() {
	global $wpdb;
	$cid = wplc_validate_user_call();
	if ( $cid > 0 ) {
		$wplc_settings         = TCXSettings::getSettings();
		$external_session_code = "";
		if ( isset( $_POST['ext_session'] ) && ! empty( $_POST['ext_session'] ) ) {
			$external_session_code = sanitize_text_field( $_POST['ext_session'] );
		}

		$chat = TCXChatData::get_chat( $wpdb, $cid );
		if ( $chat->status == ChatStatus::ACTIVE || $chat->status == ChatStatus::PENDING_AGENT ) {
			TCXChatHelper::set_chat_external_session( $cid, $external_session_code );
		}
		die( TCXChatAjaxResponse::success_ajax_respose( array(
			'cid' => $cid
		), $chat->status ) );
	}
}

function wplc_get_chat_history() {
	global $wpdb;
	$cid    = wplc_validate_user_call();
	$result = array();

	if ( $cid > 0 ) {
		$chat     = TCXChatData::get_chat( $wpdb, $cid );
		$agent    = TCXAgentsHelper::get_agent( $chat->agent_id );
		$messages = wplc_load_client_messages( $chat, "HISTORY" );
		if ( in_array( $chat->status, array(
			ChatStatus::ENDED_BY_CLIENT,
			ChatStatus::ENDED_BY_AGENT,
			ChatStatus::ENDED_DUE_CLIENT_INACTIVITY,
			ChatStatus::ENDED_DUE_AGENT_INACTIVITY
		) ) ) {
			TCXPhpSessionHelper::clean_session();
		}
		$result["Messages"] = $messages;
		$result["Operator"] = $agent != null ? $agent->display_name : 'Support';
		die( TCXChatAjaxResponse::success_ajax_respose( $result, $chat->status ) );
	} else {
		die( TCXChatAjaxResponse::error_ajax_respose( "CID NOT PROVIDED" ) );
	}
}

function wplc_client_polling() {
	global $wpdb;
	$cid = wplc_validate_user_call();

	if ( $cid > 0 ) {
		$wplc_settings = TCXSettings::getSettings();
		$iterations    = TCXUtilsHelper::setup_polling( $wplc_settings );

		$wplcsession = '';
		if ( ! empty( $_POST['wplcsession'] ) ) {
			$wplcsession = sanitize_text_field( $_POST['wplcsession'] );
		}

		$client_status = - 1;
		if ( ! empty( $_POST['status'] ) ) {
			$client_status = intval( sanitize_text_field( $_POST['status'] ) );
		}

		$last_code_delivered = "NONE";
		if ( ! empty( $_POST['last_informed'] ) ) {
			$last_code_delivered = sanitize_text_field( $_POST['last_informed'] );
		}

		$i        = 1;
		$result   = array();
		$messages = array();
		$codes    = array();
		$stop     = false;

		$last_status = $client_status;
		$chat        = TCXChatData::get_chat( $wpdb, $cid );
		$agent       = TCXAgentsHelper::get_agent( $chat->agent_id );

		while ( $i <= $iterations ) {
			if ( $i == 1 ) {
				$chat->status = TCXChatHelper::update_chat_status( $chat );
			}
			$clean_previous = $i % 10 == 0;
			$changeSet      = TCXChatHelper::get_queued_actions( $cid, $chat->session, $last_code_delivered, $clean_previous );
			foreach ( $changeSet as $change ) {
				$data               = json_decode( $change->data, true );
				$message_properties = json_decode( $change->message_properties );
				switch ( $change->action_type ) {
					case ActionTypes::START_QUEUE:
						$codes[] = array( "code" => $change->code, "added_at" => $change->timestamp_added_at );
						$stop    = true;
						break;
					case ActionTypes::NEW_MESSAGE:
						$messages[ $change->message_id ] = array(
							"msg"        => TCXChatHelper::decrypt_msg( $data ),
							"added_at"   => $change->timestamp_added_at,
							"originates" => $change->sender,
							"file"       => is_object( $message_properties ) && isset( $message_properties->isFile ) && isset( $message_properties->file ) ? $message_properties->file : null
						);
						$codes[]                         = array(
							"code"     => $change->code,
							"added_at" => $change->timestamp_added_at
						);
						$stop                            = true;
						break;
					case ActionTypes::CHANGE_STATUS:
						TCXChatHelper::set_chat_user_data( $cid, $data['new_status'], $wplcsession );
						$last_status = $data['new_status'];
						switch ( $data['new_status'] ) {
							case ChatStatus::ACTIVE:
								$chat                      = TCXChatData::get_chat( $wpdb, $cid );
								$agent                     = TCXAgentsHelper::get_agent( $chat->agent_id );
								$messages[0]['msg']        = stripslashes( __( "Agent" . " " . $agent->display_name . " joined the chat", 'wp-live-chat-support' ) );
								$messages[0]['added_at']   = $change->timestamp_added_at;
								$messages[0]['originates'] = UserTypes::SYSTEM;
								$codes[]                   = array(
									"code"     => $change->code,
									"added_at" => $change->timestamp_added_at
								);
								$stop                      = true;
								break;
							case ChatStatus::MISSED:
							case ChatStatus::ENDED_DUE_AGENT_INACTIVITY:
								$messages[0]['msg']        = stripslashes( TCXSettings::getSettingValue( "wplc_user_no_answer", __( "No agent was able to answer your chat request. Please try again.", 'wp-live-chat-support' ) ) );
								$messages[0]['added_at']   = $change->timestamp_added_at;
								$messages[0]['originates'] = UserTypes::SYSTEM;
								//TCXPhpSessionHelper::clean_session();
								$codes[] = array( "code" => $change->code, "added_at" => $change->timestamp_added_at );
								$stop    = true;
								break;
							case ChatStatus::ENDED_DUE_CLIENT_INACTIVITY:
								$messages[0]['msg']        = __( "Your chat ended due to long period of inactivity. Please try again.", 'wp-live-chat-support' );
								$messages[0]['added_at']   = $change->timestamp_added_at;
								$messages[0]['originates'] = UserTypes::SYSTEM;
								//TCXPhpSessionHelper::clean_session();
								$codes[] = array( "code" => $change->code, "added_at" => $change->timestamp_added_at );
								$stop    = true;
								break;
							case ChatStatus::ENDED_BY_AGENT:
								$messages[0]['msg']        = __( "Admin has closed and ended the chat", 'wp-live-chat-support' );
								$messages[0]['added_at']   = $change->timestamp_added_at;
								$messages[0]['originates'] = UserTypes::SYSTEM;
								//TCXPhpSessionHelper::clean_session();
								$codes[] = array( "code" => $change->code, "added_at" => $change->timestamp_added_at );
								$stop    = true;
								break;
							default:
								break;
						}
						break;
				}
			}

			if ( $stop ) {
				break;
			}

			$i ++;
			if ( defined( 'WPLC_DELAY_BETWEEN_LOOPS' ) ) {
				usleep( WPLC_DELAY_BETWEEN_LOOPS );
			} else {
				usleep( 500000 );
			}
		}
		$result["Messages"]    = $messages;
		$result["ActionCodes"] = $codes;
		$result["Operator"]    = $agent != null ? $agent->display_name : 'Support';
		die( TCXChatAjaxResponse::success_ajax_respose( $result, $last_status ) );
	}
}

function wplc_user_send_message() {
	$cid = wplc_validate_user_call();

	if ( $cid > 0 ) {
		$chat_msg     = stripslashes( $_POST['msg'] );
		$wplc_rec_msg = TCXChatHelper::add_chat_message( UserTypes::CLIENT, $cid, $chat_msg );
		if ( $wplc_rec_msg ) {
			die( TCXChatAjaxResponse::success_ajax_respose( array( "cid" => $cid ) ) );
		} else {
			die( TCXChatAjaxResponse::error_ajax_respose( "There was an error sending your chat message. Please contact support" ) );
		}
	} else {
		die( TCXChatAjaxResponse::error_ajax_respose( "Invalid Chat ID" ) );
	}
}

function wplc_user_close_chat() {
	$cid = wplc_validate_user_call();

	if ( $cid > 0 ) {

		if ( TCXChatHelper::end_chat( $cid, ChatStatus::ENDED_BY_CLIENT ) ) {
			TCXPhpSessionHelper::clean_session();
			die( TCXChatAjaxResponse::success_ajax_respose( array( 'cid' => $cid ), ChatStatus::ENDED_BY_CLIENT ) );
		} else {
			die( TCXChatAjaxResponse::error_ajax_respose( "Unable to set status" ) );
		}
	} else {
		die( TCXChatAjaxResponse::error_ajax_respose( "Invalid Chat ID" ) );
	}
}

function wplc_user_reset_session() {
	global $wpdb;
	$cid = - 1;

	if ( isset( $_POST["cid"] ) ) {
		$cid = sanitize_text_field( $cid );
	}

	if ( $cid > 0 ) {
		$ended_chat_statuses = array(
			ChatStatus::MISSED,
			ChatStatus::ENDED_BY_AGENT,
			ChatStatus::ENDED_BY_CLIENT,
			ChatStatus::ENDED_DUE_AGENT_INACTIVITY,
			ChatStatus::ENDED_DUE_CLIENT_INACTIVITY
		);
		$chat                = TCXChatData::get_chat( $wpdb, $cid );
		if ( ! in_array( $ended_chat_statuses, $chat->status ) ) {
			wplc_validate_user_call();
			TCXPhpSessionHelper::clean_session();
		}
		die( TCXChatAjaxResponse::success_ajax_respose( array( 'cid' => $cid ), $chat->status ) );
	} else {
		die( TCXChatAjaxResponse::error_ajax_respose( "Invalid Chat ID" ) );
	}
}

function wplc_user_minimize_chat() {
	global $wpdb;
	$cid = wplc_validate_user_call();

	if ( $cid > 0 ) {
		$chat = TCXChatData::get_chat( $wpdb, $cid );
		TCXChatHelper::set_chat_state( $cid, ChatState::MINIMIZED );
	}
	die( TCXChatAjaxResponse::success_ajax_respose( array( 'cid' => $cid ), $chat->status ) );
}

function wplc_user_maximize_chat() {
	global $wpdb;
	$cid = wplc_validate_user_call();

	if ( $cid > 0 ) {
		$chat = TCXChatData::get_chat( $wpdb, $cid );
		TCXChatHelper::set_chat_state( $cid, ChatState::ACTIVE );
	}
	die( TCXChatAjaxResponse::success_ajax_respose( array( 'cid' => $cid ), $chat->status ) );
}

function wplc_typing() {

	$result = "Fail";
	$cid    = wplc_validate_user_call();

	if ( $cid > 0 ) {

		if ( isset( $_POST['user'] ) && isset( $_POST['type'] ) ) {
			TCXChatHelper::set_typing_indicator( sanitize_text_field( $_POST['user'] ), $cid, sanitize_text_field( $_POST['type'] ) );
			die( TCXChatAjaxResponse::success_ajax_respose( array( 'cid' => $cid ) ) );
		}
	}
	die( TCXChatAjaxResponse::error_ajax_respose( $result ) );
}

function wplc_load_client_messages( $chat, $type = "POLL" ) {
	$result   = array();
	$messages = array();
	if ( $type == "POLL" ) {
		$messages = TCXChatHelper::get_chat_messages( $chat->id, "NON_READ", "User" );
	} else if ( $type == "HISTORY" ) {
		$messages = TCXChatHelper::get_chat_messages( $chat->id );
	}

	if ( $messages && is_array( $messages ) ) {
		foreach ( $messages as $message ) {
			$result[ $message->id ] = array(
				"msg"        => TCXChatHelper::decrypt_msg( $message->msg ),
				"added_at"   => $message->timestamp,
				"originates" => $message->originates,
				"file"       => TCXChatHelper::get_message_file( $message )
			);
		};//convert_to_client_messages( $chat,$messages );
		TCXChatHelper::mark_messages_as_read( array_map( function ( $message ) {
			return $message->id;
		}, $messages ) );
	}

	return $result;
}

function wplc_validate_user_call( $check_id = true ) {
	if ( ! check_ajax_referer( 'wplc', 'security' ) ) {
		die( TCXChatAjaxResponse::error_ajax_respose( "Invalid nonce." ) );
	}
	$cid = - 1;
	if ( $check_id ) {
		TCXPhpSessionHelper::start_session();
		$cid = array_key_exists( 'wplc_session_chat_session_id', $_SESSION ) ? $_SESSION['wplc_session_chat_session_id'] : - 1;
		TCXPhpSessionHelper::close_session();
		if ( ! isset( $_POST["cid"] ) || $cid <= 0 || $cid != sanitize_text_field( $_POST["cid"] ) ) {
			TCXPhpSessionHelper::clean_session();
			die( TCXChatAjaxResponse::error_ajax_respose( "Wrong Chat id" ) );
		}
	}

	return $cid;
}

function wplc_send_offline_msg() {
	global $wpdb;
	$email = "";
	if ( isset( $_POST['email'] ) && ! empty( $_POST['email'] ) ) {
		$email = strip_tags( sanitize_text_field( $_POST['email'] ) );
	}
	$name = "";
	if ( isset( $_POST['name'] ) && ! empty( $_POST['name'] ) ) {
		$name = sanitize_text_field( $_POST['name'] );
	}

	$message = "";
	if ( isset( $_POST['message'] ) && ! empty( $_POST['message'] ) ) {
		$message = sanitize_text_field( $_POST['message'] );
	}

	if ( $name != "" && $email != "" && $message != "" ) {
		if ( TCXOfflineMessagesData::add_offline_message( $wpdb, $name, $email, $message ) !== false ) {
			TCXOfflineMessagesHelper::send_offline_message_autorespond( $name, $email );
			TCXOfflineMessagesHelper::send_offline_notification_mail( $name, $email, $message );
			die( TCXChatAjaxResponse::success_ajax_respose( "OK" ) );
		} else {
			die( TCXChatAjaxResponse::error_ajax_respose( "Unable to store message. Please try again later." ) );
		}
	} else {
		die( TCXChatAjaxResponse::error_ajax_respose( "Incomplete request" ) );
	}
}

function wplc_upload_file() {
	$cid = wplc_validate_user_call();
	add_filter( 'upload_dir', 'wplc_set_wplc_upload_dir_filter' );
	foreach ( $_FILES as $file ) {
		$upload_overrides = array( 'test_form' => false );

		$file_info = wp_handle_upload( $file, $upload_overrides );

		$chat_msg = $file_info['url'];

		$fileData           = new stdClass();
		$fileData->FileName = $file['name'];
		$fileData->FileLink = $file_info['url'];
		$fileData->FileSize = $file['size'];

		$wplc_rec_msg = TCXChatHelper::add_chat_message( UserTypes::CLIENT, $cid, $chat_msg, $fileData );

	}
	remove_filter( 'upload_dir', 'wplc_set_wplc_upload_dir_filter' );
	die( TCXChatAjaxResponse::success_ajax_respose( $fileData ) );
}

function wplc_rate_chat() {
	$cid          = sanitize_text_field( $_POST['cid'] );
	$rating_score = sanitize_text_field( $_POST['rate'] );

	if ( TCXChatRatingHelper::set_chat_rating( $cid, $rating_score ) !== false ) {
		die( TCXChatAjaxResponse::success_ajax_respose( array( 'cid' => $cid ) ) );
	} else {
		die( TCXChatAjaxResponse::error_ajax_respose( "There was an error sending your chat rating. Please contact support" ) );
	}
}

