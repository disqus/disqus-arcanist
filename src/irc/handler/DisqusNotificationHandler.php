<?php

/**
 * @group irc
 */
final class DisqusNotificationHandler extends PhabricatorIRCHandler {

  private $lastSeenChronoKey = 0;
  private $next = 0;

  private function careAbout($event_class, $event_text) {
    switch ($event_class) {
      case 'PhabricatorFeedStoryDifferential':
        if (preg_match('/^.+? (created|closed) revision.*/', $event_text)) {
          return true;
        }
      break;
      default:
        return false;
      break;
    }

    return false;
  }

  public function receiveMessage(PhabricatorIRCMessage $message) {
    return;
  }

  public function runBackgroundTasks() {
    if (microtime(true) < $this->next) {
      return;
    } elseif ($this->next == 0) {
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

      $this->next = microtime(true) + 30;

      return;
    }

    $config_maxPages = $this->getConfig('notification.maxPages', 2);
    $config_pageSize = $this->getConfig('notification.pageSize', 10);

    $lastSeenChronoKey = $this->lastSeenChronoKey;
    $chronoKeyCursor = 0;

    // Not efficient but works due to feed.query API
    for ($maxPages = $config_maxPages; $maxPages > 0; $maxPages--) {
      $stories = $this->getConduit()->callMethodSynchronous(
        'feed.query',
        array(
          'limit'=>$config_pageSize,
          'after'=>$chronoKeyCursor,
          'view'=>'text'
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

        if (!$event['text'] || !$this->careAbout($event['class'], $event['text'])) {
          continue;
        }

        $channels = $this->getConfig('notification.channels', array());
        foreach ($channels as $channel) {
          $this->write('PRIVMSG', "{$channel} :{$event['text']}");
        }
      }
    }

    $this->next = microtime(true) + $this->getConfig('notification.sleep', 1);
  }

}
