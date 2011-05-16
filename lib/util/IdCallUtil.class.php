<?php

function http_post($url, $data)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $response = curl_exec($ch);
  curl_close($ch);

  return $response;
}

function is_valid_phone_number($tel)
{
  if (!$tel) return false;

  $len = strlen($tel);
  return ((10 === $len || 11 === $len) && '0' === $tel[0]) ? true : false;
}

function pushcall($tel)
{
  $pushcallApi = sfConfig::get('app_id_call_pushcall_api', array());
  if (!$pushcallApi) return;

  $apiUrl    = $pushcallApi['url'];
  $siteid    = $pushcallApi['siteid'];
  $key       = $pushcallApi['key'];
  //$resulturl = $pushcallApi['resulturl'];
  $resulturl = null;

  $data = sprintf('siteid=%s&key=%s&tel=%s', urlencode($siteid), urlencode($key), $tel);
  if ($resulturl) $data .= '&'.urlencode($resulturl);

  $json = http_post($apiUrl, $data);
  if ($json) return json_decode($json);
}

class IdCallUtil
{
  const TEST = true;

  const PC_CALL     = 1;
  const MOBILE_CALL = 2;
  const PHONE_CALL  = 4;

  private static $rev_mapping = null;
  private static $valid_recipients = null;
  private static $nicknames = null;

  private static function init()
  {
    // 初期化済
    if (!is_null(self::$rev_mapping))
    {
      return;
    }

    // pc_frontend, mobile_frontend 以外の場合は何も行わない
    $userInstanceName = version_compare(OPENPNE_VERSION, '3.5.0-dev', '>') ? 'opSecurityUser' : 'sfOpenPNESecurityUser';
    if (!(sfContext::getInstance()->getUser() instanceof $userInstanceName))
    {
      return;
    }

    $me = sfContext::getInstance()->getUser()->getMember();
    $rev_mapping_cache = $me->getConfig('id_call_rev_mapping');
    if ($rev_mapping_cache)
    {
      self::$rev_mapping = unserialize($rev_mapping_cache);
      self::$nicknames = unserialize($me->getConfig('id_call_nicknames'));
      return;
    }

    self::$rev_mapping = array();
    self::$nicknames = array();
    self::$valid_recipients = self::validRecipients($me->getId());

    $mapping = array();
    foreach (self::$valid_recipients as $memberId => $name)
    {
      $mapping[$memberId] = self::split_ids($name);
    }
    self::set_mapping($mapping, true); // ここの先頭項目を通知時の呼称とする

    // ymlファイルの設定を読み込んで追加
    $mapping = sfConfig::get('app_id_call_mapping', array());
    if (!is_null($mapping))
    {
      self::set_mapping($mapping, false);
    }

    // プロフィール設定で本人が指定したニックネームやコールIDがあれば追加
    $profileNames = array('fullname', 'nickname', 'call_id');
    foreach ($profileNames as $name)
    {
      self::load_mapping_from_profile($name);
    }

    $me->setConfig('id_call_rev_mapping', serialize(self::$rev_mapping));
    $me->setConfig('id_call_nicknames', serialize(self::$nicknames));
  }

  private static function validRecipients($myMemberId = null)
  {
    if (!$myMemberId)
    {
      $myMemberId = sfContext::getInstance()->getUser()->getMember()->getId();
    }

    // memberテーブルから、名前を取得
    $rs = Doctrine::getTable('Member')
      ->createQuery()
      ->where('is_active = ? AND is_login_rejected = ?')
      ->execute(array(1, 0));

    $validRecipients = array();

    foreach ($rs as $member)
    {
      if ($member->id == $myMemberId)
      {
        $validRecipients[(int)$member->id] = $member->name;
        continue;
      }

      $relation = Doctrine::getTable('MemberRelationship')->retrieveByFromAndTo($myMemberId, $member->id);
      if ($relation)
      {
        if ($relation->isAccessBlocked() || $relation->getIsAccessBlock())
        {
          continue;
        }
        if ($relation->isFriend())
        {
          $validRecipients[(int)$member->id] = $member->name;
        }
      }
    }

    return $validRecipients;
  }

  private static function split_ids($str)
  {
    $mapping = array();
    foreach (preg_split('/[\s,　]+/u', $str) as $callId)
    {
      $callId = preg_replace('/^[@＠]/u', '', $callId);
      $mapping[] = $callId;
    }

    return $mapping;
  }

  public static function load_mapping_from_profile($profileName)
  {
    $profile = Doctrine::getTable('Profile')->retrieveByName($profileName);
    if (!$profile) return false;

    $rs = Doctrine::getTable('MemberProfile')
      ->createQuery()
      ->where('profile_id = ?')
      ->execute(array($profile->id));

    $mapping = array();
    foreach ($rs as $memberProfile)
    {
      $memberId = $memberProfile->getMemberId();
      $mapping[$memberId] = self::split_ids($memberProfile->getValue());
    }
    self::set_mapping($mapping, false);
  }

  public static function set_mapping($mapping, $useFirstOneAsNickname = true)
  {
    foreach ($mapping as $memberId => $candidates)
    {
      if ($useFirstOneAsNickname)
      {
        self::$nicknames[(int)$memberId] = self::remove_suffix($candidates[0]);
      }

      if (!isset(self::$valid_recipients[(int)$memberId])) continue;

      foreach ($candidates as $cand)
      {
        $cand = self::remove_suffix($cand);

        if (isset(self::$rev_mapping[$cand]))
        {
          self::$rev_mapping[$cand][] = $memberId;
        }
        else
        {
          self::$rev_mapping[$cand] = array($memberId);
        }
      }
    }
  }

  private static function extract_callees($text)
  {
    if (0 === strncmp($text, '%nocall', 7))
    {
      return array();
    }

    preg_match_all('/(ktai|m|)([@]+)([-._0-9A-Za-z]+)/',
      $text, $matches1, PREG_PATTERN_ORDER);

    preg_match_all('/(ktai|m|)([@＠]+)([-._0-9A-Za-z()０-９Ａ-Ｚａ-ｚぁ-んァ-ヴー一-龠]+)/u',
      $text, $matches2, PREG_PATTERN_ORDER);

    $ktai = array_merge($matches1[1], $matches2[1]);
    $atmarks = array_merge($matches1[2], $matches2[2]);
    $matches = array_merge($matches1[3], $matches2[3]);

    $callees = array();
    foreach ($matches as $i => $match)
    {
      $level = mb_strlen($atmarks[$i]);
      $body = self::remove_suffix($match);

      // 一斉コールはレベル1のみ許容
      if ('all' == $body) $level = 1;

      if ($level >= 3)
      {
        $callees[] = array($body, self::PHONE_CALL);
      }
      if ($level >= 2)
      {
        $callees[] = array($body, self::MOBILE_CALL);
        $callees[] = array($body, self::PC_CALL);
      }
      else
      {
        $targetChannel = ('ktai' === $ktai[$i] || 'm' === $ktai[$i]) ? self::MOBILE_CALL : self::PC_CALL;
        $callees[] = array($body, $targetChannel);
      }
    }

    return $callees;
  }

  private static function remove_suffix($name)
  {
    return preg_replace('/(様|殿|氏|君|さま|さん|くん|ちゃん|ぽん|のすけ|っち)$/u', '', $name);
  }

  private static function eliminate($matches, $test_mode = false)
  { 
    if (is_null(self::$rev_mapping)) return array();

    $calls = array();
    $checked = array();
    $unsolvedNicknames = array();
    foreach ($matches as $match)
    {
      $nickname = strtolower($match[0]);
      $targetChannel = (int)$match[1];
      if (isset(self::$rev_mapping[$nickname]))
      {
        $persons = self::$rev_mapping[$nickname];
        foreach ($persons as $person)
        {
          $key = $targetChannel.'@'.$person;
          if (!isset($checked[$key]))
          {
            $calls[] = array($person, $targetChannel);
            $checked[$key] = true;
          }
        }
      }
      else
      {
        $unsolvedNicknames[$nickname] = true;
      }
    }
  
    foreach ($unsolvedNicknames as $nickname => $dummy)
    {
      $errorMsg = 'unsolved nickname: '.$nickname;

      if ($test_mode)
      {
        throw new Exception($errorMsg);
      }

      error_log($errorMsg);
    }

    return $calls;
  }

  // テキストに含まれる＠コールを抽出し、本人っぽい人たちにお知らせ
  public static function check_at_call($text, $place = null, $route = null, $author = null, $reply_route_params = null, $test_mode = false)
  {
    self::init();

    if (is_null($author))
    {
      $author = sfContext::getInstance()->getUser()->getMember()->getName();
    }

    $callees = self::eliminate(self::extract_callees($text), $test_mode);
    if (empty($callees)) return;

    if ($test_mode)
    {
      if (!empty($callees)) sort($callees);

      return $callees;
    }
    else
    {
      sfContext::getInstance()->getConfiguration()->loadHelpers('opIdCallUtil');
      $text_ = $author . '≫' . PHP_EOL;

      foreach (split("\n", $text) as $line)
      {
        $text_ .= '＞ ' . $line . PHP_EOL;
      }

      foreach ($callees as $callee)
      {
        $memberId = (int)$callee[0];
        $targetChannel = (int)$callee[1];

        $member = Doctrine::getTable('Member')->find($memberId);
        if (!$member) continue;

        
        if (self::PC_CALL === $targetChannel || self::MOBILE_CALL === $targetChannel)
        {
          $callee_mail_address = $member->getConfig(self::MOBILE_CALL === $targetChannel ? 'mobile_address' : 'pc_address');

          $params = array(
            'nickname' => self::$nicknames[$memberId],
            'text' => $text_,
            'place' => $place,
            'route' => $route,
            'author' => $author,
            'reply_to' => self::MOBILE_CALL === $targetChannel ? op_id_call_generate_mail($reply_route_params, $member) : false,
          );
          opMailSend::sendTemplateMail(
            'idCall', $callee_mail_address,
            opConfig::get('admin_mail_address'), $params
          );
          error_log(sprintf('[DEBUG] send idcall message to #%d (%s <%s>)', $memberId, self::$nicknames[$memberId], $callee_mail_address));
        }

        if (self::PHONE_CALL === $targetChannel)
        {
          $tel = Doctrine::getTable('MemberProfile')->retrieveByMemberIdAndProfileName($member->id, 'tel');
          $tel = preg_replace('/[^0-9]/', '', $tel);
          if (is_valid_phone_number($tel))
          {
            $result = pushcall($tel);
            error_log(sprintf('[DEBUG] send idcall message to #%d (%s <%s>)', $memberId, self::$nicknames[$memberId], $tel));
          }
        }
      }
    }
  }

  // 旧API（なにもしない）
  public static function check_atcall($text, $place = null, $route = null, $author = null, $test_mode = false)
  {
  }

  public function processFormPostSave($event)
  {
    $userInstanceName = version_compare(OPENPNE_VERSION, '3.5.0-dev', '>') ? 'opSecurityUser' : 'sfOpenPNESecurityUser';
    if (!(sfContext::getInstance()->getUser() instanceof $userInstanceName))
    {
      return false;
    }

    $form = $event->getSubject();
    $author = sfContext::getInstance()->getUser()->getMember()->getName();
    $i18n = sfContext::getInstance()->getI18N();

    switch (get_class($form))
    {
      case 'DiaryForm':
        $diary = $form->getObject();

        $text = $diary->body;
        $place = $author.'さんの'.$i18n->__('Diary');
        $route = '@diary_show?id='.$diary->id;
        $reply_route_params = array('mail_op_id_call_diary_reply' => array(
          'id' => $diary->id,
        ));
        break;

      case 'DiaryCommentForm':
        $diaryComment = $form->getObject();
        $diary = $diaryComment->Diary;

        $text = $diaryComment->body;
        $place = $author.'さんの'.$i18n->__('Diary').$i18n->__('Comment');
        $route = '@diary_show?id='.$diary->id.'&comment_count='.$diary->countDiaryComments(true);
        $reply_route_params = array('mail_op_id_call_diary_comment_reply' => array(
          'id' => $diaryComment->id,
        ));
        break;

      case 'CommunityEventForm':
        $communityEvent = $form->getObject();

        $text = $communityEvent->body;
        $place = $i18n->__('Community Events').' '.$communityEvent->name;
        $route = '@communityEvent_show?id='.$communityEvent->getId();
        $reply_route_params = array('mail_op_id_call_community_event_reply' => array(
            'id' => $communityEvent->id,
        ));
        break;

      case 'CommunityEventCommentForm':
        $communityEventComment = $form->getObject();
        $communityEvent = $communityEventComment->CommunityEvent;

        $text = $communityEventComment->body;
        $place = $i18n->__('Community Events').' '.$communityEvent->name.' への'.$i18n->__('Comment');
        $route = '@communityEvent_show?id='.$communityEvent->getId();
        $reply_route_params = array('mail_op_id_call_community_event_comment_reply' => array(
          'id' => $communityEventComment->id,
        ));
        break;

      case 'CommunityTopicForm':
        $communityTopic = $form->getObject();

        $text = $communityTopic->body;
        $place = $i18n->__('Community Topics').' '.$communityTopics->name;
        $route = '@communityTopic_show?id='.$communityTopic->getId();
        $reply_route_params = array('mail_op_id_call_community_topic_reply' => array(
          'id' => $communityTopic->id,
        ));
        break;

      case 'CommunityTopicCommentForm':
        $communityTopicComment = $form->getObject();
        $communityTopic = $communityTopicComment->CommunityTopic;

        $text = $communityTopicComment->body;
        $place = $i18n->__('Community Topics').' '.$communityTopic->name.' への'.$i18n->__('Comment');
        $route = '@communityTopic_show?id='.$communityTopic->getId();
        $reply_route_params = array('mail_op_id_call_community_topic_comment_reply' => array(
          'id' => $communityTopicComment->id,
        ));
        break;

      case 'ActivityDataForm':
        $activityData = $form->getObject();

        $text = $activityData->body;
        $place = 'アクティビティ';
        $route = 'friend/showActivity';
        $reply_route_params = array('mail_op_id_call_activity_reply' => array(
          'id' => $activityData->id,
        ));
        break;

      default:
        //error_log('form.post_save event from '.get_class($form).' is not supported.');
        return;
    } 

    IdCallUtil::check_at_call($text, $place, $route, $author, $reply_route_params);
  }

  public static function debug($msg)
  {
    error_log('[IdCallUtil] '.$msg);
  }
  public static function say($msg)
  {
    echo $msg;
  }
}

