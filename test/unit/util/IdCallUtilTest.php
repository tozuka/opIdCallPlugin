<?php

include(dirname(__FILE__).'/../../bootstrap/unit.php');
# include(dirname(__FILE__).'/../../bootstrap/database.php');

function idcall_test($t, $text, $expectedRecipientIds, $expectedException = null, $remark = null)
{
  $dummyRouting = '@diary_show?id=1234';
  $dummyAuthor = 'unit test';

  $remark = is_null($remark) ? $text : ($text.' ※'.$remark);

  $expected = array();
  foreach ($expectedRecipientIds as $id)
  {
    if (is_array($id))
    {
      $expected[] = $id;
    }
    else
    {
      $expected[] = array($id, false);
    }
  }

  if (!is_null($expectedException))
  {
    try
    {
      $actual = IdCallUtil::check_atcall($text, $remark, $dummyRouting, $dummyAuthor, IdCallUtil::TEST);
      $t->fail('期待される例外がスローされていません：'.$expectedException);
    }
    catch (Exception $e)
    {
      $t->is($e->getMessage(), $expectedException, '期待される例外("'.$expectedException.'")の検出');
    }
  }
  else
  {
    try
    {
      $actual = IdCallUtil::check_atcall($text, $remark, $dummyRouting, $dummyAuthor, IdCallUtil::TEST);
      $t->is($actual, $expected, $text);
    }
    catch (Exception $e)
    {
      $t->fail('予期されない例外の発生：'.$e->getMessage());
    }
  }
}

//
// unit test
//
$t = new lime_test(null, new lime_output_color());

IdCallUtil::set_mapping(array(
                              1 => array('佐藤', 'サトウ', 'sato', 'satou'),
                              2 => array('鈴木(甲)', '鈴木', 'suzuki-k'),
                              3 => array('鈴木(乙)', '鈴木', 'suzuki-o'),
                              4 => array('田中', 'tanaka', 'tnk'),
                              ));

$t->diag('check_atcall()');
$t->diag('@+ID の検出');
idcall_test($t, '＠佐藤さん こんにちは', array(1));
idcall_test($t, '＠サトウさん こんにちは', array(1));
idcall_test($t, '＠佐藤さん ＠サトウさん こんにちは', array(1));
idcall_test($t, '＠佐藤さん ＠佐藤さん こんにちは', array(1));
idcall_test($t, '@佐藤さん こんにちは', array(1));
idcall_test($t, '@サトウさん こんにちは', array(1));
idcall_test($t, '@佐藤さん @サトウさん こんにちは', array(1));
idcall_test($t, '＠田中さん こんにちは', array(4));
idcall_test($t, '＠田中さま こんにちは', array(4));
idcall_test($t, '＠田中っち こんにちは', array(4));
idcall_test($t, '＠田中さん　こんにちは', array(4), null, '全角スペースが続いた場合');
idcall_test($t, '＠佐藤さん　＠田中さん　こんにちは', array(1, 4), null, '全角スペースで区切られている場合');
idcall_test($t, '＠タナカさん こんにちは', array(), 'unsolved nickname: タナカ');
idcall_test($t, '＠鈴木さん こんにちは', array(2, 3));
idcall_test($t, '＠鈴木(甲)さん こんにちは', array(2));
idcall_test($t, '＠鈴木(乙)さん こんにちは', array(3));
idcall_test($t, '＠鈴木(甲,乙)さん こんにちは', array(), 'unsolved nickname: 鈴木(甲', 'これは別途コールIDテーブルに追加しないと対応しない');
idcall_test($t, '＠鈴木(甲) ＠鈴木(乙)さん こんにちは', array(2, 3));
idcall_test($t, '@sato @tanaka hello', array(1, 4));
idcall_test($t, '@Sato @Tanaka hello', array(1, 4));
idcall_test($t, '@SATO @TANAKA hello', array(1, 4));
idcall_test($t, '@sAtO @tAnAkA hello', array(1, 4));
idcall_test($t, '@tanaka,@sato hello', array(1, 4));
idcall_test($t, '@tanaka,@sato,@tanaka hello', array(1, 4));
idcall_test($t, '@tanaka @tanaka hello', array(4));
idcall_test($t, 'someone@example.com', array(), 'unsolved nickname: example.com');

$t->diag('m@+ID, ktai@+ID の検出');
idcall_test($t, 'm@sato hello', array(array(1, true)));
idcall_test($t, 'm@suzuki-k hello', array(array(2, true)));
idcall_test($t, 'm@suzuki-o hello', array(array(3, true)));
idcall_test($t, 'm@suzuki hello', array(), 'unsolved nickname: suzuki');
idcall_test($t, 'm@tanaka hello', array(array(4, true)));
idcall_test($t, 'm@tnk hello', array(array(4, true)));
idcall_test($t, 'm@tanaka m@tanaka hello', array(array(4, true)));
idcall_test($t, 'm@tanaka m@tnk hello', array(array(4, true)));
idcall_test($t, 'm@sato @sato hello', array(array(1, false), array(1, true)));
idcall_test($t, 'm@tanaka @sato hello', array(array(1, false), array(4, true)));
idcall_test($t, 'ktai@sato hello', array(array(1, true)));
idcall_test($t, 'ktai@suzuki-k hello', array(array(2, true)));
idcall_test($t, 'ktai@suzuki-o hello', array(array(3, true)));
idcall_test($t, 'ktai@suzuki hello', array(), 'unsolved nickname: suzuki');
idcall_test($t, 'ktai@tanaka hello', array(array(4, true)));
idcall_test($t, 'm@tanaka ktai@tanaka hello', array(array(4, true)));
idcall_test($t, 'o@tanaka ktaj@tanaka hello', array(array(4, false)), null, 'm@, ktai@ 以外のプレフィックスには対応しない→PC宛に送られる');
