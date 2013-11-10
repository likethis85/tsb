<?php
/*
 +--------------------------------------------------------------------------
 |   WeCenter [#RELEASE_VERSION#]
 |   ========================================
 |   by WeCenter Software
 |   © 2011 - 2013 WeCenter. All Rights Reserved
 |   http://www.wecenter.com
 |   ========================================
 |   Support: WeCenter@qq.com
 |
 +---------------------------------------------------------------------------
 */

if (!defined('IN_ANWSION')) {
	die ;
}

define('IN_MOBILE', true);

class main extends AWS_CONTROLLER {
	public function get_access_rule() {
		$rule_action['rule_type'] = 'black';
		$rule_action['actions'] = array();

		return $rule_action;
	}

	public function setup() {
		if (!is_mobile()) {
			HTTP::redirect('/');
		}

		if ($_GET['ignore_ua_check'] == 'FALSE') {
			HTTP::set_cookie('_ignore_ua_check', 'FALSE');
		}

		switch ($_GET['act']) {
			default :
				if (!$this -> user_id) {
					HTTP::redirect('/m/login/url-' . base64_encode(get_js_url($_SERVER['QUERY_STRING'])));
				}
				break;

			case 'login' :
			case 'question' :
			case 'answer' :
			case 'register' :
			case 'register_one' :
			case 'register_two' :
			case 'register_three' :
			case 'register_final' :	
			case 'validate_mobile' :
			case 'authcode' :
			case 'weixin_bind_success' :
				// Public page..
				break;
		}

		TPL::import_clean();

		//样式目录可以不用加，比如此处不用写default/***
		TPL::import_css(array('js/mobile/mobile.css','css/tsb.css' ));

		TPL::import_js(array('js/jquery.js', 'js/jquery.form.js', 'js/mobile/framework.js', 'js/mobile/mobile.js', 'js/mobile/aw-mobile-template.js'));
	}

	public function index_action() {
		$this -> crumb(AWS_APP::lang() -> _t('首页'), '/m/');
		
		$nav_menu = $this -> model('menu') -> get_nav_menu_list(null, true);

		unset($nav_menu['feature_ids']);

		TPL::assign('content_nav_menu', $nav_menu);

		TPL::output('m/index');
	}

	public function focus_action() {
		$this -> crumb(AWS_APP::lang() -> _t('我关注的问题'), '/m/focus/');

		TPL::output('m/focus');
	}

	public function invite_action() {
		$this -> crumb(AWS_APP::lang() -> _t('邀请我回答的问题'), '/m/invite/');

		TPL::output('m/invite');
	}

	public function send_pm_action() {
		if (!$_GET['recipient']) {
			HTTP::redirect('/m/inbox/');
		}

		$this -> crumb(AWS_APP::lang() -> _t('撰写私信'), '/send_pm/');

		TPL::assign('recipient', htmlspecialchars($_GET['recipient']));

		TPL::output('m/send_pm');
	}

	public function inbox_action() {
		if ($_GET['dialog_id']) {
			if (!$dialog = $this -> model('message') -> get_dialog_by_id($_GET['dialog_id'])) {
				H::redirect_msg(AWS_APP::lang() -> _t('指定的站内信不存在'), '/m/inbox/');
			}

			$this -> model('message') -> read_message($_GET['dialog_id'], $this -> user_id);

			if ($list = $this -> model('message') -> get_message_by_dialog_id($_GET['dialog_id'], $this -> user_id)) {
				if ($dialog['sender_uid'] != $this -> user_id) {
					$recipient_user = $this -> model('account') -> get_user_info_by_uid($dialog['sender_uid']);
				} else {
					$recipient_user = $this -> model('account') -> get_user_info_by_uid($dialog['recipient_uid']);
				}

				foreach ($list as $key => $value) {
					$value['notice_content'] = FORMAT::parse_links($value['notice_content']);
					$value['user_name'] = $recipient_user['user_name'];
					$value['url_token'] = $recipient_user['url_token'];

					$list_data[] = $value;
				}
			}

			$this -> crumb(AWS_APP::lang() -> _t('私信对话') . ': ' . $recipient_user['user_name'], '/m/inbox/dialog_id-' . intval($_GET['dialog_id']));

			TPL::assign('list', $list_data);

			TPL::assign('recipient_user', $recipient_user);

			TPL::output('m/inbox_read');
		} else {
			$this -> crumb(AWS_APP::lang() -> _t('私信'), '/m/inbox/');

			TPL::output('m/inbox');
		}
	}

	public function question_action() {
		if (!isset($_GET['id'])) {
			HTTP::redirect('/m/explore/');
		}

		if (!$question_id = intval($_GET['id'])) {
			H::redirect_msg(AWS_APP::lang() -> _t('问题不存在或已被删除'), '/m/explore/');
		}

		if ($_GET['notification_id']) {
			$this -> model('notify') -> read_notification($_GET['notification_id'], $this -> user_id, $_GET['ori']);
		}

		if (!$question_info = $this -> model("question") -> get_question_info_by_id($question_id)) {
			H::redirect_msg(AWS_APP::lang() -> _t('问题不存在或已被删除'), '/m/explore/');
		}

		$question_info['redirect'] = $this -> model("question") -> get_redirect($question_info['question_id']);

		if ($question_info['redirect']['target_id']) {
			$target_question = $this -> model("question") -> get_question_info_by_id($question_info['redirect']['target_id']);
		}

		if (is_numeric($_GET['rf']) and $_GET['rf']) {
			if ($from_question = $this -> model("question") -> get_question_info_by_id($_GET['rf'])) {
				$redirect_message[] = AWS_APP::lang() -> _t('从问题') . ' <a href="' . get_js_url('/m/question/' . $_GET['rf'] . '?rf=false') . '">' . $from_question['question_content'] . '</a> ' . AWS_APP::lang() -> _t('跳转而来');
			}
		}

		if ($question_info['redirect'] and !$_GET['rf']) {
			if ($target_question) {
				HTTP::redirect('/m/question/' . $question_info['redirect']['target_id'] . '?rf=' . $question_info['question_id']);
			} else {
				$redirect_message[] = AWS_APP::lang() -> _t('重定向目标问题已被删除, 将不再重定向问题');
			}
		} else if ($question_info['redirect']) {
			if ($target_question) {
				$message = AWS_APP::lang() -> _t('此问题将跳转至') . ' <a href="' . get_js_url('/m/question/' . $question_info['redirect']['target_id'] . '?rf=' . $question_info['question_id']) . '">' . $target_question['question_content'] . '</a>';

				if ($this -> user_id && ($this -> user_info['permission']['is_administortar'] OR $this -> user_info['permission']['is_moderator'] OR (!$this -> question_info['lock'] AND $this -> user_info['permission']['redirect_question']))) {
					$message .= '&nbsp; (<a href="javascript:;" onclick="ajax_request(G_BASE_URL + \'/question/ajax/redirect/\', \'item_id=' . $question_id . '\');">' . AWS_APP::lang() -> _t('撤消重定向') . '</a>)';
				}

				$redirect_message[] = $message;
			} else {
				$redirect_message[] = AWS_APP::lang() -> _t('重定向目标问题已被删除, 将不再重定向问题');
			}
		}

		if ($question_info['has_attach']) {
			$question_info['attachs'] = $this -> model('publish') -> get_attach('question', $question_info['question_id'], 'min');
			$question_info['attachs_ids'] = FORMAT::parse_attachs($question_info['question_detail'], true);
		}

		$this -> model('question') -> update_views($question_id);

		if (get_setting('answer_unique') == 'Y') {
			if ($this -> model('answer') -> has_answer_by_uid($question_id, $this -> user_id)) {
				TPL::assign('user_answered', TRUE);
			}
		}

		$question_info['question_detail'] = FORMAT::parse_attachs(FORMAT::parse_links(nl2br(FORMAT::parse_markdown($question_info['question_detail']))));

		TPL::assign('question_id', $question_id);
		TPL::assign('question_info', $question_info);
		TPL::assign('question_focus', $this -> model("question") -> has_focus_question($question_id, $this -> user_id));
		TPL::assign('question_topics', $this -> model('question') -> get_question_topic_by_question_id($question_id));

		$this -> crumb($question_info['question_content'], '/m/question/' . $question_id);

		TPL::assign('redirect_message', $redirect_message);

		if ($this -> user_id) {
			TPL::assign('question_thanks', $this -> model('question') -> get_question_thanks($question_info['question_id'], $this -> user_id));

			TPL::assign('invite_users', $this -> model('question') -> get_invite_users($question_info['question_id'], array($question_info['published_uid'])));

			//TPL::assign('user_follow_check', $this->model("follow")->user_follow_check($this->user_id, $question_info['published_uid']));

			if ($this -> user_info['draft_count'] > 0) {
				TPL::assign('draft_content', $this -> model('draft') -> get_data($question_info['question_id'], 'answer', $this -> user_id));
			}
		}

		$answer_list = $this -> model('answer') -> get_answer_list_by_question_id($question_info['question_id'], calc_page_limit($_GET['page'], 20), null, 'agree_count DESC, against_count ASC, add_time ASC');

		// 最佳回复预留
		$answers[0] = '';

		if (!is_array($answer_list)) {
			$answer_list = array();
		}

		$answer_ids = array();
		$answer_uids = array();

		foreach ($answer_list as $answer) {
			$answer_ids[] = $answer['answer_id'];
			$answer_uids[] = $answer['uid'];

			if ($answer['has_attach']) {
				$has_attach_answer_ids[] = $answer['answer_id'];
			}
		}

		if (!in_array($question_info['best_answer'], $answer_ids) AND intval($_GET['page']) < 2) {
			$answer_list = array_merge($this -> model('answer') -> get_answer_list_by_question_id($question_info['question_id'], 1, 'answer_id = ' . $question_info['best_answer']), $answer_list);
		}

		if ($answer_ids) {
			$answer_agree_users = $this -> model('answer') -> get_vote_user_by_answer_ids($answer_ids);

			$answer_vote_status = $this -> model('answer') -> get_answer_vote_status($answer_ids, $this -> user_id);

			$answer_users_rated_thanks = $this -> model('answer') -> users_rated('thanks', $answer_ids, $this -> user_id);
			$answer_users_rated_uninterested = $this -> model('answer') -> users_rated('uninterested', $answer_ids, $this -> user_id);
			$answer_attachs = $this -> model('publish') -> get_attachs('answer', $has_attach_answer_ids, 'min');
		}

		foreach ($answer_list as $answer) {
			if ($answer['has_attach']) {
				$answer['attachs'] = $answer_attachs[$answer['answer_id']];

				$answer['insert_attach_ids'] = FORMAT::parse_attachs($answer['answer_content'], true);
			}

			$answer['user_rated_thanks'] = $answer_users_rated_thanks[$answer['answer_id']];
			$answer['user_rated_uninterested'] = $answer_users_rated_uninterested[$answer['answer_id']];

			$answer['answer_content'] = $this -> model('question') -> parse_at_user(FORMAT::parse_attachs(nl2br(FORMAT::parse_markdown($answer['answer_content']))));

			$answer['agree_users'] = $answer_agree_users[$answer['answer_id']];
			$answer['agree_status'] = $answer_vote_status[$answer['answer_id']];

			if ($question_info['best_answer'] == $answer['answer_id'] AND intval($_GET['page']) < 2) {
				$answers[0] = $answer;
			} else {
				$answers[] = $answer;
			}
		}

		if (!$answers[0]) {
			unset($answers[0]);
		}

		if (get_setting('answer_unique') == 'Y') {
			if ($this -> model('answer') -> has_answer_by_uid($question_info['question_id'], $this -> user_id)) {
				TPL::assign('user_answered', TRUE);
			}
		}

		TPL::assign('answers_list', $answers);

		TPL::assign('question_related_list', $this -> model('question') -> get_related_question_list($question_info['question_id'], $question_info['question_content']));

		$total_page = $question_info['answer_count'] / 20;

		if ($total_page > intval($total_page)) {
			$total_page = intval($total_page) + 1;
		}

		if (!$_GET['page']) {
			$_GET['page'] = 1;
		}

		if ($_GET['page'] < $total_page) {
			$_GET['page'] = $_GET['page'] + 1;

			TPL::assign('next_page', $_GET['page']);
		}

		TPL::output('m/question');
	}

	public function login_action() {
		$url = base64_decode($_GET['url']);

		if (($this -> user_id AND !$_GET['weixin_id']) OR $this -> user_info['weixin_id']) {
			if ($url) {
				header('Location: ' . $url);
			} else {
				HTTP::redirect('/m/');
			}
		}

		if ($url) {
			$return_url = $url;
		} else if (strstr($_SERVER['HTTP_REFERER'], '/m/')) {
			$return_url = $_SERVER['HTTP_REFERER'];
		} else {
			$return_url = get_js_url('/m/');
		}

		TPL::assign('body_class', 'explore-body');
		TPL::assign('return_url', strip_tags($return_url));

		$this -> crumb(AWS_APP::lang() -> _t('登录'), '/m/login/');

		TPL::output('m/login');
	}

	public function register_one_action() {

		if (($this -> user_id AND !$_GET['weixin_id']) OR $this -> user_info['weixin_id']) {
			if ($url) {
				header('Location: ' . $url);
			} else {
				HTTP::redirect('/m/');
			}
		}

		if ($this -> user_id AND $_GET['invite_question_id']) {
			if ($invite_question_id = intval($_GET['invite_question_id'])) {
				HTTP::redirect('/question/' . $invite_question_id);
			}
		}

		if (get_setting('invite_reg_only') == 'Y' AND !$_GET['icode']) {
			H::redirect_msg(AWS_APP::lang() -> _t('本站只能通过邀请注册'), '/');
		}

		if ($_GET['icode']) {
			if ($this -> model('invitation') -> check_code_available($_GET['icode'])) {
				TPL::assign('icode', $_GET['icode']);
			} else {
				H::redirect_msg(AWS_APP::lang() -> _t('邀请码无效或已经使用，请使用新的邀请码'), '/');
			}
		}

		$this -> crumb(AWS_APP::lang() -> _t('手机号码注册'), '/m/phone/');

		TPL::assign('body_class', 'explore-body');

		TPL::output('m/register_one');
	}
	
	public function register_two_action(){
		//输入验证码页面	
		
		$this -> crumb(AWS_APP::lang() -> _t('手机验证码'), '/m/phone_authcode/');

		TPL::assign('body_class', 'explore-body');

		TPL::output('m/register_two');
	}

	public function register_three_action() {
		//校验验证码
		if($_POST['authcode']!=AWS_APP::session()->authcode){
			H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang() -> _t('验证码错误')));
		}
		
		$this -> crumb(AWS_APP::lang() -> _t('注册'), '/m/register_three/');

		H::ajax_json_output(AWS_APP::RSM(array('url' => get_js_url('/m/register_final/')), 1, null));

	}
	
	public function register_final_action() {
		//输入用户名和密码
		
		$this -> crumb(AWS_APP::lang() -> _t('用户名密码'), '/m/phone_authcode/');

		TPL::assign('body_class', 'explore-body');

		TPL::output('m/register_final');

	}

	public function explore_action() {
		$this -> crumb(AWS_APP::lang() -> _t('发现'), '/m/explore/');

		$nav_menu = $this -> model('menu') -> get_nav_menu_list(null, true);

		//TPL::assign('feature_ids', $nav_menu['feature_ids']);

		unset($nav_menu['feature_ids']);

		TPL::assign('content_nav_menu', $nav_menu);

		TPL::assign('sidebar_hot_topics', $this -> model('module') -> sidebar_hot_topics($_GET['category']));

		if ($_GET['feature_id']) {
			TPL::assign('feature_info', $this -> model('feature') -> get_feature_by_id($_GET['feature_id']));
		}

		if ($_GET['category']) {
			TPL::assign('category_info', $this -> model('system') -> get_category_info($_GET['category']));
		}

		TPL::output('m/explore');
	}

	public function people_action() {
		if (isset($_GET['notification_id'])) {
			$this -> model('notify') -> read_notification($_GET['notification_id'], $this -> user_id);
		}

		//if ((is_numeric($_GET['id']) AND intval($_GET['id']) == $this->user_id AND $this->user_id) OR ($this->user_id AND !$_GET['id']))
		if ($this -> user_id AND !$_GET['id']) {
			$user = $this -> user_info;
		} else {
			if (is_numeric($_GET['id'])) {
				if (!$user = $this -> model('account') -> get_user_info_by_uid($_GET['id'], TRUE)) {
					$user = $this -> model('account') -> get_user_info_by_username($_GET['id'], TRUE);
				}
			} else if ($user = $this -> model('account') -> get_user_info_by_username($_GET['id'], TRUE)) {

			} else {
				$user = $this -> model('account') -> get_user_info_by_url_token($_GET['id'], TRUE);
			}

			if (!$user) {
				H::redirect_msg(AWS_APP::lang() -> _t('用户不存在'), '/m/');
			}

			if (urldecode($user['url_token']) != $_GET['id']) {
				HTTP::redirect('/m/people/' . $user['url_token']);
			}

			$this -> model('people') -> update_views($user['uid']);
		}

		TPL::assign('user', $user);

		TPL::assign('user_follow_check', $this -> model('follow') -> user_follow_check($this -> user_id, $user['uid']));

		TPL::assign('reputation_topics', $this -> model('people') -> get_user_reputation_topic($user['uid'], $user['reputation'], 12));
		TPL::assign('fans_list', $this -> model('follow') -> get_user_fans($user['uid'], 5));
		TPL::assign('friends_list', $this -> model('follow') -> get_user_friends($user['uid'], 5));
		TPL::assign('focus_topics', $this -> model('topic') -> get_focus_topic_list($user['uid'], 10));

		TPL::assign('user_actions_questions', $this -> model('account') -> get_user_actions($user['uid'], 5, ACTION_LOG::ADD_QUESTION, $this -> user_id));
		TPL::assign('user_actions_answers', $this -> model('account') -> get_user_actions($user['uid'], 5, ACTION_LOG::ANSWER_QUESTION, $this -> user_id));

		$this -> crumb(AWS_APP::lang() -> _t('%s 的个人主页', $user['user_name']), '/m/people/' . $user['url_token']);

		TPL::output('m/people');
	}

	public function search_action() {
		if ($_POST['q']) {
			HTTP::redirect('/m/search/q-' . base64_encode($_POST['q']));
		}

		$keyword = htmlspecialchars(base64_decode($_GET['q']));

		$this -> crumb($keyword, 'm/search/q-' . urlencode($keyword));

		if (!$keyword) {
			HTTP::redirect('/m/');
		}

		TPL::assign('keyword', $keyword);
		TPL::assign('split_keyword', implode(' ', $this -> model('system') -> analysis_keyword($keyword)));

		TPL::output('m/search');
	}

	public function topic_square_action() {
		$this -> crumb(AWS_APP::lang() -> _t('话题广场'), '/m/topic_square/');

		if ($topics_hot_list = $this -> model('topic') -> get_topic_list(null, 'discuss_count DESC', 5) AND $this -> user_id) {
			foreach ($topics_hot_list AS $key => $val) {
				$topics_ids[] = $val['topic_id'];
			}

			if ($topics_ids) {
				$has_focus_topics = $this -> model('topic') -> has_focus_topics($this -> user_id, $topics_ids);
			}

			foreach ($topics_hot_list AS $key => $val) {
				$topics_hot_list[$key]['has_focus'] = $has_focus_topics[$val['topic_id']];
			}
		}

		TPL::assign('topics_hot_list', $topics_hot_list);

		TPL::output('m/topic_square');
	}

	public function topic_action() {
		if (is_numeric($_GET['id'])) {
			if (!$topic_info = $this -> model('topic') -> get_topic_by_id($_GET['id'])) {
				$topic_info = $this -> model('topic') -> get_topic_by_title($_GET['id']);
			}
		} else if (!$topic_info = $this -> model('topic') -> get_topic_by_title($_GET['id'])) {
			$topic_info = $this -> model('topic') -> get_topic_by_url_token($_GET['id']);
		}

		if (!$topic_info) {
			H::redirect_msg(AWS_APP::lang() -> _t('话题不存在'), '/m/');
		}

		if ($topic_info['merged_id']) {
			HTTP::redirect('/m/topic/' . $topic_info['merged_id'] . '?rf=' . $topic_info['topic_id']);
		}

		if (urldecode($topic_info['url_token']) != $_GET['id']) {
			HTTP::redirect('/m/topic/' . $topic_info['url_token'] . '?rf=' . $_GET['rf']);
		}

		if (is_numeric($_GET['rf']) and $_GET['rf']) {
			if ($from_topic = $this -> model('topic') -> get_topic_by_id($_GET['rf'])) {
				$redirect_message[] = AWS_APP::lang() -> _t('话题 (%s) 已与当前话题合并', $from_topic['topic_title']);
			}
		}

		if ($merged_topics = $this -> model('topic') -> get_merged_topic_ids($topic_info['topic_id'])) {
			foreach ($merged_topics AS $key => $val) {
				$merged_topic_ids[] = $val['source_id'];
			}

			$contents_topic_id = $topic_info['topic_id'] . ',' . implode(',', $merged_topic_ids);

			if ($merged_topics_info = $this -> model('topic') -> get_topics_by_ids($merged_topic_ids)) {
				foreach ($merged_topics_info AS $key => $val) {
					$contents_topic_title[] = $val['topic_title'];
				}
			}

			$contents_topic_title = $topic_info['topic_title'] . ',' . implode(',', $contents_topic_title);
		} else {
			$contents_topic_id = $topic_info['topic_id'];
			$contents_topic_title = $topic_info['topic_title'];
		}

		TPL::assign('contents_topic_id', $contents_topic_id);
		TPL::assign('contents_topic_title', $contents_topic_title);

		$topic_info['has_focus'] = $this -> model('topic') -> has_focus_topic($this -> user_id, $topic_info['topic_id']);

		TPL::assign('topic_info', $topic_info);

		$this -> crumb(AWS_APP::lang() -> _t('话题'), '/m/topic/');

		$this -> crumb($topic_info['topic_title'], '/m/topic/' . rawurlencode($topic_info['topic_title']));

		TPL::assign('redirect_message', $redirect_message);

		TPL::assign('best_answer_users', $this -> model('topic') -> get_best_answer_users($topic_info['topic_id'], $this -> user_id, 5));

		TPL::output('m/topic');
	}

	public function notifications_action() {
		$this -> crumb(AWS_APP::lang() -> _t('通知'), '/m/notifications/');

		TPL::output('m/notifications');
	}

	public function weixin_bind_success_action() {
		H::redirect_msg(AWS_APP::lang() -> _t('微信绑定成功, 请返回'));
	}

	public function draft_action() {
		$this -> crumb(AWS_APP::lang() -> _t('草稿'), '/m/draft/');

		TPL::output('m/draft');
	}

	public function settings_action() {
		TPL::assign('notification_settings', $this -> model('account') -> get_notification_setting_by_uid($this -> user_id));
		TPL::assign('notify_actions', $this -> model('notify') -> notify_action_details);

		TPL::output('m/settings');
	}

	

	public function publish_action() {
		if ($_GET['id']) {
			if (!$question_info = $this -> model('question') -> get_question_info_by_id($_GET['id'])) {
				H::redirect_msg(AWS_APP::lang() -> _t('指定问题不存在'));
			}

			if (!$this -> user_info['permission']['is_administortar'] AND !$this -> user_info['permission']['is_moderator'] AND !$this -> user_info['permission']['edit_question']) {
				if ($question_info['published_uid'] != $this -> user_id) {
					H::redirect_msg(AWS_APP::lang() -> _t('你没有权限编辑这个问题'), '/m/question/' . $_GET['id']);
				}
			}

			TPL::assign('question_info', $question_info);
		} else if (!$this -> user_info['permission']['publish_question']) {
			H::redirect_msg(AWS_APP::lang() -> _t('你所在用户组没有权限发布问题'));
		} else if ($this -> is_post() AND $_POST['question_detail']) {
			TPL::assign('question_info', array('question_content' => $_POST['question_content'], 'question_detail' => $_POST['question_detail']));

			$question_info['category_id'] = $_POST['category_id'];
		} else {
			$draft_content = $this -> model('draft') -> get_data(1, 'question', $this -> user_id);

			TPL::assign('question_info', array('question_content' => $_POST['question_content'], 'question_detail' => $draft_content['message']));
		}

		if ($this -> user_info['integral'] < 0 AND get_setting('integral_system_enabled') == 'Y' AND !$_GET['id']) {
			H::redirect_msg(AWS_APP::lang() -> _t('你的剩余积分已经不足以进行此操作'));
		}

		if (!$question_info['category_id'] AND $_GET['category_id']) {
			$question_info['category_id'] = $_GET['category_id'];
		}

		if (get_setting('category_enable') == 'Y') {
			TPL::assign('question_category_list', $this -> model('system') -> build_category_html('question', 0, $question_info['category_id']));
		}

		//TPL::assign('human_valid', human_valid('question_valid_hour'));

		TPL::output('m/publish');
	}

}
