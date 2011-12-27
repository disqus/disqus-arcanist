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
//   'ProjectAssignmentEventListener',
// ),

class ProjectAssignmentEventListener extends PhutilEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK);
  }

  public function handleEvent(PhutilEvent $event) {
    $task = $event->getValue('task');
    if ($task->getOwnerPHID()) {
        // task was assigned manually
        return;
    }
    $project_phids = null;
    foreach ($event->getValue('transactions') as $transaction) {
        if ($transaction->getTransactionType() != ManiphestTransactionType::TYPE_PROJECTS) {
            continue;
        }
        $project_phids = $transaction->getNewValue();
    }
    if (!$project_phids) {
        // no projects assigned
        return;
    }

    $affiliation = $this->getProjectAffiliation($project_phids[0]);
    if (!$affiliation) {
        // no owners found?
        return;
    }
    $project = $this->getProject($project_phids[0]);
    $user_phid = $affiliation->getUserPHID();
    $task->setOwnerPHID($user_phid);
    // TODO: Once we can modify transactions we should set assign-reason
    // $task->save();
    // $task->setAuxiliaryAttribute(
    //     'disqus:assign-reason',
    //     sprintf('This was assigned automatically to the'.
    //             ' owner of %s', $project->getName())
    // );
  }

  public function getProjectAffiliation($project_phid) {
    $affiliation = id(new PhabricatorProjectAffiliation())->loadOneWhere(
      'projectPHID = %s and isOwner = 1 LIMIT 1', $project_phid);

    return $affiliation;
  }

  public function getProject($project_phid) {
    $project = id(new PhabricatorProject())->loadOneWhere(
      'phid = %s LIMIT 1', $project_phid);

    return $project;
  }

}
