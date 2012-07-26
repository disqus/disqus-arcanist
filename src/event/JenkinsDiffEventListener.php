<?php

/*
 * Copyright 2012 Disqus, Inc.
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
//   'JenkinsDiffEventListener',
// ),

class JenkinsDiffEventListener extends PhutilEventListener {

  public function register() {
    $this->listen(ArcanistEventType::TYPE_DIFF_WASCREATED);
  }

  public function handleEvent(PhutilEvent $event) {
    $diff_id = $event->getValue('diffID');

    /* Need to send a get request to jenkins to trigger the job. We pass the
     * diff id to jenkins via its api.
     */

    $url = "http://ci.local.disqus.net/job/disqus-phabricator/buildWithParameters?DIFF_ID=".$diff_id;

    file_get_contents($url);
  }
}
