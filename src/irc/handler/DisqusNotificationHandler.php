<?php

/**
 * @group irc
 */
final class DisqusNotificationHandler extends PhabricatorIRCHandler {

  private $skippedOldEvents;

  private function careAbout($action) {
    switch ($action) {
      case DifferentialAction::ACTION_CREATE:
      case DifferentialAction::ACTION_CLOSE:
        return true;
      default:
        return false;
    }
  }

  private function getDiffMessage($event_data) {
    return 'D'.$event_data['revision_id'].' '.$revision['revision_name'].' - '.
      PhabricatorEnv::getURI('/D'.$event_data['revision_id']);
  }

  public function receiveMessage(PhabricatorIRCMessage $message) {
    return;
  }

  public function runBackgroundTasks() {
    $iterator = new PhabricatorTimelineIterator('ircdiffx', array('difx'));

    if (!$this->skippedOldEvents) {
      // Since we only want to post notifications about new events, skip
      // everything that's happened in the past when we start up so we'll
      // only process real-time events.
      foreach ($iterator as $event) {
        // Ignore all old events.
      }
      $this->skippedOldEvents = true;
      return;
    }

    foreach ($iterator as $event) {
      $data = $event->getData();
      if (!$data || !$this->careAbout($data['action'])) {
        continue;
      }

      $actor_phid = $data['actor_phid'];
      $phids = array($actor_phid);
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
      $verb = DifferentialAction::getActionPastTenseVerb($data['action']);

      $actor_name = $handles[$actor_phid]->getName();
      $message = "{$actor_name} {$verb} revision ".$this->getDiffMessage($data);

      $channels = $this->getConfig('notification.channels', array());
      foreach ($channels as $channel) {
        $this->write('PRIVMSG', "{$channel} :{$message}");
      }
    }
  }

}