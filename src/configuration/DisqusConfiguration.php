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
 * Basic lint engine which just applies several linters based on the file types
 *
 * @group config
 */
class DisqusConfiguration extends ArcanistConfiguration {
  public function didRunWorkflow($command, ArcanistBaseWorkflow $workflow, $err) {
    if ($command != 'diff') {
      return;
    }
    $working_copy = $workflow->getWorkingCopy();
    $project_root = $working_copy->getProjectRoot();

    // check for coverage file
    $bleed_report_path = $project_root.'/test_results/coverage.json';
    if (Filesystem::pathExists($bleed_report_path)) {
      $bleed_report_data = Filesystem::readFile($bleed_report_path);
      $bleed_report = json_decode($bleed_report_data, true);
      if (!is_array($bleed_report)) {
        print "Your 'coverage.json' file is not a valid JSON file.";
        return;
        // throw new ArcanistUsageException(
        //   "Your 'nosebleed.json' file is not a valid JSON file.");
      }

      $conduit = $workflow->getConduit();

      $resp = $conduit->callMethodSynchronous('differential.setdiffproperty', array(
        'diff_id' => $workflow->diffID,
        'name' => 'disqus:coverage',
        'data' => $bleed_report_data,
      ));
    }
  }
}
