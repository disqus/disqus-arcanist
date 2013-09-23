<?php

/*
 * Copyright 2011 Disqus, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// This gets installed by adding the following to the config:
// 'events.listeners' => array(
//   'DesignDecisionEventListener',
// ),

class DesignDecisionEventListener extends PhutilEventListener {

  private function titleHasPrefix($title) {
    return (strpos($title, '[DDN] ') === 0);
  }

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK);
  }

  public function handleEvent(PhutilEvent $event) {
    $task = $event->getValue('task');

    // discover DDN field from transactions
    $ddn = null;
    foreach ($event->getValue('transactions') as $transaction) {
      if ($transaction->getTransactionType() != PhabricatorTransactions::TYPE_CUSTOMFIELD) {
          continue;
      }
      $key = $transaction->getMetadataValue('aux:key');
      if ($key != 'disqus:ddn') {
        continue;
      }
      $ddn = (bool)$transaction->getNewValue();
    }

    if ($ddn === null) {
      return;
    }

    $title = $task->getTitle();

    $has_ddn = $this->titleHasPrefix($title);

    if ($ddn && !$has_ddn) {
      $title = '[DDN] '.$title;
    } elseif (!$ddn && $has_ddn) {
      $title = substr($title, 6);
    }

    $task->setTitle($title);
  }

}
