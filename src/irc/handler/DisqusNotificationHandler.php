<?php

/**
 * @group irc
 */
final class DisqusNotificationHandler extends PhabricatorIRCHandler {

  private $lastSeenChronoKey = 0;

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
    return 'D'.$event_data['revision_id'].' '.$event_data['revision_name'].' - '.
      PhabricatorEnv::getURI('/D'.$event_data['revision_id']);
  }

  public function receiveMessage(PhabricatorIRCMessage $message) {
    return;
  }

  public function runBackgroundTasks() {
    if (!$this->lastSeenChronoKey) {
      // Since we only want to post notifications about new events, skip
      // everything that's happened in the past when we start up so we'll
      // only process real-time events.
      $latest = $this->getConduit()->callMethodSynchronous(
        'feed.query',
        array(
          'limit'=>1
        ));

      foreach ($latest as $story) {
        if ($story['chronologicalKey'] > $this->lastSeenChronoKey) {
          $this->lastSeenChronoKey = $story['chronologicalKey'];
        }
      }

      return;
    }

    $lastSeenChronoKey = $this->lastSeenChronoKey;
    $chronoKeyCursor = 0;

    // Not efficient but works due to feed.query API
    for ($maxPages = 10; $maxPages > 0; $maxPages--) {
      $stories = $this->getConduit()->callMethodSynchronous(
        'feed.query',
        array(
          'limit'=>10,
          'after'=>$chronoKeyCursor
        ));

      foreach ($stories as $event) {
        if ($event['chronologicalKey'] > $this->lastSeenChronoKey) {
          $this->lastSeenChronoKey = $event['chronologicalKey'];
        }
        if ($event['chronologicalKey'] == $lastSeenChronoKey) {
          return;
        }
        if (!$chronoKeyCursor || $event['chronologicalKey'] < $chronoKeyCursor) {
          $chronoKeyCursor = $event['chronologicalKey'];
        }

        switch ($event['class']) {
          case 'PhabricatorFeedStoryDifferential':
            break;
          default:
            continue 2;
            break;
        }

        if (!$event['data'] || !$this->careAbout($event['data']['action'])) {
          continue;
        }

        $actor_phid = $event['data']['actor_phid'];
        $phids = array($actor_phid);
        $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
        $verb = DifferentialAction::getActionPastTenseVerb($event['data']['action']);

        $actor_name = $handles[$actor_phid]->getName();
        $message = "{$actor_name} {$verb} revision ".$this->getDiffMessage($event['data']);

        $channels = $this->getConfig('notification.channels', array());
        foreach ($channels as $channel) {
          $this->write('PRIVMSG', "{$channel} :{$message}");
        }
      }
    }
  }
}
