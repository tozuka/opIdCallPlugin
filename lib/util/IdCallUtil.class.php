<?php

class IdCallUtil
{
  const TEST = true;

  private static $rev_mapping = null;
  private static $nicknames = null;

  private static function init()
  {
    if (!is_null(self::$rev_mapping)) return;

    // memberテーブルから、名前を取得
    $rs = Doctrine::getTable('Member')
      ->createQuery()
      ->where('is_active = ? AND is_login_rejected = ?')
      ->execute(array(1, 0));

    $mapping = array();
    foreach ($rs as $member)
    {
      $mapping[$member->id] = self::split_ids($member->name);
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
    foreach ($mapping as $person => $candidates)
    {
      if ($useFirstOneAsNickname)
      {
        self::$nicknames[$person] = self::remove_suffix($candidates[0]);
      }

      foreach ($candidates as $cand)
      {
        $cand = self::remove_suffix($cand);

        if (isset(self::$rev_mapping[$cand]))
        {
          self::$rev_mapping[$cand][] = $person;
        }
        else
        {
          self::$rev_mapping[$cand] = array($person);
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

    preg_match_all('/(ktai|m|)@([-._0-9A-Za-z]+)/',
      $text, $matches1, PREG_PATTERN_ORDER);

    preg_match_all('/(ktai|m|)[@＠]([-._0-9A-Za-z()０-９Ａ-Ｚａ-ｚぁ-んァ-ヴー一-龠]+)/u',
      $text, $matches2, PREG_PATTERN_ORDER);

    $ktai = array_merge($matches1[1], $matches2[1]);
    $matches = array_merge($matches1[2], $matches2[2]);

    $callees = array();
    foreach ($matches as $i => $match)
    {
      $isKtai = ('ktai' === $ktai[$i] || 'm' === $ktai[$i]) ? true : false;
      $callees[] = array(self::remove_suffix($match), $isKtai);
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
      $isKtai = $match[1];
      if (isset(self::$rev_mapping[$nickname]))
      {
        $persons = self::$rev_mapping[$nickname];
        foreach ($persons as $person)
        {
          $key = ($isKtai ? 'm@' : '@').$person;
          if (!isset($checked[$key]))
          {
            $calls[] = array($person, $isKtai);
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
  public static function check_at_call($text, $place = null, $route = null, $author = null, $test_mode = false)
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
      $text_ = $author . '≫' . PHP_EOL;

      foreach (split("\n", $text) as $line)
      {
        $text_ .= '＞ ' . $line . PHP_EOL;
      }

      foreach ($callees as $callee)
      {
        $memberId = $callee[0];
        $isKtai = $callee[1];

        $member = Doctrine::getTable('Member')->find($memberId);
        if (!$member) continue;

        $callee_mail_address = $member->getConfig($isKtai ? 'mobile_address' : 'pc_address');

        $params = array(
          'nickname' => self::$nicknames[$memberId],
          'text' => $text_,
          'place' => $place,
          'route' => $route,
          'author' => $author,
        );
        opMailSend::sendTemplateMail(
          'idCall', $callee_mail_address,
          opConfig::get('admin_mail_address'), $params
        );
        // error_log(sprintf('[DEBUG] send idcall message to #%d (%s)', $memberId, $callee_mail_address));
      }
    }
  }

  // 旧API（なにもしない）
  public static function check_atcall($text, $place = null, $route = null, $author = null, $test_mode = false)
  {
  }

  public function processFormPostSave($event)
  {
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
        break;

      case 'DiaryCommentForm':
        $diaryComment = $form->getObject();
        $diary = $diaryComment->Diary;

        $text = $diaryComment->body;
        $place = $author.'さんの'.$i18n->__('Diary').$i18n->__('Comment');
        $route = '@diary_show?id='.$diary->id.'&comment_count='.$diary->countDiaryComments(true);
        break;

      case 'CommunityEventForm':
        $communityEvent = $form->getObject();

        $text = $communityEvent->body;
        $place = $i18n->__('Community Events').' '.$communityEvent->name;
        $route = '@communityEvent_show?id='.$communityEvent->getId();
        break;

      case 'CommunityEventCommentForm':
        $communityEventComment = $form->getObject();
        $communityEvent = $communityEventComment->CommunityEvent;

        $text = $communityEventComment->body;
        $place = $i18n->__('Community Events').' '.$communityEvent->name.' への'.$i18n->__('Comment');
        $route = '@communityEvent_show?id='.$communityEvent->getId();
        break;

      case 'CommunityTopicForm':
        $communityTopic = $form->getObject();

        $text = $communityTopic->body;
        $place = $i18n->__('Community Topics').' '.$communityTopics->name;
        $route = '@communityTopic_show?id='.$communityTopic->getId();
        break;

      case 'CommunityTopicCommentForm':
        $communityTopicComment = $form->getObject();
        $communityTopic = $communityTopicComment->CommunityTopic;

        $text = $communityTopicComment->body;
        $place = $i18n->__('Community Topics').' '.$communityTopic->name.' への'.$i18n->__('Comment');
        $route = '@communityTopic_show?id='.$communityTopic->getId();
        break;

      case 'ActivityDataForm':
        $activityData = $form->getObject();

        $text = $activityData->body;
        $place = 'アクティビティ';
        $route = 'friend/showActivity';
        break;

      default:
        //error_log('form.post_save event from '.get_class($form).' is not supported.');
        return;
    } 

    IdCallUtil::check_at_call($text, $place, $route, $author);
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

