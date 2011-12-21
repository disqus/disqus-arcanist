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

class MockManiphestTask extends ManiphestTask {
  public function setAuxiliaryAttribute($key, $val) {
    $attribute = new ManiphestTaskAuxiliaryStorage();
    $attribute->setTaskPHID($this->phid);
    $attribute->setName($key);
    $attribute->setValue($val);
    $attribute->save();
  }
}

class MockedProjectAssignmentEventListener
  extends ProjectAssignmentEventListener {

  public function getProjectAffiliation($project_phid) {
    $affiliation = new PhabricatorProjectAffiliation();
    $affiliation->setUserPHID('PHID-USER-sapmgsmdravm3xumx3ua');
    $affiliation->setProjectPHID($project_phid);

    return $affiliation;
  }

  public function getProject($project_phid) {
    $project = new PhabricatorProject();
    $project->setPHID($project_phid);
    $project->setName('Test Project');
    $project->setStatus(PhabricatorProjectStatus::ONGOING);

    return $project;
  }
}

final class ProjectAssignmentEventListenerTestCase
  extends PhabricatorTestCase {

  /**
   * This is more of an acceptance test case instead of a unittest. It verifies
   * that all symbols can be loaded correctly. It can catch problem like missing
   * methods in descendants of abstract base classes.
   */
  public function testAutoAssign() {
    $listener = new MockedProjectAssignmentEventListener();

    $user = new PhabricatorUser();
    $user->setUsername('test');
    $user->setEmail('test@example.com');
    $user->setPHID('PHID-USER-sapmgsmdravm3xumx3ua');

    $project = $listener->getProject('PHID-PROJ-sapmgsmdravm3xumx3ua');

    $task = new MockManiphestTask();
    $task->setPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
    $task->setAuthorPHID($user->getPHID());

    $task->setTitle('Test ticket');
    $task->setDescription('Test ticket description');

    $changes = array();
    $changes[ManiphestTransactionType::TYPE_STATUS] =
      ManiphestTaskStatus::STATUS_OPEN;

    // $owner_phid = $request->getValue('ownerPHID');
    // if ($owner_phid !== null) {
    //   $changes[ManiphestTransactionType::TYPE_OWNER] = $owner_phid;
    // }

    $project_phids = array($project->getPHID());
    $changes[ManiphestTransactionType::TYPE_PROJECTS] = $project_phids;

    $template = new ManiphestTransaction();
    //$template->setContentSource($content_source);
    $template->setAuthorPHID($user->getPHID());

    $transactions = array();
    foreach ($changes as $type => $value) {
      $transaction = clone $template;
      $transaction->setTransactionType($type);
      $transaction->setNewValue($value);
      $transactions[] = $transaction;
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_MANIPHEST_WILLEDITTASK,
      array(
        'task'          => $task,
        'new'           => true,
        'transactions'  => $transactions,
      ));

    $listener->handleEvent($event);
  }
}

