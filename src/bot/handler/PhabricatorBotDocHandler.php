<?php

/**
 * Watches for "tell me about <slug>"
 *   and "phabot remember <slug> as: <text>"
 *
 * @group irc
 */
final class PhabricatorBotDocHandler extends PhabricatorBotHandler {

   /**
   * Map of titles to the last mention of them (as an epoch timestamp); prevents
   * us from spamming chat when a single object is discussed.
   */
  private $recentlyMentioned = array();

  public function receiveMessage(PhabricatorBotMessage $message) {

    switch ($message->getCommand()) {
    case 'MESSAGE':
      $matches = null;

      $text = $message->getBody();

      $target_name = $message->getTarget()->getName();
      if (empty($this->recentlyMentioned[$target_name])) {
        $this->recentlyMentioned[$target_name] = array();
      }

      $pattern =
        '@^'.
        '(?:'.$this->getConfig('nick', 'phabot').')?'.
        '.?\s*tell me about '.
        '(.*)'.
        '$@';

      if (preg_match($pattern, $text, $matches)) {
        $slug = $matches[1];

        $quiet_until = idx(
          $this->recentlyMentioned[$target_name],
          $slug,
          0) + (60 * 10);

        if (time() < $quiet_until) {
          // Remain quiet on this channel.
          break;
        } else {
          $this->recentlyMentioned[$target_name][$slug] = time();
        }

        try {
          $result = $this->getConduit()->callMethodSynchronous(
            'phriction.info',
            array(
              'slug' => 'docbot/docs/'.$slug,
            ));
        } catch (ConduitClientException $ex) {
          phlog($ex);
          $result = null;
        }

        $response = array();

        if ($result) {
          $content = phutil_split_lines(
            $result['content'],
            $retain_newlines = false);

          foreach ($content as $line) {
            $response = array_merge($response, str_split($line, 400));

            if (count($response) >= 3) {
              break;
            }
          }
        } else {
          $response[] = "Nothing to say about ".$slug;
        }

        foreach (array_slice($response, 0, 3) as $output) {
          $this->replyTo($message, html_entity_decode($output));
        }
        break;
      }

      $pattern =
        '@'.
        $this->getConfig('nick', 'phabot').
        ' remember '.
        '(.*?)'.
        ' as:'.
        '(.*)$'.
        '@';

      if (preg_match($pattern, $text, $matches)) {
        $result = $this->getConduit()->callMethodSynchronous(
          'phriction.edit',
          array(
            'slug' => 'docbot/docs/'.$matches[1],
            'content' => $matches[2],
          ));

        $slug = explode('/', trim($result['slug'], '/'), 3);

        $output = "Saved as '${slug[2]}' at ${result['uri']}";

        $this->replyTo($message, $output);

        unset($this->recentlyMentioned[$target_name][$slug[2]]);
        unset($this->recentlyMentioned[$target_name][$matches[1]]);

        break;
      }
      break;
    }
  }

}
