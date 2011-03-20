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
  public function preExecute()
  {
    $this->member = $this->getRoute()->getMember();
    if (!$this->member)
    {
      exit;
    }

    $validator = new opValidatorString(array('rtrim' => true));
    try
    {
      $this->body = $validator->clean($this->getRequest()->getMailMessage()->getContent());
    }
    catch (Exception $e)
    {
      exit;
    }
  }

  private function isDiaryPostable($diary)
  {
    if (!$diary || !$diary->isViewable($this->member->id))
    {
      return false;
    }

    return !$this->isAccessBlocked($diary->member_id);
  }

  private function isCommunityPostable($community)
  {
    if (!$community || !$community->isPrivilegeBelong($this->member->id))
    {
      return false;
    }

    return true;
  }

  private function isActivityPostable($activityData)
  {
    if (!$activityData)
    {
      return false;
    }

    return !$this->isAccessBlocked($activityData->member_id);
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
    $diaryComment = new DiaryComment();
    $diaryComment->setDiary($diary);
    $diaryComment->setMember($this->member);
    $diaryComment->setBody($this->generateBody());

    return $diaryComment->save();
  }

  private function postCommunityEventComment($communityEvent)
  {
    $communityEventComment = new CommunityEventComment();
    $communityEventComment->setCommunityEvent($communityEvent);
    $communityEventComment->setMember($this->member);
    $communityEventComment->setBody($this->generateBody());

    return $communityEventComment->save();
  }

  private function postCommunityTopicComment($communityTopic)
  {
    $communityTopicComment = new CommunityTopicComment();
    $communityTopicComment->setCommunityTopic($communityTopic);
    $communityTopicComment->setMember($this->member);
    $communityTopicComment->setBody($this->generateBody());

    return $communityTopicComment->save();
  }

  private function postActivityData($activityData)
  {
    $options = array(
      'public_flag' => $this->member->getConfig(
        MemberConfigIdCallForm::ID_CALL_ACTIVITY_PUBLIC_FLAG,
        ActivityDataTable::PUBLIC_FLAG_SNS
      ),
      'is_pc' => $activityData->is_pc,
      'is_mobile' => $activityData->is_mobile,
    );

    return Doctrine_Core::getTable('ActivityData')->updateActivity($this->member->id, $this->generateBody(), $options);
  }

  private function generateBody($toName)
  {
    return sprintf(">%s%s [i:110]\n%s", $this->targetMemberName, $this->member->getConfig(MemberConfigIdCallForm::ID_CALL_MAIL_POST_NAME_SUFFIX, '殿'), $this->body);
  }
}
