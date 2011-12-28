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

/**
 * Sends changes from your working copy to Differential for code review.
 *
 * @group workflow
 */
class DisqusLandWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **land** __branch__
          Supports: git
          Rebases a branch onto master.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'branch',
    );
  }

  protected function didParseArguments() {
    if (!$this->getArgument('branch')) {
      throw new ArcanistUsageException("Specify a branch to land.");
    }
    $branch = $this->getArgument('branch');
    if (count($branch) > 1) {
      throw new ArcanistUsageException("Specify only one branch to merge.");
    } else {
      $branch = head($branch);
    }

    $this->branch = $branch;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function shouldShellComplete() {
    return false;
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();
    if (!($repository_api instanceof ArcanistGitAPI)) {
      throw new ArcanistUsageException(
        "arc land is only supported under git."
      );
    }

    $branch = $this->getBranch($repository_api, $this->branch);
    if (!$branch) {
      throw new ArcanistUsageException(
        "Invalid branch name."
      );
    }
    $branch->setCurrent();

    $branch_name = $branch->getName();

    $revision_status = $this->getDifferentialStatus($branch->getRevisionId());
    $owner = $repository_api->getRepositoryOwner();

    if ($revision_status != 'Accepted') {
      throw new ArcanistUsageException(
        "arc land can only be used on accepted revisions."
      );
    }

    echo phutil_console_format("Landing branch <fg:green>%s</fg>..\n",
      $branch_name);


    echo phutil_console_format("* Updating <fg:blue>master</fg>.. ");

    execx('git checkout master');
    execx('git pull --rebase');

    echo "done!\n";

    echo phutil_console_format(
      "* Rebasing <fg:blue>master</fg> onto <fg:green>%s</fg>.. ",
      $branch_name);

    try {
      execx('git rebase master %s', $branch_name);
    } catch (CommandException $e) {
      execx('git rebase --abort');
      throw $e;
    }

    echo "done!\n";

    execx('git checkout master');

    echo phutil_console_format(
      "* Merging <fg:green>%s</fg> into <fg:blue>master</fg>.. ",
      $branch_name);

    execx('git merge %s', $branch_name);
    execx('git branch -d %s', $branch_name);
    execx('arc amend');

    echo "done!\n";

    echo phutil_console_format(
      "Done! <fg:green>%s</fg> was landed into <fg:blue>master</fg>.\n",
      $branch_name);

    echo phutil_console_format(
      "You can now '<fg:cyan>git push</fg>' to submit your changes upstream.");

  }

  /**
   * Returns information regarding a given branch.
   */
  private function getBranch($api, $branch_name) {
    $branches = BranchInfo::loadAll($api);
    foreach ($branches as $branch) {
      if ($branch->getName() == $branch_name) {
        return $branch;
      }
    }
  }

  /**
   * Makes a conduit call to differential to find out revision status
   */
  private function getDifferentialStatus($rev_id) {
    $conduit = $this->getConduit();
    $revision_future = $conduit->callMethod(
      'differential.find',
      array(
        'guids' => array($rev_id),
        'query' => 'revision-ids',
      ));
    $revisions = array();
    foreach ($revision_future->resolve() as $revision_dict) {
      $revisions[] = ArcanistDifferentialRevisionRef::newFromDictionary(
        $revision_dict);
    }
    $statuses = mpull($revisions, 'getStatusName', 'getId');
    return head($statuses);
  }
}
