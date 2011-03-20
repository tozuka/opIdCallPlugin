<?php

/**
 * opIdCall actions.
 *
 * @package    OpenPNE
 * @subpackage opIdCall
 * @author     Shinichi Urabe <urabe@tejimaya.com>
 */
class opIdCallActions extends sfActions
{
  const LOG_SUFFIX = '[mail post]';

  public function preExecute()
  {
    $this->member = $this->getRoute()->getMember();
    if (!$this->member)
    {
      opIdCallToolkit::log(sprintf('%s %s', self::LOG_SUFFIX, 'undefined member'));
      exit;
    }

    $validator = new opValidatorString(array('rtrim' => true));
    try
    {
      $this->body = $validator->clean($this->getRequest()->getMailMessage()->getContent());
    }
    catch (Exception $e)
    {
      opIdCallToolkit::log(sprintf('%s %s', self::LOG_SUFFIX, 'message: '.$e->getMessage.', code:'.$e->getCode()));
      exit;
    }
  }

  private function isDiaryPostable($diary)
  {
    if (!$diary || !$diary->isViewable($this->member->id))
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d can\'t post diary comment in diary_id:%d', self::LOG_SUFFIX, $this->member->id, $diary->id));

      return false;
    }

    if ($this->isAccessBlocked($diary->member_id))
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d can\'t post diary comment in diary_id:%d, becouse access blocked', self::LOG_SUFFIX, $this->member->id, $diary->id));

      return false;
    }

    return true;
  }

  private function isCommunityPostable($community)
  {
    if (!$community)
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d post undefined community', self::LOG_SUFFIX, $this->member->id));

      return false;
    }
    if (!$community->isPrivilegeBelong($this->member->id))
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d post not joined community community_id:%d', self::LOG_SUFFIX, $this->member->id, $community->id));
      $this->sendJoinCommunityNotification($community, $this->member);

      return false;
    }

    return true;
  }

  private function isActivityPostable($activityData)
  {
    if (!$activityData)
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d post undefined activity', self::LOG_SUFFIX, $this->member->id));

      return false;
    }

    if ($this->isAccessBlocked($activityData->member_id))
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d can\'t post activity comment, becouse access blocked', self::LOG_SUFFIX, $this->member->id));

      return false;
    }

    return true;
  }

  private function isAccessBlocked($member_id_to)
  {
    if ($member_id_to !== $this->member->id)
    {
      $relation = Doctrine::getTable('MemberRelationship')->retrieveByFromAndTo($member_id_to, $this->member->id);

      return $relation && $relation->getIsAccessBlock();
    }

    return false;
  }

 /**
  * Executes diary action
  *
  * @param sfWebRequest $request A request object
  */
  public function executeDiary(sfWebRequest $request)
  {
    $diary = Doctrine::getTable('Diary')->find($request['id']);
    if (!$this->isDiaryPostable($diary))
    {
      return sfView::NONE;
    }
    $this->targetMemberName = $diary->Member->name;
    $this->postDiaryComment($diary);

    return sfView::NONE;
  }

 /**
  * Executes diaryComment action
  *
  * @param sfWebRequest $request A request object
  */
  public function executeDiaryComment(sfWebRequest $request)
  {
    $diaryComment = Doctrine::getTable('DiaryComment')->find($request['id']);
    if (!$diaryComment)
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d post undefined diary comment', self::LOG_SUFFIX, $this->member->id));

      return sfView::NONE;
    }
    if (!$this->isDiaryPostable($diary = $diaryComment->Diary))
    {
      return sfView::NONE;
    }

    $this->targetMemberName = $diaryComment->Member->name;
    $this->postDiaryComment($diary);

    return sfView::NONE;
  }

 /**
  * Executes communityEvent action
  *
  * @param sfWebRequest $request A request object
  */
  public function executeCommunityEvent(sfWebRequest $request)
  {
    $communityEvent = Doctrine::getTable('CommunityEvent')->find($request['id']);
    if (!$communityEvent)
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d post undefined event', self::LOG_SUFFIX, $this->member->id));

      return sfView::NONE;
    }
    if (!$this->isCommunityPostable($communityEvent->Community))
    {
      return sfView::NONE;
    }

    $this->targetMemberName = $communityEvent->Member->name;
    $this->postCommunityEventComment($communityEvent);

    return sfView::NONE;
  }

 /**
  * Executes communityEventComment action
  *
  * @param sfWebRequest $request A request object
  */
  public function executeCommunityEventComment(sfWebRequest $request)
  {
    $communityEventComment = Doctrine::getTable('CommunityEventComment')->find($request['id']);
    if (!$communityEventComment || !($communityEvent = $communityEventComment->CommunityEvent))
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d post undefined event or event comment', self::LOG_SUFFIX, $this->member->id));

      return sfView::NONE;
    }
    if (!$this->isCommunityPostable($communityEvent->Community))
    {
      return sfView::NONE;
    }

    $this->targetMemberName = $communityEventComment->Member->name;
    $this->postCommunityEventComment($communityEvent);

    return sfView::NONE;
  }

 /**
  * Executes communityTopic action
  *
  * @param sfWebRequest $request A request object
  */
  public function executeCommunityTopic(sfWebRequest $request)
  {
    $communityTopic = Doctrine::getTable('CommunityTopic')->find($request['id']);
    if (!$communityTopic)
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d post undefined topic', self::LOG_SUFFIX, $this->member->id));

      return sfView::NONE;
    }
    if (!$this->isCommunityPostable($communityTopic->Community))
    {
      return sfView::NONE;
    }

    $this->targetMemberName = $communityTopic->Member->name;
    $this->postCommunityTopicComment($communityTopic);

    return sfView::NONE;
  }

 /**
  * Executes communityTopicComment action
  *
  * @param sfWebRequest $request A request object
  */
  public function executeCommunityTopicComment(sfWebRequest $request)
  {
    $communityTopicComment = Doctrine::getTable('CommunityTopicComment')->find($request['id']);
    if (!$communityTopicComment || !($communityTopic = $communityTopicComment->CommunityTopic))
    {
      opIdCallToolkit::log(sprintf('%s member_id:%d post undefined topic or topic comment', self::LOG_SUFFIX, $this->member->id));

      return sfView::NONE;
    }
    if (!$this->isCommunityPostable($communityTopic->Community))
    {
      return sfView::NONE;
    }

    $this->targetMemberName = $communityTopicComment->Member->name;
    $this->postCommunityTopicComment($communityTopic);

    return sfView::NONE;
  }

 /**
  * Executes activity action
  *
  * @param sfWebRequest $request A request object
  */
  public function executeActivity(sfWebRequest $request)
  {
    $activityData = Doctrine::getTable('ActivityData')->find($request['id']);
    if (!$this->isActivityPostable($activityData))
    {
      return sfView::NONE;
    }

    $this->targetMemberName = $activityData->Member->name;
    $this->postActivityData($activityData);

    return sfView::NONE;
  }

  private function postDiaryComment($diary)
  {
    opIdCallToolkit::log(sprintf('%s <start> member_id:%d post diary comment', self::LOG_SUFFIX, $this->member->id));
    $diaryComment = new DiaryComment();
    $diaryComment->setDiary($diary);
    $diaryComment->setMember($this->member);
    $diaryComment->setBody($this->generateBody());

    $result = $diaryComment->save();
    if ($result)
    {
      opIdCallToolkit::log(sprintf('%s <end> member_id:%d post diary comment diary_comment_id:%d', self::LOG_SUFFIX, $this->member->id, $result->id));
    }

    return $result;
  }

  private function postCommunityEventComment($communityEvent)
  {
    opIdCallToolkit::log(sprintf('%s <start> member_id:%d post event comment', self::LOG_SUFFIX, $this->member->id));
    $communityEventComment = new CommunityEventComment();
    $communityEventComment->setCommunityEvent($communityEvent);
    $communityEventComment->setMember($this->member);
    $communityEventComment->setBody($this->generateBody());

    $result = $communityEventComment->save();
    if ($result)
    {
      opIdCallToolkit::log(sprintf('%s <end> member_id:%d post event comment event_comment_id:%d', self::LOG_SUFFIX, $this->member->id, $result->id));
    }

    return $result;
  }

  private function postCommunityTopicComment($communityTopic)
  {
    opIdCallToolkit::log(sprintf('%s <start> member_id:%d post topic comment', self::LOG_SUFFIX, $this->member->id));
    $communityTopicComment = new CommunityTopicComment();
    $communityTopicComment->setCommunityTopic($communityTopic);
    $communityTopicComment->setMember($this->member);
    $communityTopicComment->setBody($this->generateBody());

    $result = $communityTopicComment->save();
    if ($result)
    {
      opIdCallToolkit::log(sprintf('%s <end> member_id:%d post topic comment topic_comment_id:%d', self::LOG_SUFFIX, $this->member->id, $result->id));
    }

    return $result;
  }

  private function postActivityData($activityData)
  {
    opIdCallToolkit::log(sprintf('%s <start> member_id:%d post activity data', self::LOG_SUFFIX, $this->member->id));
    $options = array(
      'public_flag' => $this->member->getConfig(
        MemberConfigIdCallForm::ID_CALL_ACTIVITY_PUBLIC_FLAG,
        ActivityDataTable::PUBLIC_FLAG_SNS
      ),
      'is_pc' => $activityData->is_pc,
      'is_mobile' => $activityData->is_mobile,
    );

    $result = Doctrine_Core::getTable('ActivityData')->updateActivity($this->member->id, $this->generateBody(), $options);

    if ($result)
    {
      opIdCallToolkit::log(sprintf('%s <end> member_id:%d post activity data activity_data_id:%d', self::LOG_SUFFIX, $this->member->id, $result->id));
    }

    return $result;
  }

  private function generateBody($toName)
  {
    return sprintf(">%s%s [i:110]\n%s", $this->targetMemberName, $this->member->getConfig(MemberConfigIdCallForm::ID_CALL_MAIL_POST_NAME_SUFFIX, '殿'), $this->body);
  }

  private function sendJoinCommunityNotification(Community $community, Member $member)
  {
    $requestContext = $this->getRequest()->getRequestContext();
    if (!opToolKit::isMobileEmailAddress($requestContext['from_address']))
    {
      return false;
    }

    $params = array(
      'nickname' => $member->name,
      'community' => $community,
    );
    opMailSend::sendTemplateMail('idCallJoinCommunityNotification', $requestContext['from_address'], opConfig::get('admin_mail_address'), $params);
  }
}
