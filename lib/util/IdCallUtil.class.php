<?php

class IdCallUtil
{
  const TEST = true;

  private static $rev_mapping = null;
  private static $nicknames = null;

  private static function init()
  {
    if (!is_null(self::$rev_mapping)) return;

    $mapping = sfConfig::get('app_id_call_mapping', array());
    self::set_mapping($mapping);
  }

  public static function set_mapping($mapping)
  {
    foreach ($mapping as $person => $candidates)
    {
      self::$nicknames[$person] = $candidates[0];

      foreach ($candidates as $cand)
      {
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
    preg_match_all('/(ktai|m|)@([-._0-9A-Za-z]+)/',
      $text, $matches1, PREG_PATTERN_ORDER);

    preg_match_all('/(ktai|m|)[@＠]([-._0-9A-Za-z()０-９Ａ-Ｚａ-ｚぁ-んァ-ヴー一-龠]+)/u',
      $text, $matches2, PREG_PATTERN_ORDER);

    $ktai = array_merge($matches1[1], $matches2[1]);
    $matches = array_merge($matches1[2], $matches2[2]);

    $callees = array();
    foreach ($matches as $i => $match)
    {
      $nickname = preg_replace('/(さん|くん|ちゃん|ぽん|のすけ|っち)$/u', '', $match);
      $isKtai = ('ktai' === $ktai[$i] || 'm' === $ktai[$i]) ? true : false;
      $callees[] = array($nickname, $isKtai);
    }

    return $callees;
  }


  private static function eliminate($matches)
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
          if (!isset($checked[$person]))
          {
            $calls[] = array($person, $isKtai);
            $checked[$person] = true;
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
      error_log('unsolved nickname: '.$nickname);
    }

    return $calls;
  }

  private static function notify_call($callee, $msg)
  {
    echo '----' . PHP_EOL;
    echo self::$nicknames[$callee].'さん'.PHP_EOL;
    echo PHP_EOL;
    echo $msg;
    echo PHP_EOL;
  }

  // 通知文を生成
  private static function generate_notify_call_message($text, $title, $url)
  {
    $msg = '';

    if ($title)
    {
      $msg .= $title . ' で';
    }
    $msg .= '呼ばれているかもです。'.PHP_EOL;

    if ($url)
    {
      $msg .= $url . PHP_EOL;
    }

    $msg .= PHP_EOL;

    foreach (split("\n", $text) as $line)
    {
      $msg .= '> ' . $line . PHP_EOL;
      $msg .= '...'.PHP_EOL; break;
    }

    return $msg;
  }

  // テキストに含まれる＠コールを抽出し、本人っぽい人たちにお知らせ
  public static function check_atcall($text, $place = null, $url = null, $test_mode = false)
  {
    self::init();

    $callees = self::eliminate( self::extract_callees($text) );
    if (empty($callees)) return;

    if ($test_mode)
    {
      if (!empty($callees))
      {
        $msg = self::generate_notify_call_message($text, $place, $url);
        foreach ($callees as $callee) self::notify_call($callee[0], $msg);
      }
      sort($callees);
      return $callees;
    }
    else
    {
      $text_ = '';
      foreach (split("\n", $text) as $line)
      {
        $text_ .= '＞ ' . $line . PHP_EOL;
      }

      foreach ($callees as $callee)
      {
        $memberId = $callee[0];
        $isKtai = $callee[1];

        $member = Doctrine::getTable('Member')->find($memberId);
        $callee_mail_address = $member->getConfig($isKtai ? 'mobile_address' : 'pc_address');

        $params = array(
          'nickname' => self::$nicknames[$memberId],
          'text' => $text_,
          'place' => $place,
          'url' => $url,
        );
        opMailSend::sendTemplateMail(
          'idCall', $callee_mail_address,
          opConfig::get('admin_mail_address'), $params
        );
      }
    }
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

